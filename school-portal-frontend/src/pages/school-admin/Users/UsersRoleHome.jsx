import { NavLink, Outlet, useParams } from "react-router-dom";
import { useEffect, useState } from "react";
import api from "../../../services/api";

export default function UsersRoleHome() {
  const { role } = useParams();
  const [counts, setCounts] = useState({ active: 0, inactive: 0, pending_fees: 0, graduated: 0 });

  useEffect(() => {
    let mounted = true;
    api.get("/api/school-admin/users", { params: { role, status: "active", per_page: 1 } })
      .then((response) => {
        if (mounted) setCounts({ active: 0, inactive: 0, pending_fees: 0, graduated: 0, ...(response.data?.meta?.status_counts || {}) });
      })
      .catch(() => {
        if (mounted) setCounts({ active: 0, inactive: 0, pending_fees: 0, graduated: 0 });
      });

    return () => { mounted = false; };
  }, [role]);

  const btnStyle = ({ isActive }) => ({
    padding: "8px 12px",
    borderRadius: 8,
    textDecoration: "none",
    border: "1px solid #d1d5db",
    background: isActive ? "#111827" : "#fff",
    color: isActive ? "#fff" : "#111827",
    display: "inline-flex",
    alignItems: "center",
    gap: 6,
    marginRight: 10,
    marginBottom: 8,
  });

  const label = (title, count) => <>{title} <strong>({Number(count || 0).toLocaleString()})</strong></>;
  const roleTitle = role === "staff" ? "Staff" : "Students";

  return (
    <div style={{ borderTop: "1px solid #e5e7eb", paddingTop: 16, minWidth: 0 }}>
      <h3 style={{ marginTop: 0 }}>{roleTitle}</h3>
      <div style={{ marginBottom: 16, display: "flex", flexWrap: "wrap", gap: 2 }}>
        <NavLink to="active" style={btnStyle}>{label("Active", counts.active)}</NavLink>
        <NavLink to="inactive" style={btnStyle}>{label("Inactive", counts.inactive)}</NavLink>
        {role === "student" ? <>
          <NavLink to="pending-fees" style={btnStyle}>{label("Pending Fees", counts.pending_fees)}</NavLink>
          <NavLink to="graduated" style={btnStyle}>{label("Graduated", counts.graduated)}</NavLink>
        </> : null}
      </div>
      <Outlet />
    </div>
  );
}