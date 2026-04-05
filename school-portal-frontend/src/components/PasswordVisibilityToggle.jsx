import "./PasswordVisibilityToggle.css";

function EyeIcon({ visible }) {
  return visible ? (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M3 3l18 18" />
      <path d="M10.6 10.6a2 2 0 1 0 2.8 2.8" />
      <path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c5.5 0 9.5 4.8 10 7-.2 1-1.2 2.8-2.9 4.4" />
      <path d="M6.6 6.7C4.5 8.1 3.3 10 2 12c.5 2.2 4.5 7 10 7 1.6 0 3-.3 4.2-.8" />
    </svg>
  ) : (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  );
}

export default function PasswordVisibilityToggle({ visible, onToggle }) {
  return (
    <button
      type="button"
      className="password-visibility-toggle"
      onClick={onToggle}
      aria-label={visible ? "Hide password" : "Show password"}
      aria-pressed={visible}
      title={visible ? "Hide password" : "Show password"}
    >
      <EyeIcon visible={visible} />
    </button>
  );
}
