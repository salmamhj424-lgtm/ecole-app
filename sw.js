const CACHE_NAME = "ecole-static-v1";
const STATIC_FILES = [
  "/style.css",
  "/manifest.json",
  "/icons/icon-192.png",
  "/icons/icon-512.png"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_FILES))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Les pages PHP restent toujours connectées au serveur :
  // aucune page privée, session ou réponse de formulaire n'est mise en cache.
  if (request.method !== "GET" || url.origin !== self.location.origin ||
      url.pathname.endsWith(".php")) {
    return;
  }

  // Cache uniquement les ressources statiques de l'application.
  if (STATIC_FILES.includes(url.pathname)) {
    event.respondWith(
      caches.match(request).then((cached) =>
        cached || fetch(request).then((response) => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          }
          return response;
        })
      )
    );
  }
});
