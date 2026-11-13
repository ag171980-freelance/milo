// Notificaciones del navegador para avisarle al cliente que su pedido está listo.
//
// Hoy funcionan mientras la página del pedido está abierta (aunque esté en
// segundo plano): la página consulta el estado y, si cambia, muestra la
// notificación. Para avisar con el navegador cerrado hace falta Web Push
// desde el servidor (ver README, «Lo que sigue»).

let reg = null;

export function registerServiceWorker() {
  if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register('/sw.js').then((r) => { reg = r; }).catch(() => {});
  }
}

const isIOS = () =>
  /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isStandalone = () =>
  navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;

/** 'granted' | 'default' | 'denied' | 'ios-home' | 'insecure' | 'unsupported' */
export function notifySupport() {
  if (!window.isSecureContext) return 'insecure';
  if (!('Notification' in window)) return isIOS() && !isStandalone() ? 'ios-home' : 'unsupported';
  return Notification.permission;
}

export async function requestNotifications() {
  try {
    return await Notification.requestPermission();
  } catch {
    return 'denied';
  }
}

export async function notify(title, body) {
  if (!('Notification' in window) || Notification.permission !== 'granted') return;
  const opts = { body, icon: '/icon.svg', badge: '/icon.svg', tag: 'copia-facil', renotify: true };
  try {
    const r = reg || (navigator.serviceWorker && (await navigator.serviceWorker.getRegistration()));
    if (r) { await r.showNotification(title, opts); return; }
  } catch { /* cae al constructor */ }
  try { new Notification(title, opts); } catch { /* Android sin SW */ }
}
