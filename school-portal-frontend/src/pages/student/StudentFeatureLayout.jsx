import { Outlet, useLocation } from "react-router-dom";
import FeatureShell from "../../components/FeatureShell";

const TITLE_BY_PATH = {
  "/student/dashboard": "Student Dashboard",
  "/student/profile": "Profile",
  "/student/subjects": "Subjects",
  "/student/results": "Results",
  "/student/topics": "Topics",
  "/student/e-library": "E-Library",
  "/student/class-activities": "Class Activities",
  "/student/virtual-class": "Virtual Class",
  "/student/cbt": "CBT",
  "/student/school-fees": "School Fees",
};

export default function StudentFeatureLayout() {
  const { pathname } = useLocation();
  const title = TITLE_BY_PATH[pathname] || "Student";
  const showHeader = pathname !== "/student/dashboard";

  return (
    <FeatureShell title={title} showHeader={showHeader}>
      <Outlet />
    </FeatureShell>
  );
}
