import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
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

export default function CBTHome() {
  const [subjects, setSubjects] = useState([]);
  const [exams, setExams] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedExamId, setSelectedExamId] = useState("");
  const [examQuestions, setExamQuestions] = useState([]);

  const [form, setForm] = useState({
    term_subject_id: "",
    title: "",
    instructions: "",
    starts_at: "",
    ends_at: "",
    duration_minutes: 60,
    status: "draft",
    security_policy: {
      fullscreen_required: true,
      block_copy_paste: true,
      block_tab_switch: true,
      no_face_timeout_seconds: 30,
      max_warnings: 3,
      auto_submit_on_violation: true,
      logout_on_violation: true,
      ai_proctoring_enabled: true,
    },
  });

  const load = async () => {
    setLoading(true);
    const [subjectsRes, examsRes] = await Promise.allSettled([
      api.get("/api/staff/cbt/subjects"),
      api.get("/api/staff/cbt/exams"),
    ]);

    if (subjectsRes.status === "fulfilled") {
      setSubjects(subjectsRes.value.data?.data || []);
    } else {
      setSubjects([]);
      console.warn("CBT subjects failed:", subjectsRes.reason?.response?.data || subjectsRes.reason?.message);
    }

    if (examsRes.status === "fulfilled") {
      setExams(examsRes.value.data?.data || []);
    } else {
      setExams([]);
      console.warn("CBT exams failed:", examsRes.reason?.response?.data || examsRes.reason?.message);
    }

    setLoading(false);
  };

  useEffect(() => {
    load();
  }, []);

  const createExam = async (e) => {
    e.preventDefault();
    try {
      await api.post("/api/staff/cbt/exams", {
        ...form,
        term_subject_id: Number(form.term_subject_id),
      });
      await load();
      alert("CBT exam created");
    } catch (err) {
      alert(err?.response?.data?.message || "Create failed");
    }
  };

  const removeExam = async (id) => {
    if (!window.confirm("Delete this exam?")) return;
    try {
      await api.delete(`/api/staff/cbt/exams/${id}`);
      if (String(selectedExamId) === String(id)) {
        setSelectedExamId("");
        setExamQuestions([]);
      }
      await load();
    } catch (err) {
      alert(err?.response?.data?.message || "Delete failed");
    }
  };

  const openExam = async (id) => {
    setSelectedExamId(id);
    try {
      const res = await api.get(`/api/staff/cbt/exams/${id}/questions`);
      setExamQuestions(res.data?.data || []);
    } catch {
      alert("Failed to load exam questions");
    }
  };

  return (
    <StaffFeatureLayout title="CBT (Staff)">
      <div className="cbx-page cbx-page--staff">
        <section className="cbx-hero">
          <div>
            <span className="cbx-pill">Staff CBT Desk</span>
            <h2 className="cbx-title">Create and manage CBT exams professionally</h2>
            <p className="cbx-subtitle">
              Set exam windows, enforce security policy, and monitor question exports by subject.
            </p>
            <div className="cbx-meta">
              <span>{loading ? "Loading..." : `${exams.length} exam${exams.length === 1 ? "" : "s"}`}</span>
              <span>{`${subjects.length} assigned subject${subjects.length === 1 ? "" : "s"}`}</span>
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
          <h3 style={{ marginTop: 0 }}>Create CBT Exam</h3>
          <form onSubmit={createExam} className="cbx-form cbx-form--wide">
            <select
              className="cbx-field"
              value={form.term_subject_id}
              onChange={(e) => setForm({ ...form, term_subject_id: e.target.value })}
              required
            >
              <option value="">Select assigned subject</option>
              {subjects.map((s) => (
                <option key={s.term_subject_id} value={s.term_subject_id}>
                  {s.subject_name} - {s.class_name} ({s.term_name})
                </option>
              ))}
            </select>

            <input
              className="cbx-field"
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
              placeholder="Exam title"
              required
            />
            <textarea
              className="cbx-field"
              rows={2}
              value={form.instructions}
              onChange={(e) => setForm({ ...form, instructions: e.target.value })}
              placeholder="Instructions"
            />
            <div className="cbx-form-grid">
              <input
                className="cbx-field"
                type="datetime-local"
                value={form.starts_at}
                onChange={(e) => setForm({ ...form, starts_at: e.target.value })}
                required
              />
              <input
                className="cbx-field"
                type="datetime-local"
                value={form.ends_at}
                onChange={(e) => setForm({ ...form, ends_at: e.target.value })}
                required
              />
              <input
                className="cbx-field"
                type="number"
                min="1"
                max="300"
                value={form.duration_minutes}
                onChange={(e) => setForm({ ...form, duration_minutes: Number(e.target.value) })}
              />
              <select className="cbx-field" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                <option value="draft">Draft</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <small className="cbx-note">Note: School admin publishes CBT to make it visible to students.</small>

            <div className="cbx-security">
              <strong>Security Policy (Exam Runtime Controls)</strong>
              <div className="cbx-security-grid">
                {[
                  ["fullscreen_required", "Require Fullscreen"],
                  ["block_copy_paste", "Block Copy/Paste"],
                  ["block_tab_switch", "Block Tab Switch"],
                  ["auto_submit_on_violation", "Auto-submit on violation"],
                  ["logout_on_violation", "Logout on major violation"],
                  ["ai_proctoring_enabled", "Enable AI proctoring hooks"],
                ].map(([k, label]) => (
                  <label key={k} className="cbx-check">
                    <input
                      type="checkbox"
                      checked={!!form.security_policy[k]}
                      onChange={(e) =>
                        setForm({
                          ...form,
                          security_policy: { ...form.security_policy, [k]: e.target.checked },
                        })
                      }
                    />{" "}
                    {label}
                  </label>
                ))}
              </div>
              <div className="cbx-form-grid cbx-form-grid--security">
                <input
                  className="cbx-field"
                  type="number"
                  min="10"
                  max="300"
                  value={form.security_policy.no_face_timeout_seconds}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      security_policy: {
                        ...form.security_policy,
                        no_face_timeout_seconds: Number(e.target.value),
                      },
                    })
                  }
                  placeholder="No-face timeout seconds"
                />
                <input
                  className="cbx-field"
                  type="number"
                  min="1"
                  max="20"
                  value={form.security_policy.max_warnings}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      security_policy: { ...form.security_policy, max_warnings: Number(e.target.value) },
                    })
                  }
                  placeholder="Max warnings"
                />
              </div>
            </div>
            <button className="cbx-btn" type="submit">Create CBT</button>
          </form>
        </section>

        <section className="cbx-panel">
          {loading ? (
            <p className="cbx-state cbx-state--loading">Loading CBT exams...</p>
          ) : (
            <div className="cbx-table-wrap">
              <table className="cbx-table">
                <thead>
                  <tr>
                    <th>S/N</th>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Window</th>
                    <th>Status</th>
                    <th>Questions</th>
                    <th>Action</th>
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
                      <td>{x.status}</td>
                      <td>
                        <button className="cbx-btn cbx-btn--soft" onClick={() => openExam(x.id)}>View</button>
                      </td>
                      <td>
                        <button className="cbx-btn cbx-btn--danger" onClick={() => removeExam(x.id)}>Delete</button>
                      </td>
                    </tr>
                  ))}
                  {!exams.length && (
                    <tr>
                      <td colSpan="7">No CBT exams created yet.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {selectedExamId ? (
          <section className="cbx-panel">
            <h3 style={{ marginTop: 0 }}>Exam Questions (from Question Bank export)</h3>
            <div className="cbx-table-wrap">
              <table className="cbx-table">
                <thead>
                  <tr>
                    <th>S/N</th>
                    <th>Question</th>
                    <th>Correct</th>
                  </tr>
                </thead>
                <tbody>
                  {examQuestions.map((q, idx) => (
                    <tr key={q.id}>
                      <td>{idx + 1}</td>
                      <td>{q.question_text}</td>
                      <td>{q.correct_option}</td>
                    </tr>
                  ))}
                  {!examQuestions.length && (
                    <tr>
                      <td colSpan="3">No questions exported to this exam yet.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>
        ) : null}
      </div>
    </StaffFeatureLayout>
  );
}
