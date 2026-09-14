import api from "../services/api";

function urlBase64ToUint8Array(value) {
  const padding = "=".repeat((4 - (value.length % 4)) % 4);
  const base64 = (value + padding).replace(/-/g, "+").replace(/_/g, "/");
  const raw = window.atob(base64);
  return Uint8Array.from(raw, (character) => character.charCodeAt(0));
}

export function canUseDeviceNotifications() {
  return typeof window !== "undefined" && "Notification" in window && "serviceWorker" in navigator && "PushManager" in window;
}

export async function enableDeviceNotifications() {
  if (!canUseDeviceNotifications()) throw new Error("This browser does not support device notifications.");
  const permission = await window.Notification.requestPermission();
  if (permission !== "granted") throw new Error("Notifications were not allowed. You can enable them later in your browser settings.");
  const keyResponse = await api.get("/api/notifications/vapid-public-key");
  const publicKey = keyResponse.data?.data?.public_key;
  if (!publicKey) throw new Error("Device notifications are not configured yet.");
  const registration = await navigator.serviceWorker.register("/push-sw.js", { scope: "/notifications/" });
  let subscription = await registration.pushManager.getSubscription();
  if (!subscription) subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(publicKey) });
  await api.post("/api/notifications/subscriptions", subscription.toJSON());
  return subscription;
}

export async function disableDeviceNotifications() {
  if (!canUseDeviceNotifications()) return;
  const registration = await navigator.serviceWorker.getRegistration("/notifications/");
  const subscription = await registration?.pushManager.getSubscription();
  if (!subscription) return;
  await api.delete("/api/notifications/subscriptions", { data: { endpoint: subscription.endpoint } });
  await subscription.unsubscribe();
}