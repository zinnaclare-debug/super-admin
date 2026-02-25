// App.jsx
import { Routes, Route, Navigate } from "react-router-dom";

// Public
import Login from "./pages/Login";
import Home from "./pages/Home";

// Super Admin
import Dashboard from "./pages/Dashboard";
import Overview from "./pages/super-admin/Overview";
import Schools from "./pages/super-admin/Schools";
import Users from "./pages/super-admin/Users";
import SchoolUsersByLevel from "./pages/super-admin/SchoolUsersByLevel";
import SuperAdminPayments from "./pages/super-admin/Payments";
import SchoolAcademicSessions from "./pages/super-admin/SchoolAcademicSessions";

// School Admin
import SchoolDashboard from "./pages/school/Dashboard";
import ClassTemplates from "./pages/school/ClassTemplates";
import SchoolFeatures from "./pages/school-admin/SchoolFeatures";
import Register from "./pages/school-admin/Register";

// School Admin - Users feature
import UsersHome from "./pages/school-admin/Users/UsersHome";
import UsersRoleHome from "./pages/school-admin/Users/UsersRoleHome";
import ActiveUsers from "./pages/school-admin/Users/ActiveUsers";
import InactiveUsers from "./pages/school-admin/Users/InactiveUsers";
import LoginDetails from "./pages/school-admin/Users/LoginDetails";

// School Admin - Academic Session feature
import AcademicSessions from "./pages/school-admin/academics/AcademicSessions";
import AcademicSessionDetails from "./pages/school-admin/academics/AcademicSessionDetails";
import ClassPage from "./pages/school-admin/academics/ClassPage";
import AcademicSession from "./pages/school-admin/academicssession"; // create/manage session page

import AssignTeacher from "./pages/school-admin/academics/AssignTeacher";
import EnrollStudents from "./pages/school-admin/academics/EnrollStudents";
import TermCourses from "./pages/school-admin/academics/TermCourses";
import ClassTermStudents from "./pages/school-admin/academics/ClassTermStudents";
import ClassTermEnroll from "./pages/school-admin/academics/ClassTermEnroll";
import AcademicsHome from "./pages/school-admin/academics/AcademicsHome";
import ClassSubjects from "./pages/school-admin/academics/ClassSubjects";
import SubjectCbtPublish from "./pages/school-admin/academics/SubjectCbtPublish";
import SchoolAdminPayments from "./pages/school-admin/Payments";
import Promotion from "./pages/school-admin/Promotion";
import TeacherReport from "./pages/school-admin/reports/TeacherReport";
import StudentReport from "./pages/school-admin/reports/StudentReport";
import StudentResult from "./pages/school-admin/reports/StudentResult";
import Broadsheet from "./pages/school-admin/reports/Broadsheet";
import Transcript from "./pages/school-admin/reports/Transcript";
import AnnouncementDesk from "./pages/school-admin/AnnouncementDesk";
import SchoolAdminFeatureLayout from "./pages/school-admin/SchoolAdminFeatureLayout";



// Layout & Guard
import DashboardLayout from "./components/DashboardLayout";
import ProtectedRoute from "./components/ProtectedRoute";

// Staff/Student
import StaffDashboard from "./pages/staff/Dashboard";
import StaffProfile from "./pages/staff/Profile";
import StudentDashboard from "./pages/student/Dashboard";
import FeaturePage from "./pages/shared/FeaturePage";
import ResultsHome from "./pages/staff/results/ResultsHome";
import SubjectScores from "./pages/staff/results/SubjectScores";
import TopicsHome from "./pages/staff/topics/TopicsHome";
import TopicMaterials from "./pages/staff/topics/TopicMaterials";
import ELibraryHome from "./pages/staff/e-library/ELibraryHome";
import AttendanceHome from "./pages/staff/attendance/AttendanceHome";
import BehaviourRatingHome from "./pages/staff/behaviour-rating/BehaviourRatingHome";
import ClassActivitiesHome from "./pages/staff/class-activities/ClassActivitiesHome";
import VirtualClassHome from "./pages/staff/virtual-class/VirtualClassHome";
import QuestionBankHome from "./pages/staff/question-bank/QuestionBankHome";
import CBTHome from "./pages/staff/cbt/CBTHome";
import StaffAnnouncementsHome from "./pages/staff/announcements/AnnouncementsHome";
import StudentELibrary from "./pages/student/e-library/StudentELibrary";
import StudentResultsHome from "./pages/student/results/ResultsHome";
import StudentProfile from "./pages/student/Profile";
import StudentTopicsHome from "./pages/student/topics/TopicsHome";
import StudentClassActivitiesHome from "./pages/student/class-activities/ClassActivitiesHome";
import StudentVirtualClassHome from "./pages/student/virtual-class/VirtualClassHome";
import StudentCBTHome from "./pages/student/cbt/CBTHome";
import StudentSchoolFees from "./pages/student/SchoolFees";
import StudentSubjectsHome from "./pages/student/subjects/SubjectsHome";
import StudentAnnouncementsHome from "./pages/student/announcements/AnnouncementsHome";
import StudentFeatureLayout from "./pages/student/StudentFeatureLayout";






function App() {
  return (
    <Routes>
      {/* PUBLIC */}
      <Route path="/" element={<Home />} />
      <Route path="/login" element={<Login />} />

      {/* SUPER ADMIN */}
      <Route
        path="/super-admin/*"
        element={
          <ProtectedRoute roles={["super_admin"]}>
            <DashboardLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Overview />} />
        <Route path="overview" element={<Overview />} />
        <Route path="schools" element={<Schools />} />
        <Route path="schools/:schoolId/academic-sessions" element={<SchoolAcademicSessions />} />
        <Route path="users" element={<Users />} />
        <Route path="users/:schoolId" element={<SchoolUsersByLevel />} />
        <Route path="dashboard" element={<Dashboard />} />
        <Route path="payments" element={<SuperAdminPayments />} />
      </Route>

      {/* SCHOOL ADMIN */}
      <Route
        path="/school/*"
        element={
          <ProtectedRoute roles={["school_admin"]}>
            <DashboardLayout />
          </ProtectedRoute>
        }
      >
        <Route path="dashboard" element={<SchoolDashboard />} />
        <Route path="features" element={<SchoolFeatures />} />

        <Route element={<SchoolAdminFeatureLayout />}>
        {/* Admin Features */}
        <Route path="admin/class-templates" element={<ClassTemplates />} />
        <Route path="admin/register" element={<Register />} />

        {/* Users */}
        <Route path="admin/users" element={<UsersHome />}>
          <Route path="login-details" element={<LoginDetails />} />
          <Route path=":role" element={<UsersRoleHome />}>
            <Route path="active" element={<ActiveUsers />} />
            <Route path="inactive" element={<InactiveUsers />} />
          </Route>
        </Route>

        {/* Academic Session */}
        <Route path="admin/academic_session" element={<AcademicSessions />} />
        <Route path="admin/academic_session/manage" element={<AcademicSession />} />
        <Route
          path="admin/academic_session/:sessionId"
          element={<AcademicSessionDetails />}
        />

        {/* Class Terms Page (Primary 1 → Terms table) */}
        <Route path="admin/classes/:classId" element={<ClassPage />} />
        <Route path="admin/classes/:classId" element={<ClassPage />} />
        <Route path="admin/classes/:classId/assign-teacher" element={<AssignTeacher />} />
        <Route path="admin/classes/:classId/enroll-students" element={<EnrollStudents />} />
        <Route path="admin/classes/:classId/terms/:termId" element={<TermCourses />} />
        <Route path="admin/classes/:classId/terms/:termId/students" element={<ClassTermStudents />} />
        <Route path="admin/classes/:classId/terms/:termId/enroll" element={<ClassTermEnroll />} />
        <Route path="admin/classes/:classId/terms/:termId/enroll/bulk" element={<ClassTermEnroll />} />



        <Route path="admin/academics" element={<AcademicsHome />} />
        <Route path="admin/academics/classes/:classId/subjects" element={<ClassSubjects />} />
        <Route
          path="admin/academics/classes/:classId/terms/:termId/subjects/:subjectId/cbt"
          element={<SubjectCbtPublish />}
        />
        <Route path="admin/payments" element={<SchoolAdminPayments />} />
        <Route path="admin/promotion" element={<Promotion />} />
        <Route path="admin/broadsheet" element={<Broadsheet />} />
        <Route path="admin/transcript" element={<Transcript />} />
        <Route path="admin/teacher_report" element={<TeacherReport />} />
        <Route path="admin/student_report" element={<StudentReport />} />
        <Route path="admin/student_result" element={<StudentResult />} />
        <Route path="admin/announcements" element={<AnnouncementDesk />} />
        </Route>

      </Route>

      {/* STAFF */}
      <Route
        path="/staff/*"
        element={
          <ProtectedRoute roles={["staff"]}>
            <DashboardLayout />
          </ProtectedRoute>
        }
      >
        <Route path="dashboard" element={<StaffDashboard />} />
       <Route path="profile" element={<StaffProfile />} />
        <Route path="announcements" element={<StaffAnnouncementsHome />} />
        <Route path="results" element={<ResultsHome />} />
        <Route path="results/:termSubjectId" element={<SubjectScores />} />
        <Route path=":featureKey" element={<FeaturePage />} />
        <Route path="attendance" element={<AttendanceHome />} />
        <Route path="behaviour-rating" element={<BehaviourRatingHome />} />
        <Route path="topics" element={<TopicsHome />} />
        <Route path="topics/:termSubjectId" element={<TopicMaterials />} />
        <Route path="e-library" element={<ELibraryHome />} />
        <Route path="class-activities" element={<ClassActivitiesHome />} />
        <Route path="virtual-class" element={<VirtualClassHome />} />
        <Route path="question-bank" element={<QuestionBankHome />} />
        <Route path="cbt" element={<CBTHome />} />

      </Route>

      {/* STUDENT */}
      <Route
        path="/student/*"
        element={
          <ProtectedRoute roles={["student"]}>
            <DashboardLayout />
          </ProtectedRoute>
        }
      >
        <Route element={<StudentFeatureLayout />}>
          <Route index element={<Navigate to="dashboard" replace />} />
          <Route path="dashboard" element={<StudentDashboard />} />
          <Route path="announcements" element={<StudentAnnouncementsHome />} />
          <Route path="results" element={<StudentResultsHome />} />
          <Route path="profile" element={<StudentProfile />} />
          <Route path="subjects" element={<StudentSubjectsHome />} />
          <Route path="topics" element={<StudentTopicsHome />} />
          <Route path="e-library" element={<StudentELibrary />} />
          <Route path="class-activities" element={<StudentClassActivitiesHome />} />
          <Route path="virtual-class" element={<StudentVirtualClassHome />} />
          <Route path="cbt" element={<StudentCBTHome />} />
          <Route path="school-fees" element={<StudentSchoolFees />} />
          <Route path=":featureKey" element={<FeaturePage />} />
        </Route>

      </Route>

      {/* FALLBACK */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

export default App;

// TPM1LRPnhO
