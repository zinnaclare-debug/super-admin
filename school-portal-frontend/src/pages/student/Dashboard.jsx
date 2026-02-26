import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";
import {
  getLatestAnnouncement,
  getSeenAnnouncementRank,
  markAnnouncementSeen,
  unreadAnnouncementCount,
} from "../../utils/announcementNotifier";
import { getStoredUser } from "../../utils/authStorage";
import teachingArt from "../../assets/student-dashboard/teaching.svg";
import trendsArt from "../../assets/student-dashboard/trends.svg";
import articlesArt from "../../assets/student-dashboard/online-articles.svg";
import swipeProfilesArt from "../../assets/student-dashboard/swipe-profiles.svg";
import subjectMainArt from "../../assets/subject-dashboard/multitasking.svg";
import subjectExamArt from "../../assets/subject-dashboard/exam-prep.svg";
import subjectReadArt from "../../assets/subject-dashboard/relaxed-reading.svg";
import cbtMeetingsArt from "../../assets/cbt-dashboard/online-meetings.svg";
import cbtResumeArt from "../../assets/cbt-dashboard/online-resume.svg";
import cbtProfilesArt from "../../assets/cbt-dashboard/swipe-profiles.svg";
import "./Dashboard.css";

export default function StudentDashboard() {
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [announcementUnreadCount, setAnnouncementUnreadCount] = useState(0);
  const [latestAnnouncement, setLatestAnnouncement] = useState(null);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError("");
      try {
        const res = await api.get("/api/student/profile");
        setProfile(res.data?.data || null);
      } catch (e) {
        setError(e?.response?.data?.message || "Failed to load profile");
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  useEffect(() => {
    let active = true;

    const loadAnnouncements = async () => {
      try {
        const res = await api.get("/api/student/announcements");
        const items = res?.data?.data || [];
        if (!active) return;

        const storedUser = getStoredUser();
        const latest = getLatestAnnouncement(items);
        const seenRank = getSeenAnnouncementRank(storedUser, "student");

        setLatestAnnouncement(latest);
        setAnnouncementUnreadCount(unreadAnnouncementCount(items, seenRank));
      } catch {
        if (!active) return;
        setLatestAnnouncement(null);
        setAnnouncementUnreadCount(0);
      }
    };

    loadAnnouncements();

    return () => {
      active = false;
    };
  }, []);

  const toAbsoluteUrl = (url) => {
    if (!url) return "";

    const base = (api.defaults.baseURL || "").replace(/\/$/, "");
    const apiOrigin = base ? new URL(base).origin : window.location.origin;

    if (/^(blob:|data:)/i.test(url)) return url;

    if (/^https?:\/\//i.test(url)) {
      try {
        const parsed = new URL(url);
        if (parsed.pathname.startsWith("/storage/")) {
          return `${apiOrigin}${parsed.pathname}${parsed.search}`;
        }
      } catch {
        return url;
      }
      return url;
    }

    return `${apiOrigin}${url.startsWith("/") ? "" : "/"}${url}`;
  };

  const user = profile?.user || {};
  const student = profile?.student || {};
  const currentSession = profile?.current_session || null;
  const currentTerm = profile?.current_term || null;
  const currentClass = profile?.current_class || null;
  const currentDepartment = profile?.current_department || null;
  const rawPhotoUrl =
    profile?.photo_url ||
    (profile?.photo_path ? `/storage/${profile.photo_path}` : "") ||
    (student?.photo_path ? `/storage/${student.photo_path}` : "");
  const photoUrl = toAbsoluteUrl(rawPhotoUrl);

  const openAnnouncements = () => {
    const storedUser = getStoredUser();
    if (latestAnnouncement) {
      markAnnouncementSeen(storedUser, "student", latestAnnouncement);
      setAnnouncementUnreadCount(0);
    }
    navigate("/student/announcements");
  };

  const quickActions = [
    { label: "Profile", hint: "Personal details", path: "/student/profile", tone: "calm" },
    { label: "Subjects", hint: "Subject list", path: "/student/subjects", tone: "warm", variant: "subject" },
    { label: "Results", hint: "Scores and grades", path: "/student/results", tone: "bright" },
    { label: "Topics", hint: "Topic resources", path: "/student/topics", tone: "calm" },
    { label: "E-Library", hint: "Digital textbooks", path: "/student/e-library", tone: "warm" },
    { label: "Class Activities", hint: "Assignments", path: "/student/class-activities", tone: "bright" },
    { label: "Virtual Class", hint: "Join meetings", path: "/student/virtual-class", tone: "calm" },
    { label: "CBT", hint: "Computer tests", path: "/student/cbt", tone: "bright", variant: "cbt" },
    { label: "School Fees", hint: "Payments", path: "/student/school-fees", tone: "warm" },
    { label: "Announcements", hint: "School updates", path: "/student/announcements", tone: "calm" },
  ];

  return (
    <div className="sdx-page">
      <section className="sdx-hero">
        <div>
          <span className="sdx-pill">Student Dashboard</span>
          <h2>All your academic tools in one colorful workspace</h2>
          <p>
            Access learning tools quickly, track your profile details, and stay up to date with school announcements.
          </p>
          <div className="sdx-meta">
            <span>{currentClass ? `${currentClass.name} (${currentClass.level})` : "Class pending"}</span>
            <span>{currentTerm?.name || "Term pending"}</span>
            <span>{currentSession?.session_name || currentSession?.academic_year || "Session pending"}</span>
          </div>
        </div>

        <div className="sdx-hero-art" aria-hidden="true">
          <div className="sdx-art sdx-art--main">
            <img src={teachingArt} alt="" />
          </div>
          <div className="sdx-art sdx-art--trend">
            <img src={trendsArt} alt="" />
          </div>
          <div className="sdx-art sdx-art--article">
            <img src={articlesArt} alt="" />
          </div>
        </div>
      </section>

      <section className="sdx-panel">
        {loading ? <p className="sdx-state sdx-state--loading">Loading profile...</p> : null}
        {!loading && error ? <p className="sdx-state sdx-state--error">{error}</p> : null}

        {!loading && !error ? (
          <>
            {announcementUnreadCount > 0 && latestAnnouncement ? (
              <div className="sdx-alert">
                <p>
                  {announcementUnreadCount} new announcement{announcementUnreadCount > 1 ? "s" : ""}
                </p>
                <p>
                  Latest: <strong>{latestAnnouncement.title}</strong>
                </p>
                <button className="sdx-btn" onClick={openAnnouncements}>
                  Open Announcement Desk
                </button>
              </div>
            ) : null}

            <div className="sdx-quick">
              <div className="sdx-quick-head">
                <div>
                  <h3>Quick Actions</h3>
                  <p>Open any student tool in one click.</p>
                </div>
                <div className="sdx-quick-art" aria-hidden="true">
                  <img src={swipeProfilesArt} alt="" />
                </div>
              </div>
              <div className="sdx-quick-grid">
                {quickActions.map((item) => (
                  <button
                    key={item.path}
                    className={`sdx-quick-btn sdx-quick-btn--${item.tone}${item.label === "Subjects" ? " sdx-quick-btn--subject" : ""}${
                      item.variant === "cbt" ? " sdx-quick-btn--cbt" : ""
                    }`}
                    onClick={() => navigate(item.path)}
                  >
                    <span className="sdx-quick-btn__title">{item.label}</span>
                    <span className="sdx-quick-btn__hint">{item.hint}</span>
                    {item.variant === "subject" ? <span className="sdx-quick-btn__badge">Featured</span> : null}
                    {item.variant === "subject" ? (
                      <span className="sdx-quick-btn__visual sdx-quick-btn__visual--subject" aria-hidden="true">
                        <img className="sdx-quick-btn__visual-main" src={subjectMainArt} alt="" />
                        <img
                          className="sdx-quick-btn__visual-float sdx-quick-btn__visual-float--one sdx-quick-btn__visual-float--subject"
                          src={subjectExamArt}
                          alt=""
                        />
                        <img
                          className="sdx-quick-btn__visual-float sdx-quick-btn__visual-float--two sdx-quick-btn__visual-float--subject"
                          src={subjectReadArt}
                          alt=""
                        />
                      </span>
                    ) : null}
                    {item.variant === "cbt" ? (
                      <>
                        <span className="sdx-quick-btn__badge sdx-quick-btn__badge--cbt">Exam Zone</span>
                        <span className="sdx-quick-btn__visual" aria-hidden="true">
                          <img className="sdx-quick-btn__visual-main" src={cbtMeetingsArt} alt="" />
                          <img className="sdx-quick-btn__visual-float sdx-quick-btn__visual-float--one" src={cbtResumeArt} alt="" />
                          <img className="sdx-quick-btn__visual-float sdx-quick-btn__visual-float--two" src={cbtProfilesArt} alt="" />
                        </span>
                      </>
                    ) : null}
                  </button>
                ))}
              </div>
            </div>

            <div className="sdx-grid">
              <article className="sdx-card">
                <h3>Student Details</h3>
                <div className="sdx-kv">
                  <div className="sdx-row"><span>Name</span><strong>{user.name || "-"}</strong></div>
                  <div className="sdx-row"><span>Sex</span><strong>{student.sex || "-"}</strong></div>
                  <div className="sdx-row"><span>DOB</span><strong>{student.dob || "-"}</strong></div>
                  <div className="sdx-row"><span>Address</span><strong>{student.address || "-"}</strong></div>
                </div>
              </article>

              <article className="sdx-card">
                <h3>Photo</h3>
                <div className="sdx-photo-box">
                  {photoUrl ? <img src={photoUrl} alt="Student profile" /> : <span>No Photo</span>}
                </div>
              </article>
            </div>

            <div className="sdx-card" style={{ marginTop: 12 }}>
              <h3>Current Academic Info</h3>
              <div className="sdx-kv">
                <div className="sdx-row">
                  <span>Current Session</span>
                  <strong>{currentSession?.session_name || currentSession?.academic_year || "-"}</strong>
                </div>
                <div className="sdx-row">
                  <span>Current Term</span>
                  <strong>{currentTerm?.name || "-"}</strong>
                </div>
                <div className="sdx-row">
                  <span>Current Class</span>
                  <strong>{currentClass ? `${currentClass.name} (${currentClass.level})` : "-"}</strong>
                </div>
                <div className="sdx-row">
                  <span>Department</span>
                  <strong>{currentDepartment?.name || "-"}</strong>
                </div>
              </div>
            </div>
          </>
        ) : null}
      </section>
    </div>
  );
}
