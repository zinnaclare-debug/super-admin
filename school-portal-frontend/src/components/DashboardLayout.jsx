import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useEffect, useState, useMemo } from "react";
import api from "../services/api";

function DashboardLayout() {
  const navigate = useNavigate();

  const [user, setUser] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem("user") || "null");
    } catch {
      return null;
    }
  });

  const [features, setFeatures] = useState([]);
  const [canAccessClassTeacherFeatures, setCanAccessClassTeacherFeatures] = useState(false);
  const [showAdminFeatures, setShowAdminFeatures] = useState(false);

  // 🔹 Fetch features (school admin only)
  useEffect(() => {
    if (!user?.role) return;

    // school admin fetches school features endpoint
    if (user.role === "school_admin") {
      api
        .get("/api/schools/features")
        .then((res) => {
          const data = res.data.data || [];
          setFeatures(data);
          localStorage.setItem("features", JSON.stringify(data));
        })
        .catch(() => {});
      return;
    }

    // staff and student fetch their allowed features
    if (user.role === "staff" || user.role === "student") {
      api
        .get(`/api/${user.role}/features`)
        .then(async (res) => {
          console.log("Features response:", {
            status: res.status,
            data: res.data,
            roleEndpoint: `/api/${user.role}/features`,
          });
          let data = res.data?.data || [];

          if (user.role === "staff") {
            try {
              const statusRes = await api.get("/api/staff/attendance/status");
              const canAccessAttendance = !!statusRes?.data?.data?.can_access;
              setCanAccessClassTeacherFeatures(canAccessAttendance);
            } catch {
              setCanAccessClassTeacherFeatures(false);
            }
            data = data.filter((f) => f !== "school fees");
          }

          if (user.role === "student") {
            data = data.filter((f) => f !== "attendance" && f !== "question bank");
          }

          setFeatures(data);
        })
        .catch((err) => {
          console.error("Features fetch error:", err.message);
        });
    }
  }, [user]);

  // 🔐 AUTH GUARD
  useEffect(() => {
    const token = localStorage.getItem("token");
    if (!token || !user) {
      navigate("/login", { replace: true });
    }
  }, [navigate, user]);

  const handleLogout = () => {
    localStorage.clear();
    navigate("/login", { replace: true });
  };

  // ✅ STEP 4b — derived feature groups
  const generalFeatures = useMemo(
    () =>
      features.filter(
        (f) => f.enabled && f.category === "general"
      ),
    [features]
  );

  const adminFeatures = useMemo(
    () =>
      features.filter(
        (f) => f.enabled && f.category === "admin"
      ),
    [features]
  );

  const roleLabel = useMemo(() => {
    if (!user?.role) return "";
    switch (user.role) {
      case "super_admin":
        return "Super Admin";
      case "school_admin":
        return "School Admin";
      case "staff":
        return "Staff";
      case "student":
        return "Student";
      default:
        return user.role;
    }
  }, [user]);

  const linkStyle = ({ isActive }) => ({
    display: "block",
    padding: "10px 12px",
    marginBottom: 6,
    borderRadius: 6,
    color: "#fff",
    textDecoration: "none",
    background: isActive ? "#2563eb" : "transparent",
  });

  const roleFeaturePath = (role, featureKey) => {
    const map = {
      "class activities": "class-activities",
      "e-library": "e-library",
      "virtual class": "virtual-class",
      "question bank": "question-bank",
      "school fees": "school-fees",
      cbt: "cbt",
      attendance: "attendance",
      "behaviour rating": "behaviour-rating",
    };
    return `/${role}/${map[featureKey] || encodeURIComponent(featureKey)}`;
  };

  const featureLabel = (value) => String(value || "").replaceAll("_", " ").toUpperCase();

  return (
    <div style={{ display: "flex", minHeight: "100vh" }}>
      {/* SIDEBAR */}
      <aside
        style={{
          width: 240,
          background: "#1f2937",
          color: "#fff",
          padding: 20,
        }}
      >
        <h3>Welcome, {user?.name}</h3>
        <p style={{ opacity: 0.8 }}>{roleLabel}</p>

        <nav>
          {/* SUPER ADMIN */}
          {user?.role === "super_admin" && (
            <>
              <NavLink to="/super-admin/dashboard" style={linkStyle}>
                Platform Dashboard
              </NavLink>

               <NavLink to="/super-admin" style={linkStyle}>
                Overview
              </NavLink>

              <NavLink to="/super-admin/schools" style={linkStyle}>
                Schools
              </NavLink>
              <NavLink to="/super-admin/users" style={linkStyle}>
                Users
              </NavLink>
              <NavLink to="/super-admin/payments" style={linkStyle}>
                Payments
              </NavLink>
            </>
          )}

          {/* SCHOOL ADMIN */}
          {user?.role === "school_admin" && (
            <>
              <NavLink to="/school/dashboard" style={linkStyle}>
                Dashboard
              </NavLink>
              <NavLink to="/school/admin/payments" style={linkStyle}>
                Payments
              </NavLink>
              <NavLink to="/school/admin/promotion" style={linkStyle}>
                Promotion
              </NavLink>

              {/* ✅ GENERAL FEATURES */}
              <div style={{ marginTop: 20 }}>
                <strong style={{ fontSize: 13 }}>General Features</strong>
                <ul style={{ marginTop: 10, paddingLeft: 16 }}>
                  {generalFeatures.map((f) => (
                    <li
                      key={f.feature}
                      style={{ fontSize: 13, opacity: 0.7 }}
                    >
                      {featureLabel(f.feature)}
                    </li>
                  ))}
                </ul>
              </div>

              {/* ✅ ADMIN FEATURES */}
              <div style={{ marginTop: 20 }}>
                <button
                  onClick={() => setShowAdminFeatures(!showAdminFeatures)}
                  style={{
                    width: "100%",
                    padding: 8,
                    background: "#374151",
                    color: "#fff",
                    border: "none",
                    borderRadius: 6,
                  }}
                >
                  Admin Features ▾
                </button>

                {showAdminFeatures &&
                  adminFeatures.map((f) => (
                    <NavLink
                      key={f.feature}
                      to={`/school/admin/${f.feature}`}
                      style={linkStyle}
                    >
                      {featureLabel(f.feature)}
                    </NavLink>
                  ))}
              </div>
            </>
          )}

          {/* STAFF & STUDENT */}
          {(user?.role === "staff" || user?.role === "student") && (
            <>
              <NavLink to={`/${user.role}/dashboard`} style={linkStyle}>
                Dashboard
              </NavLink>

              {/* STAFF / STUDENT FEATURES */}
              <div style={{ marginTop: 20 }}>
                <strong style={{ fontSize: 13 }}>Features</strong>
                <ul style={{ marginTop: 10, paddingLeft: 16 }}>
                  {features.map((f) => (
                    <li key={f} style={{ fontSize: 13, opacity: 0.85 }}>
                      {user?.role === "staff" &&
                      (f === "attendance" || f === "behaviour rating") &&
                      !canAccessClassTeacherFeatures ? (
                        <span
                          title="Class teacher only"
                          style={{
                            display: "block",
                            padding: "10px 12px",
                            marginBottom: 6,
                            borderRadius: 6,
                            color: "#9ca3af",
                            cursor: "not-allowed",
                            background: "transparent",
                          }}
                        >
                          {featureLabel(f)} (CLASS TEACHER ONLY)
                        </span>
                      ) : (
                        <NavLink to={roleFeaturePath(user.role, f)} style={linkStyle}>
                          {featureLabel(f)}
                        </NavLink>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            </>
          )}

          <button
            onClick={handleLogout}
            style={{
              marginTop: 20,
              width: "100%",
              padding: 10,
              borderRadius: 6,
              border: "none",
              background: "#dc2626",
              color: "#fff",
            }}
          >
            Logout
          </button>
        </nav>
      </aside>

      {/* CONTENT */}
      <main style={{ flex: 1, padding: 30 }}>
        <Outlet />
      </main>
    </div>
  );
}

export default DashboardLayout;
