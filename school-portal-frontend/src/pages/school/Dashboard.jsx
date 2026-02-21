import { useEffect, useRef, useState } from "react";
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

function SchoolDashboard() {
  const [stats, setStats] = useState({
    school_name: "",
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
  const [savingBranding, setSavingBranding] = useState(false);
  const logoInputRef = useRef(null);
  const signatureInputRef = useRef(null);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get("/api/school-admin/stats");
        setStats({
          school_name: res.data?.school_name ?? "",
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
      } catch {
        setStats((prev) => ({
          ...prev,
          school_name: "",
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

  const saveBranding = async () => {
    const normalizedName = (headName || "").trim();
    const existingName = (stats.head_of_school_name || "").trim();
    const hasNameChange = normalizedName !== existingName;
    const hasLogo = !!logoFile;
    const hasSignature = !!headSignatureFile;

    if (!hasNameChange && !hasLogo && !hasSignature) {
      return alert("No branding changes to save.");
    }

    setSavingBranding(true);
    try {
      const fd = new FormData();
      fd.append("head_of_school_name", normalizedName);
      if (logoFile) fd.append("logo", logoFile);
      if (headSignatureFile) fd.append("head_signature", headSignatureFile);

      const res = await api.post("/api/school-admin/branding", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      const data = res.data?.data || {};
      setStats((prev) => ({
        ...prev,
        school_name: data.school_name ?? prev.school_name,
        school_logo_url: data.school_logo_url ?? prev.school_logo_url,
        head_of_school_name: data.head_of_school_name ?? prev.head_of_school_name,
        head_signature_url: data.head_signature_url ?? prev.head_signature_url,
      }));
      setHeadName(data.head_of_school_name ?? normalizedName);
      setLogoFile(null);
      setLogoPreview(null);
      setHeadSignatureFile(null);
      setHeadSignaturePreview(null);
      alert("Branding updated");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to update branding");
    } finally {
      setSavingBranding(false);
    }
  };

  const onPickLogo = (e) => {
    const file = e.target.files?.[0] || null;
    setLogoFile(file);
    setLogoPreview(file ? URL.createObjectURL(file) : null);
  };

  const onPickSignature = (e) => {
    const file = e.target.files?.[0] || null;
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
          </div>

          <div className="sd-actions">
            <button onClick={saveBranding} disabled={savingBranding}>
              {savingBranding ? "Saving..." : "Save Branding"}
            </button>
          </div>
        </div>

        <div className="sd-branding__art">
          <img src={brandingArt} alt="Branding artwork" />
        </div>
      </section>
    </div>
  );
}

export default SchoolDashboard;
