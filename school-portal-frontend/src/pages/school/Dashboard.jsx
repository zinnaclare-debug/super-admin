import { useEffect, useMemo, useRef, useState } from "react";
import api from "../../services/api";
import "./Dashboard.css";

import heroArt from "../../assets/dashboard/hero.svg";
import brandingArt from "../../assets/dashboard/branding.svg";
import studentsArt from "../../assets/dashboard/students.svg";
import staffArt from "../../assets/dashboard/staff.svg";
import modulesArt from "../../assets/dashboard/modules.svg";
import featuresArt from "../../assets/dashboard/features.svg";

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

const toModuleLabel = (value) =>
  String(value || "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (x) => x.toUpperCase());

function SchoolDashboard() {
  const [stats, setStats] = useState({
    school_name: "",
    school_logo_url: null,
    head_of_school_name: "",
    head_signature_url: null,
    students: 0,
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

  const enabledFeatureNames = useMemo(() => {
    try {
      const raw = JSON.parse(localStorage.getItem("features") || "[]");
      if (!Array.isArray(raw)) return [];

      if (raw.length > 0 && typeof raw[0] === "object") {
        return raw
          .filter((f) => f && f.enabled)
          .map((f) => toModuleLabel(f.feature))
          .filter(Boolean);
      }

      return raw.map((f) => toModuleLabel(f)).filter(Boolean);
    } catch {
      return [];
    }
  }, [stats.enabled_modules]);

  const moduleHighlights = useMemo(() => {
    if (enabledFeatureNames.length > 0) return enabledFeatureNames.slice(0, 8);
    return ["CBT", "Results", "Admissions", "E-Library", "Attendance", "Virtual Class"];
  }, [enabledFeatureNames]);

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

  const metricCards = [
    {
      key: "students",
      title: "Students",
      subtitle: "Total learners",
      value: loading ? "..." : formatCount(stats.students),
      art: studentsArt,
      accent: "sd-metric--blue",
    },
    {
      key: "staff",
      title: "Staff",
      subtitle: "Teachers and staff",
      value: loading ? "..." : formatCount(stats.staff),
      art: staffArt,
      accent: "sd-metric--teal",
    },
    {
      key: "modules",
      title: "Modules",
      subtitle: "Enabled modules",
      value: loading ? "..." : formatCount(stats.enabled_modules),
      art: modulesArt,
      accent: "sd-metric--green",
    },
    {
      key: "features",
      title: "Features",
      subtitle: "Active tools",
      value: formatCount(enabledFeatureNames.length || stats.enabled_modules),
      art: featuresArt,
      accent: "sd-metric--orange",
    },
  ];

  return (
    <div className="school-dashboard">
      <section className="sd-card sd-hero">
        <div className="sd-hero__content">
          <p className="sd-kicker">School Admin Dashboard</p>
          <h1>{stats.school_name || "Your School"}</h1>
          <p className="sd-subtext">
            Manage students, staff, modules and school branding from one modern control center.
          </p>

          <div className="sd-tags">
            <span className="sd-tag">{brandingReady ? "Branding Ready" : "Branding Incomplete"}</span>
            <span className="sd-tag sd-tag--soft">{formatCount(stats.students)} Students</span>
            <span className="sd-tag sd-tag--soft">{formatCount(stats.staff)} Staff</span>
          </div>
        </div>

        <div className="sd-hero__visual">
          <img src={heroArt} alt="Education dashboard artwork" />
        </div>
      </section>

      <section className="sd-metrics">
        {metricCards.map((item) => (
          <article key={item.key} className={`sd-card sd-metric ${item.accent}`}>
            <div>
              <p className="sd-metric__title">{item.title}</p>
              <h3>{item.value}</h3>
              <p className="sd-metric__sub">{item.subtitle}</p>
            </div>
            <img src={item.art} alt={`${item.title} illustration`} />
          </article>
        ))}
      </section>

      <section className="sd-card sd-modules">
        <div className="sd-section-head">
          <h2>Modules and Features</h2>
          <p>Quick view of tools enabled for your school.</p>
        </div>

        <div className="sd-module-scroll">
          {moduleHighlights.map((feature, idx) => (
            <div key={`${feature}-${idx}`} className="sd-module-item">
              <span>{feature}</span>
            </div>
          ))}
        </div>
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
