import { Link } from "react-router-dom";
import heroArt from "../assets/dashboard/branding.svg";
import modulesArt from "../assets/dashboard/modules.svg";
import flyerArt from "../assets/home/lytebridge-flyer.jpeg";
import brandArt from "../assets/home/lytebridge-brand.jpg";
import "./Home.css";

const FEATURE_LIST = [
  "Notes Upload: Teachers can upload lesson notes and learning materials for students.",
  "Announcement Desk: Central notice board for school-wide and class-specific announcements.",
  "Class Activities: Assignment submission, quizzes, and interactive classroom tasks.",
  "Timetable Access for Parents: Enables parents to monitor class schedules and academic activities.",
  "Integration with Social Media and School Website: Direct links to official social media platforms and the school website.",
  "Question Bank: Centralized repository of exam and practice questions.",
  "AI-Powered Questions (Standard-Based): Intelligent question generation aligned with approved curriculum standards.",
  "CBT Examination Features: Secure computer-based testing with automated marking and monitoring.",
  "Results Management (Modified to School Standard): Accurate result processing and reporting customized to school formats.",
  "E-Library: Digital library with textbooks, reference materials, and past questions.",
  "Virtual Class (Google Meet Integration): Live online classes with real-time teacher-student interaction.",
  "Mobile Application: User-friendly mobile app for students, parents, and teachers.",
  "Cloud Upload and Data Storage: Secure cloud-based storage for academic records and learning materials.",
  "Secure Server: High-level data protection for data safety and system reliability.",
  "Google Drive Integration / Print and Store Options: Flexible backup, printing, and physical record options.",
  "Beautiful and User-Friendly Interface: Clean, modern design for easy navigation and better user experience.",
  "Teachers' Training and Support: Onboarding, continuous training, and technical support.",
];

const EXTRA_VALUE = [
  "Multi-school tenancy with subdomain setup for each school.",
  "Role-based access for super admin, school admin, staff, students, and parents.",
  "Real-time operational visibility for academics, attendance, assessments, and billing.",
  "Deployment and migration support for schools moving from manual to digital workflows.",
];

const CONTACT_LINES = [
  { phone: "+234 806 798 8449", dial: "+2348067988449", location: "FCT" },
  { phone: "+234 9136806652", dial: "+2349136806652", location: "Ekiti State" },
  { phone: "+234 816 986 6477", dial: "+2348169866477", location: "Kwara State" },
  { phone: "+234 706 690 6190", dial: "+2347066906190", location: "FCT" },
];

function Home() {
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
            <p className="home-kicker">Digital School Operations</p>
            <h1>Build a smarter school with one reliable platform.</h1>
            <p>
              Lytebridge helps schools run teaching, examinations, reporting, communication, and
              parent engagement from one secure cloud platform.
            </p>
            <div className="home-hero-badges">
              <span>One Term Free Trial</span>
              <span>N350 / Student / Term</span>
              <span>One-time Setup: N40,000</span>
            </div>
            <div className="home-hero-actions">
              <a href="tel:08067988449">Call 08067988449</a>
              <a href="https://wa.me/2348067988449" target="_blank" rel="noreferrer">
                WhatsApp Us
              </a>
            </div>
          </div>

          <div className="home-hero-visual">
            <img src={heroArt} alt="School platform visual" />
          </div>
        </section>

        <section className="home-section">
          <div className="home-section-head">
            <h2>Key Features</h2>
            <p>Designed for teachers, school admins, students, and parents.</p>
          </div>
          <div className="home-feature-grid">
            {FEATURE_LIST.map((item) => (
              <article key={item} className="home-feature-card">
                {item}
              </article>
            ))}
          </div>
        </section>

        <section className="home-section home-pricing">
          <div className="home-section-head">
            <h2>Pricing & Subscription</h2>
            <p>Simple pricing for schools of all sizes.</p>
          </div>
          <div className="home-pricing-grid">
            <article>
              <h3>Subscription Fee</h3>
              <p className="home-price">N350</p>
              <p>Per student, per term.</p>
            </article>
            <article>
              <h3>Free Trial</h3>
              <p className="home-price">1 Term</p>
              <p>Free trial for new schools.</p>
            </article>
            <article>
              <h3>Installation Fee</h3>
              <p className="home-price">N40,000</p>
              <p>One-time setup, configuration, and onboarding.</p>
            </article>
          </div>
        </section>

        <section className="home-section home-section--value">
          <div className="home-section-head">
            <h2>Additional Value</h2>
            <p>What schools also get from Lytebridge.</p>
          </div>
          <div className="home-value-wrap">
            <ul>
              {EXTRA_VALUE.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
            <img src={flyerArt} alt="Lytebridge school management platform brochure" />
          </div>
        </section>

        <section className="home-section home-contact">
          <img src={modulesArt} alt="Modules and workflows" />
          <div className="home-contact-copy">
            <h2>Ready to launch your school portal?</h2>
            <p>
              Reach us for onboarding, deployment, support, and account setup across Nigeria.
            </p>
            <div className="home-contact-grid">
              {CONTACT_LINES.map((line) => (
                <a key={`${line.phone}-${line.location}`} className="home-contact-card" href={`tel:${line.dial}`}>
                  <span className="home-contact-phone">{line.phone}</span>
                  <span className="home-contact-state">{line.location}</span>
                </a>
              ))}
            </div>
            <div className="home-contact-actions">
              <a href="mailto:lytebridgeprofessionalservices@gmail.com">
                lytebridgeprofessionalservices@gmail.com
              </a>
              <a href="https://wa.me/2348067988449" target="_blank" rel="noreferrer">
                WhatsApp Chat
              </a>
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}

export default Home;
