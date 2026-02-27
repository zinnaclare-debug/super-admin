import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../services/api";
import "./SchoolInformation.css";

const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;
const ALLOWED_TYPES = ["image/jpeg", "image/jpg", "image/png", "image/webp"];
const DEFAULT_EXAM_RECORD = {
  ca_maxes: [30, 0, 0, 0, 0],
  exam_max: 70,
  total_max: 100,
};

const toAbsoluteUrl = (u) => {
  if (!u) return "";
  if (/^(https?:\/\/|blob:|data:)/i.test(u)) return u;
  const base = (api.defaults.baseURL || "").replace(/\/$/, "");
  return `${base}${u.startsWith("/") ? "" : "/"}${u}`;
};

const normalizeExamRecord = (record) => {
  const raw = record || {};
  const caMaxes = Array.isArray(raw.ca_maxes) ? raw.ca_maxes : DEFAULT_EXAM_RECORD.ca_maxes;
  const normalizedCa = Array.from({ length: 5 }, (_, idx) => {
    const value = Number(caMaxes[idx] || 0);
    return Number.isFinite(value) ? Math.max(0, Math.min(100, Math.round(value))) : 0;
  });
  const caTotal = normalizedCa.reduce((sum, v) => sum + v, 0);
  const requestedExam = Number(raw.exam_max ?? 100 - caTotal);
  const examMax = Number.isFinite(requestedExam) ? Math.max(0, Math.min(100, Math.round(requestedExam))) : 0;

  return {
    ca_maxes: normalizedCa,
    exam_max: caTotal + examMax === 100 ? examMax : Math.max(0, 100 - caTotal),
    total_max: 100,
  };
};

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

export default function SchoolInformation() {
  const navigate = useNavigate();
  const { schoolId } = useParams();
  const [loading, setLoading] = useState(true);
  const [savingBranding, setSavingBranding] = useState(false);
  const [savingExamRecord, setSavingExamRecord] = useState(false);
  const [savingClassTemplates, setSavingClassTemplates] = useState(false);

  const [school, setSchool] = useState(null);
  const [branding, setBranding] = useState({
    school_location: "",
    contact_email: "",
    contact_phone: "",
    school_logo_url: null,
    head_of_school_name: "",
    head_signature_url: null,
  });

  const [headName, setHeadName] = useState("");
  const [schoolLocation, setSchoolLocation] = useState("");
  const [contactEmail, setContactEmail] = useState("");
  const [contactPhone, setContactPhone] = useState("");

  const [logoFile, setLogoFile] = useState(null);
  const [logoPreview, setLogoPreview] = useState(null);
  const [signatureFile, setSignatureFile] = useState(null);
  const [signaturePreview, setSignaturePreview] = useState(null);

  const logoInputRef = useRef(null);
  const signatureInputRef = useRef(null);

  const [examRecord, setExamRecord] = useState(DEFAULT_EXAM_RECORD);
  const [classTemplates, setClassTemplates] = useState([]);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get(`/api/super-admin/schools/${schoolId}/information`);
        const payload = res.data || {};
        const brandingData = payload.branding || {};
        setSchool(payload.school || null);
        setBranding({
          school_location: brandingData.school_location ?? "",
          contact_email: brandingData.contact_email ?? "",
          contact_phone: brandingData.contact_phone ?? "",
          school_logo_url: brandingData.school_logo_url ?? null,
          head_of_school_name: brandingData.head_of_school_name ?? "",
          head_signature_url: brandingData.head_signature_url ?? null,
        });
        setHeadName(brandingData.head_of_school_name ?? "");
        setSchoolLocation(brandingData.school_location ?? "");
        setContactEmail(brandingData.contact_email ?? "");
        setContactPhone(brandingData.contact_phone ?? "");
        setExamRecord(normalizeExamRecord(payload.exam_record));
        setClassTemplates(normalizeTemplates(payload.class_templates));
      } catch (err) {
        alert(err?.response?.data?.message || "Failed to load school information.");
      } finally {
        setLoading(false);
      }
    };

    load();
  }, [schoolId]);

  const examDraftCaTotal = useMemo(
    () => examRecord.ca_maxes.reduce((sum, val) => sum + Number(val || 0), 0),
    [examRecord]
  );

  const pickFile = (event, kind) => {
    const file = event.target.files?.[0] || null;
    if (file && !ALLOWED_TYPES.includes(file.type)) {
      alert("Image must be JPG, PNG, or WEBP.");
      event.target.value = "";
      return;
    }
    if (file && file.size > MAX_UPLOAD_BYTES) {
      alert("Image is too large. Max size is 2MB.");
      event.target.value = "";
      return;
    }

    if (kind === "logo") {
      setLogoFile(file);
      setLogoPreview(file ? URL.createObjectURL(file) : null);
      return;
    }

    setSignatureFile(file);
    setSignaturePreview(file ? URL.createObjectURL(file) : null);
  };

  const saveBranding = async () => {
    const normalizedName = (headName || "").trim();
    const normalizedLocation = (schoolLocation || "").trim();
    const normalizedContactEmail = (contactEmail || "").trim();
    const normalizedContactPhone = (contactPhone || "").trim();
    const hasNameChange = normalizedName !== (branding.head_of_school_name || "").trim();
    const hasLocationChange = normalizedLocation !== (branding.school_location || "").trim();
    const hasContactEmailChange = normalizedContactEmail !== (branding.contact_email || "").trim();
    const hasContactPhoneChange = normalizedContactPhone !== (branding.contact_phone || "").trim();
    const hasLogoChange = Boolean(logoFile);
    const hasSignatureChange = Boolean(signatureFile);

    if (
      !hasNameChange &&
      !hasLocationChange &&
      !hasContactEmailChange &&
      !hasContactPhoneChange &&
      !hasLogoChange &&
      !hasSignatureChange
    ) {
      return alert("No information changes to save.");
    }

    setSavingBranding(true);
    try {
      const fd = new FormData();
      fd.append("head_of_school_name", normalizedName);
      fd.append("school_location", normalizedLocation);
      fd.append("contact_email", normalizedContactEmail);
      fd.append("contact_phone", normalizedContactPhone);
      if (logoFile) fd.append("logo", logoFile);
      if (signatureFile) fd.append("head_signature", signatureFile);

      const res = await api.post(`/api/super-admin/schools/${schoolId}/information/branding`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      const data = res.data?.data || {};
      setBranding({
        school_location: data.school_location ?? normalizedLocation,
        contact_email: Object.prototype.hasOwnProperty.call(data, "contact_email")
          ? (data.contact_email ?? "")
          : normalizedContactEmail,
        contact_phone: Object.prototype.hasOwnProperty.call(data, "contact_phone")
          ? (data.contact_phone ?? "")
          : normalizedContactPhone,
        school_logo_url: data.school_logo_url ?? branding.school_logo_url,
        head_of_school_name: data.head_of_school_name ?? normalizedName,
        head_signature_url: data.head_signature_url ?? branding.head_signature_url,
      });
      setHeadName(data.head_of_school_name ?? normalizedName);
      setSchoolLocation(data.school_location ?? normalizedLocation);
      setContactEmail(
        Object.prototype.hasOwnProperty.call(data, "contact_email")
          ? (data.contact_email ?? "")
          : normalizedContactEmail
      );
      setContactPhone(
        Object.prototype.hasOwnProperty.call(data, "contact_phone")
          ? (data.contact_phone ?? "")
          : normalizedContactPhone
      );
      setLogoFile(null);
      setLogoPreview(null);
      setSignatureFile(null);
      setSignaturePreview(null);
      alert("School information updated.");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to save school information.");
    } finally {
      setSavingBranding(false);
    }
  };

  const updateExamDraftCa = (index, value) => {
    const parsed = Number(value);
    const sanitized = Number.isFinite(parsed) ? Math.max(0, Math.min(100, Math.round(parsed))) : 0;
    setExamRecord((prev) => {
      const caMaxes = [...prev.ca_maxes];
      caMaxes[index] = sanitized;
      return { ...prev, ca_maxes: caMaxes };
    });
  };

  const updateExamDraftExam = (value) => {
    const parsed = Number(value);
    const sanitized = Number.isFinite(parsed) ? Math.max(0, Math.min(100, Math.round(parsed))) : 0;
    setExamRecord((prev) => ({ ...prev, exam_max: sanitized }));
  };

  const saveExamRecord = async () => {
    const caMaxes = Array.from({ length: 5 }, (_, idx) => {
      const n = Number(examRecord.ca_maxes?.[idx] || 0);
      return Number.isFinite(n) ? Math.max(0, Math.min(100, Math.round(n))) : 0;
    });
    const examMaxRaw = Number(examRecord.exam_max || 0);
    const examMax = Number.isFinite(examMaxRaw) ? Math.max(0, Math.min(100, Math.round(examMaxRaw))) : 0;
    const caTotal = caMaxes.reduce((sum, val) => sum + val, 0);

    if (caTotal <= 0) {
      alert("At least one CA score must be greater than zero.");
      return;
    }

    if (caTotal + examMax !== 100) {
      alert("Total of CA maxima and exam maximum must be exactly 100.");
      return;
    }

    setSavingExamRecord(true);
    try {
      const payload = { ca_maxes: caMaxes, exam_max: examMax };
      const res = await api.put(`/api/super-admin/schools/${schoolId}/information/exam-record`, payload);
      setExamRecord(normalizeExamRecord(res.data?.data || payload));
      alert("Exam record updated.");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to save exam record.");
    } finally {
      setSavingExamRecord(false);
    }
  };

  const updateSection = (index, next) => {
    setClassTemplates((prev) => prev.map((item, idx) => (idx === index ? { ...item, ...next } : item)));
  };

  const updateClassName = (sectionIndex, classIndex, value) => {
    setClassTemplates((prev) =>
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
    setClassTemplates((prev) =>
      prev.map((section, idx) => {
        if (idx !== sectionIndex) return section;
        const classes = Array.isArray(section.classes) ? [...section.classes] : [];
        const current = normalizeClassRow(classes[classIndex]);
        classes[classIndex] = { ...current, enabled: Boolean(enabled) };
        return { ...section, classes };
      })
    );
  };

  const saveClassTemplates = async () => {
    const enabledSectionWithoutClass = classTemplates.find((section) => {
      if (!section.enabled) return false;
      const checked = (section.classes || []).filter((item) => item?.enabled && String(item?.name || "").trim() !== "");
      return checked.length === 0;
    });
    if (enabledSectionWithoutClass) {
      return alert(`Select at least one checked class in ${enabledSectionWithoutClass.label || enabledSectionWithoutClass.key}.`);
    }

    const payload = classTemplates.map((section) => ({
      key: section.key,
      label: section.label,
      enabled: Boolean(section.enabled),
      classes: (Array.isArray(section.classes) ? section.classes : []).map((row) => ({
        name: String(row?.name || ""),
        enabled: Boolean(row?.enabled),
      })),
    }));

    setSavingClassTemplates(true);
    try {
      const res = await api.put(`/api/super-admin/schools/${schoolId}/information/class-templates`, {
        class_templates: payload,
      });
      const normalized = normalizeTemplates(Array.isArray(res.data?.data) ? res.data.data : payload);
      setClassTemplates(normalized);
      alert("Class templates saved.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to save class templates.");
    } finally {
      setSavingClassTemplates(false);
    }
  };

  if (loading) {
    return <p>Loading school information...</p>;
  }

  return (
    <div className="sai-page">
      <div className="sai-top">
        <div>
          <h2>School Information</h2>
          <p>{school?.name ? `Manage ${school.name}` : "Manage school information"}</p>
        </div>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      <section className="sai-card">
        <h3>Branding</h3>
        <div className="sai-grid">
          <div className="sai-field">
            <label>School Logo</label>
            <div className="sai-upload">
              {(logoPreview || branding.school_logo_url) ? (
                <img
                  src={toAbsoluteUrl(logoPreview || branding.school_logo_url)}
                  alt="School logo preview"
                  className="sai-preview"
                />
              ) : (
                <span className="sai-empty">No logo uploaded</span>
              )}
              <input
                ref={logoInputRef}
                type="file"
                accept="image/*"
                onChange={(e) => pickFile(e, "logo")}
                style={{ display: "none" }}
              />
              <button type="button" onClick={() => logoInputRef.current?.click()}>
                Select Logo
              </button>
            </div>
          </div>

          <div className="sai-field">
            <label>Head of School Name</label>
            <input
              type="text"
              value={headName}
              onChange={(e) => setHeadName(e.target.value)}
              placeholder="Enter head of school name"
            />
          </div>

          <div className="sai-field">
            <label>School Location</label>
            <input
              type="text"
              value={schoolLocation}
              onChange={(e) => setSchoolLocation(e.target.value)}
              placeholder="Enter school location"
            />
          </div>

          <div className="sai-field">
            <label>Information Email</label>
            <input
              type="email"
              value={contactEmail}
              onChange={(e) => setContactEmail(e.target.value)}
              placeholder="contact@school.com"
            />
          </div>

          <div className="sai-field">
            <label>Mobile Number</label>
            <input
              type="tel"
              value={contactPhone}
              onChange={(e) => setContactPhone(e.target.value)}
              placeholder="+234 800 000 0000"
            />
          </div>

          <div className="sai-field">
            <label>Head Signature</label>
            <div className="sai-upload">
              {(signaturePreview || branding.head_signature_url) ? (
                <img
                  src={toAbsoluteUrl(signaturePreview || branding.head_signature_url)}
                  alt="Head signature preview"
                  className="sai-preview"
                />
              ) : (
                <span className="sai-empty">No signature uploaded</span>
              )}
              <input
                ref={signatureInputRef}
                type="file"
                accept="image/*"
                onChange={(e) => pickFile(e, "signature")}
                style={{ display: "none" }}
              />
              <button type="button" onClick={() => signatureInputRef.current?.click()}>
                Select Signature
              </button>
            </div>
          </div>
        </div>

        <div className="sai-actions">
          <button type="button" onClick={saveBranding} disabled={savingBranding}>
            {savingBranding ? "Saving..." : "Save Branding"}
          </button>
        </div>
      </section>

      <section className="sai-card">
        <h3>Exam Record</h3>
        <p className="sai-note">Configure CA1-CA5 and exam maximum. CA total plus exam must be 100.</p>
        <div className="sai-exam-grid">
          {[0, 1, 2, 3, 4].map((index) => (
            <div key={`ca-record-${index}`} className="sai-field">
              <label>CA {index + 1}</label>
              <input
                type="number"
                min="0"
                max="100"
                value={examRecord.ca_maxes[index]}
                onChange={(e) => updateExamDraftCa(index, e.target.value)}
              />
            </div>
          ))}
          <div className="sai-field">
            <label>Exam</label>
            <input
              type="number"
              min="0"
              max="100"
              value={examRecord.exam_max}
              onChange={(e) => updateExamDraftExam(e.target.value)}
            />
          </div>
        </div>
        <div className="sai-meta">
          <span>CA total: {examDraftCaTotal}</span>
          <span>Expected exam: {Math.max(0, 100 - examDraftCaTotal)}</span>
          <span>Total: {examDraftCaTotal + Number(examRecord.exam_max || 0)}</span>
        </div>
        <div className="sai-actions">
          <button type="button" onClick={saveExamRecord} disabled={savingExamRecord}>
            {savingExamRecord ? "Saving..." : "Save Exam Record"}
          </button>
        </div>
      </section>

      <section className="sai-card">
        <h3>Class Templates</h3>
        <p className="sai-note">Class templates are used for new sessions and synced into existing sessions.</p>
        <div className="sai-template-grid">
          {classTemplates.map((section, sectionIndex) => (
            <div className="sai-template-card" key={section.key || sectionIndex}>
              <div className="sai-template-head">
                <label className="sai-check">
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
                  placeholder={section.key}
                />
              </div>
              <div className="sai-template-fields">
                {(section.classes || []).map((row, classIndex) => {
                  const classRow = normalizeClassRow(row);
                  return (
                    <div key={`${section.key}-${classIndex}`} className="sai-template-row">
                      <label className="sai-check">
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
                        placeholder={`${section.label || section.key} ${classIndex + 1}`}
                        disabled={!section.enabled}
                      />
                    </div>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
        <div className="sai-actions">
          <button type="button" onClick={saveClassTemplates} disabled={savingClassTemplates}>
            {savingClassTemplates ? "Saving..." : "Save Class Templates"}
          </button>
        </div>
      </section>
    </div>
  );
}
