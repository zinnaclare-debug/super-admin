import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import api from "../services/api";
import PublicSchoolPortal from "./public-school/PublicSchoolPortal";
import brandArt from "../assets/home/lytebridge-brand.jpg";
import coreFunctionsArt from "../assets/home/lytebridge-core-functions.jpeg";
import unlockPotentialArt from "../assets/home/lytebridge-unlock-potential.jpeg";
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

const HIGHLIGHTS = [
  "Free 1 term trial at zero cost.",
  "Subsequently, 350 per term per student.",
  "Fast onboarding with support for school owners, staff, parents, and learners.",
  "Modern digital workflows that reduce paper use and improve communication.",
];

const CONTACT_LINES = [
  { phone: "+2348027453306", dial: "+2348027453306", label: "Call us now" },
  { phone: "+2349136806652", dial: "+2349136806652", label: "WhatsApp and phone" },
  { phone: "+2347066906190", dial: "+2347066906190", label: "Support line" },
];

function Home() {
  const [siteData, setSiteData] = useState(null);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    let active = true;

    api
      .get("/api/public/school-site")
      .then((res) => {
        if (!active) return;
        setSiteData(res.data || null);
      })
      .catch(() => {
        if (!active) return;
        setSiteData(null);
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
        <Link className="home-login-btn" to="/login">
          Login
        </Link>
      </header>

      <main className="home-main">
        <section className="home-hero">
          <div className="home-hero-copy">
            <p className="home-kicker">Modern ICT Solution For Schools</p>
            <h1>Upgrade your school with a smarter digital platform built for daily operations.</h1>
            <p>
              Lytebridge Professional Service LTD helps schools manage results, payments, virtual
              classes, communication, parent engagement, and learning delivery from one reliable
              portal.
            </p>
            <div className="home-hero-metrics">
              <article>
                <strong>Free 1 Term Trial</strong>
                <span>Start at zero cost</span>
              </article>
              <article>
                <strong>N350 / Student</strong>
                <span>Per term afterwards</span>
              </article>
              <article>
                <strong>Go Paperless</strong>
                <span>Save cost and time</span>
              </article>
            </div>
            <div className="home-hero-badges">
              {HIGHLIGHTS.map((item) => (
                <span key={item}>{item}</span>
              ))}
            </div>
            <div className="home-hero-actions">
              <a href="tel:+2348027453306">Call +2348027453306</a>
              <a href="https://wa.me/2348027453306" target="_blank" rel="noreferrer">
                WhatsApp Us
              </a>
            </div>
          </div>

          <div className="home-hero-visual">
            <div className="home-hero-photo-stack">
              <img src={unlockPotentialArt} alt="Lytebridge unlock potential flyer" />
              <div className="home-hero-aside">
                <p>School Management Platform</p>
                <h2>Built for school owners that want speed, visibility, and growth.</h2>
                <span>Results, broadsheet, CBT, payments, portal, mobile app and more.</span>
              </div>
            </div>
          </div>
        </section>

        <section className="home-section">
          <div className="home-section-head">
            <h2>Core Functions</h2>
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

        <section className="home-section home-pricing">
          <div className="home-pricing-grid">
            <article className="home-pricing-card home-pricing-card--wide">
              <p className="home-pricing-label">Launch Offer</p>
              <h2>Free 1 term trial at zero cost.</h2>
              <p>
                Launch your school portal, explore the dashboard, and onboard your team before
                subscription begins.
              </p>
            </article>
            <article className="home-pricing-card">
              <p className="home-pricing-label">Subscription</p>
              <p className="home-price">N350</p>
              <p>Per term, per student.</p>
            </article>
            <article className="home-pricing-card home-pricing-card--contact">
              <p className="home-pricing-label">Need a quick start?</p>
              <div className="home-pricing-actions">
                <a href="tel:+2348027453306">Call Us</a>
                <a href="https://wa.me/2348027453306" target="_blank" rel="noreferrer">
                  WhatsApp Us
                </a>
              </div>
            </article>
          </div>
        </section>

        <section className="home-section home-contact">
          <div className="home-contact-copy">
            <h2>Ready to launch your school portal?</h2>
            <p>
              Reach us for onboarding, deployment, support, and fast setup for your school portal.
            </p>
            <div className="home-contact-grid">
              {CONTACT_LINES.map((line) => (
                <a key={`${line.phone}-${line.label}`} className="home-contact-card" href={`tel:${line.dial}`}>
                  <span className="home-contact-phone">{line.phone}</span>
                  <span className="home-contact-state">{line.label}</span>
                </a>
              ))}
            </div>
            <div className="home-contact-actions">
              <a href="tel:+2348027453306">
                Call 08027453306
              </a>
              <a href="https://wa.me/2348027453306" target="_blank" rel="noreferrer">
                WhatsApp Us
              </a>
            </div>
          </div>
          <div className="home-contact-gallery">
            <div className="home-contact-photo home-contact-photo--primary">
              <img src={coreFunctionsArt} alt="Lytebridge core functions flyer" />
            </div>
            <div className="home-contact-photo home-contact-photo--secondary">
              <img src={unlockPotentialArt} alt="Lytebridge school innovation flyer" />
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}

export default Home;
