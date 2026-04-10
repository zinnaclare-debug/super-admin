import { useEffect, useState } from "react";
import api from "../services/api";

const defaultPlatformContent = {
  about_text:
    "LyteBridge Professional Services is a dynamic and innovative solutions provider specializing in Education, ICT, and School Management Software. We help schools, educational institutions, and organizations improve efficiency, embrace digital transformation, and operate with professional standards.",
  vision_text:
    "Our services are designed to simplify school administration, enhance teaching and learning, and provide reliable technology solutions tailored to modern educational needs. From school setup and educational consulting to ICT infrastructure and complete school management systems, LyteBridge delivers affordable, user-friendly, and scalable solutions.",
  mission_text:
    "At LyteBridge Professional Services, we focus on professionalism, innovation, and excellence. Our goal is to empower institutions to go paperless, save cost, improve productivity, and manage their operations smarter through technology-driven solutions.",
};

function Overview() {
  const [stats, setStats] = useState({
    schools: 0,
    active_users: 0,
    admins: 0,
  });
  const [platformContent, setPlatformContent] = useState(defaultPlatformContent);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let active = true;

    const loadStats = async () => {
      try {
        const res = await api.get("/api/super-admin/stats");
        if (!active) return;
        setStats({
          schools: res.data?.schools ?? 0,
          active_users: res.data?.active_users ?? 0,
          admins: res.data?.admins ?? 0,
        });
      } catch {
        if (!active) return;
        setStats({ schools: 0, active_users: 0, admins: 0 });
      } finally {
        if (active) setLoading(false);
      }
    };

    const loadPlatformContent = async () => {
      try {
        const res = await api.get("/api/super-admin/platform-content");
        if (!active) return;
        setPlatformContent({ ...defaultPlatformContent, ...(res.data?.data || {}) });
      } catch {
        if (!active) return;
        setPlatformContent(defaultPlatformContent);
      }
    };

    loadStats();
    loadPlatformContent();

    return () => {
      active = false;
    };
  }, []);

  const updateField = (field, value) => {
    setPlatformContent((prev) => ({ ...prev, [field]: value }));
  };

  const saveContent = async () => {
    setSaving(true);
    try {
      const res = await api.put("/api/super-admin/platform-content", platformContent);
      setPlatformContent({ ...defaultPlatformContent, ...(res.data?.data || {}) });
      alert("Platform content updated successfully.");
    } catch (err) {
      const firstValidationError = Object.values(err?.response?.data?.errors || {}).flat().find(Boolean);
      alert(firstValidationError || err?.response?.data?.message || "Failed to update platform content.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={styles.page}>
      <h1>Platform Overview</h1>
      <p>System-wide statistics</p>

      <div style={styles.statsWrap}>
        <Stat title="Total Schools" value={loading ? "..." : String(stats.schools)} />
        <Stat title="Active Users" value={loading ? "..." : String(stats.active_users)} />
        <Stat title="Admins" value={loading ? "..." : String(stats.admins)} />
      </div>

      <section style={styles.editorCard}>
        <div style={styles.editorHead}>
          <div>
            <h2 style={styles.editorTitle}>Homepage Content</h2>
            <p style={styles.editorNote}>Update the About Us, Vision, and Mission text that appears on the platform homepage.</p>
          </div>
          <button type="button" onClick={saveContent} disabled={saving} style={styles.saveButton}>
            {saving ? "Saving..." : "Save Content"}
          </button>
        </div>

        <div style={styles.fieldGrid}>
          <Field label="About Us">
            <textarea
              rows="6"
              value={platformContent.about_text}
              onChange={(e) => updateField("about_text", e.target.value)}
              style={styles.textarea}
            />
          </Field>

          <Field label="Vision">
            <textarea
              rows="6"
              value={platformContent.vision_text}
              onChange={(e) => updateField("vision_text", e.target.value)}
              style={styles.textarea}
            />
          </Field>

          <Field label="Mission">
            <textarea
              rows="6"
              value={platformContent.mission_text}
              onChange={(e) => updateField("mission_text", e.target.value)}
              style={styles.textarea}
            />
          </Field>
        </div>
      </section>
    </div>
  );
}

function Field({ label, children }) {
  return (
    <label style={styles.field}>
      <span style={styles.label}>{label}</span>
      {children}
    </label>
  );
}

function Stat({ title, value }) {
  return (
    <div style={styles.card}>
      <h3>{title}</h3>
      <p style={{ fontSize: 24, fontWeight: "bold" }}>{value}</p>
    </div>
  );
}

const styles = {
  page: {
    display: "grid",
    gap: 24,
  },
  statsWrap: {
    display: "flex",
    flexWrap: "wrap",
    gap: 20,
    marginTop: 20,
  },
  card: {
    background: "#fff",
    padding: 20,
    borderRadius: 8,
    width: 200,
    boxShadow: "0 4px 12px rgba(0,0,0,0.1)",
  },
  editorCard: {
    background: "#fff",
    padding: 24,
    borderRadius: 16,
    boxShadow: "0 10px 30px rgba(0,0,0,0.08)",
    display: "grid",
    gap: 18,
  },
  editorHead: {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: 16,
    flexWrap: "wrap",
  },
  editorTitle: {
    margin: 0,
    fontSize: 22,
  },
  editorNote: {
    margin: "8px 0 0",
    color: "#4b5563",
    lineHeight: 1.6,
  },
  saveButton: {
    border: 0,
    borderRadius: 10,
    padding: "12px 18px",
    background: "#166534",
    color: "#fff",
    fontWeight: 700,
    cursor: "pointer",
  },
  fieldGrid: {
    display: "grid",
    gap: 16,
  },
  field: {
    display: "grid",
    gap: 8,
  },
  label: {
    fontWeight: 700,
    color: "#111827",
  },
  textarea: {
    width: "100%",
    borderRadius: 12,
    border: "1px solid #d1d5db",
    padding: 14,
    fontSize: 14,
    lineHeight: 1.6,
    resize: "vertical",
    minHeight: 140,
    boxSizing: "border-box",
  },
};

export default Overview;
