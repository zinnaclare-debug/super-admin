import { useNavigate } from "react-router-dom";
import "./FeatureShell.css";

export default function FeatureShell({
  title = "",
  subtitle = "",
  showBack = false,
  showHeader = true,
  children,
}) {
  const navigate = useNavigate();

  return (
    <div className="feature-shell">
      {showHeader ? (
        <div className="feature-shell__header">
          <div>
            <h2 className="feature-shell__title">{title}</h2>
            {subtitle ? <div className="feature-shell__subtitle">{subtitle}</div> : null}
          </div>
          {showBack ? (
            <button className="feature-shell__back" onClick={() => navigate(-1)}>
              Back
            </button>
          ) : null}
        </div>
      ) : null}

      <div className="feature-shell__content">{children}</div>
    </div>
  );
}
