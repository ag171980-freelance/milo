import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import QRCode from 'qrcode';
import { useToast } from '../../components/ui.jsx';

const PRICE_LABELS = [
  ['bnSimple', 'B/N simple faz', 'por carilla'],
  ['bnDoble', 'B/N doble faz', 'por carilla'],
  ['colorSimple', 'Color simple faz', 'por carilla'],
  ['colorDoble', 'Color doble faz', 'por carilla'],
  ['abrochado', 'Abrochado', 'por copia'],
  ['anillado', 'Anillado', 'por copia'],
];

export default function Settings({ call }) {
  const [s, setS] = useState(null);
  const [form, setForm] = useState(null);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState('');
  const [toast, showToast] = useToast();

  useEffect(() => {
    call('/admin/settings').then((d) => apply(d.settings)).catch((e) => showToast(e.message));
  }, [call]); // eslint-disable-line react-hooks/exhaustive-deps

  function apply(settings) {
    setS(settings);
    setForm({
      name: settings.name,
      address: settings.address,
      legalName: settings.legalName,
      cuit: settings.cuit,
      contactEmail: settings.contactEmail,
      contactPhone: settings.contactPhone,
      offers: { ...settings.offers },
      // En pesos para editar; se mandan en centavos.
      prices: Object.fromEntries(Object.entries(settings.prices).map(([k, v]) => [k, String(v / 100)])),
    });
  }

  async function save(section, body) {
    setSaving(section);
    setErrors({});
    try {
      const d = await call('/admin/settings', { method: 'PUT', body });
      apply(d.settings);
      showToast('Guardado');
    } catch (e) {
      setErrors(e.fields || {});
      showToast(e.message);
    }
    setSaving('');
  }

  if (!s || !form) return <div className="empty"><span className="spin" /></div>;
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  return (
    <>
      <div className="ahead"><h1>Configuración</h1></div>

      <section className="card">
        <h2>Tu local</h2>
        <p className="muted">Lo que ven tus clientes arriba de todo.</p>
        <div className="grid2">
          <div className="field">
            <label className="lbl" htmlFor="s-name">Nombre</label>
            <input id="s-name" className={`txt${errors.name ? ' invalid' : ''}`} value={form.name} onChange={set('name')} />
            {errors.name && <span className="err">{errors.name}</span>}
          </div>
          <div className="field">
            <label className="lbl" htmlFor="s-address">Dirección</label>
            <input id="s-address" className="txt" value={form.address} onChange={set('address')} placeholder="Av. Corrientes 1850, CABA" />
          </div>
        </div>
        <button className="btn btn-dark btn-sm" disabled={saving === 'local'} onClick={() => save('local', { name: form.name, address: form.address })}>Guardar</button>
      </section>

      <section className="card">
        <h2>Precios</h2>
        <p className="muted">El total que ve el cliente se calcula con estos precios. Los cambios rigen para los pedidos nuevos.</p>
        <div className="grid2">
          {PRICE_LABELS.map(([k, label, unit]) => (
            <div className="field" key={k}>
              <label className="lbl" htmlFor={`p-${k}`}>{label} <span className="muted" style={{ fontWeight: 400 }}>({unit})</span></label>
              <div className="money-in">
                <span>$</span>
                <input id={`p-${k}`} type="number" inputMode="decimal" min="0" step="10" value={form.prices[k]}
                  onChange={(e) => setForm({ ...form, prices: { ...form.prices, [k]: e.target.value } })} />
              </div>
              {errors[`prices.${k}`] && <span className="err">{errors[`prices.${k}`]}</span>}
            </div>
          ))}
        </div>
        <div className="toggles">
          {[['color', 'Imprimo en color'], ['oficio', 'Tengo hojas oficio'], ['anillado', 'Hago anillados']].map(([k, label]) => (
            <label className="check" key={k}>
              <input type="checkbox" checked={form.offers[k]} onChange={(e) => setForm({ ...form, offers: { ...form.offers, [k]: e.target.checked } })} />
              <span>{label}</span>
            </label>
          ))}
        </div>
        <button className="btn btn-dark btn-sm" disabled={saving === 'precios'} onClick={() => save('precios', {
          prices: Object.fromEntries(Object.entries(form.prices).map(([k, v]) => [k, Math.round(parseFloat(String(v).replace(',', '.')) * 100)])),
          offers: form.offers,
        })}>Guardar precios</button>
      </section>

      <MercadoPagoCard s={s} call={call} onChange={apply} showToast={showToast} />

      <QrCard url={s.shopUrl} name={s.name} />

      <section className="card">
        <h2>Datos para tus clientes</h2>
        <p className="muted">La Ley de Defensa del Consumidor pide que quien vende online muestre su razón social, CUIT y un contacto. Aparecen al pie de tu página.</p>
        <div className="grid2">
          <div className="field">
            <label className="lbl" htmlFor="s-legal">Razón social o nombre del titular</label>
            <input id="s-legal" className="txt" value={form.legalName} onChange={set('legalName')} />
          </div>
          <div className="field">
            <label className="lbl" htmlFor="s-cuit">CUIT</label>
            <input id="s-cuit" className={`txt${errors.cuit ? ' invalid' : ''}`} inputMode="numeric" value={form.cuit} onChange={set('cuit')} placeholder="20-12345678-9" />
            {errors.cuit && <span className="err">{errors.cuit}</span>}
          </div>
          <div className="field">
            <label className="lbl" htmlFor="s-email">Mail de contacto</label>
            <input id="s-email" className={`txt${errors.contactEmail ? ' invalid' : ''}`} type="email" value={form.contactEmail} onChange={set('contactEmail')} />
            {errors.contactEmail && <span className="err">{errors.contactEmail}</span>}
          </div>
          <div className="field">
            <label className="lbl" htmlFor="s-phone">Teléfono</label>
            <input id="s-phone" className="txt" type="tel" value={form.contactPhone} onChange={set('contactPhone')} />
          </div>
        </div>
        <button className="btn btn-dark btn-sm" disabled={saving === 'legal'} onClick={() => save('legal', {
          legalName: form.legalName, cuit: form.cuit, contactEmail: form.contactEmail, contactPhone: form.contactPhone,
        })}>Guardar</button>
      </section>

      <PasswordCard call={call} showToast={showToast} />
      {toast}
    </>
  );
}

function MercadoPagoCard({ s, call, onChange, showToast }) {
  const [params, setParams] = useSearchParams();
  const [token, setToken] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [confirmOff, setConfirmOff] = useState(false);
  const [returnMsg, setReturnMsg] = useState(null);
  const mp = s.mercadoPago;

  // Vuelta desde Mercado Pago: ?mp=conectado o ?mp=error&detalle=…
  useEffect(() => {
    const result = params.get('mp');
    if (!result) return;
    if (result === 'conectado') {
      setReturnMsg({ ok: true, text: '¡Listo! Tu cuenta de Mercado Pago quedó conectada.' });
      call('/admin/settings').then((d) => onChange(d.settings)).catch(() => {});
    } else {
      setReturnMsg({ ok: false, text: params.get('detalle') || 'No se pudo conectar Mercado Pago. Probá de nuevo.' });
    }
    setParams({}, { replace: true });
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  async function connectOAuth() {
    setBusy(true);
    setReturnMsg(null);
    try {
      const d = await call('/admin/mercadopago/oauth', { method: 'POST' });
      window.location.assign(d.url);
    } catch (err) {
      setReturnMsg({ ok: false, text: err.message });
      setBusy(false);
    }
  }

  async function connectManual(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const d = await call('/admin/mercadopago', { method: 'POST', body: { accessToken: token.trim() } });
      onChange(d.settings);
      setToken('');
      showToast('Mercado Pago conectado');
    } catch (err) {
      setError(err.message);
    }
    setBusy(false);
  }

  async function disconnect() {
    setBusy(true);
    try {
      const d = await call('/admin/mercadopago', { method: 'DELETE' });
      onChange(d.settings);
      setConfirmOff(false);
      showToast('Mercado Pago desconectado');
    } catch (err) {
      showToast(err.message);
    }
    setBusy(false);
  }

  const manualForm = (
    <form onSubmit={connectManual}>
      {!mp.oauthAvailable && (
        <ol className="steps-mp">
          <li>Entrá a <a href="https://www.mercadopago.com.ar/developers/panel/app" target="_blank" rel="noreferrer">Mercado Pago Developers → Tus integraciones</a> con la cuenta donde querés cobrar.</li>
          <li>Creá una aplicación (tipo «Pagos online», producto «Checkout Pro»).</li>
          <li>En <b>Credenciales de producción</b>, copiá el <b>Access Token</b> (empieza con APP_USR-) y pegalo acá.</li>
        </ol>
      )}
      <div className="field">
        <label className="lbl" htmlFor="mp-token">Access Token</label>
        <input id="mp-token" className={`txt mono${error ? ' invalid' : ''}`} autoComplete="off" spellCheck="false" placeholder="APP_USR-..." value={token} onChange={(e) => setToken(e.target.value)} />
        <span className="hint">Se guarda cifrado. Nunca lo compartas con nadie más.</span>
        {error && <span className="err">{error}</span>}
      </div>
      <button className="btn btn-dark btn-sm" disabled={busy || token.trim().length < 20}>{busy ? 'Verificando…' : 'Conectar'}</button>
    </form>
  );

  const connectButton = (
    <button type="button" className="btn btn-mp" onClick={connectOAuth} disabled={busy || !mp.oauthAvailable}>
      {busy ? <span className="spin" /> : null} Conectar con Mercado Pago
    </button>
  );

  return (
    <section className="card">
      <h2>Mercado Pago</h2>
      <p className="muted">Los pagos de tus clientes entran directo a tu cuenta de Mercado Pago. Copiá Fácil no toca la plata.</p>

      {returnMsg && <div className={`alert ${returnMsg.ok ? 'alert-ok' : 'alert-bad'}`} role="status">{returnMsg.text}</div>}

      {mp.connected ? (
        <>
          <div className="mp-status">
            <span className="pill p-listo">Conectado</span>
            <span>Cuenta: <b>{mp.nickname || '—'}</b></span>
            {mp.isTest && <span className="pill p-imprimiendo">Prueba</span>}
          </div>
          {mp.via === 'oauth' && <p className="hint" style={{ margin: '0 0 14px' }}>La conexión se renueva sola. No tenés que hacer nada más.</p>}
          {mp.isTest && <div className="alert alert-warn">Es una conexión de prueba: los pagos no son reales. Antes de usarlo con clientes, conectá tu cuenta real.</div>}
          {confirmOff ? (
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
              <span className="err" style={{ fontSize: 14 }}>Sin Mercado Pago tus clientes no van a poder pedir online.</span>
              <button className="btn btn-danger btn-sm" disabled={busy} onClick={disconnect}>Desconectar</button>
              <button className="btn btn-ghost btn-sm" onClick={() => setConfirmOff(false)}>Cancelar</button>
            </div>
          ) : (
            <button className="btn btn-ghost btn-sm" onClick={() => setConfirmOff(true)}>Desconectar o cambiar de cuenta</button>
          )}
        </>
      ) : (
        <>
          {mp.expired && <div className="alert alert-bad">La conexión con Mercado Pago venció y tus clientes no pueden pagar. Volvé a conectarla.</div>}
          <p style={{ margin: '0 0 14px', fontSize: 14 }}>Vas a ir a Mercado Pago, entrás con tu cuenta de siempre y tocás <b>Autorizar</b>. Volvés acá solo, en menos de un minuto.</p>
          {connectButton}
          {mp.oauthAvailable ? (
            <details style={{ marginTop: 18 }}>
              <summary className="hint" style={{ cursor: 'pointer' }}>Opción avanzada: pegar un access token</summary>
              <div style={{ marginTop: 12 }}>{manualForm}</div>
            </details>
          ) : (
            <>
              <p className="hint" style={{ margin: '10px 0 18px' }}>La conexión con un clic todavía no está habilitada. Mientras tanto, podés conectar tu cuenta pegando el access token:</p>
              {manualForm}
            </>
          )}
        </>
      )}

      {mp.webhookUrl && mp.connected && mp.via === 'manual' && (
        <div style={{ marginTop: 18 }}>
          <span className="lbl">Notificaciones (opcional, recomendado)</span>
          <p className="hint" style={{ margin: '4px 0 8px' }}>En tu aplicación de Mercado Pago → Webhooks, pegá esta URL y elegí el evento «Pagos». Así los pagos se confirman al instante.</p>
          <div className="code">{mp.webhookUrl}</div>
        </div>
      )}
    </section>
  );
}

function QrCard({ url, name }) {
  const canvas = useRef(null);
  const [png, setPng] = useState('');

  useEffect(() => {
    if (!canvas.current) return;
    QRCode.toCanvas(canvas.current, url, { width: 220, margin: 1, errorCorrectionLevel: 'M' }, () => {});
    QRCode.toDataURL(url, { width: 1200, margin: 2, errorCorrectionLevel: 'M' }).then(setPng).catch(() => {});
  }, [url]);

  return (
    <section className="card">
      <h2>Tu QR</h2>
      <p className="muted">Imprimilo y pegalo en el mostrador. Al escanearlo, tus clientes llegan directo a tu página.</p>
      <div className="qr-wrap">
        <canvas ref={canvas} aria-label={`Código QR de ${name}`} />
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10, minWidth: 0 }}>
          <div className="code">{url}</div>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            {png && <a className="btn btn-dark btn-sm" href={png} download={`qr-${name.toLowerCase().replace(/\s+/g, '-')}.png`}>Descargar QR</a>}
            <a className="btn btn-ghost btn-sm" href={url} target="_blank" rel="noreferrer">Ver mi página</a>
          </div>
        </div>
      </div>
    </section>
  );
}

function PasswordCard({ call, showToast }) {
  const [actual, setActual] = useState('');
  const [nueva, setNueva] = useState('');
  const [error, setError] = useState('');

  async function submit(e) {
    e.preventDefault();
    setError('');
    try {
      await call('/admin/password', { method: 'POST', body: { actual, nueva } });
      setActual('');
      setNueva('');
      showToast('Contraseña cambiada. Cerramos las otras sesiones.');
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <section className="card">
      <h2>Contraseña</h2>
      <form onSubmit={submit}>
        <div className="grid2">
          <div className="field">
            <label className="lbl" htmlFor="pw-a">Actual</label>
            <input id="pw-a" className="txt" type="password" autoComplete="current-password" value={actual} onChange={(e) => setActual(e.target.value)} />
          </div>
          <div className="field">
            <label className="lbl" htmlFor="pw-n">Nueva</label>
            <input id="pw-n" className="txt" type="password" autoComplete="new-password" minLength={10} value={nueva} onChange={(e) => setNueva(e.target.value)} />
            <span className="hint">Al menos 10 caracteres.</span>
          </div>
        </div>
        {error && <div className="alert alert-bad">{error}</div>}
        <button className="btn btn-dark btn-sm" disabled={!actual || nueva.length < 10}>Cambiar contraseña</button>
      </form>
    </section>
  );
}
