// Service Worker para caching y funcionalidad offline

const CACHE_NAME = 'medical-app-v1';
const STATIC_CACHE = 'medical-static-v1';
const DYNAMIC_CACHE = 'medical-dynamic-v1';

// Recursos estáticos para cachear
const STATIC_ASSETS = [
    '/',
    '/index.html',
    '/login.html',
    '/register.html',
    '/style.css',
    '/script.js',
    '/validation.js',
    'https://cdn.tailwindcss.com',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'
];

// Instalar Service Worker
self.addEventListener('install', (event) => {
    console.log('Service Worker: Installing...');

    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                console.log('Service Worker: Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('Service Worker: Static assets cached successfully');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('Service Worker: Error caching static assets', error);
            })
    );
});

// Activar Service Worker
self.addEventListener('activate', (event) => {
    console.log('Service Worker: Activating...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Service Worker: Deleting old cache', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: Activated successfully');
                return self.clients.claim();
            })
    );
});

// Interceptar peticiones de red
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Estrategia de cache para diferentes tipos de recursos
    if (request.method === 'GET') {
        if (STATIC_ASSETS.includes(url.pathname) || url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot)$/)) {
            // Cache First para recursos estáticos
            event.respondWith(cacheFirst(request));
        } else if (url.pathname.startsWith('/api/')) {
            // Network First para API calls
            event.respondWith(networkFirst(request));
        } else {
            // Stale While Revalidate para páginas HTML
            event.respondWith(staleWhileRevalidate(request));
        }
    }
});

// Cache First Strategy
async function cacheFirst(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        const networkResponse = await fetch(request);
        const cache = await caches.open(STATIC_CACHE);

        // Solo cachear respuestas exitosas
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        console.error('Cache First failed:', error);
        return new Response('Offline content not available', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

// Network First Strategy
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);

        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        console.log('Network failed, trying cache:', error);
        const cachedResponse = await caches.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        // Si no hay cache, devolver error offline
        return new Response(JSON.stringify({
            success: false,
            message: 'Sin conexión a internet',
            offline: true
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Stale While Revalidate Strategy
async function staleWhileRevalidate(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cachedResponse = await caches.match(request);

    // Iniciar fetch en background para actualizar cache
    const networkUpdate = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch((error) => {
            console.log('Background fetch failed:', error);
        });

    // Devolver respuesta cacheada inmediatamente si existe
    if (cachedResponse) {
        return cachedResponse;
    }

    // Si no hay cache, esperar a la respuesta de red
    return networkUpdate;
}

// Manejar mensajes desde la aplicación principal
self.addEventListener('message', (event) => {
    const { type, payload } = event.data;

    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;

        case 'GET_VERSION':
            event.ports[0].postMessage({
                type: 'VERSION',
                payload: { version: CACHE_NAME }
            });
            break;

        case 'CLEAR_CACHE':
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => caches.delete(cacheName))
                );
            });
            break;

        case 'UPDATE_CACHE':
            // Forzar actualización de cache
            caches.open(STATIC_CACHE).then((cache) => {
                return cache.addAll(STATIC_ASSETS);
            });
            break;
    }
});

// Manejar notificaciones push (si se implementan)
self.addEventListener('push', (event) => {
    if (event.data) {
        const data = event.data.json();
        const options = {
            body: data.body,
            icon: '/icon-192x192.png',
            badge: '/badge-72x72.png',
            vibrate: [100, 50, 100],
            data: {
                dateOfArrival: Date.now(),
                primaryKey: data.primaryKey
            },
            actions: [
                {
                    action: 'explore',
                    title: 'Ver',
                    icon: '/explore-icon.png'
                },
                {
                    action: 'close',
                    title: 'Cerrar',
                    icon: '/close-icon.png'
                }
            ]
        };

        event.waitUntil(
            self.registration.showNotification(data.title, options)
        );
    }
});

// Manejar clicks en notificaciones
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});

// Sincronización en background
self.addEventListener('sync', (event) => {
    if (event.tag === 'background-sync') {
        event.waitUntil(
            // Implementar sincronización de datos offline
            doBackgroundSync()
        );
    }
});

async function doBackgroundSync() {
    try {
        // Sincronizar datos pendientes cuando hay conexión
        console.log('Service Worker: Background sync triggered');

        // Implementar lógica de sincronización
        // Por ejemplo, enviar datos guardados localmente al servidor

    } catch (error) {
        console.error('Service Worker: Background sync failed', error);
    }
}

// Manejar errores del service worker
self.addEventListener('error', (event) => {
    console.error('Service Worker error:', event.error);
});

self.addEventListener('unhandledrejection', (event) => {
    console.error('Service Worker unhandled rejection:', event.reason);
});

// Función para verificar si el navegador soporta Service Worker
function isServiceWorkerSupported() {
    return 'serviceWorker' in navigator;
}

// Función para registrar el Service Worker
async function registerServiceWorker() {
    if (!isServiceWorkerSupported()) {
        console.log('Service Workers not supported');
        return;
    }

    try {
        const registration = await navigator.serviceWorker.register('/service-worker.js');
        console.log('Service Worker registered successfully:', registration);

        // Verificar actualizaciones
        registration.addEventListener('updatefound', () => {
            const newWorker = registration.installing;
            newWorker.addEventListener('statechange', () => {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                    // Mostrar notificación de actualización disponible
                    showUpdateNotification();
                }
            });
        });

        return registration;
    } catch (error) {
        console.error('Service Worker registration failed:', error);
    }
}

function showUpdateNotification() {
    if ('Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification('Actualización disponible', {
            body: 'Una nueva versión de la aplicación está disponible. Recargue la página para actualizar.',
            icon: '/icon-192x192.png',
            tag: 'app-update'
        });

        notification.onclick = () => {
            window.location.reload();
        };
    }
}

// Función para desregistrar el Service Worker
async function unregisterServiceWorker() {
    if ('serviceWorker' in navigator) {
        try {
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (const registration of registrations) {
                await registration.unregister();
                console.log('Service Worker unregistered');
            }
        } catch (error) {
            console.error('Service Worker unregistration failed:', error);
        }
    }
}