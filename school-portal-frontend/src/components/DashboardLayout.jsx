import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useEffect, useMemo, useState } from "react";
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
  const [isCompactSidebar, setIsCompactSidebar] = useState(() => {
    if (typeof window === "undefined") return false;
    return window.innerWidth <= 900;
  });
  const [isMobile, setIsMobile] = useState(() => {
    if (typeof window === "undefined") return false;
    return window.innerWidth <= 768;
  });

  useEffect(() => {
    const onResize = () => {
      setIsCompactSidebar(window.innerWidth <= 900);
      setIsMobile(window.innerWidth <= 768);
    };
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, []);

  useEffect(() => {
    if (!user?.role) return;

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

    if (user.role === "staff" || user.role === "student") {
      api
        .get(`/api/${user.role}/features`)
        .then(async (res) => {
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

  const generalFeatures = useMemo(
    () => features.filter((f) => f.enabled && f.category === "general"),
    [features]
  );

  const adminFeatures = useMemo(
    () => features.filter((f) => f.enabled && f.category === "admin"),
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

  const compactSidebarWidth = isCompactSidebar ? 118 : 240;

  const linkStyle = ({ isActive }) => ({
    display: "block",
    padding: isMobile ? "10px 8px" : isCompactSidebar ? "8px 6px" : "10px 12px",
    marginBottom: 6,
    borderRadius: 6,
    color: "#fff",
    textAlign: isMobile || isCompactSidebar ? "center" : "left",
    textDecoration: "none",
    background: isActive ? "#2563eb" : "transparent",
    whiteSpace: isMobile || isCompactSidebar ? "normal" : "nowrap",
    overflow: "hidden",
    textOverflow: isMobile || isCompactSidebar ? "clip" : "ellipsis",
    fontSize: isMobile ? 12 : isCompactSidebar ? 11 : 13,
    lineHeight: isMobile || isCompactSidebar ? 1.2 : 1.35,
    wordBreak: isMobile || isCompactSidebar ? "break-word" : "normal",
  });

  const roleFeaturePath = (role, featureKey) => {
    const map = {
      "class activities": "class-activities",
      "e-library": "e-library",
      "virtual class": "virtual-class",
      "question bank": "question-bank",
      "school fees": "school-fees",
      announcements: "announcements",
      subjects: "subjects",
      cbt: "cbt",
      attendance: "attendance",
      "behaviour rating": "behaviour-rating",
    };
    return `/${role}/${map[featureKey] || encodeURIComponent(featureKey)}`;
  };

  const featureLabel = (value) => String(value || "").replaceAll("_", " ").toUpperCase();
  const sidebarFeatures =
    user?.role === "student" ? features.filter((f) => f !== "subjects") : features;

  return (
    <div
      style={{
        display: "flex",
        flexDirection: isMobile ? "column" : "row",
        minHeight: "100vh",
        background: "#eef3fb",
        overflowX: "hidden",
      }}
    >
      <aside
        style={{
          width: isMobile ? "100%" : compactSidebarWidth,
          maxWidth: isMobile ? "100%" : compactSidebarWidth,
          minHeight: isMobile ? "auto" : "100vh",
          background: "#1f2937",
          color: "#fff",
          padding: isMobile ? "14px 10px" : isCompactSidebar ? "16px 8px" : 20,
          overflowY: "auto",
          overflowX: "hidden",
          flexShrink: 0,
        }}
      >
        {!isCompactSidebar || isMobile ? (
          <>
            <h3 style={{ margin: 0 }}>Welcome, {user?.name}</h3>
            <p style={{ opacity: 0.8, marginTop: 6 }}>{roleLabel}</p>
          </>
        ) : (
          <h3 style={{ margin: 0, textAlign: "center" }}>SP</h3>
        )}

        <nav style={{ marginTop: 12 }}>
          {user?.role === "super_admin" && (
            <>
              <NavLink to="/super-admin/dashboard" title="Platform Dashboard" style={linkStyle}>
                {isCompactSidebar ? "PLATFORM DASHBOARD" : "Platform Dashboard"}
              </NavLink>
              <NavLink to="/super-admin" title="Overview" style={linkStyle}>
                {isCompactSidebar ? "OVERVIEW" : "Overview"}
              </NavLink>
              <NavLink to="/super-admin/schools" title="Schools" style={linkStyle}>
                {isCompactSidebar ? "SCHOOLS" : "Schools"}
              </NavLink>
              <NavLink to="/super-admin/users" title="Users" style={linkStyle}>
                {isCompactSidebar ? "USERS" : "Users"}
              </NavLink>
              <NavLink to="/super-admin/payments" title="Payments" style={linkStyle}>
                {isCompactSidebar ? "PAYMENTS" : "Payments"}
              </NavLink>
            </>
          )}

          {user?.role === "school_admin" && (
            <>
              <NavLink to="/school/dashboard" title="Dashboard" style={linkStyle}>
                {isCompactSidebar ? "DASHBOARD" : "Dashboard"}
              </NavLink>
              <NavLink to="/school/admin/payments" title="Payments" style={linkStyle}>
                {isCompactSidebar ? "PAYMENTS" : "Payments"}
              </NavLink>
              <NavLink to="/school/admin/promotion" title="Promotion" style={linkStyle}>
                {isCompactSidebar ? "PROMOTION" : "Promotion"}
              </NavLink>

              {!isCompactSidebar && (
                <div style={{ marginTop: 20 }}>
                  <strong style={{ fontSize: 13 }}>General Features</strong>
                  <ul style={{ marginTop: 10, paddingLeft: 16 }}>
                    {generalFeatures.map((f) => (
                      <li key={f.feature} style={{ fontSize: 13, opacity: 0.7 }}>
                        {featureLabel(f.feature)}
                      </li>
                    ))}
                  </ul>
                </div>
              )}

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
                    fontSize: 13,
                  }}
                >
                  {isCompactSidebar ? "ADM" : "Admin Features"}
                </button>

                {showAdminFeatures &&
                  adminFeatures.map((f) => (
                    <NavLink
                      key={f.feature}
                      to={`/school/admin/${f.feature}`}
                      title={featureLabel(f.feature)}
                      style={linkStyle}
                    >
                      {featureLabel(f.feature)}
                    </NavLink>
                  ))}
              </div>
            </>
          )}

          {(user?.role === "staff" || user?.role === "student") && (
            <>
              <NavLink to={`/${user.role}/dashboard`} title="Dashboard" style={linkStyle}>
                {isCompactSidebar ? "DASHBOARD" : "Dashboard"}
              </NavLink>

              {user?.role === "student" && (
                <NavLink to="/student/subjects" title="Subjects" style={linkStyle}>
                  {isCompactSidebar ? "SUBJECTS" : "Subjects"}
                </NavLink>
              )}

              <div style={{ marginTop: 20 }}>
                {!isCompactSidebar && <strong style={{ fontSize: 13 }}>Features</strong>}
                <ul style={{ marginTop: 10, paddingLeft: isCompactSidebar ? 0 : 16 }}>
                  {sidebarFeatures.map((f) => (
                    <li key={f} style={{ fontSize: 13, opacity: 0.85 }}>
                      {user?.role === "staff" &&
                      (f === "attendance" || f === "behaviour rating") &&
                      !canAccessClassTeacherFeatures ? (
                        <span
                          title="Class teacher only"
                          style={{
                            display: "block",
                            padding: isCompactSidebar ? "10px 8px" : "10px 12px",
                            marginBottom: 6,
                            borderRadius: 6,
                            color: "#9ca3af",
                            cursor: "not-allowed",
                            background: "transparent",
                            textAlign: isCompactSidebar ? "center" : "left",
                          }}
                        >
                          {isCompactSidebar ? featureLabel(f) : `${featureLabel(f)} (CLASS TEACHER ONLY)`}
                        </span>
                      ) : (
                        <NavLink title={featureLabel(f)} to={roleFeaturePath(user.role, f)} style={linkStyle}>
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
              textAlign: "center",
              fontSize: 13,
            }}
          >
            {isCompactSidebar ? "OUT" : "Logout"}
          </button>
        </nav>
      </aside>

      <main
        style={{
          flex: 1,
          width: isMobile ? "100%" : isCompactSidebar ? `calc(100vw - ${compactSidebarWidth}px)` : "auto",
          minWidth: 0,
          maxWidth: "100%",
          boxSizing: "border-box",
          padding: isMobile ? "12px 10px" : isCompactSidebar ? "10px 8px 10px 10px" : 30,
          overflowX: "auto",
        }}
      >
        <Outlet />
      </main>
    </div>
  );
}

export default DashboardLayout;
