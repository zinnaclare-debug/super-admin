const DEVICE_ID_KEY = "school_portal_device_id";

function createDeviceId() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID().replace(/-/g, "");
  }

  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}${Math.random().toString(36).slice(2)}`;
}

export function getPortalDeviceId() {
  try {
    const existing = String(localStorage.getItem(DEVICE_ID_KEY) || "").trim();
    if (/^[a-zA-Z0-9_-]{16,200}$/.test(existing)) {
      return existing;
    }

    const created = createDeviceId();
    localStorage.setItem(DEVICE_ID_KEY, created);
    return created;
  } catch {
    return null;
  }
}