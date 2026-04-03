import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";
import professorXcrwArt from "../../../assets/academics/professor-xcrw.svg";
import professorD7znArt from "../../../assets/academics/professor-d7zn.svg";
import bookshelvesArt from "../../../assets/academics/bookshelves.svg";
import "../../shared/PaymentsShowcase.css";
import "./AcademicsHome.css";

const prettyLevel = (value) =>
  String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

export default function AcademicsHome() {
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [levelFilter, setLevelFilter] = useState("");
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/school-admin/academics");
      setData(res.data.data);
    } catch {
      alert("Failed to load academics");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const activeLevels = data?.active_levels || [];
  const classes = data?.classes || [];

  const filtered = useMemo(() => {
    if (!levelFilter) return classes;
    return classes.filter((c) => c.level === levelFilter);
  }, [classes, levelFilter]);

  if (loading) return <p>Loading...</p>;
  if (!data) return <p>No current academic session. Set a session to CURRENT first.</p>;

  return (
    <div className="payx-page payx-page--admin">
      <section className="payx-hero academics-home__hero">
        <div>
          <span className="payx-pill">School Admin Academics</span>
          <h2 className="payx-title">Organize classes and subjects from one clearer academics workspace.</h2>
          <p className="payx-subtitle">
            Filter by education level, review active classes, and jump straight into subject management with the same polished layout used across the other admin pages.
          </p>
          <div className="payx-meta">
            <span>{data.session?.session_name || data.session?.academic_year || "Session -"}</span>
            <span>{activeLevels.length} levels</span>
            <span>{classes.length} classes</span>
          </div>
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

      <section className="payx-panel academics-home__panel">
        <div className="academics-home__toolbar">
          <div>
            <h3>Academics Overview</h3>
            <p>Session: {data.session?.session_name || data.session?.academic_year || "Current session"}</p>
          </div>

          <div className="academics-home__toolbar-actions">
            <select value={levelFilter} onChange={(e) => setLevelFilter(e.target.value)}>
              <option value="">All Levels</option>
              {activeLevels.map((lvl) => (
                <option key={lvl} value={lvl}>{prettyLevel(lvl)}</option>
              ))}
            </select>
          </div>
        </div>

        <div className="academics-home__filters">
          {activeLevels.map((lvl) => (
            <button
              key={lvl}
              type="button"
              className={`academics-home__filter-btn${levelFilter === lvl ? " academics-home__filter-btn--active" : ""}`}
              onClick={() => setLevelFilter(lvl)}
            >
              {prettyLevel(lvl)}
            </button>
          ))}
          {levelFilter ? (
            <button type="button" className="academics-home__filter-btn academics-home__filter-btn--clear" onClick={() => setLevelFilter("")}>
              Clear
            </button>
          ) : null}
        </div>

        <div className="payx-card academics-home__summary-card">
          <div className="payx-kv">
            <div className="payx-row">
              <span className="payx-label">Active Levels</span>
              <span className="payx-value">{activeLevels.length}</span>
            </div>
            <div className="payx-row">
              <span className="payx-label">Visible Classes</span>
              <span className="payx-value">{filtered.length}</span>
            </div>
          </div>
        </div>

        <div className="payx-card">
          <div className="payx-table-wrap">
            <table className="payx-table">
              <thead>
                <tr>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Level</th>
                  <th>Class</th>
                  <th style={{ width: 240 }}>Action</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((c, idx) => (
                  <tr key={c.id}>
                    <td>{idx + 1}</td>
                    <td><strong>{prettyLevel(c.level)}</strong></td>
                    <td>{c.name}</td>
                    <td>
                      <button className="payx-btn" onClick={() => navigate(`/school/admin/academics/classes/${c.id}/subjects`)}>
                        Create / Manage Subjects
                      </button>
                    </td>
                  </tr>
                ))}

                {filtered.length === 0 ? (
                  <tr>
                    <td colSpan="4">No classes found for this level.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  );
}

