import { useEffect, useMemo, useRef, useState } from "react";
import api from "../../services/api";
import remoteWorkerArt from "../../assets/announcements/remote-worker.svg";
import groupChatArt from "../../assets/announcements/group-chat.svg";
import onlineInfoArt from "../../assets/announcements/online-information.svg";
import "../shared/AnnouncementDesk.css";

const prettyLevel = (value) =>
  String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

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

function AnnouncementMedia({ item }) {
  if (!item?.media_url || item?.media_type !== "image") return null;

  return (
    <div className="announce-media-wrap">
      <img className="announce-media" src={item.media_url} alt={item.title || "Announcement attachment"} />
    </div>
  );
}

export default function AnnouncementDesk() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [levelOptions, setLevelOptions] = useState([
    { value: "", label: "School-wide (all levels)" },
  ]);
  const [form, setForm] = useState({
    title: "",
    message: "",
    level: "",
    media: null,
  });
  const fileInputRef = useRef(null);

  const hasItems = useMemo(() => items.length > 0, [items]);

  const load = async (status = statusFilter) => {
    setLoading(true);
    setError("");
    try {
      const res = await api.get("/api/school-admin/announcements", {
        params: { status },
      });
      setItems(res?.data?.data || []);
    } catch (err) {
      setError(err?.response?.data?.message || "Failed to load announcements.");
      setItems([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load(statusFilter);
  }, [statusFilter]);

  useEffect(() => {
    let mounted = true;
    const loadLevels = async () => {
      try {
        const res = await api.get("/api/school-admin/class-templates");
        const templates = Array.isArray(res.data?.data) ? res.data.data : [];
        const options = templates
          .filter((section) => Boolean(section?.enabled))
          .map((section) => String(section?.key || "").trim().toLowerCase())
          .filter(Boolean)
          .map((value) => ({ value, label: `${prettyLevel(value)} only` }));

        if (mounted) {
          setLevelOptions([{ value: "", label: "School-wide (all levels)" }, ...options]);
        }
      } catch {
        if (mounted) {
          setLevelOptions([{ value: "", label: "School-wide (all levels)" }]);
        }
      }
    };

    loadLevels();
    return () => {
      mounted = false;
    };
  }, []);

  const onChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const onFileChange = (e) => {
    const file = e.target.files?.[0] || null;
    setForm((prev) => ({ ...prev, media: file }));
  };

  const clearFile = () => {
    setForm((prev) => ({ ...prev, media: null }));
    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
  };

  const clearForm = () => {
    setForm({ title: "", message: "", level: "", media: null });
    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError("");
    try {
      const payload = new FormData();
      payload.append("title", form.title);
      payload.append("message", form.message);
      if (form.level) {
        payload.append("level", form.level);
      }
      if (form.media) {
        payload.append("media", form.media);
      }

      await api.post("/api/school-admin/announcements", payload, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      });
      clearForm();
      await load(statusFilter);
    } catch (err) {
      const fieldError = err?.response?.data?.errors
        ? Object.values(err.response.data.errors).flat()[0]
        : null;
      setError(fieldError || err?.response?.data?.message || "Failed to post announcement.");
    } finally {
      setSaving(false);
    }
  };

  const toggleActive = async (item) => {
    try {
      await api.patch(`/api/school-admin/announcements/${item.id}`, {
        is_active: !item.is_active,
      });
      await load(statusFilter);
    } catch (err) {
      setError(err?.response?.data?.message || "Failed to update announcement status.");
    }
  };

  const remove = async (item) => {
    if (!window.confirm(`Delete "${item.title}"?`)) return;
    try {
      await api.delete(`/api/school-admin/announcements/${item.id}`);
      await load(statusFilter);
    } catch (err) {
      setError(err?.response?.data?.message || "Failed to delete announcement.");
    }
  };

  return (
    <div className="announce-page">
      <section className="announce-hero">
        <div>
          <span className="announce-pill">School Admin Announcement Desk</span>
          <h2>Post updates with the same clarity students and staff already see.</h2>
          <p className="announce-subtitle">
            Publish school-wide or level-specific notices, attach photos, and manage active announcements from one bright notice desk.
          </p>
          <div className="announce-metrics">
            <span>{loading ? "Syncing..." : `${items.length} announcement${items.length === 1 ? "" : "s"}`}</span>
            <span>{statusFilter === "all" ? "All statuses" : `${prettyLevel(statusFilter)} filter`}</span>
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
        <div className="announce-card" style={{ marginBottom: 12 }}>
          <div className="announce-card-header">
            <h3>Create Announcement</h3>
            <span className="announce-badge announce-badge--all">Publish</span>
          </div>
          <p className="announce-message" style={{ marginTop: 8 }}>
            Post school-wide notices or target specific education levels. You can also attach a photo.
          </p>

          <form onSubmit={onSubmit} style={{ display: "grid", gap: 10 }}>
            <input
              name="title"
              placeholder="Announcement title"
              value={form.title}
              onChange={onChange}
              required
              maxLength={160}
              style={{ padding: 10, borderRadius: 10, border: "1px solid #cbd5e1" }}
            />
            <textarea
              name="message"
              placeholder="Write announcement details..."
              value={form.message}
              onChange={onChange}
              required
              rows={5}
              maxLength={5000}
              style={{ padding: 10, borderRadius: 10, border: "1px solid #cbd5e1", resize: "vertical" }}
            />

            <div className="announce-card" style={{ background: "#f8fafc" }}>
              <div className="announce-card-header">
                <h3 style={{ fontSize: 15 }}>Upload Picture</h3>
                <span className="announce-badge announce-badge--level">Optional</span>
              </div>
              <input ref={fileInputRef} type="file" accept="image/*" onChange={onFileChange} />
              <div className="announce-meta" style={{ marginTop: 8 }}>
                <small>Accepted: JPG, JPEG, PNG, or WEBP only.</small>
                <small>Max size: 5MB.</small>
              </div>
              {form.media ? (
                <div className="announce-metrics" style={{ marginTop: 10 }}>
                  <span>{form.media.name}</span>
                  <button type="button" onClick={clearFile}>Remove</button>
                </div>
              ) : null}
            </div>

            <div style={{ display: "flex", gap: 10, flexWrap: "wrap", alignItems: "center" }}>
              <select name="level" value={form.level} onChange={onChange} style={{ padding: 10, borderRadius: 10, border: "1px solid #cbd5e1", minWidth: 220 }}>
                {levelOptions.map((opt) => (
                  <option key={opt.value || "all"} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
              <button type="submit" disabled={saving}>
                {saving ? "Posting..." : "Post Announcement"}
              </button>
            </div>
          </form>
        </div>

        <div className="announce-card-header" style={{ marginBottom: 12 }}>
          <h3 style={{ margin: 0 }}>Posted Announcements</h3>
          <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} style={{ padding: 8, borderRadius: 10, border: "1px solid #cbd5e1" }}>
            <option value="all">All</option>
            <option value="active">Active only</option>
            <option value="inactive">Inactive only</option>
          </select>
        </div>

        {error && <p className="announce-state announce-state--error">{error}</p>}
        {loading && <p className="announce-state announce-state--loading">Loading announcements...</p>}
        {!loading && !hasItems && <p className="announce-state announce-state--empty">No announcements yet.</p>}

        <div className="announce-list">
          {!loading && hasItems && items.map((item) => (
            <article key={item.id} className="announce-card">
              <div className="announce-card-header">
                <h3>{item.title}</h3>
                <span className={badgeClass(item.audience)}>{item.audience || "All"}</span>
              </div>
              <p className="announce-message">{item.message}</p>
              <AnnouncementMedia item={item} />
              <div className="announce-meta">
                <small>Posted: {formatDate(item.published_at || item.created_at)}</small>
                <small>By: {item.author?.name || "School admin"}</small>
                <small>Status: {item.is_active ? "Active" : "Inactive"}</small>
              </div>
              <div style={{ display: "flex", gap: 10, flexWrap: "wrap", marginTop: 10 }}>
                <button type="button" onClick={() => toggleActive(item)}>
                  {item.is_active ? "Deactivate" : "Activate"}
                </button>
                <button type="button" onClick={() => remove(item)} style={{ background: "#fff1f2", color: "#b91c1c", border: "1px solid #fecdd3" }}>
                  Delete
                </button>
              </div>
            </article>
          ))}
        </div>
      </section>
    </div>
  );
}

