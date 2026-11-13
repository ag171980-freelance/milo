import { useCallback, useEffect, useState } from 'react';
import { NavLink, Route, Routes } from 'react-router-dom';
import { api, storage } from '../../lib.js';
import { Logo } from '../../components/ui.jsx';
import Login from './Login.jsx';
import Orders from './Orders.jsx';
import Settings from './Settings.jsx';

const KEY = 'cf-admin-token';

export default function AdminApp() {
  const [token, setToken] = useState(() => storage.get(KEY));
  const [user, setUser] = useState(null);

  const logout = useCallback(async () => {
    const t = token;
    storage.del(KEY);
    setToken(null);
    setUser(null);
    if (t) api('/admin/logout', { method: 'POST', token: t }).catch(() => {});
  }, [token]);

  // Un 401 en cualquier llamada del panel cierra la sesión.
  const call = useCallback(async (path, opts = {}) => {
    try {
      return await api(path, { ...opts, token });
    } catch (e) {
      if (e.status === 401) { storage.del(KEY); setToken(null); setUser(null); }
      throw e;
    }
  }, [token]);

  useEffect(() => {
    document.title = 'Panel · Copiá Fácil';
    if (!token) return;
    call('/admin/me').then((d) => setUser(d.user)).catch(() => {});
  }, [token, call]);

  if (!token) {
    return <Login onLogin={(t, u) => { storage.set(KEY, t); setToken(t); setUser(u); }} />;
  }

  return (
    <div className="admin">
      <header className="atop">
        <div className="in gutter">
          <Logo to="/admin" />
          <nav>
            <NavLink to="/admin" end>Pedidos</NavLink>
            <NavLink to="/admin/configuracion">Configuración</NavLink>
          </nav>
          <div className="right">
            <span className="who muted" style={{ fontSize: 13 }}>{user?.shop?.name}</span>
            <button className="btn btn-ghost btn-sm" onClick={logout}>Salir</button>
          </div>
        </div>
      </header>
      <main className="amain gutter">
        <Routes>
          <Route index element={<Orders call={call} />} />
          <Route path="configuracion" element={<Settings call={call} />} />
        </Routes>
      </main>
    </div>
  );
}
