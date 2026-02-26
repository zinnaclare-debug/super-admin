import { useEffect, useState } from "react";
import api from "../../../services/api";
import workingTogetherArt from "../../../assets/class-activities/working-together.svg";
import gradingPapersArt from "../../../assets/class-activities/grading-papers.svg";
import learningSketchArt from "../../../assets/class-activities/learning-to-sketch.svg";
import "./ClassActivitiesHome.css";

function formatDate(value) {
  if (!value) return null;
  try {
    return new Date(value).toLocaleString();
  } catch {
    return null;
  }
}

export default function StudentClassActivitiesHome() {
  const [subjects, setSubjects] = useState([]);
  const [items, setItems] = useState([]);
  const [termSubjectId, setTermSubjectId] = useState("");
  const [loading, setLoading] = useState(true);

  const loadSubjects = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/class-activities/subjects");
      const rows = res.data?.data || [];
      setSubjects(rows);
      if (rows.length > 0) {
        setTermSubjectId(String(rows[0].term_subject_id));
      } else {
        setTermSubjectId("");
        setItems([]);
      }
    } catch {
      alert("Failed to load class activities");
    } finally {
      setLoading(false);
    }
  };

  const loadItems = async (termId) => {
    if (!termId) {
      setItems([]);
      return;
    }
    try {
      const res = await api.get("/api/student/class-activities", {
        params: { term_subject_id: termId },
      });
      setItems(res.data?.data || []);
    } catch {
      setItems([]);
    }
  };

  useEffect(() => {
    loadSubjects();
  }, []);

  useEffect(() => {
    loadItems(termSubjectId);
  }, [termSubjectId]);

  return (
    <div className="sca-page">
      <section className="sca-hero">
        <div>
          <span className="sca-pill">Student Class Activities</span>
          <h2>Learn, download, and revise with ease</h2>
          <p className="sca-subtitle">
            Find all class resources in one place. Select a subject to view the latest activities shared by your teachers.
          </p>

          <div className="sca-metrics">
            <span>{loading ? "Loading..." : `${subjects.length} subject${subjects.length === 1 ? "" : "s"}`}</span>
            <span>{loading ? "Syncing..." : `${items.length} activit${items.length === 1 ? "y" : "ies"} shown`}</span>
          </div>
        </div>

        <div className="sca-hero-art" aria-hidden="true">
          <div className="sca-art sca-art--main">
            <img src={workingTogetherArt} alt="" />
          </div>
          <div className="sca-art sca-art--grading">
            <img src={gradingPapersArt} alt="" />
          </div>
          <div className="sca-art sca-art--sketch">
            <img src={learningSketchArt} alt="" />
          </div>
        </div>
      </section>

      <section className="sca-panel">
        {loading ? (
          <p className="sca-state sca-state--loading">Loading class activities...</p>
        ) : subjects.length === 0 ? (
          <p className="sca-state sca-state--empty">
            No class activities available for your current class and current term.
          </p>
        ) : (
          <>
            <div className="sca-filter">
              <label htmlFor="sca-subject">Subject</label>
              <select
                id="sca-subject"
                value={termSubjectId}
                onChange={(e) => setTermSubjectId(e.target.value)}
              >
                {subjects.map((s) => (
                  <option key={s.term_subject_id} value={s.term_subject_id}>
                    {s.subject_name}
                  </option>
                ))}
              </select>
            </div>

            {items.length === 0 ? (
              <p className="sca-state sca-state--empty">No activities posted yet for this subject.</p>
            ) : (
              <div className="sca-grid">
                {items.map((x, idx) => {
                  const postedAt = formatDate(x.created_at || x.published_at || x.updated_at);
                  return (
                    <article key={x.id} className="sca-card">
                      <div className="sca-card-head">
                        <span className="sca-index">{idx + 1}</span>
                        <span className="sca-subject">{x.subject_name || "Subject"}</span>
                      </div>
                      <h3>{x.title}</h3>
                      {x.description ? <p className="sca-desc">{x.description}</p> : null}
                      <div className="sca-meta">
                        <small>{postedAt ? `Posted: ${postedAt}` : "Resource file"}</small>
                      </div>
                      <a className="sca-download" href={x.file_url} target="_blank" rel="noreferrer">
                        Download Activity
                      </a>
                    </article>
                  );
                })}
              </div>
            )}
          </>
        )}
      </section>
    </div>
  );
}
