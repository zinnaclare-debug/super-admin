const wrapperStyle = {
  minHeight: "100vh",
  display: "flex",
  alignItems: "center",
  justifyContent: "center",
  padding: 24,
  background:
    "radial-gradient(circle at 20% 20%, rgba(248, 113, 113, 0.22), transparent 28%), linear-gradient(135deg, #020617, #111827)",
  color: "#f8fafc",
};

const cardStyle = {
  width: "min(100%, 520px)",
  border: "1px solid rgba(248, 250, 252, 0.18)",
  borderRadius: 24,
  padding: 28,
  background: "rgba(15, 23, 42, 0.92)",
  boxShadow: "0 24px 70px rgba(0, 0, 0, 0.36)",
  textAlign: "center",
};

export default function SuspendedSchoolNotice({ message = "This school account is suspended." }) {
  return (
    <main style={wrapperStyle}>
      <section style={cardStyle}>
        <p style={{ margin: "0 0 8px", color: "#fca5a5", fontWeight: 800, textTransform: "uppercase" }}>
          Access Suspended
        </p>
        <h1 style={{ margin: "0 0 12px", fontSize: "clamp(1.6rem, 4vw, 2.4rem)" }}>
          School Portal Unavailable
        </h1>
        <p style={{ margin: "0 0 20px", color: "#cbd5e1", lineHeight: 1.6 }}>
          {message} Please contact Lyte Bridge support or the school administrator for reactivation.
        </p>
        <a
          href="https://lyt.com.ng"
          style={{
            display: "inline-flex",
            borderRadius: 999,
            padding: "10px 16px",
            color: "#020617",
            background: "#f8fafc",
            fontWeight: 800,
            textDecoration: "none",
          }}
        >
          Back to main site
        </a>
      </section>
    </main>
  );
}
