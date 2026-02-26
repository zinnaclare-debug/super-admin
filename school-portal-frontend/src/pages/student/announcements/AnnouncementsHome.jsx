import { useEffect, useState } from "react";
import api from "../../../services/api";
import { getLatestAnnouncement, markAnnouncementSeen } from "../../../utils/announcementNotifier";
import { getStoredUser } from "../../../utils/authStorage";
import remoteWorkerArt from "../../../assets/announcements/remote-worker.svg";
import groupChatArt from "../../../assets/announcements/group-chat.svg";
import onlineInfoArt from "../../../assets/announcements/online-information.svg";
import "../../shared/AnnouncementDesk.css";

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

function badgeClass(audience) {
  const value = String(audience || "").toLowerCase();
  if (value.includes("staff")) return "announce-badge announce-badge--staff";
  if (value.includes("student")) return "announce-badge announce-badge--student";
  if (value.includes("level")) return "announce-badge announce-badge--level";
  return "announce-badge announce-badge--all";
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
        const res = await api.get("/api/student/announcements");
        const data = res?.data?.data || [];
        setItems(data);

        const latest = getLatestAnnouncement(data);
        if (latest) {
          markAnnouncementSeen(getStoredUser(), "student", latest);
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
    <div className="announce-page">
      <section className="announce-hero">
        <div>
          <span className="announce-pill">Student Announcement Desk</span>
          <h2>Catch updates quickly and stay informed</h2>
          <p className="announce-subtitle">
            Read class-level and school-wide announcements with a clearer, friendlier notice board.
          </p>
          <div className="announce-metrics">
            <span>{loading ? "Syncing..." : `${items.length} fresh notice${items.length === 1 ? "" : "s"}`}</span>
            <span>{error ? "Some items failed" : "Announcement stream active"}</span>
          </div>
        </div>

        <div className="announce-hero-art" aria-hidden="true">
          <div className="announce-art-card announce-art-card--main">
            <img src={remoteWorkerArt} alt="" />
          </div>
          <div className="announce-art-card announce-art-card--chat">
            <img src={groupChatArt} alt="" />
          </div>
          <div className="announce-art-card announce-art-card--info">
            <img src={onlineInfoArt} alt="" />
          </div>
        </div>
      </section>

      <section className="announce-panel">
        {loading && <p className="announce-state announce-state--loading">Loading announcements...</p>}
        {error && <p className="announce-state announce-state--error">{error}</p>}
        {!loading && !error && items.length === 0 && (
          <p className="announce-state announce-state--empty">No announcements yet.</p>
        )}

        <div className="announce-list">
          {items.map((item) => (
            <article key={item.id} className="announce-card">
              <div className="announce-card-header">
                <h3>{item.title}</h3>
                <span className={badgeClass(item.audience)}>{item.audience || "All"}</span>
              </div>
              <p className="announce-message">{item.message}</p>
              <div className="announce-meta">
                <small>Posted: {formatDate(item.published_at)}</small>
                <small>By: {item.author?.name || "School admin"}</small>
              </div>
            </article>
          ))}
        </div>
      </section>
    </div>
  );
}
