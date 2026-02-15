import { NavLink, Outlet, useParams } from "react-router-dom";

export default function UsersRoleHome() {
  const { role } = useParams(); // "staff" | "student"

  const btnStyle = ({ isActive }) => ({
    padding: "8px 12px",
    borderRadius: 8,
    textDecoration: "none",
    border: "1px solid #d1d5db",
    background: isActive ? "#111827" : "#fff",
    color: isActive ? "#fff" : "#111827",
    marginRight: 10,
    display: "inline-block",
  });

  const roleTitle = role === "staff" ? "Staff" : "Students";

  return (
    <div style={{ borderTop: "1px solid #e5e7eb", paddingTop: 16 }}>
      <h3 style={{ marginTop: 0 }}>{roleTitle}</h3>

      <div style={{ marginBottom: 16 }}>
        <NavLink to="active" style={btnStyle}>
          Active
        </NavLink>
        <NavLink to="inactive" style={btnStyle}>
          Inactive
        </NavLink>
      </div>

      <Outlet />
    </div>
  );
}
