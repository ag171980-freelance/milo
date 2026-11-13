import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Logo, LegalFooter } from '../components/ui.jsx';
import DemoForm from '../components/DemoForm.jsx';

export default function Home() {
  useEffect(() => {
    document.title = 'Copiá Fácil · Pedidos de impresión por QR';
    // Si alguien llega con /#demo (por ejemplo desde un volante), va directo al formulario.
    if (window.location.hash === '#demo') document.getElementById('demo')?.scrollIntoView();
  }, []);

  const goDemo = (e) => {
    e.preventDefault();
    document.getElementById('demo')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => document.getElementById('d-name')?.focus({ preventScroll: true }), 400);
  };

  return (
    <>
      <div className="home gutter">
        <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
          <Logo />
          <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
            <Link to="/arrepentimiento" className="btn btn-ghost btn-sm">Botón de arrepentimiento</Link>
            <Link to="/admin" className="btn btn-ghost btn-sm">Entrar al panel</Link>
          </div>
        </header>

        <section className="hero">
          <h1>Tus clientes mandan a imprimir desde el celu.</h1>
          <p>
            Sin mails ni WhatsApp. Escanean el QR de tu mostrador, suben el archivo, eligen copias, color y faz, y pagan con Mercado Pago.
            A vos te llega todo listo para imprimir.
          </p>
          <a className="btn btn-primary" href="#demo" onClick={goDemo}>Pedí tu demo gratis de una semana</a>
        </section>

        <ol className="steps">
          <li><b>Escanean tu QR</b><span>Entran a la página de tu local. No necesitan app ni cuenta.</span></li>
          <li><b>Eligen y pagan</b><span>Ven el precio al instante, con tus precios. La plata entra a tu Mercado Pago.</span></li>
          <li><b>Imprimís y avisás</b><span>Abrís el archivo desde tu panel, lo imprimís y marcás listo. Les llega el aviso.</span></li>
        </ol>

        <section id="demo" className="demo">
          <div className="demo-copy">
            <h2>Probalo una semana en tu local, gratis</h2>
            <p>Te armamos tu página con tus precios y te mandamos el QR para pegar en el mostrador. Lo usás con clientes reales durante 7 días y después decidís.</p>
            <ul>
              <li>Sin tarjeta ni compromiso</li>
              <li>Te ayudamos a conectar tu Mercado Pago</li>
              <li>Los pagos van directo a tu cuenta</li>
            </ul>
          </div>
          <div className="demo-form">
            <DemoForm />
          </div>
        </section>

        <p className="muted" style={{ marginTop: 32 }}>¿Sos cliente y querés imprimir? Escaneá el QR que está en el mostrador del local.</p>
      </div>
      <div className="home" style={{ paddingTop: 0 }}>
        <LegalFooter />
      </div>
    </>
  );
}
