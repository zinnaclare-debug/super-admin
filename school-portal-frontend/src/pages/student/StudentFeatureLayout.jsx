import { Outlet, useLocation } from "react-router-dom";

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

  return (
    <div
      style={{
        minHeight: "100%",
        width: "100%",
        boxSizing: "border-box",
        padding: 16,
        background: "#f4f8ff",
        color: "#111827",
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
        <h2 style={{ margin: 0, fontSize: "clamp(1.05rem, 3.5vw, 1.5rem)" }}>{title}</h2>
      </div>

      <div style={{ marginTop: 12 }}>
        <Outlet />
      </div>
    </div>
  );
}
