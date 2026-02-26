import { useEffect, useState } from "react";
import api from "../../../services/api";
import onlineTestArt from "../../../assets/topics/online-test.svg";
import bloggingArt from "../../../assets/topics/blogging.svg";
import "../../shared/TopicsShowcase.css";

export default function StudentTopicsHome() {
  const [subjects, setSubjects] = useState([]);
  const [materials, setMaterials] = useState([]);
  const [termSubjectId, setTermSubjectId] = useState("");
  const [loading, setLoading] = useState(true);

  const loadSubjects = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/topics/subjects");
      const items = res.data?.data || [];
      setSubjects(items);
      if (items.length > 0) {
        setTermSubjectId(String(items[0].term_subject_id));
      } else {
        setTermSubjectId("");
        setMaterials([]);
      }
    } catch {
      alert("Failed to load topics subjects");
    } finally {
      setLoading(false);
    }
  };

  const loadMaterials = async (termId) => {
    if (!termId) {
      setMaterials([]);
      return;
    }
    try {
      const res = await api.get("/api/student/topics", {
        params: { term_subject_id: termId },
      });
      setMaterials(res.data?.data || []);
    } catch {
      setMaterials([]);
    }
  };

  useEffect(() => {
    loadSubjects();
  }, []);

  useEffect(() => {
    loadMaterials(termSubjectId);
  }, [termSubjectId]);

  return (
    <div className="tps-page tps-page--student">
      <section className="tps-hero">
        <div>
          <span className="tps-pill">Student Topics</span>
          <h2 className="tps-title">Study topic materials by subject</h2>
          <p className="tps-subtitle">
            Select a subject and access all topic files your teachers have shared for your class and term.
          </p>
          <div className="tps-meta">
            <span>{loading ? "Loading..." : `${subjects.length} subject${subjects.length === 1 ? "" : "s"}`}</span>
            <span>{`${materials.length} material${materials.length === 1 ? "" : "s"}`}</span>
          </div>
        </div>

        <div className="tps-hero-art" aria-hidden="true">
          <div className="tps-art tps-art--main">
            <img src={onlineTestArt} alt="" />
          </div>
          <div className="tps-art tps-art--alt">
            <img src={bloggingArt} alt="" />
          </div>
        </div>
      </section>

      <section className="tps-panel">
        {loading ? <p className="tps-state tps-state--loading">Loading topics...</p> : null}
        {!loading && subjects.length === 0 ? (
          <p className="tps-state tps-state--empty">No topic subjects available for your current class and current term.</p>
        ) : null}

        {!loading && subjects.length > 0 ? (
          <>
            <div className="tps-filter">
              <label htmlFor="student-topics-subject">Subject</label>
              <select
                id="student-topics-subject"
                className="tps-select"
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

            <div className="tps-table-wrap">
              <table className="tps-table">
                <thead>
                  <tr>
                    <th style={{ width: 70 }}>S/N</th>
                    <th>Title</th>
                    <th style={{ width: 180 }}>File</th>
                  </tr>
                </thead>
                <tbody>
                  {materials.map((m, idx) => (
                    <tr key={m.id}>
                      <td>{idx + 1}</td>
                      <td>{m.title || m.original_name || "-"}</td>
                      <td>
                        <a className="tps-link" href={m.file_url} target="_blank" rel="noreferrer">
                          View / Download
                        </a>
                      </td>
                    </tr>
                  ))}
                  {materials.length === 0 ? (
                    <tr>
                      <td colSpan="3">No topic materials posted yet.</td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </>
        ) : null}
      </section>
    </div>
  );
}
