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

const SERVICE_GROUPS = [
  {
    title: "Education Services",
    items: [
      "School Setup & Registration",
      "Academic Planning & Curriculum Support",
      "Staff Training & Capacity Building",
      "School Administration Consulting",
      "Result & Report Sheet Design",
      "CBT Examination Setup",
    ],
  },
  {
    title: "ICT Services",
    items: [
      "Website Design",
      "School Portal Development",
      "Mobile App Development",
      "Computer Training",
      "Networking & System Installation",
      "Online Learning Platform Setup",
      "Technical Support",
    ],
  },
  {
    title: "School Management Software",
    items: [
      "Student Records Management",
      "Online Results & Broadsheet",
      "School Fees Management",
      "Attendance Management",
      "Parent Portal Access",
      "SMS & Notification System",
      "CBT Examination System",
      "Staff Management",
    ],
  },
  {
    title: "Digital School Solutions",
    items: [
      "Online School Website",
      "Virtual Classes",
      "Learning Portal",
      "Mobile Application",
      "Online Payments",
      "Transcript & Broadsheet Processing",
    ],
  },
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
            <p className="home-kicker">LyteBridge Professional Services</p>
            <h1>Smart Solutions for Smart Schools</h1>
            <p className="home-hero-intro">
              Education services, ICT support, school software, and digital learning solutions for
              modern institutions.
            </p>

            <div className="home-service-grid">
              {SERVICE_GROUPS.map((group) => (
                <article key={group.title} className="home-service-card">
                  <h2>{group.title}</h2>
                  <ul>
                    {group.items.map((item) => (
                      <li key={item}>{item}</li>
                    ))}
                  </ul>
                </article>
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

        <section className="home-section home-contact">
          <div className="home-contact-photo home-contact-photo--primary">
            <img src={coreFunctionsArt} alt="Lytebridge core functions flyer" />
          </div>

          <div className="home-contact-copy-wrap">
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
