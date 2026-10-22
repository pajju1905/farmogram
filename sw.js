// Dynamic base path detection for Service Worker
const isLocal = self.location.hostname === 'localhost' || self.location.hostname === '127.0.0.1';
const BASE = isLocal ? '/farmogram' : '';

const CACHE_NAME = 'farmogram-cache-v5';
const urlsToCache = [
  BASE + '/',
  BASE + '/index.php',
  BASE + '/auth/login.php',
  BASE + '/auth/register.php',
  BASE + '/about.php',
  BASE + '/assets/css/main.css',
  BASE + '/assets/images/logo-light.png',
  BASE + '/assets/images/logo-dark.png',
  BASE + '/assets/images/app-mockup.png',
  BASE + '/manifest.php'
];

const OFFLINE_PAGE = BASE + '/index.php';

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return Promise.all(
          urlsToCache.map(url => 
            fetch(url).then(response => {
              if (response.ok) {
                return cache.put(url, response);
              }
            }).catch(error => {
              console.log('Failed to cache:', url, error);
            })
          )
        );
      })
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') {
      return; 
  }
  
  // Network First for PHP pages & navigation
  if (event.request.mode === 'navigate' || event.request.url.includes('.php')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseToCache);
          });
          return response;
        })
        .catch(() => {
          return caches.match(event.request).then(cachedResponse => {
            return cachedResponse || caches.match(OFFLINE_PAGE);
          });
        })
    );
    return;
  }

  // Cache First for static assets
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request).then(
          function(response) {
            if(!response || response.status !== 200) {
              return response;
            }
            if (response.type === 'basic' || response.type === 'cors') {
              var responseToCache = response.clone();
              caches.open(CACHE_NAME)
                .then(function(cache) {
                  cache.put(event.request, responseToCache);
                });
            }
            return response;
          }
        ).catch(() => {
          return new Response('', { status: 408, statusText: 'Offline' });
        });
      })
  );
});
