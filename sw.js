// Service worker de l'application « École » : ne met en cache que les fichiers statiques.
// Les pages PHP (connexion, notes, paiements…) ne passent JAMAIS par le cache.
const CACHE_NAME = "ecole-static-v3";
const STATIC_FILES = [
  "/style.css",
  "/manifest.json",
];

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_FILES)));
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

  if (request.method !== "GET" || url.origin !== self.location.origin ||
      url.pathname.endsWith(".php") || !STATIC_FILES.includes(url.pathname)) {
    return; // le navigateur gère normalement (toujours en direct)
  }

  // Réseau d'abord : les mises à jour du design apparaissent tout de suite ;
  // le cache ne sert que si le téléphone est hors ligne.
  event.respondWith(
    fetch(request)
      .then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      })
      .catch(() => caches.match(request))
  );
});
