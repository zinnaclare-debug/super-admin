import { NavLink, Outlet } from "react-router-dom";
import { FEATURE_DEFINITIONS } from "../../config/features";

function FeaturesLayout() {
  const user = JSON.parse(localStorage.getItem("user"));
  const enabledFeatures = JSON.parse(localStorage.getItem("features") || "[]");

  const isEnabled = (key) =>
    enabledFeatures.some((f) => f.feature === key && f.enabled);

  const visibleFeatures = FEATURE_DEFINITIONS.filter(
    (f) =>
      f.roles.includes(user.role) &&
      (user.role === "super_admin" || isEnabled(f.key))
  );

  const grouped = visibleFeatures.reduce((acc, f) => {
    acc[f.group] = acc[f.group] || [];
    acc[f.group].push(f);
    return acc;
  }, {});

  return (
    <div style={{ display: "flex", gap: 24 }}>
      <aside style={{ width: 260 }}>
        {Object.entries(grouped).map(([group, features]) => (
          <div key={group} style={{ marginBottom: 20 }}>
            <h4 style={{ textTransform: "capitalize" }}>{group}</h4>

            {features.map((f) => (
              <NavLink
                key={f.route}
                to={f.route}
                style={({ isActive }) => ({
                  display: "block",
                  padding: "8px 12px",
                  marginBottom: 6,
                  borderRadius: 6,
                  textDecoration: "none",
                  background: isActive ? "#e5e7eb" : "transparent",
                })}
              >
                {f.label}
              </NavLink>
            ))}
          </div>
        ))}
      </aside>

      <main style={{ flex: 1 }}>
        <Outlet />
      </main>
    </div>
  );
}

export default FeaturesLayout;
