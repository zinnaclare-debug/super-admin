import { useEffect, useMemo, useRef, useState } from "react";
import { Link, NavLink, Navigate, useSearchParams } from "react-router-dom";
import api from "../../services/api";
import { createCbtSecurityFramework } from "../../utils/cbtSecurityFramework";
import "./PublicSchoolPortal.css";
import "../shared/CbtShowcase.css";

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

function XIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M4 4 20 20" />
      <path d="M20 4 4 20" />
    </svg>
  );
}

function FacebookIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M13.5 21v-7h2.4l.4-2.8h-2.8V9.4c0-.8.3-1.4 1.5-1.4H16V5.5c-.4-.1-1.2-.1-2.1-.1-2.1 0-3.5 1.3-3.5 3.6v2.1H8V14h2.3v7h3.2Z" />
    </svg>
  );
}

function TikTokIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M14.6 3c.4 2.2 1.7 3.7 3.9 4v2.6a7 7 0 0 1-3.5-1v6.1A5.7 5.7 0 1 1 9.3 9v2.8a3 3 0 1 0 2.9 3V3h2.4Z" />
    </svg>
  );
}

function InstagramIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9ZM17.7 5.8a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" />
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
const QUESTIONS_PER_PAGE = 4;
const SOCIAL_PLATFORM_META = {
  x: {
    label: "Follow on X",
    hint: "School updates and announcements",
    icon: <XIcon />,
  },
  facebook: {
    label: "Find us on Facebook",
    hint: "School page and community posts",
    icon: <FacebookIcon />,
  },
  tiktok: {
    label: "Watch on TikTok",
    hint: "Short videos and campus moments",
    icon: <TikTokIcon />,
  },
  instagram: {
    label: "See us on Instagram",
    hint: "Photos, stories, and school highlights",
    icon: <InstagramIcon />,
  },
};

function buildSocialLinks(socialLinks) {
  if (!socialLinks || typeof socialLinks !== "object") return [];

  return Object.entries(SOCIAL_PLATFORM_META)
    .map(([key, meta]) => {
      const current = socialLinks?.[key];
      const url = String(current?.url || "").trim();

      if (!current?.enabled || !url) return null;

      return {
        key,
        url,
        ...meta,
      };
    })
    .filter(Boolean);
}

function sanitizeExamQuestion(question, index) {
  if (!question || typeof question !== "object") return null;
  const normalized = {
    id: question.id ?? index + 1,
    question: String(question.question || "").trim(),
    option_a: String(question.option_a || "").trim(),
    option_b: String(question.option_b || "").trim(),
    option_c: String(question.option_c || "").trim(),
    option_d: String(question.option_d || "").trim(),
  };

  if (!normalized.question) return null;
  if (!normalized.option_a || !normalized.option_b || !normalized.option_c || !normalized.option_d) return null;
  return normalized;
}

function sanitizeExamPayload(payload) {
  if (!payload || typeof payload !== "object") return null;
  const questions = Array.isArray(payload?.exam?.questions)
    ? payload.exam.questions.map((question, index) => sanitizeExamQuestion(question, index)).filter(Boolean)
    : [];

  return {
    ...payload,
    exam: payload.exam
      ? {
          ...payload.exam,
          questions,
          question_count: questions.length,
        }
      : payload.exam,
  };
}

export default function PublicSchoolPortal({ page = "home", initialSiteData = null }) {
    const [searchParams, setSearchParams] = useSearchParams();
  const [siteData, setSiteData] = useState(initialSiteData);
  const [loading, setLoading] = useState(!initialSiteData);
  const [error, setError] = useState("");
  const [contentFeed, setContentFeed] = useState(initialSiteData?.school?.content_feed || { data: [], meta: { current_page: 1, last_page: 1, total: 0 } });
  const [contentLoading, setContentLoading] = useState(false);
  const [expandedContentIds, setExpandedContentIds] = useState({});
  const [activeImage, setActiveImage] = useState("");
  const [applyForm, setApplyForm] = useState({
    full_name: "",
    phone: "",
    email: "",
    applying_for_class: "",
  });
  const [applyResult, setApplyResult] = useState(null);
  const [applyPayment, setApplyPayment] = useState(null);
  const [verifyingPayment, setVerifyingPayment] = useState(false);
  const [lookupForm, setLookupForm] = useState({ application_number: "" });
  const [verifyForm, setVerifyForm] = useState({ application_number: "" });
  const [examData, setExamData] = useState(null);
  const [examStarted, setExamStarted] = useState(false);
  const [examAnswers, setExamAnswers] = useState([]);
  const [examPage, setExamPage] = useState(0);
  const [examSecurityStatus, setExamSecurityStatus] = useState("");
  const [examWarnings, setExamWarnings] = useState(0);
  const [examHeadWarnings, setExamHeadWarnings] = useState(0);
  const [verifyResult, setVerifyResult] = useState(null);
  const [busyAction, setBusyAction] = useState("");
  const [isCompactOverviewScreen, setIsCompactOverviewScreen] = useState(() => (typeof window !== "undefined" ? window.innerWidth <= 640 : false));
  const [expandedOverviewSections, setExpandedOverviewSections] = useState({});
  const securityRef = useRef(null);
  const submittingRef = useRef(false);


 

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

  useEffect(() => {
    if (page !== "apply") return;
    const reference = searchParams.get("reference") || searchParams.get("trxref");
    if (!reference) return;
    verifyEntrancePayment(reference);
  }, [searchParams, page]);

  useEffect(() => {
    if (typeof window === "undefined") return undefined;
    const mediaQuery = window.matchMedia("(max-width: 640px)");
    const syncCompactOverview = (event) => {
      setIsCompactOverviewScreen(event.matches);
      if (!event.matches) {
        setExpandedOverviewSections({});
      }
    };

    syncCompactOverview(mediaQuery);
    if (typeof mediaQuery.addEventListener === "function") {
      mediaQuery.addEventListener("change", syncCompactOverview);
      return () => mediaQuery.removeEventListener("change", syncCompactOverview);
    }

    mediaQuery.addListener(syncCompactOverview);
    return () => mediaQuery.removeListener(syncCompactOverview);
  }, []);
  const school = siteData?.school || null;
  const website = school?.website_content || {};
  const entranceExam = school?.entrance_exam || {};
  const selectedClassOption = useMemo(() => {
    const options = Array.isArray(entranceExam.available_classes) ? entranceExam.available_classes : [];
    return options.find((item) => item.class_name === applyForm.applying_for_class) || null;
  }, [entranceExam.available_classes, applyForm.applying_for_class]);
  const feeAmount = Number(selectedClassOption?.application_fee_amount ?? entranceExam.application_fee_amount ?? 0);
  const feeTaxRate = Number(selectedClassOption?.application_fee_tax_rate ?? entranceExam.application_fee_tax_rate ?? 0);
  const feeTaxAmount = Number(selectedClassOption?.application_fee_tax_amount ?? entranceExam.application_fee_tax_amount ?? 0);
  const feeTotal = Number(selectedClassOption?.application_fee_total ?? entranceExam.application_fee_total ?? 0);
  const logoUrl = school?.logo_url ? toAbsoluteUrl(school.logo_url) : "";
  const currentYear = new Date().getFullYear();
  const contactAddress = website.address || school?.location || "";
  const contactEmail = website.contact_email || school?.contact_email || "";
  const contactPhone = website.contact_phone || school?.contact_phone || "";
  const phoneHref = normalizePhoneNumber(contactPhone);
  const whatsappHref = whatsappLink(contactPhone);
  const mapHref = mapsLink(contactAddress);
  const socialLinks = useMemo(() => buildSocialLinks(website.social_links), [website.social_links]);
  const overviewSections = useMemo(() => ([
    { key: "about", title: "About Us", text: website.about_text },
    { key: "vision", title: "Vision", text: website.vision_text },
    { key: "mission", title: "Mission", text: website.mission_text },
  ]), [website.about_text, website.vision_text, website.mission_text]);

  const themeStyle = useMemo(
    () => ({
      "--school-primary": website.primary_color || "#0f172a",
      "--school-accent": website.accent_color || "#0f766e",
    }),
    [website.primary_color, website.accent_color]
  );

  const shellStyle = useMemo(
    () => ({
      ...themeStyle,
      "--school-home-watermark": logoUrl ? `url("${logoUrl}")` : "none",
    }),
    [themeStyle, logoUrl]
  );

  const toggleOverviewSection = (key) => {
    setExpandedOverviewSections((current) => ({
      ...current,
      [key]: !current[key],
    }));
  };

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

  const stopSecurityRuntime = () => {
    if (securityRef.current) {
      securityRef.current.stop();
      securityRef.current = null;
    }
    setExamStarted(false);
  };

  const startSecurityRuntime = async () => {
    stopSecurityRuntime();

    const policy = {
      fullscreen_required: true,
      block_copy_paste: true,
      block_tab_switch: true,
      auto_submit_on_violation: true,
      auto_submit_on_fullscreen_exit: true,
      auto_submit_on_multiple_faces: true,
      ai_proctoring_enabled: true,
      max_warnings: 5,
      no_face_timeout_seconds: 30,
      max_head_movement_warnings: 2,
      head_movement_threshold_px: 60,
    };

    const runtime = createCbtSecurityFramework(policy, {
      onStatus: ({ message }) => setExamSecurityStatus(message || ""),
      onWarning: ({ reason, warnings }) => {
        setExamWarnings(warnings || 0);
        setExamSecurityStatus(`Security warning: ${reason}`);
      },
      onHeadMovement: ({ count }) => {
        setExamHeadWarnings(count || 0);
      },
      onMajorViolation: ({ reason }) => {
        setExamSecurityStatus(`Major violation detected: ${reason}`);
        submitEntranceExam("auto", reason || "major_violation");
      },
    });

    securityRef.current = runtime;
    await runtime.start();
  };

  const submitEntranceExam = async (submitMode = "manual", violationReason = null) => {
    if (!examData?.exam || submittingRef.current) return false;

    setBusyAction("exam-submit");
    submittingRef.current = true;

    try {
      const res = await api.post("/api/public/entrance-exam/submit", {
        application_number: lookupForm.application_number,
        answers: examAnswers,
        submit_mode: submitMode,
        violation_reason: violationReason || undefined,
        security_warnings: examWarnings,
        head_movement_warnings: examHeadWarnings,
      });
      setExamData({ completed: true, result: res.data?.data || null });
      stopSecurityRuntime();
      setExamSecurityStatus("Exam submitted.");
      return true;
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to submit entrance exam.");
      return false;
    } finally {
      setBusyAction("");
      submittingRef.current = false;
    }
  };

  const verifyEntrancePayment = async (reference) => {
    if (!reference) return;
    setVerifyingPayment(true);
    try {
      const res = await api.post("/api/public/entrance-exam/verify-payment", { reference });
      setApplyResult(res.data?.data || null);
      setApplyPayment(null);
      searchParams.delete("reference");
      searchParams.delete("trxref");
      setSearchParams(searchParams, { replace: true });
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to verify payment.");
    } finally {
      setVerifyingPayment(false);
    }
  };

  const downloadEntranceReceipt = async (applicationNumber) => {
    if (!applicationNumber) return;
    try {
      const res = await api.get("/api/public/entrance-exam/receipt", {
        params: { application_number: applicationNumber },
        responseType: "blob",
      });
      const blob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `entrance_exam_receipt_${applicationNumber}.pdf`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to download receipt.");
    }
  };

  useEffect(() => {
    return () => {
      stopSecurityRuntime();
    };
  }, []);

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
  const examQuestions = Array.isArray(examData?.exam?.questions) ? examData.exam.questions : [];
  const examQuestionTotal = examQuestions.length;
  const answeredCount = examAnswers.slice(0, examQuestionTotal).filter((answer) => ["A", "B", "C", "D"].includes(String(answer))).length;
  const totalExamPages = Math.max(1, Math.ceil(Math.max(examQuestionTotal, 1) / QUESTIONS_PER_PAGE));
  const examPageStart = examPage * QUESTIONS_PER_PAGE;
  const currentExamQuestions = examQuestions.slice(examPageStart, examPageStart + QUESTIONS_PER_PAGE);
  const hideExamChrome = page === "exam" && examStarted && Boolean(examData?.exam);
  const handleApply = async (e) => {
    e.preventDefault();
    setBusyAction("apply");
    setApplyResult(null);
    setApplyPayment(null);
    try {
      const res = await api.post("/api/public/apply-now", applyForm);
      const data = res.data?.data || null;
      if (data?.authorization_url) {
        setApplyPayment({ reference: data.reference, amount_total: data.amount_total });
        window.location.href = data.authorization_url;
        return;
      }
      setApplyResult(data);
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
    stopSecurityRuntime();
    try {
      const res = await api.post("/api/public/entrance-exam/lookup", lookupForm);
      if (res.data?.already_submitted) {
        setExamData({ completed: true, result: res.data.data });
        setExamAnswers([]);
      } else {
        const payload = sanitizeExamPayload(res.data?.data || null);
        if (!payload?.exam?.questions?.length) {
          alert("This entrance exam has no complete questions yet. Please contact the school admin.");
          setExamData(null);
          setExamAnswers([]);
          return;
        }
        setExamData(payload);
        setExamStarted(false);
        setExamAnswers(Array(payload.exam.questions.length).fill(""));
        setExamPage(0);
        setExamSecurityStatus("Press Begin Exam to enter fullscreen and start the secured CBT session.");
        setExamWarnings(0);
        setExamHeadWarnings(0);
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
    await submitEntranceExam("manual");
  };

  const handleBeginEntranceExam = async () => {
    if (!examData?.exam || examStarted) return;
    setBusyAction("exam-start");
    setExamSecurityStatus("Starting secured exam session...");
    try {
      await startSecurityRuntime();
      setExamStarted(true);
      setExamSecurityStatus("Security active. Stay fullscreen and focus on the exam.");
    } catch {
      stopSecurityRuntime();
      setExamSecurityStatus("Fullscreen permission is required before you can start this exam.");
      alert("Allow fullscreen access to begin the secured entrance exam.");
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
    <div className="school-site-shell" style={shellStyle}>
      {!hideExamChrome ? (
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
      ) : null}

      {error ? <p className="school-site-state school-site-state--error">{error}</p> : null}

      {page === "home" ? (
        <main className="school-site-main school-site-main--home">
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
            {overviewSections.map((section) => {
              const isExpanded = !!expandedOverviewSections[section.key];
              const shouldShowContent = !isCompactOverviewScreen || isExpanded;
              return (
                <article key={section.key} className="school-site-overview-card">
                  <div className="school-site-overview-head">
                    <h3>{section.title}</h3>
                    {isCompactOverviewScreen ? (
                      <button
                        type="button"
                        className="school-site-overview-toggle"
                        onClick={() => toggleOverviewSection(section.key)}
                      >
                        {isExpanded ? "Show less" : "See more"}
                      </button>
                    ) : null}
                  </div>
                  {shouldShowContent ? <p>{section.text}</p> : null}
                </article>
              );
            })}
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
                      {item.image_urls.map((url, index) => {
                        const imageUrl = toAbsoluteUrl(url);
                        return (
                          <button
                            key={`${item.id}-${index}`}
                            type="button"
                            className="school-site-content-image-btn"
                            onClick={() => setActiveImage(imageUrl)}
                          >
                            <img src={imageUrl} alt={`${item.heading} ${index + 1}`} />
                          </button>
                        );
                      })}
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

          {socialLinks.length ? (
            <section className="school-site-social-section school-site-section">
              <div className="school-site-content-head">
                <div>
                  <h2>Social Media</h2>
                  <p>Follow the school on the official channels activated by the school admin.</p>
                </div>
              </div>

              <div className="school-site-social-grid">
                {socialLinks.map((item) => (
                  <a
                    key={item.key}
                    className={`school-site-social-card school-site-social-card--${item.key}`}
                    href={item.url}
                    target="_blank"
                    rel="noreferrer"
                  >
                    <span className="school-site-social-icon" aria-hidden="true">{item.icon}</span>
                    <div className="school-site-social-copy">
                      <strong>{item.label}</strong>
                      <span>{item.hint}</span>
                    </div>
                  </a>
                ))}
              </div>
            </section>
          ) : null}
        </main>
      ) : null}

                 {page === "apply" ? (
        <main className="school-site-main school-site-form-page">
          <section className="school-site-section">
            <h1>Apply Now</h1>
            <p>{entranceExam.apply_intro || website.admissions_intro}</p>

            <div className="school-site-result-card school-site-fee-card" style={{ marginBottom: 16 }}>
              <h3>Entrance Exam Fee</h3>
              <p>{selectedClassOption?.class_name ? `Fee for ${selectedClassOption.class_name}` : "Select a class to view the fee"}</p>
              <p>Application Fee: NGN {feeAmount.toFixed(2)}</p>
              <p>Processing Fee ({feeTaxRate.toFixed(1)}%): NGN {feeTaxAmount.toFixed(2)}</p>
              <p><strong>Total: NGN {feeTotal.toFixed(2)}</strong></p>
            </div>

            <form className="school-site-form" onSubmit={handleApply}>
              <input
                placeholder="Applicant Name"
                value={applyForm.full_name}
                onChange={(e) => setApplyForm((prev) => ({ ...prev, full_name: e.target.value }))}
                required
              />
              <input
                placeholder="Phone Number"
                value={applyForm.phone}
                onChange={(e) => setApplyForm((prev) => ({ ...prev, phone: e.target.value }))}
                required
              />
              <input
                type="email"
                placeholder="Email Address"
                value={applyForm.email}
                onChange={(e) => setApplyForm((prev) => ({ ...prev, email: e.target.value }))}
                required
              />
              <select
                value={applyForm.applying_for_class}
                onChange={(e) => setApplyForm((prev) => ({ ...prev, applying_for_class: e.target.value }))}
                required
              >
                <option value="">Select Class</option>
                {classOptions.map((item) => (
                  <option key={item.class_name} value={item.class_name}>
                    {item.class_name}
                  </option>
                ))}
              </select>
              <button type="submit" disabled={busyAction === "apply"}>
                {busyAction === "apply"
                  ? "Processing..."
                  : feeTotal > 0
                    ? "Pay & Submit Application"
                    : "Submit Application"}
              </button>
            </form>

            {verifyingPayment ? (
              <p className="school-site-content-empty">Verifying payment, please wait...</p>
            ) : null}

            {applyResult ? (
              <div className="school-site-result-card">
                <h3>Application Submitted</h3>
                <p>Applicant: <strong>{applyResult.full_name}</strong></p>
                <p>Application Number: <strong>{applyResult.application_number}</strong></p>
                <p>Keep this number for entrance exam and verification.</p>
                {applyResult.application_number ? (
                  <button type="button" onClick={() => downloadEntranceReceipt(applyResult.application_number)}>
                    Generate Receipt
                  </button>
                ) : null}
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
                <input
                  placeholder="Application Number"
                  value={lookupForm.application_number}
                  onChange={(e) => setLookupForm((prev) => ({ ...prev, application_number: e.target.value }))}
                  required
                />
                <button type="submit" disabled={busyAction === "exam-lookup"}>
                  {busyAction === "exam-lookup" ? "Checking..." : "Load Exam"}
                </button>
              </form>
            ) : null}

            {examData?.completed ? (
              <div className="school-site-result-card">
                <h3>Exam Submitted</h3>
                <p><strong>{examData.result?.full_name}</strong></p>
                <p>We have received your entrance exam submission.</p>
              </div>
            ) : null}

            {examData?.exam ? (
              <div className="cbx-page cbx-page--student school-site-exam-stage">
                {!examStarted ? (
                  <section className="cbx-panel school-site-exam-shell school-site-exam-shell--intro">
                    <div className="school-site-exam-hero">
                      <div>
                        <span className="cbx-pill">Secured Entrance CBT</span>
                        <h3 className="school-site-exam-title">{examData.application?.applying_for_class || "Entrance Exam"}</h3>
                        <p className="school-site-exam-copy">
                          Review the instructions below, then begin the exam in fullscreen mode. Questions stay hidden until the candidate clicks Begin Exam, and leaving fullscreen or switching tabs can auto-submit the exam.
                        </p>
                      </div>
                      <div className="school-site-exam-stats">
                        <article>
                          <span>Pages</span>
                          <strong>{totalExamPages}</strong>
                        </article>
                        <article>
                          <span>Duration</span>
                          <strong>{examData.exam?.duration_minutes || 0} mins</strong>
                        </article>
                      </div>
                    </div>

                    {examData.exam?.instructions ? (
                      <div className="school-site-exam-brief">
                        <h4>Exam Instructions</h4>
                        <p>{examData.exam.instructions}</p>
                      </div>
                    ) : null}

                    <div className="school-site-security-grid">
                      <article className="school-site-security-card">
                        <h4>Security Rules</h4>
                        <ul>
                          <li>Fullscreen is compulsory throughout the exam.</li>
                          <li>Tab switching, copy, paste, and right-click are blocked.</li>
                          <li>Webcam-based monitoring may flag multiple faces or major head movement.</li>
                        </ul>
                      </article>
                      <article className="school-site-security-card">
                        <h4>Before You Begin</h4>
                        <ul>
                          <li>Use a stable browser window and keep this tab active.</li>
                          <li>Check that your device can allow fullscreen access.</li>
                          <li>Click Begin Exam only when you are ready to answer immediately.</li>
                        </ul>
                      </article>
                    </div>

                    {examSecurityStatus ? <p className="cbx-state cbx-state--warning">{examSecurityStatus}</p> : null}

                    <div className="school-site-exam-actions school-site-exam-actions--intro">
                      <button
                        type="button"
                        className="cbx-btn"
                        onClick={handleBeginEntranceExam}
                        disabled={busyAction === "exam-start"}
                      >
                        {busyAction === "exam-start" ? "Starting Exam..." : "Begin Exam"}
                      </button>
                    </div>
                  </section>
                ) : (
                  <form className="cbx-panel school-site-exam-shell school-site-exam-shell--active" onSubmit={handleExamSubmit}>
                    <div className="school-site-exam-topbar">
                      <div className="school-site-exam-progress">
                        <span className="school-site-exam-progress-label">Progress</span>
                        <strong>Answered {answeredCount} of {examQuestionTotal}</strong>
                      </div>
                      <div className="school-site-exam-badges">
                        <span className="school-site-exam-badge">Page {examPage + 1} / {totalExamPages}</span>
                        <span className="school-site-exam-badge">Warnings {examWarnings}</span>
                        <span className="school-site-exam-badge">Head Movement {examHeadWarnings}</span>
                      </div>
                    </div>

                    {examSecurityStatus ? (
                      <p className="cbx-state cbx-state--warning">{examSecurityStatus}</p>
                    ) : null}

                    <div className="school-site-question-stack" key={`page-${examPage}`}>
                      {currentExamQuestions.map((question, idx) => {
                        const questionIndex = examPageStart + idx;
                        return (
                          <article key={`question-${questionIndex}`} className="school-site-question-block school-site-question-card">
                            <div className="school-site-question-number">Question {questionIndex + 1}</div>
                            <h4>{question.question}</h4>
                            <div className="school-site-option-list">
                              {["A", "B", "C", "D"].map((optionKey) => {
                                const optionValue = question[`option_${optionKey.toLowerCase()}`];
                                return (
                                  <label key={optionKey} className={`school-site-option${examAnswers[questionIndex] === optionKey ? " school-site-option--selected" : ""}`}>
                                    <input
                                      type="radio"
                                      name={`question-${questionIndex}`}
                                      value={optionKey}
                                      checked={examAnswers[questionIndex] === optionKey}
                                      onChange={(e) =>
                                        setExamAnswers((prev) =>
                                          prev.map((item, answerIndex) =>
                                            answerIndex === questionIndex ? e.target.value : item
                                          )
                                        )
                                      }
                                    />
                                    <span className="school-site-option-key">{optionKey}</span>
                                    <span className="school-site-option-text">{optionValue}</span>
                                  </label>
                                );
                              })}
                            </div>
                          </article>
                        );
                      })}
                    </div>

                    <div className="school-site-exam-actions">
                      <button
                        type="button"
                        className="cbx-btn cbx-btn--soft"
                        onClick={() => setExamPage((pageIndex) => Math.max(0, pageIndex - 1))}
                        disabled={examPage <= 0}
                      >
                        Previous Page
                      </button>
                      <button
                        type="button"
                        className="cbx-btn cbx-btn--soft"
                        onClick={() => setExamPage((pageIndex) => Math.min(totalExamPages - 1, pageIndex + 1))}
                        disabled={examPage >= totalExamPages - 1}
                      >
                        Next Page
                      </button>
                      <button
                        type="submit"
                        className="cbx-btn school-site-exam-submit"
                        disabled={busyAction === "exam-submit"}
                      >
                        {busyAction === "exam-submit" ? "Submitting..." : "Submit Entrance Exam"}
                      </button>
                    </div>
                  </form>
                )}
              </div>
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
              <input
                placeholder="Application Number"
                value={verifyForm.application_number}
                onChange={(e) => setVerifyForm((prev) => ({ ...prev, application_number: e.target.value }))}
                required
              />
              <button type="submit" disabled={busyAction === "verify"}>
                {busyAction === "verify" ? "Checking..." : "Verify Score"}
              </button>
            </form>
            {verifyResult ? (
              <div className="school-site-result-card">
                <h3>{verifyResult.full_name}</h3>
                <p>Application Number: {verifyResult.application_number}</p>
                <p>Class: {verifyResult.applying_for_class}</p>
                <p><strong>Result: {verifyResult.review_status}</strong></p>
              </div>
            ) : null}
          </section>
        </main>
      ) : null}


      {activeImage ? (
        <div className="school-site-lightbox" role="dialog" aria-modal="true" onClick={() => setActiveImage("")}>
          <button type="button" className="school-site-lightbox-close" onClick={() => setActiveImage("")}>Close</button>
          <div className="school-site-lightbox-stage" onClick={(event) => event.stopPropagation()}>
            <img src={activeImage} alt="Selected school content" />
          </div>
        </div>
      ) : null}

      {!hideExamChrome ? (
      <footer className="school-site-footer">
        <div className="school-site-footer-mark">
          <span className="school-site-footer-c">{"\u00A9"}</span>
          <span>{currentYear}</span>
        </div>
        <a className="school-site-footer-link" href="mailto:lytebridgeinfo@gmail.com">
          DESIGNED BY LYTE BRIDGE
        </a>
      </footer>
      ) : null}
    </div>
  );
}








