import professorXcrwArt from "../../../assets/academics/professor-xcrw.svg";
import professorD7znArt from "../../../assets/academics/professor-d7zn.svg";
import bookshelvesArt from "../../../assets/academics/bookshelves.svg";
import "../../shared/PaymentsShowcase.css";
import "../AcademicSession.css";
import "./AcademicsHome.css";

export default function AcademicPageShell({
  pill = "School Admin Academics",
  title,
  subtitle,
  meta = [],
  children,
}) {
  return (
    <div className="payx-page payx-page--admin academic-inner">
      <section className="payx-hero academic-inner__hero">
        <div>
          <span className="payx-pill">{pill}</span>
          <h2 className="payx-title">{title}</h2>
          {subtitle ? <p className="payx-subtitle">{subtitle}</p> : null}
          {meta.length > 0 ? (
            <div className="payx-meta">
              {meta.filter(Boolean).map((item) => (
                <span key={item}>{item}</span>
              ))}
            </div>
          ) : null}
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main academics-home__art-main">
            <img src={professorXcrwArt} alt="" />
          </div>
          <div className="payx-art payx-art--card academics-home__art-card">
            <img src={professorD7znArt} alt="" />
          </div>
          <div className="payx-art payx-art--online academics-home__art-online">
            <img src={bookshelvesArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel academic-inner__panel">{children}</section>
    </div>
  );
}
