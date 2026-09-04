import { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import api from "../../services/api";
import { getStoredFeatures } from "../../utils/authStorage";
import "./Dashboard.css";

import heroArt from "../../assets/dashboard/features.svg";
import cbtArt from "../../assets/dashboard/staff.svg";
import resultsArt from "../../assets/dashboard/hero.svg";
import classArt from "../../assets/dashboard/modules.svg";
import brandingArt from "../../assets/dashboard/branding.svg";
import SchoolSubscriptionStatus from "./SchoolSubscriptionStatus";

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
    show_result_position: true,
    paystack_subaccount_code: "",
    school_logo_url: "",
    head_of_school_name: "",
    head_signature_url: "",
    students: 0,
    male_students: 0,
    female_students: 0,
    unspecified_students: 0,
    profile_completeness_notices: [],
    staff: 0,
    enabled_modules: 0,
  });
  const [loading, setLoading] = useState(true);
  const [headOfSchoolName, setHeadOfSchoolName] = useState("");
  const [schoolLocation, setSchoolLocation] = useState("");
  const [contactEmail, setContactEmail] = useState("");
  const [contactPhone, setContactPhone] = useState("");
  const [schoolMotto, setSchoolMotto] = useState("");
  const [showResultPosition, setShowResultPosition] = useState(true);
  const [paystackSubaccountCode, setPaystackSubaccountCode] = useState("");
  const [logoFile, setLogoFile] = useState(null);
  const [signatureFile, setSignatureFile] = useState(null);
  const [savingBranding, setSavingBranding] = useState(false);
  const logoInputRef = useRef(null);
  const signatureInputRef = useRef(null);
  const [enabledFeatures, setEnabledFeatures] = useState(() => getStoredFeatures());

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const [res, featuresRes] = await Promise.all([
          api.get("/api/school-admin/stats"),
          api.get("/api/schools/features").catch(() => null),
        ]);
        setStats({
          school_name: res.data?.school_name ?? "",
          school_location: res.data?.school_location ?? "",
          contact_email: res.data?.contact_email ?? "",
          contact_phone: res.data?.contact_phone ?? "",
          school_motto: res.data?.school_motto ?? "",
          show_result_position: Boolean(res.data?.show_result_position ?? true),
          paystack_subaccount_code: res.data?.paystack_subaccount_code ?? "",
          school_logo_url: res.data?.school_logo_url ?? "",
          head_of_school_name: res.data?.head_of_school_name ?? "",
          head_signature_url: res.data?.head_signature_url ?? "",
          students: res.data?.students ?? 0,
          male_students: res.data?.male_students ?? 0,
          female_students: res.data?.female_students ?? 0,
          unspecified_students: res.data?.unspecified_students ?? 0,
          profile_completeness_notices: Array.isArray(res.data?.profile_completeness_notices)
            ? res.data.profile_completeness_notices
            : [],
          staff: res.data?.staff ?? 0,
          enabled_modules: res.data?.enabled_modules ?? 0,
        });
        setHeadOfSchoolName(res.data?.head_of_school_name ?? "");
        setSchoolLocation(res.data?.school_location ?? "");
        setContactEmail(res.data?.contact_email ?? "");
        setContactPhone(res.data?.contact_phone ?? "");
        setSchoolMotto(res.data?.school_motto ?? "");
        setShowResultPosition(Boolean(res.data?.show_result_position ?? true));
        setPaystackSubaccountCode(res.data?.paystack_subaccount_code ?? "");

        setEnabledFeatures(Array.isArray(featuresRes?.data?.data) ? featuresRes.data.data : []);
      } catch {
        setStats({
          school_name: "",
          school_location: "",
          contact_email: "",
          contact_phone: "",
          school_motto: "",
          show_result_position: true,
          paystack_subaccount_code: "",
    school_logo_url: "",
    head_of_school_name: "",
    head_signature_url: "",
          students: 0,
          male_students: 0,
          female_students: 0,
          unspecified_students: 0,
          staff: 0,
          enabled_modules: 0,
        });
        setHeadOfSchoolName("");
        setSchoolLocation("");
        setContactEmail("");
        setContactPhone("");
        setSchoolMotto("");
        setShowResultPosition(true);
        setPaystackSubaccountCode("");
        setEnabledFeatures([]);
      } finally {
        setLoading(false);
      }
    };

    load();
  }, []);

  const saveBranding = async () => {
    const normalizedHeadName = (headOfSchoolName || "").trim();
    const normalizedLocation = (schoolLocation || "").trim();
    const normalizedContactEmail = (contactEmail || "").trim();
    const normalizedContactPhone = (contactPhone || "").trim();
    const normalizedSchoolMotto = (schoolMotto || "").trim();
    const normalizedSubaccountCode = (paystackSubaccountCode || "").trim();
    const existingHeadName = (stats.head_of_school_name || "").trim();
    const existingLocation = (stats.school_location || "").trim();
    const existingContactEmail = (stats.contact_email || "").trim();
    const existingContactPhone = (stats.contact_phone || "").trim();
    const existingSchoolMotto = (stats.school_motto || "").trim();
    const existingShowResultPosition = Boolean(stats.show_result_position ?? true);
    const existingSubaccountCode = (stats.paystack_subaccount_code || "").trim();
    const hasHeadNameChange = normalizedHeadName !== existingHeadName;
    const hasLogoChange = Boolean(logoFile);
    const hasSignatureChange = Boolean(signatureFile);
    const hasLocationChange = normalizedLocation !== existingLocation;
    const hasContactEmailChange = normalizedContactEmail !== existingContactEmail;
    const hasContactPhoneChange = normalizedContactPhone !== existingContactPhone;
    const hasSchoolMottoChange = normalizedSchoolMotto !== existingSchoolMotto;
    const hasShowResultPositionChange = Boolean(showResultPosition) !== existingShowResultPosition;
    const hasSubaccountCodeChange = normalizedSubaccountCode !== existingSubaccountCode;

    if (!hasHeadNameChange && !hasLogoChange && !hasSignatureChange && !hasLocationChange && !hasContactEmailChange && !hasContactPhoneChange && !hasSchoolMottoChange && !hasShowResultPositionChange && !hasSubaccountCodeChange) {
      return alert("No school information changes to save.");
    }

    setSavingBranding(true);
    try {
      const fd = new FormData();
      fd.append("head_of_school_name", normalizedHeadName);
      fd.append("school_location", normalizedLocation);
      fd.append("contact_email", normalizedContactEmail);
      fd.append("contact_phone", normalizedContactPhone);
      fd.append("school_motto", normalizedSchoolMotto);
      fd.append("show_result_position", showResultPosition ? "1" : "0");
      fd.append("paystack_subaccount_code", normalizedSubaccountCode);
      if (logoFile) fd.append("logo", logoFile);
      if (signatureFile) fd.append("head_signature", signatureFile);

      const res = await api.post("/api/school-admin/branding", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      const data = res.data?.data || {};
      setStats((prev) => ({
        ...prev,
        school_name: data.school_name ?? prev.school_name,
        head_of_school_name: data.head_of_school_name ?? prev.head_of_school_name,
        school_logo_url: data.school_logo_url ?? prev.school_logo_url,
        head_signature_url: data.head_signature_url ?? prev.head_signature_url,
        school_location: data.school_location ?? prev.school_location,
        contact_email: Object.prototype.hasOwnProperty.call(data, "contact_email")
          ? (data.contact_email ?? "")
          : prev.contact_email,
          contact_phone: Object.prototype.hasOwnProperty.call(data, "contact_phone")
            ? (data.contact_phone ?? "")
            : prev.contact_phone,
          school_motto: Object.prototype.hasOwnProperty.call(data, "school_motto")
            ? (data.school_motto ?? "")
            : prev.school_motto,
          show_result_position: Object.prototype.hasOwnProperty.call(data, "show_result_position")
            ? Boolean(data.show_result_position)
            : prev.show_result_position,
          paystack_subaccount_code: Object.prototype.hasOwnProperty.call(data, "paystack_subaccount_code")
            ? (data.paystack_subaccount_code ?? "")
            : prev.paystack_subaccount_code,
      }));
      setHeadOfSchoolName(data.head_of_school_name ?? normalizedHeadName);
      setLogoFile(null);
      setSignatureFile(null);
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
      setSchoolMotto(
        Object.prototype.hasOwnProperty.call(data, "school_motto")
          ? (data.school_motto ?? "")
          : normalizedSchoolMotto
      );
      setShowResultPosition(
        Object.prototype.hasOwnProperty.call(data, "show_result_position")
          ? Boolean(data.show_result_position)
          : Boolean(showResultPosition)
      );
      setPaystackSubaccountCode(
        Object.prototype.hasOwnProperty.call(data, "paystack_subaccount_code")
          ? (data.paystack_subaccount_code ?? "")
          : normalizedSubaccountCode
      );
      alert("School information updated");
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

  const websiteEnabled = enabledFeatures.some((item) => String(item?.feature || "").toLowerCase() === "website" && Boolean(item?.enabled));
  const entranceExamEnabled = enabledFeatures.some((item) => String(item?.feature || "").toLowerCase() === "entrance_exam" && Boolean(item?.enabled));

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
            <SchoolSubscriptionStatus />
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

        {!loading && (stats.profile_completeness_notices || []).map((notice) => {
          const count = Number(notice?.count || 0);
          if (count <= 0) return null;

          const isSingleProfile = Number(notice?.user_id || 0) > 0;
          const target = isSingleProfile
            ? `/school/admin/users/${notice.role || "student"}/active?user_id=${notice.user_id}`
            : `/school/admin/users/${notice.role || "student"}/active?missing_field=${encodeURIComponent(notice.field || "")}`;

          return (
            <p key={notice.key || notice.field} className="sd-note">
              {formatCount(count)} {notice.role || "user"} record(s) do not have {notice.field || "required profile information"} set yet.{" "}
              <Link to={target}>{isSingleProfile ? "Open profile" : "Review profiles"}</Link>
            </p>
          );
        })}
      </section>      {websiteEnabled || entranceExamEnabled ? (
        <section className="sd-card">
          <div className="sd-section-head">
            <h2>Website</h2>
            <p>Manage your public school homepage, About Us content, contact details, school contents, and entrance exam setup.</p>
          </div>
          <div className="sd-actions">
            {websiteEnabled ? <Link to="/school/admin/website" className="sd-link-button">Open Website</Link> : null}
            {websiteEnabled ? <Link to="/school/admin/website?createContent=1" className="sd-link-button">Create Contents</Link> : null}
            {entranceExamEnabled ? <Link to="/school/admin/entrance-exam" className="sd-link-button">Open Entrance Exam</Link> : null}
          </div>
        </section>
      ) : null}
      <section className="sd-card sd-branding">
        <div className="sd-branding__form">
          <div className="sd-section-head">
            <h2>School Information</h2>
            <p>Update the school identity, head details, payment account, and contact information used across your school portal.</p>
          </div>

          <div className="sd-field-grid">
            <div className="sd-field">
              <label>School Logo</label>
              <div className="sd-file-control">
                {logoFile || stats.school_logo_url ? <img src={logoFile ? URL.createObjectURL(logoFile) : stats.school_logo_url} alt="School logo" /> : <span>No logo uploaded</span>}
                <input ref={logoInputRef} type="file" accept="image/png,image/jpeg,image/webp" onChange={(e) => setLogoFile(e.target.files?.[0] || null)} hidden />
                <button type="button" onClick={() => logoInputRef.current?.click()}>Choose Logo</button>
              </div>
            </div>

            <div className="sd-field">
              <label>Head of School Name</label>
              <input type="text" value={headOfSchoolName} onChange={(e) => setHeadOfSchoolName(e.target.value)} placeholder="Enter head of school name" />
            </div>

            <div className="sd-field">
              <label>Head Signature or Stamp</label>
              <div className="sd-file-control">
                {signatureFile || stats.head_signature_url ? <img src={signatureFile ? URL.createObjectURL(signatureFile) : stats.head_signature_url} alt="Head signature or stamp" /> : <span>No signature or stamp uploaded</span>}
                <input ref={signatureInputRef} type="file" accept="image/png,image/jpeg,image/webp" onChange={(e) => setSignatureFile(e.target.files?.[0] || null)} hidden />
                <button type="button" onClick={() => signatureInputRef.current?.click()}>Choose Signature or Stamp</button>
              </div>
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
              <label>School Motto</label>
              <input
                type="text"
                value={schoolMotto}
                onChange={(e) => setSchoolMotto(e.target.value)}
                placeholder="Enter school motto"
              />
            </div>

            <div className="sd-field">
              <label>Paystack Subaccount Code</label>
              <input
                type="text"
                value={paystackSubaccountCode}
                onChange={(e) => setPaystackSubaccountCode(e.target.value)}
                placeholder="ACCT_xxxxxxxxx"
              />
            </div>

            <div className="sd-field">
              <label>Result PDF Position</label>
              <label className="sd-checkbox-field">
                <input
                  type="checkbox"
                  checked={showResultPosition}
                  onChange={(e) => setShowResultPosition(e.target.checked)}
                />
                <span>Show position on student result PDF</span>
              </label>
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
