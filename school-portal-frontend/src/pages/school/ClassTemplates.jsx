import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";
import "./ClassTemplates.css";

const pretty = (value) =>
  String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

const normalizeClassRow = (row) => {
  if (row && typeof row === "object" && !Array.isArray(row)) {
    return {
      name: String(row.name || "").trim(),
      enabled: row.enabled !== false,
    };
  }

  return {
    name: String(row || "").trim(),
    enabled: true,
  };
};

const normalizeTemplates = (items) =>
  (Array.isArray(items) ? items : []).map((section) => ({
    key: section?.key || "",
    label: section?.label || "",
    enabled: Boolean(section?.enabled),
    classes: (Array.isArray(section?.classes) ? section.classes : []).map(normalizeClassRow),
  }));

export default function ClassTemplates() {
  const navigate = useNavigate();
  const [templates, setTemplates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const loadTemplates = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/school-admin/class-templates");
      setTemplates(normalizeTemplates(res.data?.data));
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
        const current = normalizeClassRow(classes[classIndex]);
        classes[classIndex] = { ...current, name: value };
        return { ...section, classes };
      })
    );
  };

  const updateClassEnabled = (sectionIndex, classIndex, enabled) => {
    setTemplates((prev) =>
      prev.map((section, idx) => {
        if (idx !== sectionIndex) return section;
        const classes = Array.isArray(section.classes) ? [...section.classes] : [];
        const current = normalizeClassRow(classes[classIndex]);
        classes[classIndex] = { ...current, enabled: Boolean(enabled) };
        return { ...section, classes };
      })
    );
  };

  const saveTemplates = async () => {
    const enabledSectionWithoutClass = templates.find((section) => {
      if (!section.enabled) return false;
      const checked = (section.classes || []).filter((item) => item?.enabled && String(item?.name || "").trim() !== "");
      return checked.length === 0;
    });
    if (enabledSectionWithoutClass) {
      return alert(`Select at least one checked class in ${enabledSectionWithoutClass.label || pretty(enabledSectionWithoutClass.key)}.`);
    }

    const payload = templates.map((section) => ({
      key: section.key,
      label: section.label,
      enabled: Boolean(section.enabled),
      classes: (Array.isArray(section.classes) ? section.classes : []).map((row) => ({
        name: String(row?.name || ""),
        enabled: Boolean(row?.enabled),
      })),
    }));

    setSaving(true);
    try {
      const res = await api.put("/api/school-admin/class-templates", {
        class_templates: payload,
      });
      const normalized = normalizeTemplates(Array.isArray(res.data?.data) ? res.data.data : payload);
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
                {(section.classes || []).map((row, classIndex) => {
                  const classRow = normalizeClassRow(row);
                  return (
                    <div key={`${section.key}-${classIndex}`} className="class-template-row">
                      <label className="class-template-check">
                        <input
                          type="checkbox"
                          checked={Boolean(classRow.enabled)}
                          onChange={(e) => updateClassEnabled(sectionIndex, classIndex, e.target.checked)}
                          disabled={!section.enabled}
                        />
                        <span>Show</span>
                      </label>
                      <input
                        type="text"
                        value={classRow.name || ""}
                        onChange={(e) => updateClassName(sectionIndex, classIndex, e.target.value)}
                        placeholder={`${section.label || pretty(section.key)} ${classIndex + 1}`}
                        disabled={!section.enabled}
                      />
                    </div>
                  );
                })}
              </div>
              <p className="class-template-hint">
                Checked classes are used for new academic sessions.
              </p>
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
