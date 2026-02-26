import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useEffect, useMemo, useState } from "react";
import api from "../services/api";
import {
  clearAuthState,
  getStoredToken,
  getStoredUser,
  setStoredFeatures,
} from "../utils/authStorage";
import subjectNavArt from "../assets/subject-dashboard/multitasking.svg";
import cbtNavArt from "../assets/cbt-dashboard/online-meetings.svg";
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

  const featuredLinkStyle = ({ isActive }, featureKey) => {
    const base = linkStyle({ isActive });
    const key = String(featureKey || "").toLowerCase();
    const withArt = !isMobile && !isCompactSidebar;

    if (key === "subjects") {
      return {
        ...base,
        opacity: 1,
        fontWeight: 800,
        border: "1px solid #818cf8",
        color: "#ffffff",
        boxShadow: isActive
          ? "0 10px 18px rgba(67, 56, 202, 0.45)"
          : "0 6px 12px rgba(67, 56, 202, 0.3)",
        padding: withArt ? "10px 54px 10px 12px" : base.padding,
        backgroundImage: withArt
          ? `${isActive
              ? "linear-gradient(140deg, #4f46e5, #4338ca 52%, #1d4ed8)"
              : "linear-gradient(140deg, #6366f1, #4f46e5 54%, #2563eb)"}, url(${subjectNavArt})`
          : isActive
            ? "linear-gradient(140deg, #4f46e5, #4338ca 52%, #1d4ed8)"
            : "linear-gradient(140deg, #6366f1, #4f46e5 54%, #2563eb)",
        backgroundRepeat: withArt ? "no-repeat, no-repeat" : "no-repeat",
        backgroundSize: withArt ? "auto, 34px 34px" : "auto",
        backgroundPosition: withArt ? "center, right 9px center" : "center",
      };
    }

    if (key === "cbt") {
      return {
        ...base,
        opacity: 1,
        fontWeight: 800,
        border: "1px solid #22d3ee",
        color: "#ffffff",
        boxShadow: isActive
          ? "0 10px 18px rgba(8, 145, 178, 0.45)"
          : "0 6px 12px rgba(8, 145, 178, 0.3)",
        padding: withArt ? "10px 54px 10px 12px" : base.padding,
        backgroundImage: withArt
          ? `${isActive
              ? "linear-gradient(140deg, #0e7490, #0891b2 52%, #0284c7)"
              : "linear-gradient(140deg, #0891b2, #0ea5e9 54%, #0284c7)"}, url(${cbtNavArt})`
          : isActive
            ? "linear-gradient(140deg, #0e7490, #0891b2 52%, #0284c7)"
            : "linear-gradient(140deg, #0891b2, #0ea5e9 54%, #0284c7)",
        backgroundRepeat: withArt ? "no-repeat, no-repeat" : "no-repeat",
        backgroundSize: withArt ? "auto, 34px 34px" : "auto",
        backgroundPosition: withArt ? "center, right 9px center" : "center",
      };
    }

    return base;
  };

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
                      key={String(f.feature || "").trim()}
                      to={adminFeaturePath(f.feature)}
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
                <NavLink to="/student/subjects" title="Subjects" style={(state) => featuredLinkStyle(state, "subjects")}>
                  {isCompactSidebar ? "SUBJECTS" : "Subjects"}
                </NavLink>
              )}

              <div style={{ marginTop: 20 }}>
                {!isCompactSidebar && <strong style={{ fontSize: 13 }}>Features</strong>}
                <ul style={{ marginTop: 10, paddingLeft: isCompactSidebar ? 0 : 16 }}>
                  {sidebarFeatures.map((f) => (
                    <li
                      key={f}
                      style={{
                        fontSize: 13,
                        opacity: ["subjects", "cbt"].includes(String(f || "").toLowerCase()) ? 1 : 0.85,
                      }}
                    >
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
                          style={(state) => featuredLinkStyle(state, f)}
                          onClick={isAnnouncementFeature(f) ? handleOpenAnnouncements : undefined}
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
                                <span role="img" aria-label="new announcements">
                                  🔔
                                </span>
                                {announcementUnreadCount}
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
