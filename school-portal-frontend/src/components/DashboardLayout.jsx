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

  useEffect(() => {
    const onResize = () => setIsCompactSidebar(window.innerWidth <= 900);
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

  const compactLabel = (value) => {
    const v = String(value || "").toLowerCase();
    const map = {
      dashboard: "DB",
      payments: "PM",
      promotion: "PR",
      subjects: "SB",
      profile: "PF",
      results: "RS",
      cbt: "CB",
      attendance: "AT",
      topics: "TP",
      "e-library": "EL",
      "class activities": "CA",
      "virtual class": "VC",
      "question bank": "QB",
      "school fees": "SF",
      "behaviour rating": "BR",
      users: "US",
      schools: "SC",
      overview: "OV",
      "platform dashboard": "PD",
      register: "RG",
      academics: "AC",
      academic_session: "AS",
      transcript: "TR",
      teacher_report: "TE",
      student_report: "SR",
    };

    return map[v] || v.slice(0, 2).toUpperCase();
  };

  const navText = (label) => (isCompactSidebar ? compactLabel(label) : label);

  const linkStyle = ({ isActive }) => ({
    display: "block",
    padding: isCompactSidebar ? "10px 8px" : "10px 12px",
    marginBottom: 6,
    borderRadius: 6,
    color: "#fff",
    textAlign: isCompactSidebar ? "center" : "left",
    textDecoration: "none",
    background: isActive ? "#2563eb" : "transparent",
    whiteSpace: "nowrap",
    overflow: "hidden",
    textOverflow: "ellipsis",
    fontSize: 13,
  });

  const roleFeaturePath = (role, featureKey) => {
    const map = {
      "class activities": "class-activities",
      "e-library": "e-library",
      "virtual class": "virtual-class",
      "question bank": "question-bank",
      "school fees": "school-fees",
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
    <div style={{ display: "flex", minHeight: "100vh", background: "#eef3fb" }}>
      <aside
        style={{
          width: isCompactSidebar ? 88 : 240,
          minHeight: "100vh",
          background: "#1f2937",
          color: "#fff",
          padding: isCompactSidebar ? "16px 8px" : 20,
          overflowY: "auto",
          overflowX: "hidden",
          flexShrink: 0,
        }}
      >
        {!isCompactSidebar ? (
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
                {navText("platform dashboard")}
              </NavLink>
              <NavLink to="/super-admin" title="Overview" style={linkStyle}>
                {navText("overview")}
              </NavLink>
              <NavLink to="/super-admin/schools" title="Schools" style={linkStyle}>
                {navText("schools")}
              </NavLink>
              <NavLink to="/super-admin/users" title="Users" style={linkStyle}>
                {navText("users")}
              </NavLink>
              <NavLink to="/super-admin/payments" title="Payments" style={linkStyle}>
                {navText("payments")}
              </NavLink>
            </>
          )}

          {user?.role === "school_admin" && (
            <>
              <NavLink to="/school/dashboard" title="Dashboard" style={linkStyle}>
                {navText("dashboard")}
              </NavLink>
              <NavLink to="/school/admin/payments" title="Payments" style={linkStyle}>
                {navText("payments")}
              </NavLink>
              <NavLink to="/school/admin/promotion" title="Promotion" style={linkStyle}>
                {navText("promotion")}
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
                      {isCompactSidebar ? compactLabel(f.feature) : featureLabel(f.feature)}
                    </NavLink>
                  ))}
              </div>
            </>
          )}

          {(user?.role === "staff" || user?.role === "student") && (
            <>
              <NavLink to={`/${user.role}/dashboard`} title="Dashboard" style={linkStyle}>
                {navText("dashboard")}
              </NavLink>

              {user?.role === "student" && (
                <NavLink to="/student/subjects" title="Subjects" style={linkStyle}>
                  {navText("subjects")}
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
                          {isCompactSidebar ? "CT" : `${featureLabel(f)} (CLASS TEACHER ONLY)`}
                        </span>
                      ) : (
                        <NavLink title={featureLabel(f)} to={roleFeaturePath(user.role, f)} style={linkStyle}>
                          {isCompactSidebar ? compactLabel(f) : featureLabel(f)}
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
          minWidth: 0,
          padding: isCompactSidebar ? 14 : 30,
          overflowX: "hidden",
        }}
      >
        <Outlet />
      </main>
    </div>
  );
}

export default DashboardLayout;
