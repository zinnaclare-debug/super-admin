const MOBILE_SCHOOL_STORAGE_KEY = "mobile_school";

function hasWindow() {
  return typeof window !== "undefined";
}

function readMobileSchool() {
  if (!hasWindow()) return null;

  try {
    const raw = window.localStorage.getItem(MOBILE_SCHOOL_STORAGE_KEY);
    if (!raw) return null;

    const school = JSON.parse(raw);
    return school?.school_code && school?.api_base_url ? school : null;
  } catch {
    return null;
  }
}

export function getMobileSchool() {
  return readMobileSchool();
}

export function setMobileSchool(school) {
  if (!school?.school_code || !school?.api_base_url) {
    throw new Error("A school code and school address are required.");
  }

  const selectedSchool = {
    ...school,
    school_code: String(school.school_code).toUpperCase(),
    api_base_url: String(school.api_base_url).replace(/\/$/, ""),
  };

  if (hasWindow()) {
    window.localStorage.setItem(MOBILE_SCHOOL_STORAGE_KEY, JSON.stringify(selectedSchool));
  }

  return selectedSchool;
}

export function clearMobileSchool() {
  if (!hasWindow()) return;

  window.localStorage.removeItem(MOBILE_SCHOOL_STORAGE_KEY);
}