import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import cbtMainArt from "../../../assets/cbt-dashboard/online-meetings.svg";
import cbtResumeArt from "../../../assets/cbt-dashboard/online-resume.svg";
import cbtProfilesArt from "../../../assets/cbt-dashboard/swipe-profiles.svg";
import "../../shared/CbtShowcase.css";

const defaultSecurityPolicy = {
  fullscreen_required: true,
  block_copy_paste: true,
  block_tab_switch: true,
  no_face_timeout_seconds: 30,
  max_warnings: 3,
  auto_submit_on_violation: true,
  logout_on_violation: true,
  ai_proctoring_enabled: true,
};

function createDefaultForm() {
  return {
    term_subject_id: "",
    title: "",
    instructions: "",
    starts_at: "",
    ends_at: "",
    duration_minutes: 60,
    status: "draft",
    security_policy: { ...defaultSecurityPolicy },
  };
}

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

function toDateTimeLocal(value) {
  if (!value) return "";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value).slice(0, 16);
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function localDateTimeToIso(value) {
  if (!value) return "";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toISOString();
}

export default function CBTHome() {
  const [subjects, setSubjects] = useState([]);
  const [exams, setExams] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedExamId, setSelectedExamId] = useState("");
  const [examQuestions, setExamQuestions] = useState([]);
  const [editingExamId, setEditingExamId] = useState(null);

  const [form, setForm] = useState(createDefaultForm());

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

  const selectedExam = exams.find((x) => String(x.id) === String(selectedExamId)) || null;

  useEffect(() => {
    load();
  }, []);

  const resetForm = () => {
    setEditingExamId(null);
    setForm(createDefaultForm());
  };

  const saveExam = async (e) => {
    e.preventDefault();
    const payload = {
      ...form,
      term_subject_id: Number(form.term_subject_id),
      starts_at: localDateTimeToIso(form.starts_at),
      ends_at: localDateTimeToIso(form.ends_at),
      duration_minutes: Number(form.duration_minutes),
      security_policy: {
        ...form.security_policy,
        no_face_timeout_seconds: Number(form.security_policy.no_face_timeout_seconds),
        max_warnings: Number(form.security_policy.max_warnings),
      },
    };

    try {
      if (editingExamId) {
        await api.patch(`/api/staff/cbt/exams/${editingExamId}`, payload);
      } else {
        await api.post("/api/staff/cbt/exams", payload);
      }
      await load();
      alert(editingExamId ? "CBT exam updated" : "CBT exam created");
      resetForm();
    } catch (err) {
      const validationErrors = err?.response?.data?.errors || {};
      const firstValidationError = Object.values(validationErrors)?.[0]?.[0];
      alert(
        err?.response?.data?.message ||
          firstValidationError ||
          (editingExamId ? "Update failed" : "Create failed")
      );
    }
  };

  const startEdit = (exam) => {
    const examPolicy = exam?.security_policy && typeof exam.security_policy === "object" ? exam.security_policy : {};
    setEditingExamId(exam.id);
    setForm({
      term_subject_id: exam.term_subject_id ? String(exam.term_subject_id) : "",
      title: exam.title || "",
      instructions: exam.instructions || "",
      starts_at: toDateTimeLocal(exam.starts_at),
      ends_at: toDateTimeLocal(exam.ends_at),
      duration_minutes: Number(exam.duration_minutes ?? exam.duration ?? 60),
      status: exam.status || "draft",
      security_policy: { ...defaultSecurityPolicy, ...examPolicy },
    });
    window.scrollTo({ top: 0, behavior: "smooth" });
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
          <h3 style={{ marginTop: 0 }}>{editingExamId ? "Edit CBT Exam" : "Create CBT Exam"}</h3>
          <form onSubmit={saveExam} className="cbx-form cbx-form--wide">
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
                {form.status === "published" ? <option value="published">Published</option> : null}
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
            <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
              <button className="cbx-btn" type="submit">{editingExamId ? "Update CBT" : "Create CBT"}</button>
              {editingExamId ? (
                <button className="cbx-btn cbx-btn--soft" type="button" onClick={resetForm}>
                  Cancel Edit
                </button>
              ) : null}
            </div>
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
                        <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                          <button className="cbx-btn cbx-btn--danger" onClick={() => removeExam(x.id)}>Delete</button>
                          <button className="cbx-btn cbx-btn--soft" onClick={() => startEdit(x)}>
                            {editingExamId === x.id ? "Editing..." : "Edit"}
                          </button>
                        </div>
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
            <h3 style={{ marginTop: 0 }}>CBT Exam View</h3>
            {selectedExam ? (
              <div className="cbx-table-wrap" style={{ marginBottom: 14 }}>
                <table className="cbx-table">
                  <tbody>
                    <tr>
                      <th style={{ width: 160 }}>Title</th>
                      <td>{selectedExam.title || "-"}</td>
                      <th style={{ width: 160 }}>Subject</th>
                      <td>{selectedExam.subject_name || "-"}</td>
                    </tr>
                    <tr>
                      <th>Class</th>
                      <td>{selectedExam.class_name || "-"}</td>
                      <th>Term</th>
                      <td>{selectedExam.term_name || "-"}</td>
                    </tr>
                    <tr>
                      <th>Start Time</th>
                      <td>{formatDate(selectedExam.starts_at)}</td>
                      <th>End Time</th>
                      <td>{formatDate(selectedExam.ends_at)}</td>
                    </tr>
                    <tr>
                      <th>Duration</th>
                      <td>{selectedExam.duration_minutes || selectedExam.duration || "-"} mins</td>
                      <th>Status</th>
                      <td>{selectedExam.status || "-"}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            ) : null}
            <div className="cbx-table-wrap">
              <table className="cbx-table">
                <thead>
                  <tr>
                    <th>S/N</th>
                    <th>Question</th>
                    <th>Option A</th>
                    <th>Option B</th>
                    <th>Option C</th>
                    <th>Option D</th>
                    <th>Correct</th>
                  </tr>
                </thead>
                <tbody>
                  {examQuestions.map((q, idx) => (
                    <tr key={q.id}>
                      <td>{idx + 1}</td>
                      <td>{q.question_text}</td>
                      <td>{q.option_a || "-"}</td>
                      <td>{q.option_b || "-"}</td>
                      <td>{q.option_c || "-"}</td>
                      <td>{q.option_d || "-"}</td>
                      <td>{q.correct_option}</td>
                    </tr>
                  ))}
                  {!examQuestions.length && (
                    <tr>
                      <td colSpan="7">No questions exported to this exam yet.</td>
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
