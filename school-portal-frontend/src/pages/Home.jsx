import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import api from "../services/api";
import PublicSchoolPortal from "./public-school/PublicSchoolPortal";
import brandArt from "../assets/home/lytebridge-brand.jpg";
import coreFunctionsArt from "../assets/home/lytebridge-core-functions.jpeg";
import "./Home.css";

const CORE_FUNCTIONS = [
  "Broadsheet Management",
  "Result Management (Report Cards)",
  "Student Transcript Generation",
  "Announcements & Notice Board",
  "Online School Fees Payment (Paystack Integration)",
  "Free School Website (Included)",
  "Mobile Application (Students, Parents & Teachers)",
  "Parent Engagement Dashboard",
  "Timetable Management",
  "Virtual Classes (Live Classes - Google Meet Integration)",
  "CBT Examinations (Computer-Based Tests)",
  "Online Assignments & Submissions",
  "Online Classes / Learning Portal",
  "Saves Thousands in Paper Costs (Go Paperless)",
];

const DEFAULT_PLATFORM_CONTENT = {
  about_text:
    "LyteBridge Professional Services is a dynamic and innovative solutions provider specializing in Education, ICT, and School Management Software. We are committed to helping schools, educational institutions, and organizations improve efficiency, embrace digital transformation, and operate with professional standards.",
  vision_text:
    "Our services are designed to simplify school administration, enhance teaching and learning, and provide reliable technology solutions tailored to modern educational needs. From school setup and educational consulting to ICT infrastructure and complete school management systems, LyteBridge delivers affordable, user-friendly, and scalable solutions.",
  mission_text:
    "At LyteBridge Professional Services, we focus on professionalism, innovation, and excellence. Our goal is to empower institutions to go paperless, save cost, improve productivity, and manage their operations smarter through technology-driven solutions.",
  content_section_title: "Content Area",
  content_section_intro:
    "Use this space to present the platform rollout plan, onboarding highlights, and the next steps schools should follow before launch.",
  content_todo_items: [
    "Plan school onboarding, admin access, and rollout timeline.",
    "Prepare portal content, branding, and staff orientation materials.",
    "Launch admissions, payments, results, and communication workflows.",
  ],
};

const ABOUT_CARDS = [
  { key: "about", title: "About Us", field: "about_text" },
  { key: "vision", title: "Vision", field: "vision_text" },
  { key: "mission", title: "Mission", field: "mission_text" },
];

const CONTACT_LINES = [
  { phone: "+2348027453306", dial: "+2348027453306", label: "Call us now" },
  { phone: "+2349136806652", dial: "+2349136806652", label: "WhatsApp and phone" },
  { phone: "+2347066906190", dial: "+2347066906190", label: "Support line" },
];

function scrollToSection(sectionId) {
  const target = document.getElementById(sectionId);
  if (!target) return;
  target.scrollIntoView({ behavior: "smooth", block: "start" });
}

function Home() {
  const [siteData, setSiteData] = useState(null);
  const [platformContent, setPlatformContent] = useState(DEFAULT_PLATFORM_CONTENT);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    let active = true;

    Promise.allSettled([
      api.get("/api/public/school-site"),
      api.get("/api/public/platform-content"),
    ])
      .then(([siteResult, contentResult]) => {
        if (!active) return;

        if (siteResult.status === "fulfilled") {
          setSiteData(siteResult.value.data || null);
        } else {
          setSiteData(null);
        }

        if (contentResult.status === "fulfilled") {
          setPlatformContent({ ...DEFAULT_PLATFORM_CONTENT, ...(contentResult.value.data?.data || {}) });
        } else {
          setPlatformContent(DEFAULT_PLATFORM_CONTENT);
        }
      })
      .finally(() => {
        if (active) setLoaded(true);
      });

    return () => {
      active = false;
    };
  }, []);

  if (!loaded) {
    return <div className="home-page"><p style={{ padding: 24 }}>Loading...</p></div>;
  }

  if (siteData?.is_tenant && siteData?.school) {
    return <PublicSchoolPortal page="home" initialSiteData={siteData} />;
  }

  return (
    <div className="home-page">
      <header className="home-nav">
        <div className="home-brand">
          <img className="home-brand-logo" src={brandArt} alt="Lytebridge Professional Services logo" />
          <div>
            <p className="home-brand-name">Lytebridge Professional Service LTD</p>
            <p className="home-brand-sub">School Management Platform</p>
          </div>
        </div>

        <nav className="home-anchor-nav" aria-label="Homepage sections">
          {ABOUT_CARDS.map((item) => (
            <button key={item.key} type="button" onClick={() => scrollToSection(item.key)}>
              {item.title}
            </button>
          ))}
        </nav>

        <Link className="home-login-btn" to="/login">
          Login
        </Link>
      </header>

      <main className="home-main">
        <section className="home-hero">
          <div className="home-hero-copy">
            <p className="home-kicker">LyteBridge Professional Services</p>
            <div className="home-hero-layout">
              <div className="home-hero-panel">
                <h1>Company Profile</h1>
                <p className="home-hero-note">
                  Explore who we are, what we believe, and how we help schools launch, manage, and scale their digital operations.
                </p>
              </div>
              <div className="home-hero-panel home-hero-panel--todo">
                <span>Content Area</span>
                <p>{platformContent.content_section_intro}</p>
                <ul className="home-hero-todo">
                  {(platformContent.content_todo_items || []).slice(0, 3).map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
        </section>

        <section className="home-section home-company-stack">
          {ABOUT_CARDS.map((card) => (
            <article key={card.key} id={card.key} className="home-overview-card home-overview-card--stacked">
              <div className="home-overview-card-head">
                <span>{card.title}</span>
              </div>
              <p>{platformContent[card.field]}</p>
            </article>
          ))}
        </section>

        <section className="home-section home-content-area">
          <div className="home-section-head home-content-head">
            <div>
              <h2>{platformContent.content_section_title || "Content Area"}</h2>
              <p>{platformContent.content_section_intro}</p>
            </div>
          </div>

          <div className="home-content-grid">
            {(platformContent.content_todo_items || []).map((item, index) => (
              <article key={`${item}-${index}`} className="home-content-card">
                <div className="home-content-index">0{index + 1}</div>
                <div className="home-content-copy">
                  <h3>Platform Content Item</h3>
                  <ul className="home-content-todo-list">
                    <li>{item}</li>
                  </ul>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="home-section">
          <div className="home-section-head">
            <h2>School Management Core Functions</h2>
            <p>Everything your school needs to run a modern digital operation.</p>
          </div>
          <div className="home-feature-grid">
            {CORE_FUNCTIONS.map((item) => (
              <article key={item} className="home-feature-card">
                {item}
              </article>
            ))}
          </div>
        </section>

        <section className="home-section home-contact">
          <div className="home-contact-copy-wrap">
            <div className="home-contact-photo home-contact-photo--flyer">
              <img src={coreFunctionsArt} alt="Lytebridge core functions flyer" />
            </div>

            <div className="home-contact-copy">
              <p className="home-contact-kicker">Launch Offer</p>
              <h2>Ready to launch your school portal?</h2>
              <p>
                Free 1 term trial at zero cost. Launch your school portal, explore the dashboard,
                and onboard your team before subscription begins.
              </p>
            </div>

            <div className="home-contact-summary">
              <article className="home-summary-card">
                <span>Subscription</span>
                <strong>N350</strong>
                <p>Per term, per student.</p>
              </article>
              <article className="home-summary-card">
                <span>Support</span>
                <strong>Fast Setup</strong>
                <p>Deployment, onboarding, and technical support for your school portal.</p>
              </article>
            </div>

            <div className="home-contact-grid">
              {CONTACT_LINES.map((line) => (
                <a key={`${line.phone}-${line.label}`} className="home-contact-card" href={`tel:${line.dial}`}>
                  <span className="home-contact-phone">{line.phone}</span>
                  <span className="home-contact-state">{line.label}</span>
                </a>
              ))}
            </div>

            <div className="home-contact-actions">
              <a href="tel:+2348027453306">Call 08027453306</a>
              <a href="https://wa.me/2348027453306" target="_blank" rel="noreferrer">
                WhatsApp Us
              </a>
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}

export default Home;
