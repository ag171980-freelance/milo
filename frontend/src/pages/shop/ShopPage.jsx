import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { API, ApiError, api, money, plural, quote } from '../../lib.js';
import { LegalFooter, Segmented, Stepper } from '../../components/ui.jsx';
import NotFound from '../NotFound.jsx';

const ACCEPT = '.pdf,.jpg,.jpeg,.png,.doc,.docx';

/** Cuenta páginas de un PDF en el navegador, para mostrar el precio antes de subir. */
async function countPdfPages(file) {
  try {
    if (file.size > 60e6) return null;
    const txt = new TextDecoder('latin1').decode(await file.arrayBuffer());
    const m = txt.match(/\/Type\s*\/Page(?![s\w])/g);
    if (m && m.length) return m.length;
    let max = 0;
    for (const x of txt.matchAll(/\/Count\s+(\d+)/g)) max = Math.max(max, +x[1]);
    return max || null;
  } catch {
    return null;
  }
}

/** Sube con XMLHttpRequest para poder mostrar el progreso. */
function upload(path, form, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', API + path);
    xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded / e.total);
    xhr.onload = () => {
      let data = null;
      try { data = JSON.parse(xhr.responseText); } catch { /* respuesta vacía */ }
      if (xhr.status >= 200 && xhr.status < 300) resolve(data);
      else reject(new ApiError(xhr.status, data));
    };
    xhr.onerror = () => reject(new ApiError(0, null));
    xhr.send(form);
  });
}

export default function ShopPage() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const [shop, setShop] = useState(null);
  const [loadError, setLoadError] = useState(null);

  const [name, setName] = useState('');
  const [file, setFile] = useState(null);
  const [pages, setPages] = useState(1);
  const [pagesKnown, setPagesKnown] = useState(true);
  const [copies, setCopies] = useState(1);
  const [color, setColor] = useState('bn');
  const [sides, setSides] = useState('doble');
  const [paper, setPaper] = useState('A4');
  const [finish, setFinish] = useState('ninguna');
  const [note, setNote] = useState('');
  const [accept, setAccept] = useState(false);

  const [sending, setSending] = useState(false);
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState(null);
  const [fields, setFields] = useState({});
  const fileInput = useRef(null);

  useEffect(() => {
    api(`/t/${slug}`)
      .then((d) => { setShop(d.shop); document.title = `${d.shop.name} · Copiá Fácil`; })
      .catch((e) => setLoadError(e));
  }, [slug]);

  const q = useMemo(
    () => (shop ? quote(shop.prices, { pages, copies, color, sides, finish }) : null),
    [shop, pages, copies, color, sides, finish],
  );

  if (loadError?.status === 404) return <NotFound message="No encontramos ese local. Revisá el QR o el link." />;

  if (loadError) {
    return (
      <div className="client gutter">
        <main className="closed"><h1>No pudimos cargar el local</h1><p className="muted">{loadError.message}</p></main>
      </div>
    );
  }
  if (!shop) return <div className="client gutter"><main className="closed"><span className="spin" /></main></div>;

  const initials = shop.name.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
  const header = (
    <div className="shopbar">
      <div className="av" aria-hidden="true">{initials}</div>
      <div><b>{shop.name}</b><span>{shop.address}</span></div>
    </div>
  );

  if (!shop.acceptsOrders) {
    return (
      <div className="client">
        <main className="gutter">
          {header}
          <div className="closed">
            <h1>{shop.trialEnded ? 'No está tomando pedidos online' : shop.isOpen ? 'Todavía no toma pedidos online' : 'Ahora está cerrado'}</h1>
            <p className="muted">
              {shop.trialEnded
                ? 'Por ahora este local no recibe pedidos por acá. Pedí en el mostrador.'
                : shop.isOpen ? 'Este local está terminando de configurar los pagos. Pedí en el mostrador.' : 'Volvé a escanear el QR cuando abra.'}
            </p>
          </div>
        </main>
        <LegalFooter shop={shop} />
      </div>
    );
  }

  const maxBytes = shop.maxUploadMb * 1048576;

  async function pickFile(f) {
    setError(null);
    setFields((x) => ({ ...x, file: undefined }));
    if (!f) return;
    if (f.size > maxBytes) {
      setFields((x) => ({ ...x, file: `El archivo supera los ${shop.maxUploadMb} MB.` }));
      return;
    }
    setFile(f);
    let n = null;
    if (/\.pdf$/i.test(f.name) || f.type === 'application/pdf') n = await countPdfPages(f);
    else if (/^image\//.test(f.type)) n = 1;
    setPages(n || 1);
    setPagesKnown(!!n);
  }

  async function submit(e) {
    e.preventDefault();
    const errs = {};
    if (name.trim().length < 2) errs.name = 'Poné tu nombre.';
    if (!file) errs.file = 'Elegí el archivo que querés imprimir.';
    if (!accept) errs.acceptTerms = 'Tenés que aceptar para continuar.';
    setFields(errs);
    if (Object.keys(errs).length) return;

    const form = new FormData();
    Object.entries({ name: name.trim(), pages, copies, color, sides, paper, finish, note, acceptTerms: '1' })
      .forEach(([k, v]) => form.append(k, String(v)));
    form.append('file', file);

    setSending(true);
    setError(null);
    setProgress(0);
    try {
      const res = await upload(`/t/${slug}/orders`, form, setProgress);
      const path = `/${slug}/pedido/${res.order.publicId}`;
      if (res.checkoutUrl) {
        window.location.assign(res.checkoutUrl);
      } else {
        navigate(path);
      }
    } catch (err) {
      setSending(false);
      setFields(err.fields || {});
      setError(err.message);
    }
  }

  const ext = file ? (file.name.split('.').pop() || '').slice(0, 4) : '+';

  return (
    <div className="client">
      <main className="gutter">
        {header}
        <form onSubmit={submit} noValidate>
          <h1>Mandá a imprimir</h1>

          <div className="field">
            <label className="lbl" htmlFor="f-name">Tu nombre</label>
            <input id="f-name" className={`txt${fields.name ? ' invalid' : ''}`} autoComplete="given-name" placeholder="Ej: Juan" maxLength={60} value={name} onChange={(e) => setName(e.target.value)} />
            {fields.name && <span className="err">{fields.name}</span>}
          </div>

          <div className="field">
            <span className="lbl">Archivo</span>
            <label className={`drop${file ? ' has' : ''}${fields.file ? ' invalid' : ''}`} htmlFor="f-file">
              <span className="ic">{ext}</span>
              <span className="meta">
                <b>{file ? file.name : 'Elegí un archivo'}</b>
                <span>{file ? `${pagesKnown ? plural(pages, 'página', 'páginas') + ' · ' : ''}Cambiar` : `PDF, Word o foto · hasta ${shop.maxUploadMb} MB`}</span>
              </span>
            </label>
            <input ref={fileInput} className="vis-hidden" type="file" id="f-file" accept={ACCEPT} onChange={(e) => pickFile(e.target.files?.[0])} />
            {fields.file && <span className="err">{fields.file}</span>}
          </div>

          {file && !pagesKnown && (
            <div className="inline">
              <label className="lbl" htmlFor="f-pages">¿Cuántas páginas tiene?</label>
              <Stepper id="f-pages" label="páginas" value={pages} min={1} max={2000} onChange={setPages} />
            </div>
          )}

          <div className="inline">
            <label className="lbl" htmlFor="f-copies">Copias</label>
            <Stepper id="f-copies" label="copias" value={copies} min={1} max={50} onChange={setCopies} />
          </div>

          <div className="field">
            <Segmented label="Color" value={color} onChange={setColor} options={[
              { value: 'bn', label: 'Blanco y negro' },
              { value: 'color', label: 'Color', disabled: !shop.offers.color },
            ]} />
          </div>

          <div className="field">
            <Segmented label="Faz" value={sides} onChange={setSides} options={[
              { value: 'simple', label: 'Simple faz' },
              { value: 'doble', label: 'Doble faz' },
            ]} />
          </div>

          <details className="more">
            <summary>Más opciones</summary>
            <div className="field">
              <span className="lbl">Tamaño</span>
              <Segmented label="Tamaño" value={paper} onChange={setPaper} options={[
                { value: 'A4', label: 'A4' },
                { value: 'Oficio', label: 'Oficio', disabled: !shop.offers.oficio },
              ]} />
            </div>
            <div className="field">
              <span className="lbl">Terminación</span>
              <Segmented label="Terminación" value={finish} onChange={setFinish} options={[
                { value: 'ninguna', label: 'Suelto' },
                { value: 'abrochado', label: `Abrochado` },
                { value: 'anillado', label: 'Anillado', disabled: !shop.offers.anillado },
              ]} />
              {finish !== 'ninguna' && (
                <span className="hint">{finish === 'anillado' ? 'Anillado' : 'Abrochado'}: {money(finish === 'anillado' ? shop.prices.anillado : shop.prices.abrochado)} por copia</span>
              )}
            </div>
            <div className="field" style={{ marginBottom: 0 }}>
              <label className="lbl" htmlFor="f-note">Nota para el local</label>
              <textarea id="f-note" className="txt" rows={2} maxLength={200} placeholder="Ej: la tapa en color" value={note} onChange={(e) => setNote(e.target.value)} />
            </div>
          </details>

          <div className="field">
            <label className="check" htmlFor="f-accept">
              <input id="f-accept" type="checkbox" checked={accept} onChange={(e) => setAccept(e.target.checked)} />
              <span>
                Acepto los <Link to="/terminos" target="_blank">Términos y condiciones</Link> y la{' '}
                <Link to="/privacidad" target="_blank">Política de privacidad</Link>. Declaro que tengo derecho a imprimir este archivo.
              </span>
            </label>
            {fields.acceptTerms && <span className="err">{fields.acceptTerms}</span>}
          </div>

          {error && <div className="alert alert-bad" role="alert">{error}</div>}
          {shop.simulatedPayments && (
            <div className="alert alert-warn">Modo de prueba: este local todavía no conectó Mercado Pago y el pago va a ser simulado.</div>
          )}

          <div className="paybar">
            <div className="tot">
              <b>{money(q.total)}</b>
              <span>{plural(q.carillas, 'carilla', 'carillas')} × {money(q.unit)}{finish !== 'ninguna' ? ` + ${finish}` : ''}</span>
            </div>
            <button type="submit" className="btn btn-primary" disabled={sending}>
              {sending ? (<><span className="spin" /> {progress < 1 ? `Subiendo ${Math.round(progress * 100)}%` : 'Preparando pago…'}</>) : 'Ir a pagar'}
            </button>
          </div>
        </form>
      </main>
      <LegalFooter shop={shop} />
    </div>
  );
}
