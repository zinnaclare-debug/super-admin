import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";
import "./ClassTemplates.css";

const pretty = (value) =>
  String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

export default function ClassTemplates() {
  const navigate = useNavigate();
  const [templates, setTemplates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const loadTemplates = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/school-admin/class-templates");
      setTemplates(Array.isArray(res.data?.data) ? res.data.data : []);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load class templates.");
      setTemplates([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadTemplates();
  }, []);

  const updateSection = (index, next) => {
    setTemplates((prev) => prev.map((item, idx) => (idx === index ? { ...item, ...next } : item)));
  };

  const updateClassName = (sectionIndex, classIndex, value) => {
    setTemplates((prev) =>
      prev.map((section, idx) => {
        if (idx !== sectionIndex) return section;
        const classes = Array.isArray(section.classes) ? [...section.classes] : [];
        classes[classIndex] = value;
        return { ...section, classes };
      })
    );
  };

  const saveTemplates = async () => {
    const payload = templates.map((section) => ({
      key: section.key,
      label: section.label,
      enabled: Boolean(section.enabled),
      classes: Array.isArray(section.classes) ? section.classes : [],
    }));

    setSaving(true);
    try {
      const res = await api.put("/api/school-admin/class-templates", {
        class_templates: payload,
      });
      const normalized = Array.isArray(res.data?.data) ? res.data.data : payload;
      setTemplates(normalized);
      alert("Class templates saved.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to save class templates.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="class-template-page">
      <div className="class-template-top">
        <button type="button" className="class-template-back" onClick={() => navigate(-1)}>
          Back
        </button>
        <div>
          <h2>Create Class Templates</h2>
          <p>
            Configure section headers and class names here. This structure is used for new sessions and
            added into existing sessions without deleting old class records.
          </p>
        </div>
      </div>

      {loading ? (
        <p>Loading class templates...</p>
      ) : (
        <div className="class-template-grid">
          {(templates || []).map((section, sectionIndex) => (
            <div className="class-template-card" key={section.key || sectionIndex}>
              <div className="class-template-card-head">
                <label className="class-template-check">
                  <input
                    type="checkbox"
                    checked={Boolean(section.enabled)}
                    onChange={(e) => updateSection(sectionIndex, { enabled: e.target.checked })}
                  />
                  <span>Enable</span>
                </label>
                <input
                  type="text"
                  value={section.label || ""}
                  onChange={(e) => updateSection(sectionIndex, { label: e.target.value })}
                  placeholder={pretty(section.key)}
                />
              </div>

              <div className="class-template-fields">
                {(section.classes || []).map((name, classIndex) => (
                  <input
                    key={`${section.key}-${classIndex}`}
                    type="text"
                    value={name || ""}
                    onChange={(e) => updateClassName(sectionIndex, classIndex, e.target.value)}
                    placeholder={`${section.label || pretty(section.key)} ${classIndex + 1}`}
                    disabled={!section.enabled}
                  />
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="class-template-actions">
        <button type="button" onClick={saveTemplates} disabled={saving || loading}>
          {saving ? "Saving..." : "Save Class Templates"}
        </button>
      </div>
    </div>
  );
}
