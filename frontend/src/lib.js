// Utilidades compartidas: API, formato y el cálculo de precio que se muestra.

export const API = import.meta.env.VITE_API_URL || '/api';

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.error?.mensaje || 'No pudimos conectarnos. Revisá tu conexión y probá de nuevo.');
    this.status = status;
    this.code = body?.error?.codigo || 'error';
    this.fields = body?.error?.campos || {};
  }
}

/** fetch a la API. `body` puede ser un objeto (va como JSON) o un FormData. */
export async function api(path, { method = 'GET', body, token } = {}) {
  const headers = {};
  if (token) headers.Authorization = `Bearer ${token}`;
  let payload;
  if (body instanceof FormData) payload = body;
  else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(API + path, { method, headers, body: payload });
  } catch {
    throw new ApiError(0, null);
  }
  const data = await res.json().catch(() => null);
  if (!res.ok) throw new ApiError(res.status, data);
  return data;
}

/** Centavos → "$ 1.250" (o "$ 1.250,50" si hay centavos). */
export function money(cents) {
  const pesos = cents / 100;
  return '$ ' + pesos.toLocaleString('es-AR', {
    minimumFractionDigits: Number.isInteger(pesos) ? 0 : 2,
    maximumFractionDigits: 2,
  });
}

export function plural(n, one, many) {
  return `${n} ${n === 1 ? one : many}`;
}

/**
 * El mismo cálculo que backend/src/Order/Pricing.php. Sólo para mostrar:
 * lo que se cobra lo decide siempre el servidor.
 */
export function quote(prices, { pages, copies, color, sides, finish }) {
  const n = Math.max(1, pages | 0);
  const c = Math.max(1, copies | 0);
  const doble = sides === 'doble' && n > 1;
  const unit = prices[(color === 'color' ? 'color' : 'bn') + (doble ? 'Doble' : 'Simple')];
  const carillas = n * c;
  const hojas = (doble ? Math.ceil(n / 2) : n) * c;
  const finishUnit = finish === 'abrochado' ? prices.abrochado : finish === 'anillado' ? prices.anillado : 0;
  return { doble, carillas, hojas, unit, total: unit * carillas + finishUnit * c };
}

export function specText(o) {
  const parts = [
    plural(o.copies, 'copia', 'copias'),
    plural(o.pages, 'pág.', 'págs.'),
    o.color === 'color' ? 'Color' : 'B/N',
    o.sides === 'doble' ? 'Doble faz' : 'Simple faz',
  ];
  if (o.paper && o.paper !== 'A4') parts.push(o.paper);
  if (o.finish === 'abrochado') parts.push('Abrochado');
  if (o.finish === 'anillado') parts.push('Anillado');
  return parts.join(' · ');
}

export function timeAgo(iso) {
  if (!iso) return '';
  const m = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
  if (m < 1) return 'recién';
  if (m < 60) return `hace ${m} min`;
  const h = Math.round(m / 60);
  if (h < 24) return `hace ${h} h`;
  return new Date(iso).toLocaleDateString('es-AR', { day: 'numeric', month: 'short' });
}

/** localStorage que no rompe en modo privado. */
export const storage = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch { /* sin storage */ } },
  del(k) { try { localStorage.removeItem(k); } catch { /* sin storage */ } },
};
