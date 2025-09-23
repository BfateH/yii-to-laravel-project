console.log('Service Worker загружен');

self.addEventListener('push', function(event) {
    console.log('Получено push-уведомление', event);

    if (!(self.Notification && self.Notification.permission === 'granted')) {
        console.log('Уведомления не разрешены');
        return;
    }

    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = {
                title: 'Уведомление',
                body: event.data.text()
            };
        }
    } else {
        data = {
            title: 'Уведомление',
            body: 'У вас новое уведомление'
        };
    }

    const title = data.title || 'Уведомление';
    const options = {
        body: data.body || '',
        icon: data.icon || '/images/notification-icon.png',
        badge: data.badge || '/images/notification-badge.png',
        data: data.data || {},
        requireInteraction: true
    };

    console.log('Отображение уведомления:', title, options);

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    console.log('Клик по уведомлению', event);

    event.notification.close();
    event.waitUntil(
        clients.openWindow('/admin').catch(err => {
            console.error('Ошибка открытия окна:', err);
        })
    );
});


self.addEventListener('error', function(event) {
    console.error('Service Worker ошибка:', event.error);
});

self.addEventListener('install', function(event) {
    console.log('Service Worker установлен');
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function(event) {
    console.log('Service Worker активирован');
    event.waitUntil(self.clients.claim());
});
