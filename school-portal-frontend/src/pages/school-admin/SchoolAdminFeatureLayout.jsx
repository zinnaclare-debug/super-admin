import { Outlet, useLocation, useNavigate } from "react-router-dom";

const NO_BACK_EXACT_PATHS = new Set([
  "/school/admin/register",
  "/school/admin/users",
  "/school/admin/academic_session",
  "/school/admin/academics",
  "/school/admin/payments",
  "/school/admin/promotion",
  "/school/admin/teacher_report",
  "/school/admin/student_report",
]);

function titleFromPath(pathname) {
  if (pathname.startsWith("/school/admin/users")) return "Users";
  if (pathname.startsWith("/school/admin/academic_session")) return "Academic Session";
  if (pathname.startsWith("/school/admin/academics")) return "Academics";
  if (pathname.startsWith("/school/admin/classes")) return "Class Setup";
  if (pathname.startsWith("/school/admin/payments")) return "Payments";
  if (pathname.startsWith("/school/admin/promotion")) return "Promotion";
  if (pathname.startsWith("/school/admin/teacher_report")) return "Teacher Report";
  if (pathname.startsWith("/school/admin/student_report")) return "Student Report";
  if (pathname.startsWith("/school/admin/announcements")) return "Announcement Desk";
  if (pathname.startsWith("/school/admin/register")) return "Register";
  return "School Admin";
}

export default function SchoolAdminFeatureLayout() {
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const showBack = !NO_BACK_EXACT_PATHS.has(pathname);

  return (
    <div
      style={{
        minHeight: "100%",
        width: "100%",
        boxSizing: "border-box",
        padding: 16,
        background: "#f4f8ff",
        borderRadius: 12,
      }}
    >
      <div
        style={{
          display: "flex",
          flexWrap: "wrap",
          justifyContent: "space-between",
          alignItems: "center",
          gap: 12,
          background: "#0d6efd",
          color: "#fff",
          padding: "12px 16px",
          borderRadius: 10,
        }}
      >
        <h2 style={{ margin: 0, fontSize: "clamp(1.05rem, 3.5vw, 1.5rem)" }}>{titleFromPath(pathname)}</h2>
        {showBack ? (
          <button
            onClick={() => navigate(-1)}
            style={{
              background: "#fff",
              color: "#0d6efd",
              border: "none",
              borderRadius: 6,
              padding: "6px 12px",
              cursor: "pointer",
              flexShrink: 0,
            }}
          >
            Back
          </button>
        ) : null}
      </div>

      <div style={{ marginTop: 12 }}>
        <Outlet />
      </div>
    </div>
  );
}
