import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Logo } from '../../components/ui.jsx';
import { ULTIMA_ACTUALIZACION } from '../../legal.js';

export default function LegalLayout({ title, children, updated = true }) {
  useEffect(() => { document.title = `${title} · Copiá Fácil`; window.scrollTo(0, 0); }, [title]);

  return (
    <div className="doc gutter">
      <header><Logo /></header>
      <h1>{title}</h1>
      {children}
      {updated && <p className="updated">Última actualización: {ULTIMA_ACTUALIZACION}</p>}
      <nav className="legal-nav" aria-label="Documentos legales">
        <Link to="/terminos">Términos y condiciones</Link>
        <Link to="/privacidad">Política de privacidad</Link>
        <Link to="/comercios">Términos para comercios</Link>
        <Link to="/arrepentimiento"><b>Botón de arrepentimiento</b></Link>
        <a href="https://www.argentina.gob.ar/produccion/defensadelconsumidor/formulario" target="_blank" rel="noreferrer">
          Defensa de las y los consumidores. Para reclamos ingrese aquí
        </a>
      </nav>
    </div>
  );
}
