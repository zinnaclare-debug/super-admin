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
import "./Dashboard.css";

export default function StaffDashboard() {
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [announcementUnreadCount, setAnnouncementUnreadCount] = useState(0);
  const [latestAnnouncement, setLatestAnnouncement] = useState(null);

  const toAbsoluteUrl = (url) => {
    if (!url) return null;

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

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError("");
      try {
        const res = await api.get("/api/staff/profile");
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
        const res = await api.get("/api/staff/announcements");
        const items = res?.data?.data || [];
        if (!active) return;

        const storedUser = getStoredUser();
        const latest = getLatestAnnouncement(items);
        const seenRank = getSeenAnnouncementRank(storedUser, "staff");

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

  const user = profile?.user || {};
  const staff = profile?.staff || {};
  const classes = profile?.classes || [];
  const isClassTeacher = classes.length > 0;
  const staffPhotoUrl = toAbsoluteUrl(
    staff.photo_url || (staff.photo_path ? `/storage/${staff.photo_path}` : "")
  );

  const openAnnouncements = () => {
    const storedUser = getStoredUser();
    if (latestAnnouncement) {
      markAnnouncementSeen(storedUser, "staff", latestAnnouncement);
      setAnnouncementUnreadCount(0);
    }
    navigate("/staff/announcements");
  };

  const quickActions = [
    { label: "Profile", hint: "Personal details", path: "/staff/profile", tone: "calm" },
    { label: "Results", hint: "Scores and courses", path: "/staff/results", tone: "bright" },
    { label: "Topics", hint: "Manage topics", path: "/staff/topics", tone: "warm" },
    { label: "E-Library", hint: "Learning resources", path: "/staff/e-library", tone: "calm" },
    { label: "Class Activities", hint: "Assignments and files", path: "/staff/class-activities", tone: "warm" },
    { label: "Virtual Class", hint: "Live sessions", path: "/staff/virtual-class", tone: "bright" },
    { label: "CBT Console", hint: "Create and manage CBT exams", path: "/staff/cbt", tone: "bright" },
    { label: "Announcements", hint: "School updates", path: "/staff/announcements", tone: "calm" },
  ];

  return (
    <div className="stx-page">
      <section className="stx-hero">
        <div>
          <span className="stx-pill">Staff Dashboard</span>
          <h2>Manage teaching tools from one colorful workspace</h2>
          <p>
            Open your teaching modules faster, stay updated with announcements, and review your profile in one place.
          </p>
          <div className="stx-meta">
            <span>{staff.education_level || "Education level pending"}</span>
            <span>{staff.position || "Position pending"}</span>
            <span>{classes.length} class teacher assignment{classes.length === 1 ? "" : "s"}</span>
          </div>
        </div>

        <div className="stx-hero-art" aria-hidden="true">
          <div className="stx-art stx-art--main">
            <img src={teachingArt} alt="" />
          </div>
          <div className="stx-art stx-art--trend">
            <img src={trendsArt} alt="" />
          </div>
          <div className="stx-art stx-art--article">
            <img src={articlesArt} alt="" />
          </div>
        </div>
      </section>

      <section className="stx-panel">
        {loading ? <p className="stx-state stx-state--loading">Loading profile...</p> : null}
        {!loading && error ? <p className="stx-state stx-state--error">{error}</p> : null}

        {!loading && !error ? (
          <>
            {announcementUnreadCount > 0 && latestAnnouncement ? (
              <div className="stx-alert">
                <p>
                  {announcementUnreadCount} new announcement{announcementUnreadCount > 1 ? "s" : ""}
                </p>
                <p>
                  Latest: <strong>{latestAnnouncement.title}</strong>
                </p>
                <button className="stx-btn" onClick={openAnnouncements}>
                  Open Announcement Desk
                </button>
              </div>
            ) : null}

            <div className="stx-quick">
              <div className="stx-quick-head">
                <div>
                  <h3>Quick Actions</h3>
                  <p>Open your staff tools in one click.</p>
                </div>
                <div className="stx-quick-art" aria-hidden="true">
                  <img src={swipeProfilesArt} alt="" />
                </div>
              </div>

              <div className="stx-quick-grid">
                {quickActions.map((item) => (
                  <button
                    key={item.path}
                    className={`stx-quick-btn stx-quick-btn--${item.tone}`}
                    onClick={() => navigate(item.path)}
                  >
                    <span className="stx-quick-btn__title">{item.label}</span>
                    <span className="stx-quick-btn__hint">{item.hint}</span>
                  </button>
                ))}
              </div>
            </div>

            <div className="stx-grid">
              <article className="stx-card">
                <h3>Staff Details</h3>
                <div className="stx-kv">
                  <div className="stx-row"><span>Name</span><strong>{user.name || "-"}</strong></div>
                  <div className="stx-row"><span>Email</span><strong>{user.email || "-"}</strong></div>
                  <div className="stx-row"><span>Username</span><strong>{user.username || "-"}</strong></div>
                  <div className="stx-row"><span>Education Level</span><strong>{staff.education_level || "-"}</strong></div>
                  <div className="stx-row"><span>Position</span><strong>{staff.position || "-"}</strong></div>
                  <div className="stx-row"><span>Sex</span><strong>{staff.sex || "-"}</strong></div>
                  <div className="stx-row"><span>DOB</span><strong>{staff.dob || "-"}</strong></div>
                  <div className="stx-row"><span>Address</span><strong>{staff.address || "-"}</strong></div>
                </div>
              </article>

              <article className="stx-card">
                <h3>Photo</h3>
                <div className="stx-photo-box">
                  {staffPhotoUrl ? <img src={staffPhotoUrl} alt="Staff profile" /> : <span>No Photo</span>}
                </div>
              </article>
            </div>

            {isClassTeacher ? (
              <article className="stx-card stx-card--class">
                <h3>Class Teacher Assignments</h3>
                <p className="stx-class-subtext">You are assigned as class teacher to the class(es) below.</p>
                <div className="stx-table-wrap">
                  <table className="stx-table">
                    <thead>
                      <tr>
                        <th>S/N</th>
                        <th>Class</th>
                        <th>Level</th>
                      </tr>
                    </thead>
                    <tbody>
                      {classes.map((c, idx) => (
                        <tr key={c.id}>
                          <td>{idx + 1}</td>
                          <td>{c.name}</td>
                          <td>{c.level}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </article>
            ) : null}
          </>
        ) : null}
      </section>
    </div>
  );
}
