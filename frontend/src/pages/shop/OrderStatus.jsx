import { useCallback, useEffect, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api, money, specText } from '../../lib.js';
import { BELL, CHECK, LegalFooter } from '../../components/ui.jsx';
import { notify, notifySupport, requestNotifications } from '../../notifications.js';
import NotFound from '../NotFound.jsx';

const LEADS = {
  nuevo: 'Estás en la cola. Te avisamos acá cuando esté listo.',
  imprimiendo: 'Están imprimiendo tu pedido.',
  listo: '¡Listo! Pasá por el mostrador y decí tu nombre o tu número.',
  entregado: 'Ya retiraste tu pedido. ¡Gracias!',
};

const REFUND_TEXT = {
  devuelto: 'Te devolvimos la plata por Mercado Pago. Si pagaste con dinero en cuenta la ves enseguida; con tarjeta puede tardar unos días según tu banco.',
  devolucion_pendiente: 'La devolución está en proceso. Si no la ves en las próximas horas, acercate al local.',
  devuelto_efectivo: 'El local te devolvió la plata en efectivo.',
};

export default function OrderStatus() {
  const { slug, publicId } = useParams();
  const [order, setOrder] = useState(null);
  const [shop, setShop] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const [perm, setPerm] = useState(notifySupport());
  const [permMsg, setPermMsg] = useState('');
  const [confirmCancel, setConfirmCancel] = useState(false);
  const [cancelResult, setCancelResult] = useState(null);
  const prevStatus = useRef(null);
  const syncTries = useRef(0);

  const load = useCallback(async () => {
    try {
      // Mientras no figure pagado, se le pregunta a Mercado Pago (no hay
      // webhook en local, y en producción puede tardar unos segundos).
      const pending = !prevStatus.current || prevStatus.current === 'pendiente_pago';
      const d = pending && syncTries.current < 40
        ? (syncTries.current++, await api(`/t/${slug}/orders/${publicId}/sincronizar`, { method: 'POST' }))
        : await api(`/t/${slug}/orders/${publicId}`);
      const o = d.order;
      const before = prevStatus.current;
      if (before && before !== o.status) {
        if (o.status === 'listo') notify('¡Tu pedido está listo!', `#${o.number} · Pasá a retirarlo por ${o.shop.name}.`);
        if (o.status === 'rechazado') notify('No pudieron imprimir tu pedido', `Te devolvimos ${money(o.total)}.`);
      }
      prevStatus.current = o.status;
      setOrder(o);
      setError(null);
    } catch (e) {
      setError(e);
    }
  }, [slug, publicId]);

  useEffect(() => {
    load();
    api(`/t/${slug}`).then((d) => setShop(d.shop)).catch(() => {});
    const id = setInterval(() => {
      if (document.visibilityState === 'visible' || 'Notification' in window) load();
    }, 6000);
    return () => clearInterval(id);
  }, [load, slug]);

  if (error?.status === 404) return <NotFound message="No encontramos ese pedido." />;
  if (!order) {
    return <div className="client gutter"><main className="closed">{error ? <p>{error.message}</p> : <span className="spin" />}</main></div>;
  }

  async function payAgain() {
    setBusy(true);
    try {
      const d = await api(`/t/${slug}/orders/${publicId}/pagar`, { method: 'POST' });
      if (d.checkoutUrl) window.location.assign(d.checkoutUrl);
      else setBusy(false);
    } catch (e) {
      setBusy(false);
      setError(e);
    }
  }

  async function fakePay() {
    setBusy(true);
    try {
      const d = await api(`/t/${slug}/orders/${publicId}/pago-simulado`, { method: 'POST' });
      prevStatus.current = d.order.status;
      setOrder(d.order);
    } catch (e) {
      setError(e);
    }
    setBusy(false);
  }

  async function cancel() {
    setBusy(true);
    try {
      const d = await api('/arrepentimiento', { method: 'POST', body: { pedido: publicId } });
      setCancelResult(d);
      setConfirmCancel(false);
      await load();
    } catch (e) {
      setError(e);
    }
    setBusy(false);
  }

  async function askPermission() {
    const res = await requestNotifications();
    setPerm(notifySupport());
    if (res === 'granted') {
      setPermMsg('');
      notify('¡Listo, te vamos a avisar!', `Te mandamos una notificación cuando tu pedido #${order.number} esté para retirar.`);
    } else if (res === 'default') {
      setPermMsg('No se activaron. Tocá de nuevo y elegí "Permitir".');
    }
  }

  const s = order.status;
  const steps = ['Pago recibido', 'Imprimiendo', 'Listo para retirar'];
  const idx = s === 'entregado' ? 2 : ['nuevo', 'imprimiendo', 'listo'].indexOf(s);
  const active = idx >= 0;

  let banner;
  if (s === 'pendiente_pago') {
    banner = <div className="okline wait"><span className="spin" /> {order.paymentStatus === 'rechazado' ? 'El pago fue rechazado' : 'Esperando la confirmación del pago…'}</div>;
  } else if (s === 'rechazado' || s === 'cancelado') {
    banner = <div className="okline bad">{s === 'rechazado' ? 'El local no pudo imprimir tu pedido' : 'Pedido cancelado'}</div>;
  } else {
    banner = <div className="okline">{CHECK} Pagaste {money(order.total)}</div>;
  }

  return (
    <div className="client">
      <main className="gutter">
        <div className="shopbar">
          <div className="av" aria-hidden="true">{order.shop.name.split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase()}</div>
          <div><b>{order.shop.name}</b><span>{order.shop.address}</span></div>
        </div>

        {banner}
        <div className="ordlbl">Tu número</div>
        <div className="ordnum">#{order.number}</div>

        {active && <p className="muted" style={{ margin: 0 }}>{s === 'listo' ? `${order.customerName}, ${LEADS.listo.toLowerCase()}` : LEADS[s]}</p>}
        {s === 'rechazado' && order.rejectReason && <p className="muted" style={{ margin: 0 }}>Motivo: {order.rejectReason}</p>}
        {(s === 'rechazado' || s === 'cancelado') && REFUND_TEXT[order.paymentStatus] && (
          <div className="alert alert-info" style={{ marginTop: 14 }}>{REFUND_TEXT[order.paymentStatus]}</div>
        )}
        {cancelResult && <div className="alert alert-ok" style={{ marginTop: 14 }}>{cancelResult.mensaje} Código de solicitud: <b className="mono">{cancelResult.codigo}</b></div>}

        {active && (
          <ol className="timeline">
            {steps.map((label, i) => {
              const st = i === 0 || i < idx || (i === 2 && idx === 2) ? 'done' : i === idx ? 'now' : '';
              return <li key={label} className={st}><span className="dot">{st === 'done' ? CHECK : null}</span><span>{label}</span></li>;
            })}
          </ol>
        )}

        {s === 'pendiente_pago' && (
          <div style={{ margin: '20px 0' }}>
            {shop?.simulatedPayments ? (
              <button className="btn btn-dark btn-block" onClick={fakePay} disabled={busy}>Pagar (modo prueba)</button>
            ) : (
              <button className="btn btn-dark btn-block" onClick={payAgain} disabled={busy}>
                {order.paymentStatus === 'rechazado' ? 'Probar con otro medio de pago' : 'Ir a pagar con Mercado Pago'}
              </button>
            )}
          </div>
        )}

        {(s === 'nuevo' || s === 'imprimiendo') && (
          <div className={`notify${perm === 'granted' ? ' ok' : perm === 'default' ? '' : ' muted'}`}>
            <span className="bell">{BELL}</span>
            {perm === 'granted' && <p>Te vamos a avisar al celu cuando esté listo, mientras tengas esta página abierta.</p>}
            {perm === 'default' && (<>
              <p>{permMsg || '¿Te avisamos cuando esté listo? Así no tenés que esperar en el local.'}</p>
              <button type="button" className="btn btn-dark btn-sm" onClick={askPermission}>Recibir notificación</button>
            </>)}
            {perm === 'denied' && <p>Bloqueaste las notificaciones. Podés activarlas desde la configuración del navegador.</p>}
            {perm === 'ios-home' && <p>En iPhone: tocá Compartir y «Agregar a inicio», y abrí la página desde ahí para recibir avisos.</p>}
            {(perm === 'insecure' || perm === 'unsupported') && <p>Tu navegador no permite notificaciones. Dejá esta página abierta para ver el estado.</p>}
          </div>
        )}

        <div className="summary-card">
          <div className="row"><span className="muted">Archivo</span><span>{order.fileName}</span></div>
          <div className="row"><span className="muted">Detalle</span><span>{specText(order)}</span></div>
          {order.note && <div className="row"><span className="muted">Nota</span><span>{order.note}</span></div>}
          <div className="row"><span className="muted">Total</span><span className="mono">{money(order.total)}</span></div>
        </div>

        {order.canWithdraw && !cancelResult && (
          <div style={{ textAlign: 'center', marginBottom: 12 }}>
            {confirmCancel ? (
              <div className="alert alert-warn" style={{ textAlign: 'left' }}>
                <p style={{ margin: '0 0 10px' }}>¿Cancelar el pedido? {s === 'nuevo' ? `Te devolvemos ${money(order.total)} por Mercado Pago.` : 'No se te va a cobrar nada.'}</p>
                <div style={{ display: 'flex', gap: 8 }}>
                  <button className="btn btn-danger btn-sm" onClick={cancel} disabled={busy}>Sí, cancelar</button>
                  <button className="btn btn-ghost btn-sm" onClick={() => setConfirmCancel(false)}>No</button>
                </div>
              </div>
            ) : (
              <button className="link" onClick={() => setConfirmCancel(true)}>Cancelar pedido (botón de arrepentimiento)</button>
            )}
          </div>
        )}

        {error && error.status !== 404 && <div className="alert alert-bad">{error.message}</div>}
      </main>
      <LegalFooter shop={shop} />
    </div>
  );
}
