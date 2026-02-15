import { FEATURE_DEFINITIONS } from "../../config/features";
import { NavLink } from "react-router-dom";
import { useEffect, useState } from "react";
import api from "../../services/api";

function AdminFeatures() {
  const adminFeatures = FEATURE_DEFINITIONS.filter(
    (f) => f.category === "admin"
  );

  const [enabledFeatures, setEnabledFeatures] = useState([]);

  useEffect(() => {
    api.get("/api/schools/features").then((res) => {
      setEnabledFeatures(res.data.data || []);
    });
  }, []);

  const isEnabled = (key) =>
    enabledFeatures.find((f) => f.feature === key)?.enabled;

  return (
    <div>
      <h2>Admin Features</h2>

      <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 20 }}>
        {adminFeatures.map((f) => (
          <NavLink
            key={f.key}
            to={`/school/admin/${f.key}`}
            style={{
              padding: 20,
              border: "1px solid #ccc",
              borderRadius: 8,
              textDecoration: "none",
              opacity: isEnabled(f.key) ? 1 : 0.45,
              pointerEvents: isEnabled(f.key) ? "auto" : "none",
            }}
          >
            {f.label}
            {!isEnabled(f.key) && <div style={{ fontSize: 12, marginTop: 8 }}>Disabled</div>}
          </NavLink>
        ))}
      </div>
    </div>
  );
}

export default AdminFeatures;
