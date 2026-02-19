import { useNavigate } from "react-router-dom";

export default function StaffFeatureLayout({ title, subtitle = "", children, showBack = true }) {
  const navigate = useNavigate();

  return (
    <div
      style={{
        minHeight: "100%",
        width: "100%",
        boxSizing: "border-box",
        padding: 16,
        background: "#f4f8ff",
        borderRadius: 12,
      }}
    >
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          gap: 12,
          background: "#0d6efd",
          color: "#fff",
          padding: "12px 16px",
          borderRadius: 10,
        }}
      >
        <div>
          <h2 style={{ margin: 0 }}>{title}</h2>
          {subtitle ? <div style={{ marginTop: 4, opacity: 0.92, fontSize: 13 }}>{subtitle}</div> : null}
        </div>
        {showBack ? (
          <button
            onClick={() => navigate(-1)}
            style={{
              background: "#fff",
              color: "#0d6efd",
              border: "none",
              borderRadius: 6,
              padding: "6px 12px",
              cursor: "pointer",
              flexShrink: 0,
            }}
          >
            Back
          </button>
        ) : null}
      </div>

      <div style={{ marginTop: 12 }}>{children}</div>
    </div>
  );
}
