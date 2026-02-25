import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";
import "./Dashboard.css";

import heroArt from "../../assets/dashboard/features.svg";
import cbtArt from "../../assets/dashboard/staff.svg";
import resultsArt from "../../assets/dashboard/hero.svg";
import classArt from "../../assets/dashboard/modules.svg";
import brandingArt from "../../assets/dashboard/branding.svg";

const toAbsoluteUrl = (u) => {
  if (!u) return "";
  if (/^(https?:\/\/|blob:|data:)/i.test(u)) return u;
  const base = (api.defaults.baseURL || "").replace(/\/$/, "");
  return `${base}${u.startsWith("/") ? "" : "/"}${u}`;
};

const formatCount = (value) => {
  const n = Number(value || 0);
  return Number.isFinite(n) ? n.toLocaleString() : "0";
};

const MAX_BRANDING_UPLOAD_BYTES = 2 * 1024 * 1024;
const ALLOWED_BRANDING_TYPES = ["image/jpeg", "image/jpg", "image/png", "image/webp"];
const DEFAULT_EXAM_RECORD = {
  ca_maxes: [30, 0, 0, 0, 0],
  exam_max: 70,
  total_max: 100,
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

function SchoolDashboard() {
  const navigate = useNavigate();
  const [stats, setStats] = useState({
    school_name: "",
    school_location: "",
    school_logo_url: null,
    head_of_school_name: "",
    head_signature_url: null,
    students: 0,
    male_students: 0,
    female_students: 0,
    unspecified_students: 0,
    staff: 0,
    enabled_modules: 0,
  });
  const [loading, setLoading] = useState(true);
  const [logoFile, setLogoFile] = useState(null);
  const [logoPreview, setLogoPreview] = useState(null);
  const [headSignatureFile, setHeadSignatureFile] = useState(null);
  const [headSignaturePreview, setHeadSignaturePreview] = useState(null);
  const [headName, setHeadName] = useState("");
  const [schoolLocation, setSchoolLocation] = useState("");
  const [savingBranding, setSavingBranding] = useState(false);
  const [examRecord, setExamRecord] = useState(DEFAULT_EXAM_RECORD);
  const [examDraft, setExamDraft] = useState(DEFAULT_EXAM_RECORD);
  const [showExamRecordModal, setShowExamRecordModal] = useState(false);
  const [savingExamRecord, setSavingExamRecord] = useState(false);
  const [departmentTemplates, setDepartmentTemplates] = useState([]);
  const [newDepartmentName, setNewDepartmentName] = useState("");
  const [savingDepartmentTemplate, setSavingDepartmentTemplate] = useState(false);
  const logoInputRef = useRef(null);
  const signatureInputRef = useRef(null);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const [statsRes, examRes, departmentRes] = await Promise.allSettled([
          api.get("/api/school-admin/stats"),
          api.get("/api/school-admin/exam-record"),
          api.get("/api/school-admin/department-templates"),
        ]);

        if (statsRes.status !== "fulfilled") {
          throw statsRes.reason;
        }

        const res = statsRes.value;
        setStats({
          school_name: res.data?.school_name ?? "",
          school_location: res.data?.school_location ?? "",
          school_logo_url: res.data?.school_logo_url ?? null,
          head_of_school_name: res.data?.head_of_school_name ?? "",
          head_signature_url: res.data?.head_signature_url ?? null,
          students: res.data?.students ?? 0,
          male_students: res.data?.male_students ?? 0,
          female_students: res.data?.female_students ?? 0,
          unspecified_students: res.data?.unspecified_students ?? 0,
          staff: res.data?.staff ?? 0,
          enabled_modules: res.data?.enabled_modules ?? 0,
        });
        setHeadName(res.data?.head_of_school_name ?? "");
        setSchoolLocation(res.data?.school_location ?? "");

        const examFromStats = res.data?.assessment_schema || null;
        const examFromEndpoint =
          examRes.status === "fulfilled" ? examRes.value?.data?.data : null;
        const normalizedExam = normalizeExamRecord(examFromEndpoint || examFromStats);
        setExamRecord(normalizedExam);
        setExamDraft(normalizedExam);

        const departmentsFromStats = Array.isArray(res.data?.department_templates)
          ? res.data.department_templates
          : [];
        const departmentsFromEndpoint =
          departmentRes.status === "fulfilled" && Array.isArray(departmentRes.value?.data?.data)
            ? departmentRes.value.data.data
            : [];
        setDepartmentTemplates(
          Array.from(new Set([...departmentsFromStats, ...departmentsFromEndpoint].map((x) => String(x).trim()).filter(Boolean)))
        );
      } catch {
        setStats((prev) => ({
          ...prev,
          school_name: "",
          school_location: "",
          school_logo_url: null,
          head_of_school_name: "",
          head_signature_url: null,
          students: 0,
          male_students: 0,
          female_students: 0,
          unspecified_students: 0,
          staff: 0,
          enabled_modules: 0,
        }));
        setHeadName("");
        setSchoolLocation("");
        setExamRecord(DEFAULT_EXAM_RECORD);
        setExamDraft(DEFAULT_EXAM_RECORD);
        setDepartmentTemplates([]);
      } finally {
        setLoading(false);
      }
    };

    load();
  }, []);

  const brandingReady =
    Boolean(logoPreview || stats.school_logo_url) &&
    Boolean(headSignaturePreview || stats.head_signature_url) &&
    Boolean((headName || stats.head_of_school_name || "").trim());

  const examDraftCaTotal = useMemo(
    () => examDraft.ca_maxes.reduce((sum, val) => sum + Number(val || 0), 0),
    [examDraft]
  );
  const examRecordSummary = useMemo(() => {
    const caParts = examRecord.ca_maxes
      .map((val, idx) => ({ val: Number(val || 0), idx }))
      .filter((item) => item.val > 0)
      .map((item) => `CA${item.idx + 1} (${item.val})`);

    return `${caParts.length ? caParts.join(" | ") : "No CA configured"} | EXAM (${examRecord.exam_max})`;
  }, [examRecord]);

  const updateExamDraftCa = (index, value) => {
    const parsed = Number(value);
    const sanitized = Number.isFinite(parsed) ? Math.max(0, Math.min(100, Math.round(parsed))) : 0;
    setExamDraft((prev) => {
      const caMaxes = [...prev.ca_maxes];
      caMaxes[index] = sanitized;
      return { ...prev, ca_maxes: caMaxes };
    });
  };

  const updateExamDraftExam = (value) => {
    const parsed = Number(value);
    const sanitized = Number.isFinite(parsed) ? Math.max(0, Math.min(100, Math.round(parsed))) : 0;
    setExamDraft((prev) => ({ ...prev, exam_max: sanitized }));
  };

  const openExamRecordModal = () => {
    setExamDraft(examRecord);
    setShowExamRecordModal(true);
  };

  const closeExamRecordModal = () => {
    if (savingExamRecord) return;
    setShowExamRecordModal(false);
  };

  const saveExamRecord = async () => {
    const caMaxes = Array.from({ length: 5 }, (_, idx) => {
      const n = Number(examDraft.ca_maxes?.[idx] || 0);
      return Number.isFinite(n) ? Math.max(0, Math.min(100, Math.round(n))) : 0;
    });
    const examMaxRaw = Number(examDraft.exam_max || 0);
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
      const res = await api.put("/api/school-admin/exam-record", payload);
      const saved = normalizeExamRecord(res.data?.data || payload);
      setExamRecord(saved);
      setExamDraft(saved);
      setShowExamRecordModal(false);
      alert("Exam record updated");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to save exam record");
    } finally {
      setSavingExamRecord(false);
    }
  };

  const saveBranding = async () => {
    const normalizedName = (headName || "").trim();
    const normalizedLocation = (schoolLocation || "").trim();
    const existingName = (stats.head_of_school_name || "").trim();
    const existingLocation = (stats.school_location || "").trim();
    const hasNameChange = normalizedName !== existingName;
    const hasLocationChange = normalizedLocation !== existingLocation;
    const hasLogo = !!logoFile;
    const hasSignature = !!headSignatureFile;

    if (!hasNameChange && !hasLocationChange && !hasLogo && !hasSignature) {
      return alert("No branding changes to save.");
    }

    setSavingBranding(true);
    try {
      const fd = new FormData();
      fd.append("head_of_school_name", normalizedName);
      fd.append("school_location", normalizedLocation);
      if (logoFile) fd.append("logo", logoFile);
      if (headSignatureFile) fd.append("head_signature", headSignatureFile);

      const res = await api.post("/api/school-admin/branding", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      const data = res.data?.data || {};
      setStats((prev) => ({
        ...prev,
        school_name: data.school_name ?? prev.school_name,
        school_location: data.school_location ?? prev.school_location,
        school_logo_url: data.school_logo_url ?? prev.school_logo_url,
        head_of_school_name: data.head_of_school_name ?? prev.head_of_school_name,
        head_signature_url: data.head_signature_url ?? prev.head_signature_url,
      }));
      setHeadName(data.head_of_school_name ?? normalizedName);
      setSchoolLocation(data.school_location ?? normalizedLocation);
      setLogoFile(null);
      setLogoPreview(null);
      setHeadSignatureFile(null);
      setHeadSignaturePreview(null);
      alert("Branding updated");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to update branding");
    } finally {
      setSavingBranding(false);
    }
  };

  const addDepartmentTemplate = async () => {
    const name = (newDepartmentName || "").trim();
    if (!name) {
      alert("Enter a department name.");
      return;
    }

    setSavingDepartmentTemplate(true);
    try {
      const res = await api.post("/api/school-admin/department-templates", { name });
      const items = Array.isArray(res.data?.data) ? res.data.data : [];
      setDepartmentTemplates(items);
      setNewDepartmentName("");
      alert("Department saved and applied to all sessions/classes.");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to add department");
    } finally {
      setSavingDepartmentTemplate(false);
    }
  };

  const onPickLogo = (e) => {
    const file = e.target.files?.[0] || null;
    if (file && !ALLOWED_BRANDING_TYPES.includes(file.type)) {
      alert("Logo must be JPG, PNG, or WEBP.");
      e.target.value = "";
      setLogoFile(null);
      setLogoPreview(null);
      return;
    }
    if (file && file.size > MAX_BRANDING_UPLOAD_BYTES) {
      alert("Logo is too large. Max size is 2MB.");
      e.target.value = "";
      setLogoFile(null);
      setLogoPreview(null);
      return;
    }
    setLogoFile(file);
    setLogoPreview(file ? URL.createObjectURL(file) : null);
  };

  const onPickSignature = (e) => {
    const file = e.target.files?.[0] || null;
    if (file && !ALLOWED_BRANDING_TYPES.includes(file.type)) {
      alert("Signature must be JPG, PNG, or WEBP.");
      e.target.value = "";
      setHeadSignatureFile(null);
      setHeadSignaturePreview(null);
      return;
    }
    if (file && file.size > MAX_BRANDING_UPLOAD_BYTES) {
      alert("Signature is too large. Max size is 2MB.");
      e.target.value = "";
      setHeadSignatureFile(null);
      setHeadSignaturePreview(null);
      return;
    }
    setHeadSignatureFile(file);
    setHeadSignaturePreview(file ? URL.createObjectURL(file) : null);
  };

  const featureCards = [
    {
      key: "cbt",
      title: "CBT",
      description: "Computer-based testing with secure timed exams.",
      art: cbtArt,
    },
    {
      key: "results",
      title: "Exam Results",
      description: "Students rejoicing over exam results and performance growth.",
      art: resultsArt,
    },
    {
      key: "class",
      title: "Learners in Class",
      description: "Learners in class with active participation and engagement.",
      art: classArt,
    },
  ];

  const populationStats = [
    { key: "male", label: "Total Male Students", value: stats.male_students },
    { key: "female", label: "Total Female Students", value: stats.female_students },
    { key: "students", label: "Total Students", value: stats.students },
    { key: "staff", label: "Total Staff", value: stats.staff },
  ];

  return (
    <div className="school-dashboard">
      <section className="sd-card sd-hero">
        <div className="sd-hero__content">
          <p className="sd-kicker">School Admin Dashboard</p>
          <h1>{stats.school_name || "Your School"}</h1>
          <p className="sd-subtext">
            Modern school operations dashboard for academics, staff, and student performance.
          </p>

          <div className="sd-tags">
            <span className="sd-tag">{brandingReady ? "Branding Ready" : "Branding Incomplete"}</span>
            <span className="sd-tag sd-tag--soft">{formatCount(stats.enabled_modules)} Enabled Modules</span>
          </div>
        </div>

        <div className="sd-hero__visual">
          <img src={heroArt} alt="School features illustration" />
        </div>
      </section>

      <section className="sd-card sd-main-features">
        <div className="sd-section-head">
          <h2>Main Features</h2>
          <p>Three core tools for academic workflow.</p>
        </div>

        <div className="sd-features-grid">
          {featureCards.map((item) => (
            <article key={item.key} className="sd-feature-card">
              <img src={item.art} alt={`${item.title} illustration`} />
              <div>
                <h3>{item.title}</h3>
                <p>{item.description}</p>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="sd-card sd-population">
        <div className="sd-section-head">
          <h2>Population Details</h2>
          <p>Live count by gender, total students, and staff.</p>
        </div>

        <div className="sd-population-grid">
          {populationStats.map((item) => (
            <div key={item.key} className="sd-population-item">
              <p>{item.label}</p>
              <h3>{loading ? "..." : formatCount(item.value)}</h3>
            </div>
          ))}
        </div>

        {!loading && Number(stats.unspecified_students || 0) > 0 && (
          <p className="sd-note">
            {formatCount(stats.unspecified_students)} student record(s) do not have gender set yet.
          </p>
        )}
      </section>

      <section className="sd-card sd-branding">
        <div className="sd-branding__form">
          <div className="sd-section-head">
            <h2>School Branding</h2>
            <p>Update logo, head name and signature for reports and official pages.</p>
          </div>

          <div className="sd-field-grid">
            <div className="sd-field">
              <label>School Logo</label>
              <div className="sd-upload-box">
                {(logoPreview || stats.school_logo_url) ? (
                  <img
                    src={toAbsoluteUrl(logoPreview || stats.school_logo_url)}
                    alt="School logo preview"
                    className="sd-preview"
                  />
                ) : (
                  <span className="sd-empty">No logo uploaded</span>
                )}
                <input
                  ref={logoInputRef}
                  type="file"
                  accept="image/*"
                  onChange={onPickLogo}
                  style={{ display: "none" }}
                />
                <button type="button" onClick={() => logoInputRef.current?.click()}>
                  Select Logo
                </button>
              </div>
            </div>

            <div className="sd-field">
              <label>Head of School Name</label>
              <input
                type="text"
                value={headName}
                onChange={(e) => setHeadName(e.target.value)}
                placeholder="Enter head of school name"
              />
            </div>

            <div className="sd-field">
              <label>School Location</label>
              <input
                type="text"
                value={schoolLocation}
                onChange={(e) => setSchoolLocation(e.target.value)}
                placeholder="Enter school address/location"
              />
            </div>

            <div className="sd-field">
              <label>Head Signature</label>
              <div className="sd-upload-box">
                {(headSignaturePreview || stats.head_signature_url) ? (
                  <img
                    src={toAbsoluteUrl(headSignaturePreview || stats.head_signature_url)}
                    alt="Head signature preview"
                    className="sd-preview"
                  />
                ) : (
                  <span className="sd-empty">No signature uploaded</span>
                )}
                <input
                  ref={signatureInputRef}
                  type="file"
                  accept="image/*"
                  onChange={onPickSignature}
                  style={{ display: "none" }}
                />
                <button type="button" onClick={() => signatureInputRef.current?.click()}>
                  Select Signature
                </button>
              </div>
            </div>

            <div className="sd-field">
              <label>Department Templates</label>
              <div className="sd-dept-box">
                <div className="sd-dept-add">
                  <input
                    type="text"
                    value={newDepartmentName}
                    onChange={(e) => setNewDepartmentName(e.target.value)}
                    placeholder="e.g. Science, Arts, Commercial"
                  />
                  <button type="button" onClick={addDepartmentTemplate} disabled={savingDepartmentTemplate}>
                    {savingDepartmentTemplate ? "Saving..." : "Add Department"}
                  </button>
                </div>
                <p className="sd-note" style={{ marginTop: 8 }}>
                  New department here is auto-applied to all levels and all class terms.
                </p>
                <div className="sd-dept-tags">
                  {departmentTemplates.length === 0 ? (
                    <span className="sd-empty">No departments added yet.</span>
                  ) : (
                    departmentTemplates.map((name) => (
                      <span key={name} className="sd-dept-tag">{name}</span>
                    ))
                  )}
                </div>
              </div>
            </div>
          </div>

          <div className="sd-actions">
            <button onClick={saveBranding} disabled={savingBranding}>
              {savingBranding ? "Saving..." : "Save Branding"}
            </button>
            <button
              type="button"
              className="sd-actions__alt"
              onClick={() => navigate("/school/admin/class-templates")}
            >
              Create Class
            </button>
            <button type="button" className="sd-actions__alt" onClick={openExamRecordModal}>
              Create Exam Record
            </button>
          </div>
          <p className="sd-note" style={{ marginTop: 8 }}>
            Current exam record: {examRecordSummary}
          </p>
        </div>

        <div className="sd-branding__art">
          <img src={brandingArt} alt="Branding artwork" />
        </div>
      </section>

      {showExamRecordModal && (
        <div className="sd-modal-backdrop" role="dialog" aria-modal="true">
          <div className="sd-modal-card">
            <div className="sd-modal-head">
              <h3>Create Exam Record</h3>
              <button type="button" className="sd-modal-close" onClick={closeExamRecordModal}>
                Close
              </button>
            </div>

            <p className="sd-modal-help">
              Configure CA1 to CA5 and exam maximum. CA total plus exam must equal 100.
            </p>
            <p className="sd-modal-help" style={{ marginTop: 4 }}>
              Grade scale: A (70-100), B (60-69), C (50-59), D (40-49), E (30-39), F (0-29).
            </p>

            <div className="sd-exam-grid">
              {[0, 1, 2, 3, 4].map((index) => (
                <div key={`ca-record-${index}`} className="sd-field">
                  <label>CA {index + 1}</label>
                  <input
                    type="number"
                    min="0"
                    max="100"
                    value={examDraft.ca_maxes[index]}
                    onChange={(e) => updateExamDraftCa(index, e.target.value)}
                  />
                </div>
              ))}
              <div className="sd-field">
                <label>Exam</label>
                <input
                  type="number"
                  min="0"
                  max="100"
                  value={examDraft.exam_max}
                  onChange={(e) => updateExamDraftExam(e.target.value)}
                />
              </div>
            </div>

            <div className="sd-exam-meta">
              <span>CA total: {examDraftCaTotal}</span>
              <span>Expected exam: {Math.max(0, 100 - examDraftCaTotal)}</span>
              <span>Total: {examDraftCaTotal + Number(examDraft.exam_max || 0)}</span>
            </div>

            <div className="sd-actions">
              <button type="button" onClick={saveExamRecord} disabled={savingExamRecord}>
                {savingExamRecord ? "Saving..." : "Save Exam Record"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default SchoolDashboard;
