import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { API, money, specText, timeAgo } from '../../lib.js';
import { useToast } from '../../components/ui.jsx';

const STATUS = {
  nuevo: ['Nuevo', 'p-nuevo'],
  imprimiendo: ['Imprimiendo', 'p-imprimiendo'],
  listo: ['Listo', 'p-listo'],
  entregado: ['Entregado', 'p-entregado'],
  rechazado: ['Rechazado', 'p-rechazado'],
  cancelado: ['Cancelado', 'p-cancelado'],
};

const PAYMENT = {
  aprobado: 'pagado',
  devuelto: 'devuelto',
  devuelto_efectivo: 'devuelto en efectivo',
  devolucion_pendiente: 'devolución pendiente',
};

/** Un «ding» corto con WebAudio cuando entra un pedido (sólo después de una interacción). */
function ding() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.frequency.value = 880;
    g.gain.setValueAtTime(0.2, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
    o.connect(g).connect(ctx.destination);
    o.start();
    o.stop(ctx.currentTime + 0.6);
  } catch { /* sin audio */ }
}

export default function Orders({ call }) {
  const [scope, setScope] = useState('activos');
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(null);
  const [rejecting, setRejecting] = useState(null);
  const [reason, setReason] = useState('');
  const [fresh, setFresh] = useState(() => new Set());
  const seen = useRef(null);
  const [toast, showToast] = useToast();

  const load = useCallback(async () => {
    try {
      const d = await call(`/admin/orders?scope=${scope}`);
      if (scope === 'activos') {
        const ids = d.orders.filter((o) => o.status === 'nuevo').map((o) => o.publicId);
        if (seen.current) {
          const nuevos = ids.filter((id) => !seen.current.has(id));
          if (nuevos.length) {
            ding();
            setFresh(new Set(nuevos));
            showToast(nuevos.length === 1 ? 'Entró un pedido nuevo' : `Entraron ${nuevos.length} pedidos nuevos`);
          }
        }
        seen.current = new Set(ids);
        document.title = d.stats.queue ? `(${d.stats.queue}) Pedidos · Copiá Fácil` : 'Pedidos · Copiá Fácil';
      }
      setData(d);
      setError('');
    } catch (e) {
      setError(e.message);
    }
  }, [call, scope, showToast]);

  useEffect(() => {
    load();
    const id = setInterval(load, 10000);
    return () => clearInterval(id);
  }, [load]);

  async function act(order, path, body, okMsg) {
    setBusy(order.publicId);
    try {
      await call(`/admin/orders/${order.publicId}/${path}`, { method: 'POST', body });
      if (okMsg) showToast(okMsg);
      await load();
    } catch (e) {
      showToast(e.message);
    }
    setBusy(null);
  }

  /** Abre el archivo en otra pestaña (visor del navegador, desde ahí se imprime). */
  async function openFile(order, markPrinting) {
    // La pestaña se abre ya, dentro del clic: si se abre después del await,
    // Safari y algunos Chrome la bloquean como popup.
    const win = window.open('', '_blank');
    try {
      const d = await call(`/admin/orders/${order.publicId}/archivo`, { method: 'POST' });
      const href = new URL(API + d.url, window.location.origin).href;
      if (win) win.location.href = href;
      else window.location.href = href;
      if (markPrinting && order.status === 'nuevo') await act(order, 'estado', { estado: 'imprimiendo' });
    } catch (e) {
      win?.close();
      showToast(e.message);
    }
  }

  async function toggleOpen() {
    try {
      await call('/admin/abierto', { method: 'POST', body: { abierto: !data.shop.isOpen } });
      await load();
    } catch (e) {
      showToast(e.message);
    }
  }

  if (!data) return <div className="empty">{error || <span className="spin" />}</div>;

  return (
    <>
      <div className="ahead">
        <div>
          <h1>Pedidos</h1>
          <button className="switch" role="switch" aria-checked={data.shop.isOpen} onClick={toggleOpen} style={{ marginTop: 10 }}>
            <span className="knob" /> {data.shop.isOpen ? 'Recibiendo pedidos' : 'Cerrado'}
          </button>
        </div>
        <div className="stats">
          <div className="stat"><small>Por imprimir</small><b>{data.stats.queue}</b></div>
          <div className="stat"><small>Pedidos hoy</small><b>{data.stats.todayCount}</b></div>
          <div className="stat"><small>Cobrado hoy</small><b>{money(data.stats.todayTotal)}</b></div>
        </div>
      </div>

      {data.shop.trialEndsAt && (
        data.shop.trialEnded ? (
          <div className="alert alert-bad">
            Tu semana de prueba terminó y tu página dejó de recibir pedidos. Escribinos para seguir usando Copiá Fácil.
          </div>
        ) : (
          <div className="alert alert-info">
            Estás en tu semana de prueba gratis: termina el{' '}
            <b>{new Date(data.shop.trialEndsAt).toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' })}</b>.
          </div>
        )
      )}
      {!data.shop.paymentsConnected && (
        <div className="alert alert-warn">
          Todavía no conectaste Mercado Pago: tus clientes no pueden pagar online. <Link to="/admin/configuracion">Conectalo en Configuración</Link>.
        </div>
      )}
      {error && <div className="alert alert-bad">{error}</div>}

      <div className="tabs" role="group" aria-label="Ver pedidos">
        <button aria-pressed={scope === 'activos'} onClick={() => { setScope('activos'); setData(null); }}>En curso</button>
        <button aria-pressed={scope === 'historial'} onClick={() => { setScope('historial'); setData(null); }}>Historial</button>
      </div>

      <div className="olist">
        {data.orders.length === 0 && (
          <div className="empty">{scope === 'activos' ? 'No hay pedidos pendientes. Cuando un cliente pague, aparece acá.' : 'Sin pedidos en los últimos 30 días.'}</div>
        )}
        {data.orders.map((o) => {
          const [label, cls] = o.paymentStatus === 'devolucion_pendiente' ? ['Devolución pendiente', 'p-devolucion'] : STATUS[o.status];
          const isBusy = busy === o.publicId;
          return (
            <article key={o.publicId} className={`oitem${fresh.has(o.publicId) ? ' fresh' : ''}`}>
              <div className="ohead">
                <span className="name">{o.customerName}</span>
                <span className="num">#{o.number}</span>
                <span className="time">{timeAgo(o.paidAt || o.createdAt)}</span>
                <span className={`pill ${cls}`}>{label}</span>
              </div>
              <div className="ofile">{o.fileName}</div>
              <div className="ospec">{specText(o)}</div>
              {o.note && <div className="onote">“{o.note}”</div>}
              {!o.pagesCounted && o.fileAvailable && <div className="owarn">No pudimos contar las páginas: el cliente declaró {o.pages}. Revisalo antes de imprimir.</div>}
              {!o.fileAvailable && <div className="owarn">El archivo ya se borró automáticamente.</div>}
              {o.rejectReason && <div className="onote">Motivo del rechazo: {o.rejectReason}</div>}

              <div className="ofoot">
                <span className="oprice">{money(o.total)}<small className={o.paymentStatus === 'aprobado' ? '' : 'muted'} style={{ color: o.paymentStatus === 'aprobado' ? 'var(--ok)' : undefined }}>{PAYMENT[o.paymentStatus] || ''}</small></span>
                <div className="oactions">
                  {rejecting === o.publicId ? (
                    <>
                      <input aria-label="Motivo (opcional)" placeholder="Motivo (opcional)" value={reason} maxLength={200} onChange={(e) => setReason(e.target.value)} />
                      <button className="btn btn-ghost btn-sm" onClick={() => setRejecting(null)}>No</button>
                      <button className="btn btn-danger btn-sm" disabled={isBusy} onClick={async () => { await act(o, 'rechazar', { motivo: reason }, 'Pedido rechazado'); setRejecting(null); setReason(''); }}>
                        Rechazar y devolver {money(o.total)}
                      </button>
                    </>
                  ) : (
                    <>
                      {o.paymentStatus === 'devolucion_pendiente' && (<>
                        <button className="btn btn-ghost btn-sm" disabled={isBusy} onClick={() => act(o, 'devolucion', { modo: 'reintentar' }, 'Devolución reintentada')}>Reintentar devolución</button>
                        <button className="btn btn-dark btn-sm" disabled={isBusy} onClick={() => act(o, 'devolucion', { modo: 'efectivo' }, 'Marcado como devuelto en efectivo')}>Devolví en efectivo</button>
                      </>)}
                      {['nuevo', 'imprimiendo'].includes(o.status) && (
                        <button className="btn btn-ghost btn-sm" style={{ borderColor: 'transparent', color: 'var(--muted)' }} onClick={() => { setRejecting(o.publicId); setReason(''); }}>Rechazar</button>
                      )}
                      {o.fileAvailable && ['nuevo', 'imprimiendo', 'listo'].includes(o.status) && (
                        <button className={`btn btn-sm ${o.status === 'nuevo' ? 'btn-dark' : 'btn-ghost'}`} disabled={isBusy} onClick={() => openFile(o, true)}>
                          {o.status === 'nuevo' ? 'Abrir e imprimir' : 'Abrir archivo'}
                        </button>
                      )}
                      {['nuevo', 'imprimiendo'].includes(o.status) && (
                        <button className={`btn btn-sm ${o.status === 'imprimiendo' ? 'btn-dark' : 'btn-ghost'}`} disabled={isBusy} onClick={() => act(o, 'estado', { estado: 'listo' }, `Le avisamos a ${o.customerName}`)}>Marcar listo</button>
                      )}
                      {o.status === 'listo' && (
                        <button className="btn btn-dark btn-sm" disabled={isBusy} onClick={() => act(o, 'estado', { estado: 'entregado' }, 'Entregado')}>Entregado</button>
                      )}
                    </>
                  )}
                </div>
              </div>

              {o.events.length > 1 && (
                <details>
                  <summary className="hint" style={{ cursor: 'pointer' }}>Historial</summary>
                  <ul className="hint" style={{ margin: '6px 0 0', paddingLeft: 18 }}>
                    {o.events.map((e, i) => (
                      <li key={i}>{new Date(e.at).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' })} · {e.message}</li>
                    ))}
                  </ul>
                </details>
              )}
            </article>
          );
        })}
      </div>
      {toast}
    </>
  );
}
