import { useEffect, useState } from "react";
import api from "../../services/api";
import "./Dashboard.css";

import heroArt from "../../assets/dashboard/features.svg";
import cbtArt from "../../assets/dashboard/staff.svg";
import resultsArt from "../../assets/dashboard/hero.svg";
import classArt from "../../assets/dashboard/modules.svg";
import brandingArt from "../../assets/dashboard/branding.svg";

const formatCount = (value) => {
  const n = Number(value || 0);
  return Number.isFinite(n) ? n.toLocaleString() : "0";
};

function SchoolDashboard() {
  const [stats, setStats] = useState({
    school_name: "",
    school_location: "",
    contact_email: "",
    contact_phone: "",
    students: 0,
    male_students: 0,
    female_students: 0,
    unspecified_students: 0,
    staff: 0,
    enabled_modules: 0,
  });
  const [loading, setLoading] = useState(true);
  const [schoolLocation, setSchoolLocation] = useState("");
  const [contactEmail, setContactEmail] = useState("");
  const [contactPhone, setContactPhone] = useState("");
  const [savingBranding, setSavingBranding] = useState(false);
  const [departmentTemplates, setDepartmentTemplates] = useState([]);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get("/api/school-admin/stats");
        setStats({
          school_name: res.data?.school_name ?? "",
          school_location: res.data?.school_location ?? "",
          contact_email: res.data?.contact_email ?? "",
          contact_phone: res.data?.contact_phone ?? "",
          students: res.data?.students ?? 0,
          male_students: res.data?.male_students ?? 0,
          female_students: res.data?.female_students ?? 0,
          unspecified_students: res.data?.unspecified_students ?? 0,
          staff: res.data?.staff ?? 0,
          enabled_modules: res.data?.enabled_modules ?? 0,
        });
        setSchoolLocation(res.data?.school_location ?? "");
        setContactEmail(res.data?.contact_email ?? "");
        setContactPhone(res.data?.contact_phone ?? "");

        const departmentsFromStats = Array.isArray(res.data?.department_templates) ? res.data.department_templates : [];
        setDepartmentTemplates(Array.from(new Set(departmentsFromStats.map((x) => String(x).trim()).filter(Boolean))));
      } catch {
        setStats({
          school_name: "",
          school_location: "",
          contact_email: "",
          contact_phone: "",
          students: 0,
          male_students: 0,
          female_students: 0,
          unspecified_students: 0,
          staff: 0,
          enabled_modules: 0,
        });
        setSchoolLocation("");
        setContactEmail("");
        setContactPhone("");
        setDepartmentTemplates([]);
      } finally {
        setLoading(false);
      }
    };

    load();
  }, []);

  const saveBranding = async () => {
    const normalizedLocation = (schoolLocation || "").trim();
    const normalizedContactEmail = (contactEmail || "").trim();
    const normalizedContactPhone = (contactPhone || "").trim();
    const existingLocation = (stats.school_location || "").trim();
    const existingContactEmail = (stats.contact_email || "").trim();
    const existingContactPhone = (stats.contact_phone || "").trim();
    const hasLocationChange = normalizedLocation !== existingLocation;
    const hasContactEmailChange = normalizedContactEmail !== existingContactEmail;
    const hasContactPhoneChange = normalizedContactPhone !== existingContactPhone;

    if (!hasLocationChange && !hasContactEmailChange && !hasContactPhoneChange) {
      return alert("No contact information changes to save.");
    }

    setSavingBranding(true);
    try {
      const fd = new FormData();
      fd.append("school_location", normalizedLocation);
      fd.append("contact_email", normalizedContactEmail);
      fd.append("contact_phone", normalizedContactPhone);

      const res = await api.post("/api/school-admin/branding", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      const data = res.data?.data || {};
      setStats((prev) => ({
        ...prev,
        school_name: data.school_name ?? prev.school_name,
        school_location: data.school_location ?? prev.school_location,
        contact_email: Object.prototype.hasOwnProperty.call(data, "contact_email")
          ? (data.contact_email ?? "")
          : prev.contact_email,
        contact_phone: Object.prototype.hasOwnProperty.call(data, "contact_phone")
          ? (data.contact_phone ?? "")
          : prev.contact_phone,
      }));
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
      alert("Contact information updated");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to update contact information");
    } finally {
      setSavingBranding(false);
    }
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

  const informationReady =
    Boolean((schoolLocation || stats.school_location || "").trim()) &&
    Boolean((contactEmail || stats.contact_email || "").trim()) &&
    Boolean((contactPhone || stats.contact_phone || "").trim());

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
            <span className="sd-tag">{informationReady ? "Contact Ready" : "Contact Incomplete"}</span>
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
            <h2>School Information</h2>
            <p>Update contact details here. Logo, head details, exam record, class templates, and department setup are managed by Super Admin.</p>
          </div>

          <div className="sd-field-grid">
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
              <label>Information Email</label>
              <input
                type="email"
                value={contactEmail}
                onChange={(e) => setContactEmail(e.target.value)}
                placeholder="contact@school.com"
              />
            </div>

            <div className="sd-field">
              <label>Mobile Number</label>
              <input
                type="tel"
                value={contactPhone}
                onChange={(e) => setContactPhone(e.target.value)}
                placeholder="+234 800 000 0000"
              />
            </div>

            <div className="sd-field">
              <label>Department Templates</label>
              <div className="sd-dept-box">
                <p className="sd-note" style={{ marginTop: 8 }}>
                  Department creation and edit are now controlled in Super Admin / School / Information / Class Templates.
                </p>
                <div className="sd-dept-tags">
                  {departmentTemplates.length === 0 ? (
                    <span className="sd-empty">No departments added yet.</span>
                  ) : (
                    departmentTemplates.map((name) => (
                      <span key={name} className="sd-dept-tag">
                        <span>{name}</span>
                      </span>
                    ))
                  )}
                </div>
              </div>
            </div>
          </div>

          <div className="sd-actions">
            <button onClick={saveBranding} disabled={savingBranding}>
              {savingBranding ? "Saving..." : "Save Information"}
            </button>
          </div>
        </div>

        <div className="sd-branding__art">
          <img src={brandingArt} alt="School information artwork" />
        </div>
      </section>
    </div>
  );
}

export default SchoolDashboard;
