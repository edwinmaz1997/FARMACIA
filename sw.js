// Service worker: permite instalar la app y muestra una pantalla sin conexión.
// Las páginas siempre se piden a la red (datos en tiempo real); solo los archivos estáticos se guardan en caché.
const VERSION = 'farmapro-v1';
const STATIC = [
  'offline.html',
  'assets/app.css',
  'assets/vendor/lucide.min.js',
  'assets/vendor/chart.umd.min.js',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Navegación: red primero, pantalla offline si no hay conexión
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }

  // Estáticos: caché y actualización en segundo plano
  if (/\/assets\//.test(url.pathname)) {
    e.respondWith(
      caches.open(VERSION).then((c) =>
        c.match(req).then((hit) => {
          const net = fetch(req).then((res) => {
            if (res.ok) c.put(req, res.clone());
            return res;
          }).catch(() => hit);
          return hit || net;
        })
      )
    );
  }
});
