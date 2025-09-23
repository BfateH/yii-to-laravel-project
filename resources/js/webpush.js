class WebPushManager {
    constructor() {
        this.publicKey = null;
        this.isSupported = ('serviceWorker' in navigator) && ('PushManager' in window);
    }

    async init() {
        if (!this.isSupported) {
            console.log('Web Push not supported');
            return false;
        }

        try {
            const response = await fetch('/webpush/public-key');
            const data = await response.json();
            this.publicKey = data.publicKey;
            await this.registerServiceWorker();
            return true;
        } catch (error) {
            console.error('WebPush init error:', error);
            return false;
        }
    }

    async registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js');
            console.log('Service Worker registered:', registration);
            return registration;
        } catch (error) {
            console.error('Service Worker registration failed:', error);
            throw error;
        }
    }

    async subscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await this.sendSubscriptionToServer(subscription);
                return true;
            }

            const applicationServerKey = this.urlBase64ToUint8Array(this.publicKey);

            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            });

            await this.sendSubscriptionToServer(subscription);
            return true;
        } catch (error) {
            console.error('Subscription failed:', error);
            return false;
        }
    }

    async unsubscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await this.removeSubscriptionFromServer(subscription);
                await subscription.unsubscribe();
            }

            return true;
        } catch (error) {
            console.error('Unsubscription failed:', error);
            return false;
        }
    }

    async sendSubscriptionToServer(subscription) {
        const subscriptionData = {
            endpoint: subscription.endpoint,
            keys: {
                p256dh: subscription.getKey('p256dh') ?
                    btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))) : '',
                auth: subscription.getKey('auth') ?
                    btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth')))) : ''
            }
        };

        const response = await fetch('/webpush/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(subscriptionData)
        });

        if (!response.ok) {
            throw new Error('Failed to save subscription');
        }
    }

    async removeSubscriptionFromServer(subscription) {
        const response = await fetch('/webpush/unsubscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                endpoint: subscription.endpoint
            })
        });

        if (!response.ok) {
            throw new Error('Failed to remove subscription');
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async isSubscribed() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            return !!subscription;
        } catch (error) {
            console.error('Check subscription error:', error);
            return false;
        }
    }
}


document.addEventListener('DOMContentLoaded', function() {
    const webPushManager = new WebPushManager();

    webPushManager.init().then(async (supported) => {
        if (supported) {
            const isSubscribed = await webPushManager.isSubscribed();
            if (!isSubscribed) {
                await webPushManager.subscribe();
            }
        }
    });
});

window.WebPushManager = WebPushManager;
