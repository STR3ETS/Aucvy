/*
 | De meldingenknop op de kavelpagina (hfst. 14): web push voor "je bent
 | overboden", want mail is daarvoor te laat. De browser vraagt pas om
 | toestemming als de bezoeker zelf op de knop drukt.
 */
export default function pushToggle() {
    return {
        supported: false,
        enabled: false,
        denied: false,
        busy: false,

        async init() {
            this.supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

            if (! this.supported) return;

            this.denied = Notification.permission === 'denied';

            if (Notification.permission === 'granted') {
                const registration = await navigator.serviceWorker.register('/sw.js');
                this.enabled = (await registration.pushManager.getSubscription()) !== null;
            }
        },

        async toggle() {
            if (this.busy || ! this.supported) return;
            this.busy = true;

            try {
                if (this.enabled) {
                    await this.unsubscribe();
                } else {
                    await this.subscribe();
                }
            } catch {
                // Push is nice-to-have; een fout mag het bieden nooit storen.
            }

            this.busy = false;
        },

        async subscribe() {
            if (await Notification.requestPermission() !== 'granted') {
                this.denied = Notification.permission === 'denied';

                return;
            }

            const registration = await navigator.serviceWorker.register('/sw.js');
            const key = document.querySelector('meta[name=vapid-public-key]')?.content;

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.decodeKey(key),
            });

            const response = await this.send('POST', subscription.toJSON());

            if (response.ok) this.enabled = true;
        },

        async unsubscribe() {
            const registration = await navigator.serviceWorker.register('/sw.js');
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await this.send('DELETE', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }

            this.enabled = false;
        },

        send(method, body) {
            return fetch('/api/push/subscription', {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify(body),
            });
        },

        // De VAPID-sleutel is base64url; de Push API wil bytes.
        decodeKey(base64url) {
            const padded = base64url + '='.repeat((4 - (base64url.length % 4)) % 4);
            const raw = atob(padded.replace(/-/g, '+').replace(/_/g, '/'));

            return Uint8Array.from(raw, (character) => character.charCodeAt(0));
        },
    };
}
