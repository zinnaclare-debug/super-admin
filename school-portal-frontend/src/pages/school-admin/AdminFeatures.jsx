// src/pages/school-admin/AdminFeatures.jsx
import { NavLink, Outlet } from "react-router-dom";

export default function AdminFeatures() {
  const adminFeatures = [
    { key: "register", label: "Register User" },
    // Add more admin features here as needed
    // { key: "users", label: "Users" },
    // { key: "academics", label: "Academics" },
  ];

  return (
    <div style={{ display: "flex", gap: 20 }}>
      {/* SIDEBAR */}
      <aside style={{ width: 200, minHeight: "100vh" }}>
        <h3>Admin Features</h3>
        <nav>
          {adminFeatures.map((feature) => (
            <NavLink
              key={feature.key}
              to={`/school/admin/${feature.key}`}
              style={({ isActive }) => ({
                display: "block",
                padding: "10px 12px",
                marginBottom: 6,
                borderRadius: 6,
                background: isActive ? "#2563eb" : "#e5e7eb",
                color: isActive ? "#fff" : "#000",
                textDecoration: "none",
              })}
            >
              {feature.label}
            </NavLink>
          ))}
        </nav>
      </aside>

      {/* CONTENT */}
      <main style={{ flex: 1 }}>
        <Outlet />
      </main>
    </div>
  );
}
