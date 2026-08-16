// Service worker «Уроки мужества 72» — кэширует только статику сайта.
// Все страницы index.php (личные кабинеты, формы с CSRF-токеном, разные роли)
// намеренно НЕ кэшируются — идут напрямую в сеть, чтобы не показывать
// устаревшие данные и не ломать отправку форм просроченным токеном.
const CACHE_NAME = 'um72-static-v2';
const STATIC_ASSETS = [
  '/style.css',
  '/favicon.ico',
  '/favicon.png',
  '/icon-192.png',
  '/icon-512.png',
  '/icon-maskable-512.png',
  '/manifest.json',
  '/offline.html'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) => Promise.all(
      names.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Переходы по страницам (личный кабинет, формы) в сеть всегда идут напрямую —
  // но если сети совсем нет, показываем свою заглушку вместо ошибки браузера.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  const isStaticAsset = STATIC_ASSETS.some((path) => url.pathname === path || url.pathname.endsWith(path));
  if (!isStaticAsset) return;

  event.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req).then((res) => {
        if (res && res.ok) {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
        }
        return res;
      }).catch(() => cached);
      return cached || network;
    })
  );
});
