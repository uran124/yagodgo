// Временно отключаем перехват запросов service worker.
// Старые версии маскировали ошибки загрузки картинок как 504 Gateway Timeout.
const CACHE_NAME = "berrygo-cache-disabled-v5";

self.addEventListener("install", (event) => {
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

// Важно: не вызываем event.respondWith().
// Все запросы к страницам, картинкам, /assets и /uploads идут напрямую в сеть.
self.addEventListener("fetch", () => {
  return;
});
