import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { OPERADOR } from '../legal.js';

export function Logo({ to = '/' }) {
  return (
    <Link to={to} className="brand" aria-label="Copiá Fácil, inicio">
      <span className="logo" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M7 9V3h10v6" /><rect x="3" y="9" width="18" height="8" rx="2" /><path d="M7 14h10v7H7z" /></svg>
      </span>
      <b>Copiá Fácil</b>
    </Link>
  );
}

export function Segmented({ label, value, options, onChange }) {
  return (
    <div className="seg" role="radiogroup" aria-label={label}>
      {options.map((o) => (
        <button
          key={o.value}
          type="button"
          role="radio"
          aria-checked={value === o.value}
          disabled={o.disabled}
          onClick={() => onChange(o.value)}
        >
          {o.label}
        </button>
      ))}
    </div>
  );
}

export function Stepper({ id, value, min, max, onChange, label }) {
  const set = (v) => onChange(Math.min(max, Math.max(min, Number.isFinite(v) ? v : min)));
  return (
    <div className="stepper">
      <button type="button" onClick={() => set(value - 1)} aria-label={`Menos ${label}`}>−</button>
      <input id={id} type="number" inputMode="numeric" min={min} max={max} value={value} onChange={(e) => set(parseInt(e.target.value, 10))} />
      <button type="button" onClick={() => set(value + 1)} aria-label={`Más ${label}`}>+</button>
    </div>
  );
}

export const CHECK = (
  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12l5 5 9-10" /></svg>
);

export const BELL = (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
);

/**
 * Pie legal de las páginas del cliente. Incluye lo que exige la normativa
 * de consumo para ventas online: identificación del proveedor, botón de
 * arrepentimiento y enlace a Defensa del Consumidor.
 */
export function LegalFooter({ shop }) {
  const l = shop?.legal;
  return (
    <footer className="legal-foot gutter">
      <nav aria-label="Información legal">
        <Link className="arrep" to="/arrepentimiento">Botón de arrepentimiento</Link>
        <Link to="/terminos">Términos y condiciones</Link>
        <Link to="/privacidad">Privacidad</Link>
        <a href="https://www.argentina.gob.ar/produccion/defensadelconsumidor/formulario" target="_blank" rel="noreferrer">
          Defensa de las y los consumidores. Para reclamos ingrese aquí
        </a>
      </nav>
      {shop && (
        <div>
          Vende e imprime: {l?.legalName || shop.name}
          {l?.cuit ? ` · CUIT ${l.cuit}` : ''}
          {shop.address ? ` · ${shop.address}` : ''}
          {l?.email ? ` · ${l.email}` : ''}
          {l?.phone ? ` · ${l.phone}` : ''}
        </div>
      )}
      <div>Copiá Fácil es un producto de {OPERADOR.marca || OPERADOR.titular} · Pagos con Mercado Pago</div>
    </footer>
  );
}

export function useToast() {
  const [msg, setMsg] = useState('');
  const t = useRef();
  useEffect(() => () => clearTimeout(t.current), []);
  // Estable entre renders: se usa como dependencia de efectos con polling.
  const show = useCallback((m) => {
    setMsg(m);
    clearTimeout(t.current);
    t.current = setTimeout(() => setMsg(''), 2800);
  }, []);
  const node = <div className={`toast${msg ? ' show' : ''}`} role="status" aria-live="polite">{msg}</div>;
  return [node, show];
}
