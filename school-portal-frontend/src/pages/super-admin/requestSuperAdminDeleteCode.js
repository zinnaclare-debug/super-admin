export function requestSuperAdminDeleteCode(subject = "this item", action = "delete") {
  if (!window.confirm(`Are you sure you want to ${action} ${subject}? This action requires confirmation.`)) {
    return null;
  }

  const value = window.prompt(`Enter your 4-digit delete code to ${action} ${subject}:`);
  if (value === null) {
    return null;
  }

  const normalized = String(value).trim();
  if (!/^\d{4}$/.test(normalized)) {
    alert("Enter a valid 4-digit delete code.");
    return null;
  }

  return normalized;
}