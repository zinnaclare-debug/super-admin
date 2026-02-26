import { useEffect, useState } from "react";
import api from "../../../services/api";
import cbtMainArt from "../../../assets/cbt-dashboard/online-meetings.svg";
import cbtResumeArt from "../../../assets/cbt-dashboard/online-resume.svg";
import cbtProfilesArt from "../../../assets/cbt-dashboard/swipe-profiles.svg";
import "../../shared/CbtShowcase.css";

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export default function StudentCBTHome() {
  const [exams, setExams] = useState([]);
  const [selectedExam, setSelectedExam] = useState(null);
  const [questions, setQuestions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingQuestions, setLoadingQuestions] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/cbt/exams");
      setExams(res.data?.data || []);
    } catch {
      alert("Failed to load CBT");
    } finally {
      setLoading(false);
    }
  };

  const openExam = async (exam) => {
    if (!exam?.is_open) {
      alert("This CBT is not open yet. Check the exam start time.");
      return;
    }

    setSelectedExam(exam);
    setLoadingQuestions(true);
    try {
      const res = await api.get(`/api/student/cbt/exams/${exam.id}/questions`);
      setQuestions(res.data?.data || []);
    } catch (err) {
      setQuestions([]);
      alert(err?.response?.data?.message || "Failed to load exam questions");
    } finally {
      setLoadingQuestions(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  return (
    <div className="cbx-page cbx-page--student">
      <section className="cbx-hero">
        <div>
          <span className="cbx-pill">Student CBT</span>
          <h2 className="cbx-title">View active computer-based exams</h2>
          <p className="cbx-subtitle">
            Monitor exam schedules, open available sessions, and preview exam questions in one place.
          </p>
          <div className="cbx-meta">
            <span>{loading ? "Loading..." : `${exams.length} exam${exams.length === 1 ? "" : "s"}`}</span>
            <span>{`${questions.length} question${questions.length === 1 ? "" : "s"} loaded`}</span>
          </div>
        </div>

        <div className="cbx-hero-art" aria-hidden="true">
          <div className="cbx-art cbx-art--main">
            <img src={cbtMainArt} alt="" />
          </div>
          <div className="cbx-art cbx-art--resume">
            <img src={cbtResumeArt} alt="" />
          </div>
          <div className="cbx-art cbx-art--profiles">
            <img src={cbtProfilesArt} alt="" />
          </div>
        </div>
      </section>

      <section className="cbx-panel">
        {loading ? <p className="cbx-state cbx-state--loading">Loading CBT exams...</p> : null}
        {!loading && exams.length === 0 ? (
          <p className="cbx-state cbx-state--empty">No published CBT exam for your current class and current term.</p>
        ) : null}

        {!loading && exams.length > 0 ? (
          <div className="cbx-table-wrap">
            <table className="cbx-table">
              <thead>
                <tr>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Title</th>
                  <th>Subject</th>
                  <th>Window</th>
                  <th>Status</th>
                  <th style={{ width: 140 }}>Questions</th>
                </tr>
              </thead>
              <tbody>
                {exams.map((x, idx) => (
                  <tr key={x.id}>
                    <td>{idx + 1}</td>
                    <td>{x.title}</td>
                    <td>{x.subject_name || "-"}</td>
                    <td>
                      {formatDate(x.starts_at)} - {formatDate(x.ends_at)}
                    </td>
                    <td>{x.is_open ? "Open" : "Closed/Upcoming"}</td>
                    <td>
                      <button className="cbx-btn cbx-btn--soft" onClick={() => openExam(x)} disabled={!x.is_open}>
                        {x.is_open ? "View" : "Not Open"}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>

      {selectedExam ? (
        <section className="cbx-panel">
          <h3 style={{ marginTop: 0 }}>{selectedExam.title} - Questions</h3>
          {loadingQuestions ? (
            <p className="cbx-state cbx-state--loading">Loading questions...</p>
          ) : (
            <div className="cbx-table-wrap">
              <table className="cbx-table">
                <thead>
                  <tr>
                    <th style={{ width: 70 }}>S/N</th>
                    <th>Question</th>
                    <th>Options</th>
                  </tr>
                </thead>
                <tbody>
                  {questions.map((q, idx) => (
                    <tr key={q.id}>
                      <td>{idx + 1}</td>
                      <td>{q.question_text}</td>
                      <td>
                        <div>A. {q.option_a}</div>
                        <div>B. {q.option_b}</div>
                        <div>C. {q.option_c}</div>
                        <div>D. {q.option_d}</div>
                      </td>
                    </tr>
                  ))}
                  {questions.length === 0 ? (
                    <tr>
                      <td colSpan="3">No questions in this exam yet.</td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          )}
        </section>
      ) : null}
    </div>
  );
}
