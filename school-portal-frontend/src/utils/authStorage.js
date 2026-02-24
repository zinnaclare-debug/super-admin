function safeStorageGet(storage, key) {
  try {
    return storage.getItem(key);
  } catch {
    return null;
  }
}

function safeStorageSet(storage, key, value) {
  try {
    storage.setItem(key, value);
  } catch {
    // ignore storage write errors
  }
}

function safeStorageRemove(storage, key) {
  try {
    storage.removeItem(key);
  } catch {
    // ignore storage remove errors
  }
}

function hasWindow() {
  return typeof window !== "undefined";
}

function readPreferred(key) {
  if (!hasWindow()) return null;

  const sessionValue = safeStorageGet(window.sessionStorage, key);
  if (sessionValue !== null) return sessionValue;

  return safeStorageGet(window.localStorage, key);
}

export function getStoredToken() {
  return readPreferred("token");
}

export function getStoredUser() {
  const raw = readPreferred("user");
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function getStoredFeatures() {
  const raw = readPreferred("features");
  if (!raw) return [];
  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

export function setAuthState({ token, user }) {
  if (!hasWindow()) return;

  safeStorageRemove(window.localStorage, "token");
  safeStorageRemove(window.localStorage, "user");

  if (token) {
    safeStorageSet(window.sessionStorage, "token", token);
  }
  if (user) {
    safeStorageSet(window.sessionStorage, "user", JSON.stringify(user));
  }
}

export function setStoredFeatures(features) {
  if (!hasWindow()) return;

  safeStorageRemove(window.localStorage, "features");
  safeStorageSet(window.sessionStorage, "features", JSON.stringify(features || []));
}

export function clearAuthState() {
  if (!hasWindow()) return;

  ["token", "user", "features"].forEach((key) => {
    safeStorageRemove(window.sessionStorage, key);
    safeStorageRemove(window.localStorage, key);
  });
}
