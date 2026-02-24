function hasWindow() {
  return typeof window !== "undefined";
}

function safeGet(storage, key) {
  try {
    return storage.getItem(key);
  } catch {
    return null;
  }
}

function safeSet(storage, key, value) {
  try {
    storage.setItem(key, value);
  } catch {
    // ignore storage write errors
  }
}

function readPreferred(key) {
  if (!hasWindow()) return null;

  const sessionValue = safeGet(window.sessionStorage, key);
  if (sessionValue !== null) return sessionValue;

  return safeGet(window.localStorage, key);
}

function storageKey(user, role) {
  const schoolId = Number(user?.school_id || 0);
  const userId = Number(user?.id || 0);
  return `announcement_seen:${role || "unknown"}:${schoolId}:${userId}`;
}

function rankOf(item) {
  const publishedAtMs = item?.published_at ? Date.parse(item.published_at) : NaN;
  return {
    publishedAtMs: Number.isFinite(publishedAtMs) ? publishedAtMs : 0,
    id: Number(item?.id || 0),
  };
}

function isGreaterRank(a, b) {
  if (!b) return true;
  if (a.publishedAtMs !== b.publishedAtMs) return a.publishedAtMs > b.publishedAtMs;
  return a.id > b.id;
}

export function getLatestAnnouncement(items) {
  if (!Array.isArray(items) || items.length === 0) return null;
  return items.reduce((latest, current) => {
    if (!latest) return current;
    return isGreaterRank(rankOf(current), rankOf(latest)) ? current : latest;
  }, null);
}

export function getSeenAnnouncementRank(user, role) {
  const raw = readPreferred(storageKey(user, role));
  if (!raw) return null;

  try {
    const parsed = JSON.parse(raw);
    if (
      parsed &&
      Number.isFinite(Number(parsed.publishedAtMs)) &&
      Number.isFinite(Number(parsed.id))
    ) {
      return {
        publishedAtMs: Number(parsed.publishedAtMs),
        id: Number(parsed.id),
      };
    }
  } catch {
    // ignore parse errors
  }

  return null;
}

export function markAnnouncementSeen(user, role, item) {
  if (!hasWindow() || !item) return;
  const rank = rankOf(item);
  const value = JSON.stringify(rank);
  const key = storageKey(user, role);
  safeSet(window.sessionStorage, key, value);
  safeSet(window.localStorage, key, value);
}

export function unreadAnnouncementCount(items, seenRank) {
  if (!Array.isArray(items) || items.length === 0) return 0;
  if (!seenRank) return items.length;

  let count = 0;
  for (const item of items) {
    if (isGreaterRank(rankOf(item), seenRank)) {
      count += 1;
    }
  }
  return count;
}
