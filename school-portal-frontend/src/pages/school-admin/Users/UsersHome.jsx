import { NavLink, Outlet } from "react-router-dom";

export default function UsersHome() {
  const btnStyle = ({ isActive }) => ({
    padding: "10px 14px",
    borderRadius: 8,
    textDecoration: "none",
    border: "1px solid #d1d5db",
    background: isActive ? "#2563eb" : "#fff",
    color: isActive ? "#fff" : "#111827",
    marginRight: 10,
    display: "inline-block",
  });

  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        <h2 style={{ margin: 0 }}>Users</h2>
        <span style={{ opacity: 0.7, fontSize: 13 }}>
          Select a user type to manage.
        </span>
      </div>

      <div style={{ marginTop: 16, marginBottom: 16 }}>
        <NavLink to="staff/active" style={btnStyle}>
          Staff
        </NavLink>
        <NavLink to="student/active" style={btnStyle}>
          Students
        </NavLink>
      </div>

      <Outlet />
    </div>
  );
}
