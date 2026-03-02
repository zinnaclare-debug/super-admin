import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useEffect, useMemo, useState } from "react";
import api from "../services/api";
import {
  clearAuthState,
  getStoredToken,
  getStoredUser,
  setStoredFeatures,
} from "../utils/authStorage";
import {
  getLatestAnnouncement,
  getSeenAnnouncementRank,
  markAnnouncementSeen,
  unreadAnnouncementCount,
} from "../utils/announcementNotifier";

function DashboardLayout() {
  const navigate = useNavigate();

  const [user, setUser] = useState(() => {
    return getStoredUser();
  });

  const [features, setFeatures] = useState([]);
  const [resultsPublished, setResultsPublished] = useState(false);
  const [canAccessClassTeacherFeatures, setCanAccessClassTeacherFeatures] = useState(false);
  const [showAdminFeatures, setShowAdminFeatures] = useState(false);
  const [announcementUnreadCount, setAnnouncementUnreadCount] = useState(0);
  const [latestAnnouncement, setLatestAnnouncement] = useState(null);
  const [isCompactSidebar, setIsCompactSidebar] = useState(false);
  const [isMobile, setIsMobile] = useState(() => {
    if (typeof window === "undefined") return false;
    return window.innerWidth <= 768;
  });
  const [isSidebarOpen, setIsSidebarOpen] = useState(() => {
    if (typeof window === "undefined") return true;
    return window.innerWidth > 768;
  });

  useEffect(() => {
    const onResize = () => {
      const mobile = window.innerWidth <= 768;
      setIsCompactSidebar(false);
      setIsMobile(mobile);
      if (!mobile) setIsSidebarOpen(true);
    };
    onResize();
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, []);

  useEffect(() => {
    if (!user?.role) return;

    if (user.role === "school_admin") {
      Promise.all([
        api.get("/api/schools/features"),
        api.get("/api/school-admin/stats").catch(() => null),
      ])
        .then(([featuresRes, statsRes]) => {
          const data = featuresRes?.data?.data || [];
          setFeatures(data);
          setStoredFeatures(data);
          setResultsPublished(Boolean(statsRes?.data?.results_published));
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
    if (!user?.role || (user.role !== "staff" && user.role !== "student")) {
      setAnnouncementUnreadCount(0);
      setLatestAnnouncement(null);
      return;
    }

    let active = true;

    const loadAnnouncements = async () => {
      try {
        const res = await api.get(`/api/${user.role}/announcements`);
        const items = res?.data?.data || [];
        if (!active) return;

        const latest = getLatestAnnouncement(items);
        const seenRank = getSeenAnnouncementRank(user, user.role);
        setLatestAnnouncement(latest);
        setAnnouncementUnreadCount(unreadAnnouncementCount(items, seenRank));
      } catch {
        if (!active) return;
        setAnnouncementUnreadCount(0);
        setLatestAnnouncement(null);
      }
    };

    loadAnnouncements();
    return () => {
      active = false;
    };
  }, [user]);

  useEffect(() => {
    const token = getStoredToken();
    if (!token || !user) {
      navigate("/login", { replace: true });
    }
  }, [navigate, user]);

  const handleLogout = () => {
    clearAuthState();
    navigate("/login", { replace: true });
  };

  const closeSidebarOnMobile = () => {
    if (isMobile) setIsSidebarOpen(false);
  };

  const generalFeatures = useMemo(
    () => features.filter((f) => f.enabled && f.category === "general"),
    [features]
  );

  const adminFeatures = useMemo(
    () =>
      features.filter((f) => {
        if (!f.enabled || f.category !== "admin") return false;
        if (String(f.feature || "").toLowerCase() === "student_result" && !resultsPublished) return false;
        return true;
      }),
    [features, resultsPublished]
  );
  const schoolAdminPaymentsEnabled = useMemo(
    () =>
      features.some(
        (f) =>
          f?.enabled &&
          String(f?.category || "").toLowerCase() === "general" &&
          String(f?.feature || "").toLowerCase() === "school fees"
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

  const compactSidebarWidth = isCompactSidebar ? 118 : 248;
  const sidebarWidth = isMobile ? "min(86vw, 320px)" : compactSidebarWidth;

  const linkStyle = ({ isActive }) => ({
    display: "block",
    padding: isCompactSidebar ? "8px 10px" : "10px 12px",
    marginBottom: 6,
    borderRadius: 6,
    color: "#fff",
    textAlign: "left",
    textDecoration: "none",
    background: isActive ? "#2563eb" : "transparent",
    whiteSpace: "nowrap",
    overflow: "hidden",
    textOverflow: "ellipsis",
    fontSize: isCompactSidebar ? 12 : 13,
    lineHeight: 1.35,
    wordBreak: "normal",
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

  const adminFeaturePath = (featureKey) => {
    const normalized = String(featureKey || "").trim().toLowerCase();
    const map = {
      register: "register",
      users: "users",
      academics: "academics",
      academic_session: "academic_session",
      promotion: "promotion",
      broadsheet: "broadsheet",
      transcript: "transcript",
      teacher_report: "teacher_report",
      student_report: "student_report",
      student_result: "student_result",
      announcements: "announcements",
      "announcement desk": "announcements",
    };
    return `/school/admin/${map[normalized] || encodeURIComponent(normalized)}`;
  };

  const featureLabel = (value) => String(value || "").replaceAll("_", " ").toUpperCase();
  const sidebarFeatures =
    user?.role === "student" ? features.filter((f) => f !== "subjects") : features;
  const isAnnouncementFeature = (value) => String(value || "").toLowerCase() === "announcements";

  const handleOpenAnnouncements = () => {
    if (latestAnnouncement && user?.role) {
      markAnnouncementSeen(user, user.role, latestAnnouncement);
      setAnnouncementUnreadCount(0);
    }
  };

  return (
    <div
      style={{
        display: "flex",
        flexDirection: isMobile ? "column" : "row",
        minHeight: "100vh",
        background: "linear-gradient(180deg, #f8fbff 0%, #eef3fb 100%)",
        overflowX: "hidden",
      }}
    >
      {isMobile ? (
        <header
          style={{
            position: "sticky",
            top: 0,
            zIndex: 35,
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            padding: "10px 12px",
            background: "#0f172a",
            color: "#fff",
            borderBottom: "1px solid rgba(148, 163, 184, 0.35)",
          }}
        >
          <button
            type="button"
            aria-label={isSidebarOpen ? "Close feature menu" : "Open feature menu"}
            onClick={() => setIsSidebarOpen((prev) => !prev)}
            style={{
              border: "1px solid rgba(148, 163, 184, 0.45)",
              background: "rgba(15, 23, 42, 0.65)",
              color: "#fff",
              minWidth: 38,
              padding: "0 10px",
              height: 36,
              borderRadius: 8,
              fontSize: 11,
              fontWeight: 700,
              letterSpacing: 0.3,
              lineHeight: 1,
              cursor: "pointer",
            }}
          >
            {isSidebarOpen ? "X" : "MENU"}
          </button>
          <div style={{ textAlign: "right" }}>
            <div style={{ fontSize: 13, fontWeight: 700 }}>{roleLabel || "Portal"}</div>
            <div style={{ fontSize: 11, opacity: 0.8 }}>{user?.name || "User"}</div>
          </div>
        </header>
      ) : null}

      {isMobile && isSidebarOpen ? (
        <button
          type="button"
          aria-label="Close menu overlay"
          onClick={() => setIsSidebarOpen(false)}
          style={{
            position: "fixed",
            inset: 0,
            border: "none",
            background: "rgba(2, 6, 23, 0.45)",
            zIndex: 40,
            cursor: "pointer",
          }}
        />
      ) : null}

      <aside
        style={{
          position: isMobile ? "fixed" : "sticky",
          top: 0,
          left: 0,
          zIndex: isMobile ? 45 : 2,
          width: sidebarWidth,
          maxWidth: sidebarWidth,
          minHeight: "100vh",
          background: "#1f2937",
          color: "#fff",
          padding: isMobile ? "16px 12px" : 20,
          overflowY: "auto",
          overflowX: "hidden",
          flexShrink: 0,
          transform: isMobile ? (isSidebarOpen ? "translateX(0)" : "translateX(-105%)") : "none",
          transition: "transform 0.22s ease",
          boxShadow: "0 20px 35px rgba(15, 23, 42, 0.28)",
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
              <NavLink to="/super-admin/dashboard" title="Platform Dashboard" style={linkStyle} onClick={closeSidebarOnMobile}>
                {isCompactSidebar ? "PLATFORM DASHBOARD" : "Platform Dashboard"}
              </NavLink>
              <NavLink to="/super-admin" title="Overview" style={linkStyle} onClick={closeSidebarOnMobile}>
                {isCompactSidebar ? "OVERVIEW" : "Overview"}
              </NavLink>
              <NavLink to="/super-admin/schools" title="Schools" style={linkStyle} onClick={closeSidebarOnMobile}>
                {isCompactSidebar ? "SCHOOLS" : "Schools"}
              </NavLink>
              <NavLink to="/super-admin/users" title="Users" style={linkStyle} onClick={closeSidebarOnMobile}>
                {isCompactSidebar ? "USERS" : "Users"}
              </NavLink>
              <NavLink to="/super-admin/payments" title="Payments" style={linkStyle} onClick={closeSidebarOnMobile}>
                {isCompactSidebar ? "PAYMENTS" : "Payments"}
              </NavLink>
            </>
          )}

          {user?.role === "school_admin" && (
            <>
              <NavLink to="/school/dashboard" title="Dashboard" style={linkStyle} onClick={closeSidebarOnMobile}>
                {isCompactSidebar ? "DASHBOARD" : "Dashboard"}
              </NavLink>
              {schoolAdminPaymentsEnabled ? (
                <NavLink to="/school/admin/payments" title="Payments" style={linkStyle} onClick={closeSidebarOnMobile}>
                  {isCompactSidebar ? "PAYMENTS" : "Payments"}
                </NavLink>
              ) : null}
              <NavLink to="/school/admin/promotion" title="Promotion" style={linkStyle} onClick={closeSidebarOnMobile}>
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
                      key={String(f.feature || "").trim()}
                      to={adminFeaturePath(f.feature)}
                      title={featureLabel(f.feature)}
                      style={linkStyle}
                      onClick={closeSidebarOnMobile}
                    >
                      {featureLabel(f.feature)}
                    </NavLink>
                  ))}
              </div>
            </>
          )}

          {(user?.role === "staff" || user?.role === "student") && (
            <>
              <NavLink to={`/${user.role}/dashboard`} title="Dashboard" style={linkStyle} onClick={closeSidebarOnMobile}>
                {isCompactSidebar ? "DASHBOARD" : "Dashboard"}
              </NavLink>

              {user?.role === "student" && (
                <NavLink to="/student/subjects" title="Subjects" style={linkStyle} onClick={closeSidebarOnMobile}>
                  {isCompactSidebar ? "SUBJECTS" : "Subjects"}
                </NavLink>
              )}

              <div style={{ marginTop: 20 }}>
                {!isCompactSidebar && <strong style={{ fontSize: 13 }}>Features</strong>}
                <ul style={{ marginTop: 10, marginBottom: 0, paddingLeft: 0, listStyle: "none" }}>
                  {sidebarFeatures.map((f) => (
                    <li key={f} style={{ fontSize: 13, opacity: 0.85, listStyle: "none" }}>
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
                        <NavLink
                          title={featureLabel(f)}
                          to={roleFeaturePath(user.role, f)}
                          style={linkStyle}
                          onClick={() => {
                            if (isAnnouncementFeature(f)) handleOpenAnnouncements();
                            closeSidebarOnMobile();
                          }}
                        >
                          <span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>
                            <span>{featureLabel(f)}</span>
                            {isAnnouncementFeature(f) && announcementUnreadCount > 0 ? (
                              <span
                                style={{
                                  display: "inline-flex",
                                  alignItems: "center",
                                  gap: 4,
                                  background: "#b91c1c",
                                  borderRadius: 999,
                                  padding: "1px 6px",
                                  fontSize: 10,
                                  color: "#fff",
                                  fontWeight: 700,
                                }}
                              >
                                NEW {announcementUnreadCount}
                              </span>
                            ) : null}
                          </span>
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
          width: "100%",
          minWidth: 0,
          maxWidth: "100%",
          boxSizing: "border-box",
          padding: isMobile ? "14px 10px" : "16px 16px 20px",
          overflowX: "auto",
        }}
      >
        <div style={{ width: "100%", maxWidth: 1320, margin: "0 auto" }}>
          <Outlet />
        </div>
      </main>
    </div>
  );
}

export default DashboardLayout;


