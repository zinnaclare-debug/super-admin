import { useEffect, useState } from "react";
import api from "../../../services/api";
import subjectMainArt from "../../../assets/subject-dashboard/multitasking.svg";
import subjectExamArt from "../../../assets/subject-dashboard/exam-prep.svg";
import subjectReadArt from "../../../assets/subject-dashboard/relaxed-reading.svg";
import "../../shared/SubjectsShowcase.css";

export default function StudentSubjectsHome() {
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError("");
      try {
        const res = await api.get("/api/student/topics/subjects");
        setSubjects(res.data?.data || []);
      } catch (e) {
        setError(e?.response?.data?.message || "Failed to load enrolled subjects");
        setSubjects([]);
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  return (
    <div className="sbx-page sbx-page--student">
      <section className="sbx-hero">
        <div>
          <span className="sbx-pill">Student Subjects</span>
          <h2 className="sbx-title">Track your enrolled subjects with ease</h2>
          <p className="sbx-subtitle">
            Review all subjects registered for your current class and term from one organized workspace.
          </p>
          <div className="sbx-meta">
            <span>{loading ? "Loading..." : `${subjects.length} subject${subjects.length === 1 ? "" : "s"}`}</span>
            <span>Current class enrollment</span>
          </div>
        </div>

        <div className="sbx-hero-art" aria-hidden="true">
          <div className="sbx-art sbx-art--main">
            <img src={subjectMainArt} alt="" />
          </div>
          <div className="sbx-art sbx-art--exam">
            <img src={subjectExamArt} alt="" />
          </div>
          <div className="sbx-art sbx-art--read">
            <img src={subjectReadArt} alt="" />
          </div>
        </div>
      </section>

      <section className="sbx-panel">
        {loading ? <p className="sbx-state sbx-state--loading">Loading subjects...</p> : null}
        {!loading && error ? <p className="sbx-state sbx-state--error">{error}</p> : null}
        {!loading && !error && subjects.length === 0 ? (
          <p className="sbx-state sbx-state--empty">No enrolled subjects found for your current class and term.</p>
        ) : null}

        {!loading && !error && subjects.length > 0 ? (
          <div className="sbx-table-wrap">
            <table className="sbx-table">
              <thead>
                <tr>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Subject Name</th>
                  <th style={{ width: 170 }}>Subject Code</th>
                </tr>
              </thead>
              <tbody>
                {subjects.map((s, idx) => (
                  <tr key={s.term_subject_id || `${s.subject_name}-${idx}`}>
                    <td>{idx + 1}</td>
                    <td>{s.subject_name || "-"}</td>
                    <td>{s.subject_code || "-"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>
    </div>
  );
}
