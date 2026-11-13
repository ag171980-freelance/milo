import { useState } from 'react';
import { api } from '../../lib.js';
import { Logo } from '../../components/ui.jsx';

export default function Login({ onLogin }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const d = await api('/admin/login', { method: 'POST', body: { email, password } });
      onLogin(d.token, d.user);
    } catch (err) {
      setError(err.message);
      setBusy(false);
    }
  }

  return (
    <main className="login gutter">
      <Logo to="/" />
      <h1>Panel del local</h1>
      <form onSubmit={submit}>
        <div className="field">
          <label className="lbl" htmlFor="l-email">Mail</label>
          <input id="l-email" className="txt" type="email" autoComplete="username" required value={email} onChange={(e) => setEmail(e.target.value)} />
        </div>
        <div className="field">
          <label className="lbl" htmlFor="l-pass">Contraseña</label>
          <input id="l-pass" className="txt" type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} />
        </div>
        {error && <div className="alert alert-bad" role="alert">{error}</div>}
        <button className="btn btn-dark btn-block" disabled={busy}>{busy ? <span className="spin" /> : 'Entrar'}</button>
      </form>
      <p className="hint" style={{ marginTop: 18 }}>¿Olvidaste la contraseña? Escribinos y te la reseteamos.</p>
    </main>
  );
}
