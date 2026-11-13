import { Logo } from '../components/ui.jsx';

export default function NotFound({ message = 'Esta página no existe.' }) {
  return (
    <div className="client gutter">
      <main className="closed">
        <Logo />
        <h1 style={{ marginTop: 28 }}>No encontrado</h1>
        <p className="muted">{message}</p>
      </main>
    </div>
  );
}
