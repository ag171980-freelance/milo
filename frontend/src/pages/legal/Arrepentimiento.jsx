import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import LegalLayout from './LegalLayout.jsx';
import { api } from '../../lib.js';

/**
 * Botón de arrepentimiento (Res. SCI 424/2020): acceso directo desde la
 * primera pantalla, sin registro previo, y con un código de identificación
 * para quien lo usa.
 */
export default function Arrepentimiento() {
  const [params] = useSearchParams();
  const [pedido, setPedido] = useState(params.get('pedido') || '');
  const [contacto, setContacto] = useState('');
  const [detalle, setDetalle] = useState('');
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [fields, setFields] = useState({});
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    setFields({});
    try {
      setResult(await api('/arrepentimiento', { method: 'POST', body: { pedido, contacto, detalle } }));
    } catch (err) {
      setError(err.message);
      setFields(err.fields || {});
    }
    setBusy(false);
  }

  return (
    <LegalLayout title="Botón de arrepentimiento" updated={false}>
      <p>
        Si te arrepentiste de un pedido, podés cancelarlo desde acá. <b>Si todavía no empezó a imprimirse, lo cancelamos en el acto y te
        devolvemos el total.</b> Si ya se imprimió, tu solicitud queda registrada y el local la revisa. No hace falta registrarse.
      </p>

      {result ? (
        <div className="box" role="status">
          <p style={{ marginTop: 0 }}><b>{result.mensaje}</b></p>
          <p style={{ marginBottom: 0 }}>Código de tu solicitud: <b className="mono" style={{ fontSize: 18 }}>{result.codigo}</b></p>
          <p className="muted" style={{ marginBottom: 0, fontSize: 13 }}>Guardalo: te sirve para cualquier consulta o reclamo.</p>
        </div>
      ) : (
        <form onSubmit={submit} className="box" style={{ maxWidth: 520 }}>
          <div className="field">
            <label className="lbl" htmlFor="a-pedido">Link o código de tu pedido</label>
            <input id="a-pedido" className="txt" value={pedido} onChange={(e) => setPedido(e.target.value)} placeholder="Pegá el link que abriste después de pagar" />
            <span className="hint">Es el link de la página donde seguís tu pedido.</span>
          </div>
          <div className="field">
            <label className="lbl" htmlFor="a-contacto">Mail o teléfono (opcional si tenés el link)</label>
            <input id="a-contacto" className={`txt${fields.contacto ? ' invalid' : ''}`} value={contacto} onChange={(e) => setContacto(e.target.value)} />
            {fields.contacto && <span className="err">{fields.contacto}</span>}
          </div>
          <div className="field">
            <label className="lbl" htmlFor="a-detalle">Comentario (opcional)</label>
            <textarea id="a-detalle" className="txt" rows={3} maxLength={500} value={detalle} onChange={(e) => setDetalle(e.target.value)} />
          </div>
          {error && <div className="alert alert-bad">{error}</div>}
          <button className="btn btn-dark" disabled={busy || (!pedido.trim() && !contacto.trim())}>{busy ? 'Enviando…' : 'Me arrepiento: cancelar pedido'}</button>
        </form>
      )}

      <h2>¿Qué dice la ley?</h2>
      <p>
        En las compras a distancia tenés 10 días corridos para revocar la aceptación (art. 34 de la Ley 24.240 y arts. 1110 y siguientes del
        Código Civil y Comercial). Los trabajos hechos según las especificaciones del cliente, como la impresión de tu archivo, quedan
        exceptuados una vez que empezaron a hacerse (art. 1116, inc. a). Por eso cancelamos sin costo todo pedido que no haya empezado a
        imprimirse.
      </p>
    </LegalLayout>
  );
}
