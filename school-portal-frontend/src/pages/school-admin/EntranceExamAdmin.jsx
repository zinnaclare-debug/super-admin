import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";

const emptyExamConfig = {
  enabled: false,
  application_open: true,
  verification_open: true,
  apply_intro: "",
  exam_intro: "",
  verify_intro: "",
  class_exams: [],
};

const blankQuestion = {
  question: "",
  option_a: "",
  option_b: "",
  option_c: "",
  option_d: "",
  correct_option: "A",
};

function normalizeQuestion(question = {}) {
  return {
    question: String(question.question || ""),
    option_a: String(question.option_a || ""),
    option_b: String(question.option_b || ""),
    option_c: String(question.option_c || ""),
    option_d: String(question.option_d || ""),
    correct_option: ["A", "B", "C", "D"].includes(String(question.correct_option || "").toUpperCase())
      ? String(question.correct_option || "A").toUpperCase()
      : "A",
  };
}

function normalizeClassExam(className, exam = {}) {
  return {
    class_name: String(exam.class_name || className || ""),
    enabled: Boolean(exam.enabled),
    duration_minutes: Number(exam.duration_minutes || 30),
    pass_mark: Number(exam.pass_mark || 50),
    instructions: String(exam.instructions || ""),
    questions: Array.isArray(exam.questions) ? exam.questions.map(normalizeQuestion) : [],
  };
}

function normalizeData(payload = {}) {
  return {
    availableClasses: Array.isArray(payload.available_classes) ? payload.available_classes : [],
    entranceExamConfig: {
      ...emptyExamConfig,
      ...(payload.entrance_exam_config || {}),
      class_exams: Array.isArray(payload.entrance_exam_config?.class_exams)
        ? payload.entrance_exam_config.class_exams.map((exam) => normalizeClassExam(exam.class_name, exam))
        : [],
    },
  };
}

function coerceExamConfig(config, availableClasses) {
  const existing = new Map(
    (Array.isArray(config.class_exams) ? config.class_exams : []).map((exam) => [
      String(exam.class_name || "").toLowerCase(),
      normalizeClassExam(exam.class_name, exam),
    ])
  );

  const classExams = availableClasses.map((className) => {
    const key = String(className || "").toLowerCase();
    return existing.get(key) || normalizeClassExam(className);
  });

  return {
    ...emptyExamConfig,
    ...config,
    class_exams: classExams,
  };
}

export default function EntranceExamAdmin() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [availableClasses, setAvailableClasses] = useState([]);
  const [applications, setApplications] = useState([]);
  const [examConfig, setExamConfig] = useState(emptyExamConfig);

  const loadWebsite = async () => {
    const websiteRes = await api.get("/api/school-admin/entrance-exam");
    const normalized = normalizeData(websiteRes.data || {});
    setAvailableClasses(normalized.availableClasses);
    setExamConfig(coerceExamConfig(normalized.entranceExamConfig, normalized.availableClasses));
  };

  const loadApplications = async () => {
    const applicationsRes = await api.get("/api/school-admin/entrance-exam/applications");
    setApplications(Array.isArray(applicationsRes.data?.data) ? applicationsRes.data.data : []);
  };

  const load = async () => {
    setLoading(true);
    try {
      await Promise.all([loadWebsite(), loadApplications()]);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load entrance exam settings.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const questionCount = useMemo(
    () => examConfig.class_exams.reduce((sum, exam) => sum + (Array.isArray(exam.questions) ? exam.questions.length : 0), 0),
    [examConfig.class_exams]
  );

  const updateExamConfig = (field, value) => {
    setExamConfig((prev) => ({ ...prev, [field]: value }));
  };

  const updateClassExam = (index, field, value) => {
    setExamConfig((prev) => ({
      ...prev,
      class_exams: prev.class_exams.map((exam, examIndex) =>
        examIndex === index ? { ...exam, [field]: value } : exam
      ),
    }));
  };

  const updateQuestion = (examIndex, questionIndex, field, value) => {
    setExamConfig((prev) => ({
      ...prev,
      class_exams: prev.class_exams.map((exam, currentExamIndex) => {
        if (currentExamIndex !== examIndex) return exam;
        return {
          ...exam,
          questions: exam.questions.map((question, currentQuestionIndex) =>
            currentQuestionIndex === questionIndex
              ? { ...question, [field]: value }
              : question
          ),
        };
      }),
    }));
  };

  const addQuestion = (examIndex) => {
    setExamConfig((prev) => ({
      ...prev,
      class_exams: prev.class_exams.map((exam, currentExamIndex) =>
        currentExamIndex === examIndex
          ? { ...exam, questions: [...exam.questions, { ...blankQuestion }] }
          : exam
      ),
    }));
  };

  const removeQuestion = (examIndex, questionIndex) => {
    setExamConfig((prev) => ({
      ...prev,
      class_exams: prev.class_exams.map((exam, currentExamIndex) => {
        if (currentExamIndex !== examIndex) return exam;
        return {
          ...exam,
          questions: exam.questions.filter((_, currentQuestionIndex) => currentQuestionIndex !== questionIndex),
        };
      }),
    }));
  };

  const saveExamConfig = async () => {
    setSaving(true);
    try {
      const res = await api.put("/api/school-admin/entrance-exam", {
        entrance_exam_config: examConfig,
      });
      const normalized = normalizeData(res.data?.data || {});
      setAvailableClasses(normalized.availableClasses);
      setExamConfig(coerceExamConfig(normalized.entranceExamConfig, normalized.availableClasses));
      alert("Entrance exam settings saved.");
    } catch (err) {
      const validationError = Object.values(err?.response?.data?.errors || {}).flat()[0];
      alert(validationError || err?.response?.data?.message || "Failed to save entrance exam settings.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <p>Loading entrance exam settings...</p>;

  return (
    <div style={{ display: "grid", gap: 18 }}>
      <section style={{ background: "#fff", border: "1px solid #dbeafe", borderRadius: 14, padding: 18 }}>
        <div style={{ display: "flex", justifyContent: "space-between", gap: 16, flexWrap: "wrap" }}>
          <div>
            <h2 style={{ margin: 0 }}>Entrance Exam</h2>
            <p style={{ marginTop: 8, color: "#475569" }}>
              Set exam availability, per-class instructions, and CBT questions for your admissions process.
            </p>
          </div>
          <div style={{ display: "flex", gap: 10, flexWrap: "wrap", alignItems: "center" }}>
            <div style={{ borderRadius: 999, background: "#eff6ff", color: "#1d4ed8", padding: "8px 12px", fontWeight: 700 }}>
              Total Questions: {questionCount}
            </div>
            <button onClick={saveExamConfig} disabled={saving}>{saving ? "Saving..." : "Save Entrance Exam"}</button>
          </div>
        </div>

        <div style={{ display: "flex", gap: 12, flexWrap: "wrap", marginTop: 16 }}>
          <label><input type="checkbox" checked={examConfig.enabled} onChange={(e) => updateExamConfig("enabled", e.target.checked)} /> Enable Entrance Exam Portal</label>
          <label><input type="checkbox" checked={examConfig.application_open} onChange={(e) => updateExamConfig("application_open", e.target.checked)} /> Open Applications</label>
          <label><input type="checkbox" checked={examConfig.verification_open} onChange={(e) => updateExamConfig("verification_open", e.target.checked)} /> Open Score Verification</label>
        </div>

        <div style={{ display: "grid", gap: 12, marginTop: 16 }}>
          <div>
            <label>Apply Now Intro</label>
            <textarea rows="3" style={{ width: "100%", padding: 10, marginTop: 6 }} value={examConfig.apply_intro} onChange={(e) => updateExamConfig("apply_intro", e.target.value)} />
          </div>
          <div>
            <label>Entrance Exam Intro</label>
            <textarea rows="3" style={{ width: "100%", padding: 10, marginTop: 6 }} value={examConfig.exam_intro} onChange={(e) => updateExamConfig("exam_intro", e.target.value)} />
          </div>
          <div>
            <label>Verify Score Intro</label>
            <textarea rows="3" style={{ width: "100%", padding: 10, marginTop: 6 }} value={examConfig.verify_intro} onChange={(e) => updateExamConfig("verify_intro", e.target.value)} />
          </div>
        </div>

        <div style={{ display: "grid", gap: 16, marginTop: 18 }}>
          {examConfig.class_exams.map((exam, examIndex) => (
            <article key={exam.class_name || examIndex} style={{ border: "1px solid #dbe3ef", borderRadius: 12, padding: 14, background: "#f8fbff" }}>
              <div style={{ display: "flex", justifyContent: "space-between", gap: 16, flexWrap: "wrap" }}>
                <div>
                  <h3 style={{ margin: 0 }}>{exam.class_name}</h3>
                  <small>{exam.questions.length} question{exam.questions.length === 1 ? "" : "s"}</small>
                </div>
                <label><input type="checkbox" checked={exam.enabled} onChange={(e) => updateClassExam(examIndex, "enabled", e.target.checked)} /> Enable Exam</label>
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 12, marginTop: 14 }}>
                <div>
                  <label>Duration (minutes)</label>
                  <input type="number" min="5" max="180" style={{ width: "100%", padding: 10, marginTop: 6 }} value={exam.duration_minutes} onChange={(e) => updateClassExam(examIndex, "duration_minutes", Number(e.target.value || 0))} />
                </div>
                <div>
                  <label>Pass Mark</label>
                  <input type="number" min="0" max="100" style={{ width: "100%", padding: 10, marginTop: 6 }} value={exam.pass_mark} onChange={(e) => updateClassExam(examIndex, "pass_mark", Number(e.target.value || 0))} />
                </div>
                <div style={{ gridColumn: "1 / -1" }}>
                  <label>Instructions</label>
                  <textarea rows="3" style={{ width: "100%", padding: 10, marginTop: 6 }} value={exam.instructions} onChange={(e) => updateClassExam(examIndex, "instructions", e.target.value)} />
                </div>
              </div>

              <div style={{ display: "grid", gap: 12, marginTop: 14 }}>
                {exam.questions.map((question, questionIndex) => (
                  <div key={`${exam.class_name}-${questionIndex}`} style={{ border: "1px solid #dbeafe", borderRadius: 12, padding: 12, background: "#fff" }}>
                    <div style={{ display: "flex", justifyContent: "space-between", gap: 12, flexWrap: "wrap" }}>
                      <strong>Question {questionIndex + 1}</strong>
                      <button type="button" onClick={() => removeQuestion(examIndex, questionIndex)} style={{ background: "transparent", border: 0, color: "#b91c1c", cursor: "pointer", fontWeight: 700 }}>Remove</button>
                    </div>
                    <textarea rows="2" style={{ width: "100%", padding: 10, marginTop: 10 }} value={question.question} onChange={(e) => updateQuestion(examIndex, questionIndex, "question", e.target.value)} placeholder="Enter question text" />
                    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 10, marginTop: 10 }}>
                      <input style={{ padding: 10 }} value={question.option_a} onChange={(e) => updateQuestion(examIndex, questionIndex, "option_a", e.target.value)} placeholder="Option A" />
                      <input style={{ padding: 10 }} value={question.option_b} onChange={(e) => updateQuestion(examIndex, questionIndex, "option_b", e.target.value)} placeholder="Option B" />
                      <input style={{ padding: 10 }} value={question.option_c} onChange={(e) => updateQuestion(examIndex, questionIndex, "option_c", e.target.value)} placeholder="Option C" />
                      <input style={{ padding: 10 }} value={question.option_d} onChange={(e) => updateQuestion(examIndex, questionIndex, "option_d", e.target.value)} placeholder="Option D" />
                    </div>
                    <div style={{ marginTop: 10 }}>
                      <label>Correct Option</label>
                      <select style={{ width: "100%", padding: 10, marginTop: 6 }} value={question.correct_option} onChange={(e) => updateQuestion(examIndex, questionIndex, "correct_option", e.target.value)}>
                        <option value="A">Option A</option>
                        <option value="B">Option B</option>
                        <option value="C">Option C</option>
                        <option value="D">Option D</option>
                      </select>
                    </div>
                  </div>
                ))}
              </div>

              <div style={{ marginTop: 12 }}>
                <button type="button" onClick={() => addQuestion(examIndex)}>Add Question</button>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section style={{ background: "#fff", border: "1px solid #dbeafe", borderRadius: 14, padding: 18 }}>
        <h2 style={{ marginTop: 0 }}>Registered Applicants</h2>
        <p style={{ color: "#475569" }}>Candidates who applied through your public school website will appear here.</p>
        <div style={{ overflowX: "auto", marginTop: 14 }}>
          <table border="1" cellPadding="10" cellSpacing="0" width="100%">
            <thead>
              <tr>
                <th>S/N</th>
                <th>Name</th>
                <th>Information</th>
                <th>Class</th>
                <th>Application No.</th>
                <th>Exam Status</th>
                <th>Score</th>
              </tr>
            </thead>
            <tbody>
              {applications.map((application) => (
                <tr key={application.id}>
                  <td>{application.sn}</td>
                  <td>{application.full_name}</td>
                  <td>{application.information || "-"}</td>
                  <td>{application.applying_for_class}</td>
                  <td>{application.application_number}</td>
                  <td>{application.exam_status}</td>
                  <td>{application.score ?? "-"}</td>
                </tr>
              ))}
              {applications.length === 0 ? (
                <tr>
                  <td colSpan="7" style={{ textAlign: "center", opacity: 0.75 }}>No applicants yet.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

