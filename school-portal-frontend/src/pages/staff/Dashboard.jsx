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

  return (
    <div>
      <h1>Staff Dashboard</h1>

      {announcementUnreadCount > 0 && latestAnnouncement ? (
        <div
          style={{
            marginTop: 12,
            marginBottom: 10,
            border: "1px solid #facc15",
            background: "#fef9c3",
            borderRadius: 10,
            padding: 12,
          }}
        >
          <p style={{ margin: 0, fontWeight: 700 }}>
            🔔 {announcementUnreadCount} new announcement{announcementUnreadCount > 1 ? "s" : ""}
          </p>
          <p style={{ margin: "6px 0 8px" }}>
            Latest: <strong>{latestAnnouncement.title}</strong>
          </p>
          <button onClick={openAnnouncements}>Open Announcement Desk</button>
        </div>
      ) : null}

      {/* Quick Access Buttons */}
      <div style={{ marginTop: 16, display: "flex", gap: 8, flexWrap: "wrap" }}>
        <button 
          onClick={() => navigate("/staff/results")}
          style={{ padding: "10px 16px", borderRadius: 8, background: "#2563eb", color: "#fff", cursor: "pointer", border: "none" }}
        >
          View My Results/Courses
        </button>
      </div>

      <section style={{ marginTop: 24 }}>
        <h2>Profile Details</h2>

        {loading ? (
          <p>Loading profile...</p>
        ) : error ? (
          <p style={{ color: "red" }}>{error}</p>
        ) : (
          <div style={{ border: "1px solid #ddd", borderRadius: 10, padding: 14 }}>
            {staffPhotoUrl ? (
              <div style={{ marginBottom: 16 }}>
                <img
                  src={staffPhotoUrl}
                  alt="Staff profile"
                  style={{ width: 110, height: 110, borderRadius: 8, objectFit: "cover", border: "1px solid #ddd" }}
                />
              </div>
            ) : null}
            <table cellPadding="8" style={{ width: "100%" }}>
              <tbody>
                <tr><td style={{ width: 180, opacity: 0.75 }}>Name</td><td><strong>{user.name || "-"}</strong></td></tr>
                <tr><td style={{ opacity: 0.75 }}>Email</td><td>{user.email || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Username</td><td>{user.username || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Education Level</td><td>{staff.education_level || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Position</td><td>{staff.position || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Sex</td><td>{staff.sex || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>DOB</td><td>{staff.dob || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Address</td><td>{staff.address || "-"}</td></tr>
              </tbody>
            </table>
          </div>
        )}
      </section>

      {isClassTeacher ? (
        <section style={{ marginTop: 24 }}>
          <h2>Class Teacher</h2>
          <p style={{ marginTop: 0, opacity: 0.8 }}>You are assigned as class teacher to the class(es) below.</p>
          <table border="1" cellPadding="8" width="100%">
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
        </section>
      ) : null}
    </div>
  );
}
