const CACHE = 'viewpay-v2.0.0';
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
  '/',
  '/tasks',
  '/watch-ads',
  '/wallet',
  '/login',
  '/dashboard',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
];

// Install
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(PRECACHE).catch(() => {}))
    .then(() => self.skipWaiting())
  );
});

// Activate
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// Fetch — network first, fall back to cache
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  const url = new URL(e.request.url);
  // Skip admin, POST, external analytics
  if (url.pathname.startsWith('/admin') || url.hostname.includes('google-analytics')) return;

  e.respondWith(
    fetch(e.request)
      .then(res => {
        if (res.ok && res.type === 'basic') {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return res;
      })
      .catch(() => caches.match(e.request).then(r => r || new Response(
        '<html><body style="font-family:sans-serif;text-align:center;padding:60px"><h2>You are offline</h2><p>Check your connection and try again.</p></body></html>',
        { headers: { 'Content-Type': 'text/html' } }
      )))
  );
});
