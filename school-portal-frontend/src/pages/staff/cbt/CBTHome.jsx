import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";

export default function CBTHome() {
  const navigate = useNavigate();
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
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h2>CBT (Staff)</h2>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      <div style={{ marginTop: 12, border: "1px solid #ddd", borderRadius: 10, padding: 12 }}>
        <h3 style={{ marginTop: 0 }}>Create CBT Exam</h3>
        <form onSubmit={createExam} style={{ display: "grid", gap: 8, maxWidth: 920 }}>
          <select
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
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            placeholder="Exam title"
            required
          />
          <textarea
            rows={2}
            value={form.instructions}
            onChange={(e) => setForm({ ...form, instructions: e.target.value })}
            placeholder="Instructions"
          />
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 140px 160px", gap: 8 }}>
            <input
              type="datetime-local"
              value={form.starts_at}
              onChange={(e) => setForm({ ...form, starts_at: e.target.value })}
              required
            />
            <input
              type="datetime-local"
              value={form.ends_at}
              onChange={(e) => setForm({ ...form, ends_at: e.target.value })}
              required
            />
            <input
              type="number"
              min="1"
              max="300"
              value={form.duration_minutes}
              onChange={(e) => setForm({ ...form, duration_minutes: Number(e.target.value) })}
            />
            <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
              <option value="draft">Draft</option>
              <option value="closed">Closed</option>
            </select>
          </div>
          <small style={{ opacity: 0.75 }}>
            Note: School admin publishes CBT to make it visible to students.
          </small>

          <div style={{ border: "1px dashed #bbb", padding: 10 }}>
            <strong>Security Policy (Exam Runtime Controls)</strong>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8, marginTop: 8 }}>
              {[
                ["fullscreen_required", "Require Fullscreen"],
                ["block_copy_paste", "Block Copy/Paste"],
                ["block_tab_switch", "Block Tab Switch"],
                ["auto_submit_on_violation", "Auto-submit on violation"],
                ["logout_on_violation", "Logout on major violation"],
                ["ai_proctoring_enabled", "Enable AI proctoring hooks"],
              ].map(([k, label]) => (
                <label key={k}>
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
            <div style={{ display: "flex", gap: 8, marginTop: 8 }}>
              <input
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
          <button type="submit">Create CBT</button>
        </form>
      </div>

      <div style={{ marginTop: 12 }}>
        {loading ? (
          <p>Loading...</p>
        ) : (
          <table border="1" cellPadding="8" width="100%">
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
                    {x.starts_at ? new Date(x.starts_at).toLocaleString() : "-"} -{" "}
                    {x.ends_at ? new Date(x.ends_at).toLocaleString() : "-"}
                  </td>
                  <td>{x.status}</td>
                  <td>
                    <button onClick={() => openExam(x.id)}>View</button>
                  </td>
                  <td>
                    <button onClick={() => removeExam(x.id)}>Delete</button>
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
        )}
      </div>

      {selectedExamId ? (
        <div style={{ marginTop: 12, border: "1px solid #ddd", borderRadius: 10, padding: 12 }}>
          <h3 style={{ marginTop: 0 }}>Exam Questions (from Question Bank export)</h3>
          <table border="1" cellPadding="8" width="100%">
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
      ) : null}
    </div>
  );
}
