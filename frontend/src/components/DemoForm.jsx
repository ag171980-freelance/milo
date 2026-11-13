import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib.js';

/** Formulario de «Pedí tu demo gratis de una semana», para comercios. */
export default function DemoForm() {
  const [f, setF] = useState({ name: '', shopName: '', address: '', phone: '', email: '', message: '', website: '' });
  const [accept, setAccept] = useState(false);
  const [fields, setFields] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    setFields({});
    try {
      await api('/demo', { method: 'POST', body: { ...f, acceptPrivacy: accept } });
      setDone(true);
    } catch (err) {
      setError(err.message);
      setFields(err.fields || {});
    }
    setBusy(false);
  }

  if (done) {
    return (
      <div className="demo-done" role="status">
        <h3>¡Listo, {f.name.split(' ')[0]}!</h3>
        <p>Recibimos tu solicitud. Te escribimos a <b>{f.email}</b> dentro de las próximas 24 horas hábiles con el acceso a tu panel y tu QR para empezar la semana de prueba.</p>
      </div>
    );
  }

  return (
    <form onSubmit={submit} noValidate>
      <div className="grid2">
        <div className="field">
          <label className="lbl" htmlFor="d-name">Tu nombre</label>
          <input id="d-name" className={`txt${fields.name ? ' invalid' : ''}`} autoComplete="name" value={f.name} onChange={set('name')} />
          {fields.name && <span className="err">{fields.name}</span>}
        </div>
        <div className="field">
          <label className="lbl" htmlFor="d-shop">Nombre del local</label>
          <input id="d-shop" className={`txt${fields.shopName ? ' invalid' : ''}`} autoComplete="organization" value={f.shopName} onChange={set('shopName')} />
          {fields.shopName && <span className="err">{fields.shopName}</span>}
        </div>
        <div className="field">
          <label className="lbl" htmlFor="d-email">Mail</label>
          <input id="d-email" className={`txt${fields.email ? ' invalid' : ''}`} type="email" autoComplete="email" value={f.email} onChange={set('email')} />
          {fields.email && <span className="err">{fields.email}</span>}
        </div>
        <div className="field">
          <label className="lbl" htmlFor="d-phone">WhatsApp <span className="muted" style={{ fontWeight: 400 }}>(opcional)</span></label>
          <input id="d-phone" className={`txt${fields.phone ? ' invalid' : ''}`} type="tel" autoComplete="tel" value={f.phone} onChange={set('phone')} />
          {fields.phone && <span className="err">{fields.phone}</span>}
        </div>
      </div>
      <div className="field">
        <label className="lbl" htmlFor="d-address">Dirección o barrio <span className="muted" style={{ fontWeight: 400 }}>(opcional)</span></label>
        <input id="d-address" className="txt" autoComplete="street-address" value={f.address} onChange={set('address')} />
      </div>
      <div className="field">
        <label className="lbl" htmlFor="d-message">¿Algo que quieras contarnos? <span className="muted" style={{ fontWeight: 400 }}>(opcional)</span></label>
        <textarea id="d-message" className="txt" rows={2} maxLength={500} value={f.message} onChange={set('message')} placeholder="Ej: imprimimos mucho para la facultad" />
      </div>

      {/* Trampa para bots: invisible para personas y lectores de pantalla. */}
      <div aria-hidden="true" style={{ position: 'absolute', left: '-9999px', width: 1, height: 1, overflow: 'hidden' }}>
        <label htmlFor="d-website">No completar</label>
        <input id="d-website" tabIndex={-1} autoComplete="off" value={f.website} onChange={set('website')} />
      </div>

      <div className="field">
        <label className="check" htmlFor="d-accept">
          <input id="d-accept" type="checkbox" checked={accept} onChange={(e) => setAccept(e.target.checked)} />
          <span>Acepto que usen estos datos para contactarme por la demo, según la <Link to="/privacidad" target="_blank">Política de privacidad</Link>.</span>
        </label>
        {fields.acceptPrivacy && <span className="err">{fields.acceptPrivacy}</span>}
      </div>

      {error && !Object.keys(fields).length && <div className="alert alert-bad" role="alert">{error}</div>}
      <button className="btn btn-primary" disabled={busy}>{busy ? <><span className="spin" /> Enviando…</> : 'Pedir mi semana gratis'}</button>
      <p className="hint" style={{ marginTop: 10 }}>Sin tarjeta y sin compromiso. Al terminar la semana decidís si seguís.</p>
    </form>
  );
}
