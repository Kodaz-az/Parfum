/**
 * Service Worker - Enhanced PWA Support with Notifications
 * Kodaz-az - 2025-07-21 14:50:56 (UTC)
 * Login: Kodaz-az
 */

const CACHE_NAME = 'parfum-pos-v1.1.0';
const DYNAMIC_CACHE_NAME = 'parfum-pos-dynamic-v1.1.0';

const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/manifest.json',
    '/assets/css/style.css',
    '/assets/js/pwa-manager.js',
    '/assets/js/sounds.js',
    '/assets/icons/icon-192x192.png',
    '/assets/icons/icon-512x512.png',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/chart.js'
];

const CACHE_STRATEGIES = {
    pages: 'networkFirst',
    api: 'networkOnly',
    assets: 'cacheFirst',
    images: 'cacheFirst'
};

// Install event
self.addEventListener('install', event => {
    console.log('Service Worker installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('Service Worker installed successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker installation failed:', error);
            })
    );
});

// Activate event
self.addEventListener('activate', event => {
    console.log('Service Worker activating...');
    
    event.waitUntil(
        Promise.all([
            // Clean up old caches
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== CACHE_NAME && cacheName !== DYNAMIC_CACHE_NAME) {
                            console.log('Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            // Claim all clients
            self.clients.claim()
        ]).then(() => {
            console.log('Service Worker activated successfully');
            
            // Notify all clients about update
            self.clients.matchAll().then(clients => {
                clients.forEach(client => {
                    client.postMessage({
                        type: 'sw-update',
                        message: 'Service Worker updated'
                    });
                });
            });
        })
    );
});

// Fetch event with advanced caching strategies
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    
    // Handle different types of requests
    if (url.pathname.startsWith('/api/')) {
        // API requests - network only
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    // Cache successful API responses for offline access
                    if (response.ok && event.request.method === 'GET') {
                        const responseClone = response.clone();
                        caches.open(DYNAMIC_CACHE_NAME).then(cache => {
                            cache.put(event.request, responseClone);
                        });
                    }
                    return response;
                })
                .catch(() => {
                    // Return cached API response if available
                    return caches.match(event.request);
                })
        );
    } else if (url.pathname.match(/\.(jpg|jpeg|png|gif|webp|svg)$/)) {
        // Images - cache first
        event.respondWith(
            caches.match(event.request)
                .then(response => {
                    if (response) {
                        return response;
                    }
                    
                    return fetch(event.request)
                        .then(response => {
                            if (response.ok) {
                                const responseClone = response.clone();
                                caches.open(DYNAMIC_CACHE_NAME).then(cache => {
                                    cache.put(event.request, responseClone);
                                });
                            }
                            return response;
                        });
                })
        );
    } else if (url.pathname.match(/\.(css|js)$/)) {
        // CSS/JS - cache first with network fallback
        event.respondWith(
            caches.match(event.request)
                .then(response => {
                    return response || fetch(event.request)
                        .then(response => {
                            if (response.ok) {
                                const responseClone = response.clone();
                                caches.open(CACHE_NAME).then(cache => {
                                    cache.put(event.request, responseClone);
                                });
                            }
                            return response;
                        });
                })
        );
    } else {
        // Pages - network first with cache fallback
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    if (response.ok) {
                        const responseClone = response.clone();
                        caches.open(DYNAMIC_CACHE_NAME).then(cache => {
                            cache.put(event.request, responseClone);
                        });
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(event.request)
                        .then(response => {
                            return response || caches.match('/');
                        });
                })
        );
    }
});

// Push notification handling
self.addEventListener('push', event => {
    console.log('Push message received:', event);
    
    let notificationData = {
        title: 'Parfüm POS',
        body: 'Yeni bildiriş',
        icon: '/assets/icons/icon-192x192.png',
        badge: '/assets/icons/badge-72x72.png',
        tag: 'default',
        requireInteraction: false,
        vibrate: [100, 50, 100],
        actions: []
    };
    
    if (event.data) {
        try {
            const data = event.data.json();
            notificationData = { ...notificationData, ...data };
        } catch (e) {
            notificationData.body = event.data.text();
        }
    }
    
    // Add default actions if none provided
    if (notificationData.actions.length === 0) {
        notificationData.actions = [
            {
                action: 'view',
                title: 'Bax',
                icon: '/assets/icons/view.png'
            },
            {
                action: 'dismiss',
                title: 'Bağla',
                icon: '/assets/icons/close.png'
            }
        ];
    }
    
    event.waitUntil(
        self.registration.showNotification(notificationData.title, notificationData)
    );
});

// Notification click handling
self.addEventListener('notificationclick', event => {
    console.log('Notification clicked:', event);
    
    event.notification.close();
    
    const action = event.action;
    const notificationData = event.notification.data || {};
    
    if (action === 'dismiss') {
        return;
    }
    
    // Determine URL to open
    let urlToOpen = '/';
    
    if (action === 'view' || !action) {
        if (notificationData.url) {
            urlToOpen = notificationData.url;
        } else if (notificationData.type) {
            switch (notificationData.type) {
                case 'sale':
                    urlToOpen = '/?page=sales';
                    break;
                case 'stock':
                    urlToOpen = '/?page=products';
                    break;
                case 'chat':
                    urlToOpen = '/?page=chat';
                    break;
                case 'support':
                    urlToOpen = '/?page=support';
                    break;
                default:
                    urlToOpen = '/?page=dashboard';
            }
        }
    } else if (action === 'install') {
        // Send message to main thread to trigger install prompt
        event.waitUntil(
            self.clients.matchAll({ type: 'window' })
                .then(clients => {
                    clients.forEach(client => {
                        client.postMessage({
                            type: 'notification-click',
                            action: 'install'
                        });
                    });
                })
        );
        return;
    }
    
    // Open or focus the app
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clients => {
                // Check if app is already open
                for (const client of clients) {
                    const clientUrl = new URL(client.url);
                    if (clientUrl.origin === self.location.origin) {
                        if (clientUrl.pathname + clientUrl.search === urlToOpen) {
                            return client.focus();
                        } else {
                            return client.focus().then(() => {
                                return client.navigate(urlToOpen);
                            });
                        }
                    }
                }
                
                // Open new window if app is not open
                return self.clients.openWindow(urlToOpen);
            })
    );
});

// Background sync for offline actions
self.addEventListener('sync', event => {
    console.log('Background sync:', event);
    
    if (event.tag === 'background-sync') {
        event.waitUntil(
            syncOfflineActions()
        );
    }
});

// Sync offline actions when connection is restored
async function syncOfflineActions() {
    try {
        // Get offline actions from IndexedDB (if implemented)
        const offlineActions = await getOfflineActions();
        
        for (const action of offlineActions) {
            try {
                await fetch(action.url, {
                    method: action.method,
                    headers: action.headers,
                    body: action.body
                });
                
                // Remove from offline storage after successful sync
                await removeOfflineAction(action.id);
            } catch (error) {
                console.log('Failed to sync action:', action.id, error);
            }
        }
    } catch (error) {
        console.log('Background sync failed:', error);
    }
}

// Placeholder functions for offline actions (would use IndexedDB in production)
async function getOfflineActions() {
    return []; // Return offline actions from IndexedDB
}

async function removeOfflineAction(id) {
    // Remove action from IndexedDB
}

// Message handling from main thread
self.addEventListener('message', event => {
    console.log('Service Worker received message:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CACHE_URLS') {
        event.waitUntil(
            caches.open(DYNAMIC_CACHE_NAME)
                .then(cache => {
                    return cache.addAll(event.data.payload);
                })
        );
    }
});

// Periodic background sync (if supported)
self.addEventListener('periodicsync', event => {
    console.log('Periodic sync:', event);
    
    if (event.tag === 'content-sync') {
        event.waitUntil(
            syncContent()
        );
    }
});

async function syncContent() {
    try {
        // Sync critical content in background
        const criticalUrls = [
            '/api/notifications.php',
            '/?page=dashboard'
        ];
        
        const cache = await caches.open(DYNAMIC_CACHE_NAME);
        
        for (const url of criticalUrls) {
            try {
                const response = await fetch(url);
                if (response.ok) {
                    await cache.put(url, response);
                }
            } catch (error) {
                console.log('Failed to sync:', url, error);
            }
        }
    } catch (error) {
        console.log('Content sync failed:', error);
    }
}

// Error handling
self.addEventListener('error', event => {
    console.error('Service Worker error:', event.error);
});

self.addEventListener('unhandledrejection', event => {
    console.error('Service Worker unhandled rejection:', event.reason);
});

console.log('Service Worker script loaded');