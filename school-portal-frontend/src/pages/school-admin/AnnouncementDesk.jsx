import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";

const LEVEL_OPTIONS = [
  { value: "", label: "School-wide (all levels)" },
  { value: "nursery", label: "Nursery only" },
  { value: "primary", label: "Primary only" },
  { value: "secondary", label: "Secondary only" },
];

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export default function AnnouncementDesk() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [form, setForm] = useState({
    title: "",
    message: "",
    level: "",
  });

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

  const onChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError("");
    try {
      await api.post("/api/school-admin/announcements", {
        title: form.title,
        message: form.message,
        level: form.level || null,
      });
      setForm({ title: "", message: "", level: "" });
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
    <div style={{ display: "grid", gap: 14 }}>
      <section style={{ background: "#fff", padding: 14, borderRadius: 10, border: "1px solid #d6e3ff" }}>
        <h3 style={{ marginTop: 0 }}>Announcement Desk</h3>
        <p style={{ marginTop: 0, color: "#475569" }}>
          Post school-wide notices or target specific education levels.
        </p>

        <form onSubmit={onSubmit} style={{ display: "grid", gap: 8 }}>
          <input
            name="title"
            placeholder="Announcement title"
            value={form.title}
            onChange={onChange}
            required
            maxLength={160}
            style={{ padding: 10 }}
          />
          <textarea
            name="message"
            placeholder="Write announcement details..."
            value={form.message}
            onChange={onChange}
            required
            rows={5}
            maxLength={5000}
            style={{ padding: 10, resize: "vertical" }}
          />
          <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
            <select name="level" value={form.level} onChange={onChange} style={{ padding: 10 }}>
              {LEVEL_OPTIONS.map((opt) => (
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
      </section>

      <section style={{ background: "#fff", padding: 14, borderRadius: 10, border: "1px solid #d6e3ff" }}>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 8, flexWrap: "wrap" }}>
          <h3 style={{ margin: 0 }}>Posted Announcements</h3>
          <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} style={{ padding: 8 }}>
            <option value="all">All</option>
            <option value="active">Active only</option>
            <option value="inactive">Inactive only</option>
          </select>
        </div>

        {error && <p style={{ color: "#b91c1c" }}>{error}</p>}
        {loading && <p>Loading announcements...</p>}
        {!loading && !hasItems && <p>No announcements yet.</p>}

        {!loading && hasItems && (
          <div style={{ display: "grid", gap: 10, marginTop: 10 }}>
            {items.map((item) => (
              <article key={item.id} style={{ border: "1px solid #dbeafe", borderRadius: 10, padding: 12 }}>
                <div style={{ display: "flex", justifyContent: "space-between", gap: 8, flexWrap: "wrap" }}>
                  <h4 style={{ margin: 0 }}>{item.title}</h4>
                  <span
                    style={{
                      borderRadius: 999,
                      padding: "2px 8px",
                      background: item.is_active ? "#dcfce7" : "#fee2e2",
                      color: item.is_active ? "#166534" : "#991b1b",
                      fontSize: 12,
                      fontWeight: 700,
                    }}
                  >
                    {item.is_active ? "Active" : "Inactive"}
                  </span>
                </div>
                <p style={{ marginBottom: 8, whiteSpace: "pre-wrap" }}>{item.message}</p>
                <div style={{ color: "#475569", fontSize: 13 }}>
                  <div>Audience: {item.audience}</div>
                  <div>Posted: {formatDate(item.published_at || item.created_at)}</div>
                  <div>By: {item.author?.name || "School admin"}</div>
                </div>
                <div style={{ marginTop: 8, display: "flex", gap: 8, flexWrap: "wrap" }}>
                  <button onClick={() => toggleActive(item)}>
                    {item.is_active ? "Deactivate" : "Activate"}
                  </button>
                  <button onClick={() => remove(item)} style={{ background: "#fee2e2", borderColor: "#fecaca" }}>
                    Delete
                  </button>
                </div>
              </article>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}

