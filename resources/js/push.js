// Web push: "Activar notificaciones" on the rentals page and "Desactivar" in the profile.
// Server values come from <meta> tags in layouts/app.blade.php.

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';

const VAPID_PUBLIC = meta('vapid-public-key');
const isPushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

function urlBase64ToUint8Array(b64) {
    const padding = '='.repeat((4 - b64.length % 4) % 4);
    const raw = atob((b64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

function sameKey(buffer, key) {
    const current = new Uint8Array(buffer ?? []);
    return current.length === key.length && current.every((byte, i) => byte === key[i]);
}

function sendSubscription(method, body) {
    return fetch(meta('push-subscriptions-url'), {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': meta('csrf-token') },
        body: JSON.stringify(body),
    });
}

function failureMessage(response, action) {
    const isSessionExpired = response.status === 419 || response.status === 401;
    if (isSessionExpired) return 'Tu sesión venció. Recargá la página y probá de nuevo.';
    return `No se pudieron ${action} las notificaciones (error ${response.status}). Probá de nuevo.`;
}

// Registers the service worker here instead of only waiting on serviceWorker.ready,
// which hangs forever (silently) when the registration on page load failed.
async function activeServiceWorker() {
    const registration = await navigator.serviceWorker.register('/service-worker.js', { updateViaCache: 'none' });
    const timeout = new Promise((_, reject) => setTimeout(() => reject(new Error('el service worker no se activó')), 10000));
    await Promise.race([navigator.serviceWorker.ready, timeout]);
    return registration;
}

// A subscription made with other VAPID keys makes subscribe() fail: drop it and subscribe again.
async function newSubscription(registration, key) {
    const existing = await registration.pushManager.getSubscription();
    const isReusable = existing && sameKey(existing.options?.applicationServerKey, key);
    if (isReusable) return existing;
    if (existing) await existing.unsubscribe();
    return registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
}

// This browser's subscription made with the current keys, if any (without asking for permission).
async function currentSubscription() {
    const canHaveSubscription = isPushSupported && VAPID_PUBLIC && Notification.permission === 'granted';
    if (!canHaveSubscription) return null;
    const registration = await navigator.serviceWorker.getRegistration();
    const subscription = await registration?.pushManager.getSubscription();
    const isCurrent = subscription && sameKey(subscription.options?.applicationServerKey, urlBase64ToUint8Array(VAPID_PUBLIC));
    return isCurrent ? subscription : null;
}

async function enablePush(button) {
    if (!isPushSupported) {
        alert('Este navegador no permite notificaciones. En iPhone, primero instalá la app en la pantalla de inicio y abrila desde ahí.');
        return;
    }
    if (!VAPID_PUBLIC) {
        alert('Las notificaciones no están configuradas en el servidor.');
        return;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        alert('No diste permiso para las notificaciones. Habilitalo en la configuración del navegador para este sitio.');
        return;
    }

    const registration = await activeServiceWorker();
    const subscription = await newSubscription(registration, urlBase64ToUint8Array(VAPID_PUBLIC));

    const response = await sendSubscription('POST', subscription);
    if (!response.ok) {
        alert(failureMessage(response, 'activar'));
        return;
    }

    button.hidden = true;
    alert('Notificaciones activadas ✅');
}

async function disablePush(section) {
    const subscription = await currentSubscription();
    if (subscription) {
        const response = await sendSubscription('DELETE', { endpoint: subscription.endpoint });
        if (!response.ok) {
            alert(failureMessage(response, 'desactivar'));
            return;
        }
        await subscription.unsubscribe();
    }

    section.querySelector('[data-push-enabled]').hidden = true;
    section.querySelector('[data-push-disabled]').hidden = false;
}

function onClick(button, action, errorPrefix) {
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            await action();
        } catch (error) {
            console.error(error);
            alert(`${errorPrefix}: ${error.message || error}`);
        } finally {
            button.disabled = false;
        }
    });
}

// Rentals page: the button only shows when this browser is not subscribed yet. An existing
// subscription is sent again so the server keeps it (expired endpoint removed, another member...).
async function initEnableButton(button) {
    onClick(button, () => enablePush(button), 'No se pudieron activar las notificaciones');

    let subscription = null;
    try {
        subscription = await currentSubscription();
    } catch (error) {
        console.error(error);
    }
    button.hidden = Boolean(subscription);
    if (subscription) sendSubscription('POST', subscription).catch(console.error);
}

// Profile: the section only shows when this browser is subscribed.
async function initDisableSection(section) {
    onClick(section.querySelector('button'), () => disablePush(section), 'No se pudieron desactivar las notificaciones');

    const subscription = await currentSubscription().catch(() => null);
    section.hidden = !subscription;
}

const enableButton = document.getElementById('enablePush');
if (enableButton) initEnableButton(enableButton);

const disableSection = document.getElementById('pushDevice');
if (disableSection) initDisableSection(disableSection);
