const CACHE_NAME = "berrygo-cache-v3";
const urlsToCache = [
  ".",
  "manifest.json",
  "assets/images/icon-192.png",
  "assets/images/icon-512.png",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      )
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const requestUrl = new URL(event.request.url);
  const isHttpRequest = requestUrl.protocol === "http:" || requestUrl.protocol === "https:";

  if (!isHttpRequest) {
    return;
  }

  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (event.request.method === "GET") {
            const respClone = response.clone();
            caches
              .open(CACHE_NAME)
              .then((cache) => cache.put(event.request, respClone));
          }
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) {
        return cached;
      }

      return fetch(event.request).catch(() => {
        if (event.request.destination === "document") {
          return caches.match(".");
        }
        return new Response("", { status: 504, statusText: "Gateway Timeout" });
      });
    })
  );
});
