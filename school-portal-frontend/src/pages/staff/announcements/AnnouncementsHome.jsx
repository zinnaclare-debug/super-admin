import { useEffect, useState } from "react";
import api from "../../../services/api";
import { getLatestAnnouncement, markAnnouncementSeen } from "../../../utils/announcementNotifier";
import { getStoredUser } from "../../../utils/authStorage";

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export default function AnnouncementsHome() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError("");
      try {
        const res = await api.get("/api/staff/announcements");
        const data = res?.data?.data || [];
        setItems(data);

        const latest = getLatestAnnouncement(data);
        if (latest) {
          markAnnouncementSeen(getStoredUser(), "staff", latest);
        }
      } catch (err) {
        setError(err?.response?.data?.message || "Failed to load announcements.");
        setItems([]);
      } finally {
        setLoading(false);
      }
    };

    load();
  }, []);

  return (
    <div style={{ background: "#fff", borderRadius: 10, border: "1px solid #dbeafe", padding: 14 }}>
      <h2 style={{ marginTop: 0 }}>Announcement Desk</h2>
      <p style={{ marginTop: 0, color: "#475569" }}>
        School-wide and level-specific notices for staff.
      </p>

      {loading && <p>Loading announcements...</p>}
      {error && <p style={{ color: "#b91c1c" }}>{error}</p>}
      {!loading && !error && items.length === 0 && <p>No announcements yet.</p>}

      <div style={{ display: "grid", gap: 10 }}>
        {items.map((item) => (
          <article key={item.id} style={{ border: "1px solid #dbeafe", borderRadius: 10, padding: 12 }}>
            <h3 style={{ marginTop: 0, marginBottom: 6 }}>{item.title}</h3>
            <p style={{ marginTop: 0, marginBottom: 8, whiteSpace: "pre-wrap" }}>{item.message}</p>
            <small style={{ display: "block", color: "#475569" }}>Audience: {item.audience}</small>
            <small style={{ display: "block", color: "#475569" }}>Posted: {formatDate(item.published_at)}</small>
            <small style={{ display: "block", color: "#475569" }}>By: {item.author?.name || "School admin"}</small>
          </article>
        ))}
      </div>
    </div>
  );
}
