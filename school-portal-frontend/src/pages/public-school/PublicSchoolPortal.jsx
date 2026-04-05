import { useEffect, useMemo, useState } from "react";
import { Link, NavLink, Navigate } from "react-router-dom";
import api from "../../services/api";
import "./PublicSchoolPortal.css";

function toAbsoluteUrl(url) {
  if (!url) return "";
  if (/^(https?:\/\/|blob:|data:)/i.test(url)) return url;
  const base = (api.defaults.baseURL || "").replace(/\/$/, "");
  const origin = base ? new URL(base).origin : window.location.origin;
  return `${origin}${url.startsWith("/") ? "" : "/"}${url}`;
}

function normalizePhoneNumber(phone) {
  return String(phone || "").replace(/[^\d+]/g, "");
}

function whatsappLink(phone) {
  const normalized = normalizePhoneNumber(phone);
  const digitsOnly = normalized.replace(/^\+/, "");
  return digitsOnly ? `https://wa.me/${digitsOnly}` : "";
}

function mapsLink(address) {
  const value = String(address || "").trim();
  return value ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(value)}` : "";
}

function formatDate(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return date.toLocaleDateString(undefined, {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function PinIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M12 21s6-5.6 6-11a6 6 0 1 0-12 0c0 5.4 6 11 6 11Z" />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}

function MailIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="m4 7 8 6 8-6" />
    </svg>
  );
}

function PhoneIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.3 19.3 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7l.5 3.4a2 2 0 0 1-.6 1.8l-1.4 1.4a16 16 0 0 0 6 6l1.4-1.4a2 2 0 0 1 1.8-.6l3.4.5A2 2 0 0 1 22 16.9Z" />
    </svg>
  );
}

function ContactWidget({ icon, title, value, children }) {
  return (
    <article className="school-site-contact-widget">
      <span className="school-site-contact-badge" aria-hidden="true">{icon}</span>
      <div className="school-site-contact-copy">
        <h3>{title}</h3>
        <p>{value}</p>
      </div>
      <div className="school-site-contact-footer">{children}</div>
    </article>
  );
}

const CONTENT_PREVIEW_LIMIT = 100;

export default function PublicSchoolPortal({ page = "home", initialSiteData = null }) {
  const [siteData, setSiteData] = useState(initialSiteData);
  const [loading, setLoading] = useState(!initialSiteData);
  const [error, setError] = useState("");
  const [contentFeed, setContentFeed] = useState(initialSiteData?.school?.content_feed || { data: [], meta: { current_page: 1, last_page: 1, total: 0 } });
  const [contentLoading, setContentLoading] = useState(false);
  const [expandedContentIds, setExpandedContentIds] = useState({});
  const [applyForm, setApplyForm] = useState({
    full_name: "",
    phone: "",
    email: "",
    applying_for_class: "",
  });
  const [applyResult, setApplyResult] = useState(null);
  const [lookupForm, setLookupForm] = useState({ application_number: "", email: "", phone: "" });
  const [verifyForm, setVerifyForm] = useState({ application_number: "", email: "", phone: "" });
  const [examData, setExamData] = useState(null);
  const [examAnswers, setExamAnswers] = useState([]);
  const [verifyResult, setVerifyResult] = useState(null);
  const [busyAction, setBusyAction] = useState("");

  useEffect(() => {
    if (initialSiteData) {
      setContentFeed(initialSiteData?.school?.content_feed || { data: [], meta: { current_page: 1, last_page: 1, total: 0 } });
      return;
    }

    let active = true;
    setLoading(true);
    api
      .get("/api/public/school-site")
      .then((res) => {
        if (!active) return;
        setSiteData(res.data);
        setContentFeed(res.data?.school?.content_feed || { data: [], meta: { current_page: 1, last_page: 1, total: 0 } });
      })
      .catch((err) => {
        if (!active) return;
        setError(err?.response?.data?.message || "Failed to load school website.");
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [initialSiteData]);

  const school = siteData?.school || null;
  const website = school?.website_content || {};
  const entranceExam = school?.entrance_exam || {};
  const logoUrl = school?.logo_url ? toAbsoluteUrl(school.logo_url) : "";
  const currentYear = new Date().getFullYear();
  const contactAddress = website.address || school?.location || "";
  const contactEmail = website.contact_email || school?.contact_email || "";
  const contactPhone = website.contact_phone || school?.contact_phone || "";
  const phoneHref = normalizePhoneNumber(contactPhone);
  const whatsappHref = whatsappLink(contactPhone);
  const mapHref = mapsLink(contactAddress);

  const themeStyle = useMemo(
    () => ({
      "--school-primary": website.primary_color || "#0f172a",
      "--school-accent": website.accent_color || "#0f766e",
    }),
    [website.primary_color, website.accent_color]
  );

  useEffect(() => {
    if (Array.isArray(entranceExam.available_classes) && entranceExam.available_classes.length > 0 && !applyForm.applying_for_class) {
      const firstEnabled = entranceExam.available_classes.find((item) => item.enabled) || entranceExam.available_classes[0];
      setApplyForm((prev) => ({ ...prev, applying_for_class: firstEnabled?.class_name || "" }));
    }
  }, [entranceExam.available_classes, applyForm.applying_for_class]);

  const loadContentPage = async (pageNumber) => {
    if (pageNumber < 1) return;
    setContentLoading(true);
    try {
      const res = await api.get("/api/public/school-contents", { params: { page: pageNumber } });
      setContentFeed({
        data: Array.isArray(res.data?.data) ? res.data.data : [],
        meta: res.data?.meta || { current_page: 1, last_page: 1, total: 0 },
      });
      setExpandedContentIds({});
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load school contents.");
    } finally {
      setContentLoading(false);
    }
  };

  const toggleExpandedContent = (contentId) => {
    setExpandedContentIds((prev) => ({
      ...prev,
      [contentId]: !prev[contentId],
    }));
  };

  if (loading) {
    return <div className="school-site-shell"><p>Loading school website...</p></div>;
  }

  if (!siteData?.is_tenant || !school) {
    return <Navigate to="/" replace />;
  }

  const navItems = [
    { key: "home", label: "Home", href: "/", visible: true },
    { key: "apply", label: "Apply Now", href: "/apply-now", visible: Boolean(website.show_apply_now) },
    { key: "exam", label: "Entrance Exam", href: "/entrance-exam", visible: Boolean(website.show_entrance_exam) },
    { key: "verify", label: "Verify Score", href: "/verify-score", visible: Boolean(website.show_verify_score) },
    { key: "login", label: "Login", href: "/login", visible: true },
  ].filter((item) => item.visible);

  const classOptions = Array.isArray(entranceExam.available_classes)
    ? entranceExam.available_classes.filter((item) => item.enabled || page === "apply")
    : [];

  const contentItems = Array.isArray(contentFeed?.data) ? contentFeed.data : [];
  const contentMeta = contentFeed?.meta || { current_page: 1, last_page: 1, total: 0 };
  const examQuestionTotal = examData?.exam?.questions?.length || 0;
  const answeredCount = examAnswers.filter((answer) => ["A", "B", "C", "D"].includes(String(answer))).length;
  const handleApply = async (e) => {
    e.preventDefault();
    setBusyAction("apply");
    setApplyResult(null);
    try {
      const res = await api.post("/api/public/apply-now", applyForm);
      setApplyResult(res.data?.data || null);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to submit application.");
    } finally {
      setBusyAction("");
    }
  };

  const handleExamLookup = async (e) => {
    e.preventDefault();
    setBusyAction("exam-lookup");
    setExamData(null);
    try {
      const res = await api.post("/api/public/entrance-exam/lookup", lookupForm);
      if (res.data?.already_submitted) {
        setExamData({ completed: true, result: res.data.data });
        setExamAnswers([]);
      } else {
        const payload = res.data?.data || null;
        setExamData(payload);
        setExamAnswers(Array(payload?.exam?.questions?.length || 0).fill(""));
      }
    } catch (err) {
      const scheduledFor = err?.response?.data?.scheduled_for;
      if (scheduledFor) {
        alert(`Entrance exam is scheduled for ${formatDate(scheduledFor)}.`);
      } else {
        alert(err?.response?.data?.message || "Failed to load entrance exam.");
      }
    } finally {
      setBusyAction("");
    }
  };

  const handleExamSubmit = async (e) => {
    e.preventDefault();
    setBusyAction("exam-submit");
    try {
      const payload = { ...lookupForm, answers: examAnswers };
      const res = await api.post("/api/public/entrance-exam/submit", payload);
      setExamData({ completed: true, result: res.data?.data || null });
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to submit entrance exam.");
    } finally {
      setBusyAction("");
    }
  };

  const handleVerify = async (e) => {
    e.preventDefault();
    setBusyAction("verify");
    setVerifyResult(null);
    try {
      const res = await api.post("/api/public/verify-score", verifyForm);
      setVerifyResult(res.data?.data || null);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to verify score.");
    } finally {
      setBusyAction("");
    }
  };

  return (
    <div className="school-site-shell" style={themeStyle}>
      <header className="school-site-nav">
        <div className="school-site-brand">
          {logoUrl ? <img src={logoUrl} alt={`${school.name} logo`} /> : <div className="school-site-brand-mark">{school.name?.slice(0, 1) || "S"}</div>}
          <div>
            <strong>{school.name}</strong>
          </div>
        </div>
        <nav className="school-site-links">
          {navItems.map((item) => (
            <NavLink
              key={item.key}
              to={item.href}
              end={item.key === "home"}
              className={({ isActive }) => (isActive ? "is-active" : "")}
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
      </header>

      {error ? <p className="school-site-state school-site-state--error">{error}</p> : null}

      {page === "home" ? (
        <main className="school-site-main">
          <section className="school-site-hero">
            <div>
              <h1>{website.hero_title || `Welcome to ${school.name}`}</h1>
              {website.motto ? <div className="school-site-motto-box">{website.motto}</div> : null}
              {website.hero_subtitle ? <p>{website.hero_subtitle}</p> : null}
              <div className="school-site-hero-actions">
                {website.show_apply_now ? <Link to="/apply-now">Apply Now</Link> : null}
                {website.show_entrance_exam ? <Link to="/entrance-exam">Entrance Exam</Link> : null}
              </div>
            </div>
          </section>

          <section className="school-site-section school-site-cards">
            <article>
              <h3>About Us</h3>
              <p>{website.about_text}</p>
            </article>
            <article>
              <h3>Core Values</h3>
              <p>{website.core_values_text}</p>
            </article>
            <article>
              <h3>Mission</h3>
              <p>{website.mission_text}</p>
            </article>
          </section>

          <section className="school-site-content-section school-site-section">
            <div className="school-site-content-head">
              <div>
                <h2>School Contents</h2>
                <p>Highlights, updates, and important stories from the school community.</p>
              </div>
            </div>

            {contentLoading ? <p className="school-site-content-empty">Loading school contents...</p> : null}

            {!contentLoading && contentItems.length > 0 ? contentItems.map((item) => {
              const fullContent = String(item.content || "");
              const needsToggle = fullContent.length > CONTENT_PREVIEW_LIMIT;
              const isExpanded = Boolean(expandedContentIds[item.id]);
              const visibleContent = needsToggle && !isExpanded
                ? `${fullContent.slice(0, CONTENT_PREVIEW_LIMIT).trimEnd()}...`
                : fullContent;

              return (
                <article key={item.id} className="school-site-content-card">
                  <div className="school-site-content-copy">
                    <div className="school-site-content-meta">
                      <h3>{item.heading}</h3>
                      <span>{item.display_date || formatDate(item.created_at)}</span>
                    </div>
                    <p>{visibleContent}</p>
                    {needsToggle ? (
                      <button type="button" className="school-site-content-toggle" onClick={() => toggleExpandedContent(item.id)}>
                        {isExpanded ? "See less" : "See more"}
                      </button>
                    ) : null}
                  </div>
                  {item.image_urls?.length ? (
                    <div className="school-site-content-gallery">
                      {item.image_urls.map((url, index) => (
                        <img key={`${item.id}-${index}`} src={toAbsoluteUrl(url)} alt={`${item.heading} ${index + 1}`} />
                      ))}
                    </div>
                  ) : null}
                </article>
              );
            }) : null}

            {!contentLoading && contentItems.length === 0 ? (
              <div className="school-site-content-empty">No school content has been published yet.</div>
            ) : null}

            {contentMeta.last_page > 1 ? (
              <div className="school-site-content-pagination">
                <button type="button" onClick={() => loadContentPage(contentMeta.current_page - 1)} disabled={contentLoading || contentMeta.current_page <= 1}>
                  Previous
                </button>
                <span>Page {contentMeta.current_page} of {contentMeta.last_page}</span>
                <button type="button" onClick={() => loadContentPage(contentMeta.current_page + 1)} disabled={contentLoading || contentMeta.current_page >= contentMeta.last_page}>
                  Next
                </button>
              </div>
            ) : null}
          </section>

          <section className="school-site-contact-row">
            <ContactWidget icon={<PinIcon />} title="Visit the Campus" value={contactAddress || "Address coming soon"}>
              {mapHref ? (
                <a className="school-site-contact-link" href={mapHref} target="_blank" rel="noreferrer">
                  Open Map
                </a>
              ) : null}
            </ContactWidget>

            <ContactWidget icon={<MailIcon />} title="Send an Email" value={contactEmail || "No public email yet"}>
              {contactEmail ? (
                <a className="school-site-contact-link" href={`mailto:${contactEmail}`}>
                  Mail School
                </a>
              ) : null}
            </ContactWidget>

            <ContactWidget icon={<PhoneIcon />} title="Speak With the School" value={contactPhone || "No public phone yet"}>
              <div className="school-site-contact-actions">
                {phoneHref ? (
                  <a className="school-site-contact-link" href={`tel:${phoneHref}`}>
                    Call Now
                  </a>
                ) : null}
                {whatsappHref ? (
                  <a className="school-site-contact-link school-site-contact-link--alt" href={whatsappHref} target="_blank" rel="noreferrer">
                    WhatsApp
                  </a>
                ) : null}
              </div>
            </ContactWidget>
          </section>
        </main>
      ) : null}

      {page === "apply" ? (
        <main className="school-site-main school-site-form-page">
          <section className="school-site-section">
            <h1>Apply Now</h1>
            <p>{entranceExam.apply_intro || website.admissions_intro}</p>
            <form className="school-site-form" onSubmit={handleApply}>
              <input placeholder="Applicant Name" value={applyForm.full_name} onChange={(e) => setApplyForm((prev) => ({ ...prev, full_name: e.target.value }))} required />
              <input placeholder="Phone Number" value={applyForm.phone} onChange={(e) => setApplyForm((prev) => ({ ...prev, phone: e.target.value }))} required />
              <input type="email" placeholder="Email Address" value={applyForm.email} onChange={(e) => setApplyForm((prev) => ({ ...prev, email: e.target.value }))} required />
              <select value={applyForm.applying_for_class} onChange={(e) => setApplyForm((prev) => ({ ...prev, applying_for_class: e.target.value }))} required>
                <option value="">Select Class</option>
                {classOptions.map((item) => (
                  <option key={item.class_name} value={item.class_name}>{item.class_name}</option>
                ))}
              </select>
              <button type="submit" disabled={busyAction === "apply"}>{busyAction === "apply" ? "Submitting..." : "Submit Application"}</button>
            </form>
            {applyResult ? (
              <div className="school-site-result-card">
                <h3>Application Submitted</h3>
                <p>Application Number: <strong>{applyResult.application_number}</strong></p>
                <p>Keep this number for the entrance exam and score verification.</p>
              </div>
            ) : null}
          </section>
        </main>
      ) : null}

      {page === "exam" ? (
        <main className="school-site-main school-site-form-page">
          <section className="school-site-section">
            <h1>Entrance Exam</h1>
            <p>{entranceExam.exam_intro}</p>
            {!examData ? (
              <form className="school-site-form" onSubmit={handleExamLookup}>
                <input placeholder="Application Number" value={lookupForm.application_number} onChange={(e) => setLookupForm((prev) => ({ ...prev, application_number: e.target.value }))} required />
                <input type="email" placeholder="Email Address" value={lookupForm.email} onChange={(e) => setLookupForm((prev) => ({ ...prev, email: e.target.value }))} />
                <input placeholder="Phone Number" value={lookupForm.phone} onChange={(e) => setLookupForm((prev) => ({ ...prev, phone: e.target.value }))} />
                <button type="submit" disabled={busyAction === "exam-lookup"}>{busyAction === "exam-lookup" ? "Checking..." : "Load Exam"}</button>
              </form>
            ) : null}

            {examData?.completed ? (
              <div className="school-site-result-card">
                <h3>Exam Already Submitted</h3>
                <p><strong>{examData.result?.full_name}</strong></p>
                <p>Score: {examData.result?.score ?? "-"}</p>
                <p>Status: {examData.result?.result_status || examData.result?.exam_status}</p>
              </div>
            ) : null}

            {examData?.exam ? (
              <form className="school-site-form school-site-exam-form" onSubmit={handleExamSubmit}>
                <div className="school-site-result-card">
                  <p><strong>{examData.application?.full_name}</strong></p>
                  <p>Class: {examData.application?.applying_for_class}</p>
                  <p>Duration: {examData.exam.duration_minutes} minutes</p>
                  <p>Pass Mark: {examData.exam.pass_mark}</p>
                  <p className="school-site-exam-progress">Answered: {answeredCount} / {examQuestionTotal}</p>
                  <p>{examData.exam.instructions || "Answer all questions and submit once."}</p>
                </div>
                {examData.exam.questions.map((question, index) => (
                  <div key={question.id} className="school-site-question-block">
                    <h4>{index + 1}. {question.question}</h4>
                    {["A", "B", "C", "D"].map((optionKey) => {
                      const optionValue = question[`option_${optionKey.toLowerCase()}`];
                      return (
                        <label key={optionKey} className="school-site-option">
                          <input type="radio" name={`question-${index}`} value={optionKey} checked={examAnswers[index] === optionKey} onChange={(e) => setExamAnswers((prev) => prev.map((item, answerIndex) => answerIndex === index ? e.target.value : item))} />
                          <span>{optionKey}. {optionValue}</span>
                        </label>
                      );
                    })}
                  </div>
                ))}
                <button type="submit" disabled={busyAction === "exam-submit"}>{busyAction === "exam-submit" ? "Submitting..." : "Submit Entrance Exam"}</button>
              </form>
            ) : null}
          </section>
        </main>
      ) : null}

      {page === "verify" ? (
        <main className="school-site-main school-site-form-page">
          <section className="school-site-section">
            <h1>Verify Score</h1>
            <p>{entranceExam.verify_intro}</p>
            <form className="school-site-form" onSubmit={handleVerify}>
              <input placeholder="Application Number" value={verifyForm.application_number} onChange={(e) => setVerifyForm((prev) => ({ ...prev, application_number: e.target.value }))} required />
              <input type="email" placeholder="Email Address" value={verifyForm.email} onChange={(e) => setVerifyForm((prev) => ({ ...prev, email: e.target.value }))} />
              <input placeholder="Phone Number" value={verifyForm.phone} onChange={(e) => setVerifyForm((prev) => ({ ...prev, phone: e.target.value }))} />
              <button type="submit" disabled={busyAction === "verify"}>{busyAction === "verify" ? "Checking..." : "Verify Score"}</button>
            </form>
            {verifyResult ? (
              <div className="school-site-result-card">
                <h3>{verifyResult.full_name}</h3>
                <p>Application Number: {verifyResult.application_number}</p>
                <p>Class: {verifyResult.applying_for_class}</p>
                <p>Exam Status: {verifyResult.exam_status}</p>
                <p>Score: {verifyResult.score ?? "Pending"}</p>
                <p>Result: {verifyResult.result_status || "Awaiting Review"}</p>
              </div>
            ) : null}
          </section>
        </main>
      ) : null}

      <footer className="school-site-footer">
        <div className="school-site-footer-mark">
          <span className="school-site-footer-c">{"\u00A9"}</span>
          <span>{currentYear}</span>
        </div>
        <a className="school-site-footer-link" href="mailto:lytebridgeinfo@gmail.com">
          DESIGNED BY LYTE BRIDGE
        </a>
      </footer>
    </div>
  );
}






