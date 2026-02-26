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
    { label: "Profile", path: "/student/profile" },
    { label: "Subjects", path: "/student/subjects" },
    { label: "Results", path: "/student/results" },
    { label: "Topics", path: "/student/topics" },
    { label: "E-Library", path: "/student/e-library" },
    { label: "Class Activities", path: "/student/class-activities" },
    { label: "Virtual Class", path: "/student/virtual-class" },
    { label: "CBT", path: "/student/cbt" },
    { label: "School Fees", path: "/student/school-fees" },
    { label: "Announcements", path: "/student/announcements" },
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
              <h3>Quick Actions</h3>
              <div className="sdx-quick-grid">
                {quickActions.map((item) => (
                  <button key={item.path} className="sdx-quick-btn" onClick={() => navigate(item.path)}>
                    {item.label}
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
