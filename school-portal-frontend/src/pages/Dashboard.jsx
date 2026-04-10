import { useEffect, useMemo, useState } from "react";
import api from "../services/api";

const defaultPlatformContent = {
  about_text:
    "LyteBridge Professional Services is a dynamic and innovative solutions provider specializing in Education, ICT, and School Management Software. We are committed to helping schools, educational institutions, and organizations improve efficiency, embrace digital transformation, and operate with professional standards.",
  vision_text:
    "Our services are designed to simplify school administration, enhance teaching and learning, and provide reliable technology solutions tailored to modern educational needs. From school setup and educational consulting to ICT infrastructure and complete school management systems, LyteBridge delivers affordable, user-friendly, and scalable solutions.",
  mission_text:
    "At LyteBridge Professional Services, we focus on professionalism, innovation, and excellence. Our goal is to empower institutions to go paperless, save cost, improve productivity, and manage their operations smarter through technology-driven solutions.",
  content_section_title: "Content Area",
  content_section_intro:
    "Use this space to present the platform rollout plan, onboarding highlights, and the next steps schools should follow before launch.",
  content_todo_items: [
    "Plan school onboarding, admin access, and rollout timeline.",
    "Prepare portal content, branding, and staff orientation materials.",
    "Launch admissions, payments, results, and communication workflows.",
  ],
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
    const load = async () => {
      setLoading(true);
      try {
        const [statsRes, contentRes] = await Promise.all([
          api.get("/api/super-admin/stats"),
          api.get("/api/super-admin/platform-content"),
        ]);

        setStats({
          schools: statsRes.data?.schools ?? 0,
          active_users: statsRes.data?.active_users ?? 0,
          admins: statsRes.data?.admins ?? 0,
        });
        setPlatformContent({ ...defaultPlatformContent, ...(contentRes.data?.data || {}) });
      } catch {
        setStats({ schools: 0, active_users: 0, admins: 0 });
        setPlatformContent(defaultPlatformContent);
      } finally {
        setLoading(false);
      }
    };

    load();
  }, []);

  const todoListValue = useMemo(
    () => (Array.isArray(platformContent.content_todo_items) ? platformContent.content_todo_items.join("\n") : ""),
    [platformContent.content_todo_items]
  );

  const updateField = (field, value) => {
    setPlatformContent((prev) => ({ ...prev, [field]: value }));
  };

  const saveContent = async () => {
    setSaving(true);
    try {
      const payload = {
        ...platformContent,
        content_todo_items: todoListValue
          .split(/\r\n|\r|\n/)
          .map((item) => item.trim())
          .filter(Boolean),
      };
      const res = await api.put("/api/super-admin/platform-content", payload);
      setPlatformContent({ ...defaultPlatformContent, ...(res.data?.data || {}) });
      alert("Platform homepage content saved.");
    } catch (err) {
      const firstValidationError = Object.values(err?.response?.data?.errors || {}).flat().find(Boolean);
      alert(firstValidationError || err?.response?.data?.message || "Failed to save platform homepage content.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={styles.page}>
      <div style={styles.header}>
        <div>
          <h1 style={styles.title}>Platform Overview</h1>
          <p style={styles.subtitle}>System-wide statistics and editable homepage content for the main LyteBridge platform.</p>
        </div>
        <button type="button" onClick={saveContent} disabled={saving} style={styles.primaryButton}>
          {saving ? "Saving..." : "Save Platform Content"}
        </button>
      </div>

      <div style={styles.statsGrid}>
        <Stat title="Total Schools" value={loading ? "..." : String(stats.schools)} />
        <Stat title="Active Users" value={loading ? "..." : String(stats.active_users)} />
        <Stat title="Admins" value={loading ? "..." : String(stats.admins)} />
      </div>

      <section style={styles.editorCard}>
        <div style={styles.editorHead}>
          <div>
            <h2 style={styles.sectionTitle}>Platform Homepage Content</h2>
            <p style={styles.sectionNote}>
              Edit the About Us, Vision, Mission, and Content Area sections that appear on the public homepage.
            </p>
          </div>
        </div>

        <div style={styles.formGrid}>
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

          <Field label="Content Area Title">
            <input
              value={platformContent.content_section_title}
              onChange={(e) => updateField("content_section_title", e.target.value)}
              style={styles.input}
            />
          </Field>

          <Field label="Content Area Intro">
            <textarea
              rows="4"
              value={platformContent.content_section_intro}
              onChange={(e) => updateField("content_section_intro", e.target.value)}
              style={styles.textarea}
            />
          </Field>

          <Field label="Content To-do List">
            <textarea
              rows="6"
              value={todoListValue}
              onChange={(e) => updateField("content_todo_items", e.target.value.split(/\r\n|\r|\n/))}
              style={styles.textarea}
              placeholder="Enter one content item per line"
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
    <div style={styles.statCard}>
      <span style={styles.statLabel}>{title}</span>
      <strong style={styles.statValue}>{value}</strong>
    </div>
  );
}

const styles = {
  page: {
    display: "grid",
    gap: 24,
  },
  header: {
    display: "flex",
    flexWrap: "wrap",
    gap: 16,
    alignItems: "flex-start",
    justifyContent: "space-between",
  },
  title: {
    margin: 0,
    fontSize: "2rem",
    color: "#10281b",
  },
  subtitle: {
    margin: "8px 0 0",
    maxWidth: 760,
    color: "#4d6354",
    lineHeight: 1.6,
  },
  primaryButton: {
    border: 0,
    borderRadius: 999,
    padding: "12px 20px",
    background: "linear-gradient(135deg, #0c5b2d, #1c8e49)",
    color: "#fff",
    fontWeight: 800,
    cursor: "pointer",
    boxShadow: "0 14px 28px rgba(12, 91, 45, 0.18)",
  },
  statsGrid: {
    display: "grid",
    gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))",
    gap: 16,
  },
  statCard: {
    background: "linear-gradient(180deg, #ffffff, #f5fff7)",
    border: "1px solid rgba(12, 91, 45, 0.1)",
    borderRadius: 22,
    padding: 20,
    boxShadow: "0 16px 34px rgba(7, 58, 27, 0.08)",
    display: "grid",
    gap: 8,
  },
  statLabel: {
    color: "#53715e",
    fontSize: "0.9rem",
    textTransform: "uppercase",
    letterSpacing: 0.6,
    fontWeight: 700,
  },
  statValue: {
    fontSize: "2rem",
    color: "#0d331d",
  },
  editorCard: {
    background: "linear-gradient(180deg, #ffffff, #f7fff9)",
    border: "1px solid rgba(12, 91, 45, 0.1)",
    borderRadius: 28,
    padding: 24,
    boxShadow: "0 20px 44px rgba(7, 58, 27, 0.08)",
    display: "grid",
    gap: 20,
  },
  editorHead: {
    display: "flex",
    justifyContent: "space-between",
    gap: 16,
    flexWrap: "wrap",
  },
  sectionTitle: {
    margin: 0,
    color: "#10281b",
    fontSize: "1.45rem",
  },
  sectionNote: {
    margin: "8px 0 0",
    color: "#577060",
    lineHeight: 1.65,
    maxWidth: 820,
  },
  formGrid: {
    display: "grid",
    gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))",
    gap: 18,
  },
  field: {
    display: "grid",
    gap: 8,
  },
  label: {
    color: "#173223",
    fontWeight: 800,
  },
  input: {
    borderRadius: 16,
    border: "1px solid rgba(12, 91, 45, 0.14)",
    padding: "14px 16px",
    fontSize: "0.98rem",
    background: "#fff",
  },
  textarea: {
    borderRadius: 18,
    border: "1px solid rgba(12, 91, 45, 0.14)",
    padding: "14px 16px",
    fontSize: "0.98rem",
    lineHeight: 1.6,
    resize: "vertical",
    background: "#fff",
    minHeight: 140,
  },
};

export default Overview;
