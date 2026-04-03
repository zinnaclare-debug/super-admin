import { Outlet, useLocation } from "react-router-dom";
import FeatureShell from "../../components/FeatureShell";

const NO_BACK_EXACT_PATHS = new Set([
  "/school/admin/register",
  "/school/admin/users",
  "/school/admin/academic_session",
  "/school/admin/academics",
  "/school/admin/payments",
  "/school/admin/promotion",
  "/school/admin/broadsheet",
  "/school/admin/broadsheet",
  "/school/admin/transcript",
  "/school/admin/teacher_report",
  "/school/admin/student_report",
  "/school/admin/student_result",
  "/school/admin/website",
  "/school/admin/entrance-exam",
]);

const HEADERLESS_PREFIXES = [
  "/school/admin/register",
  "/school/admin/users",
  "/school/admin/academic_session",
  "/school/admin/academics",
  "/school/admin/payments",
  "/school/admin/promotion",
  "/school/admin/broadsheet",
  "/school/admin/website",
  "/school/admin/announcements",
  "/school/admin/student_result",
];

function titleFromPath(pathname) {
  if (pathname.startsWith("/school/admin/users")) return "Users";
  if (pathname.startsWith("/school/admin/academic_session")) return "Academic Session";
  if (pathname.startsWith("/school/admin/academics")) return "Academics";
  if (pathname.startsWith("/school/admin/classes")) return "Class Setup";
  if (pathname.startsWith("/school/admin/payments")) return "Payments";
  if (pathname.startsWith("/school/admin/students/") && pathname.endsWith("/set-payment")) return "Set Payment";
  if (pathname.startsWith("/school/admin/promotion")) return "Promotion";
  if (pathname.startsWith("/school/admin/class-templates")) return "Class Templates";
  if (pathname.startsWith("/school/admin/broadsheet")) return "Broadsheet";
  if (pathname.startsWith("/school/admin/transcript")) return "Transcript";
  if (pathname.startsWith("/school/admin/teacher_report")) return "Teacher Report";
  if (pathname.startsWith("/school/admin/student_report")) return "Student Report";
  if (pathname.startsWith("/school/admin/student_result")) return "Student Result";
  if (pathname.startsWith("/school/admin/announcements")) return "Announcement Desk";
  if (pathname.startsWith("/school/admin/website")) return "Website";
  if (pathname.startsWith("/school/admin/entrance-exam")) return "Entrance Exam";
  if (pathname.startsWith("/school/admin/register")) return "Register";
  return "School Admin";
}

export default function SchoolAdminFeatureLayout() {
  const { pathname } = useLocation();
  const showBack = !NO_BACK_EXACT_PATHS.has(pathname);
  const showHeader = !HEADERLESS_PREFIXES.some((prefix) => pathname.startsWith(prefix));

  return (
    <FeatureShell title={titleFromPath(pathname)} showBack={showBack} showHeader={showHeader}>
      <Outlet />
    </FeatureShell>
  );
}



