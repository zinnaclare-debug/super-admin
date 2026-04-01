import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";

const emptyWebsiteContent = {
  hero_title: "",
  hero_subtitle: "",
  about_title: "",
  about_text: "",
  core_values_text: "",
  mission_text: "",
  admissions_intro: "",
  address: "",
  contact_email: "",
  contact_phone: "",
  primary_color: "#0f172a",
  accent_color: "#0f766e",
  show_apply_now: true,
  show_entrance_exam: true,
  show_verify_score: true,
};

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
    websiteContent: { ...emptyWebsiteContent, ...(payload.website_content || {}) },
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

export default function WebsiteEntranceExam() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [websiteContent, setWebsiteContent] = useState(emptyWebsiteContent);
  const [examConfig, setExamConfig] = useState(emptyExamConfig);
  const [availableClasses, setAvailableClasses] = useState([]);
  const [applications, setApplications] = useState([]);

  const load = async () => {
    setLoading(true);
    try {
      const [websiteRes, applicationsRes] = await Promise.all([
        api.get("/api/school-admin/website"),
        api.get("/api/school-admin/website/applications"),
      ]);

      const normalized = normalizeData(websiteRes.data || {});
      setAvailableClasses(normalized.availableClasses);
      setWebsiteContent(normalized.websiteContent);
      setExamConfig(coerceExamConfig(normalized.entranceExamConfig, normalized.availableClasses));
      setApplications(Array.isArray(applicationsRes.data?.data) ? applicationsRes.data.data : []);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load website and entrance exam settings.");
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

  const updateWebsiteContent = (field, value) => {
    setWebsiteContent((prev) => ({ ...prev, [field]: value }));
  };

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

  const saveAll = async () => {
    setSaving(true);
    try {
      const payload = {
        website_content: websiteContent,
        entrance_exam_config: examConfig,
      };
      const res = await api.put("/api/school-admin/website", payload);
      const normalized = normalizeData(res.data?.data || {});
      setAvailableClasses(normalized.availableClasses);
      setWebsiteContent(normalized.websiteContent);
      setExamConfig(coerceExamConfig(normalized.entranceExamConfig, normalized.availableClasses));
      alert("Website and entrance exam settings saved.");
      await load();
    } catch (err) {
      const validationError = Object.values(err?.response?.data?.errors || {}).flat()[0];
      alert(validationError || err?.response?.data?.message || "Failed to save website and entrance exam settings.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <p>Loading website and entrance exam settings...</p>;

  return (
    <div style={{ display: "grid", gap: 18 }}>
      <section style={{ background: "#fff", border: "1px solid #dbeafe", borderRadius: 14, padding: 18 }}>
        <div style={{ display: "flex", justifyContent: "space-between", gap: 16, flexWrap: "wrap" }}>
          <div>
            <h2 style={{ margin: 0 }}>Website & Entrance Exam</h2>
            <p style={{ marginTop: 8, color: "#475569" }}>
              Manage your school subdomain homepage, admission form, entrance exam, and score verification flow.
            </p>
          </div>
          <button onClick={saveAll} disabled={saving}>{saving ? "Saving..." : "Save Website Content"}</button>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))", gap: 14, marginTop: 18 }}>
          <div>
            <label>Hero Title</label>
            <input style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.hero_title} onChange={(e) => updateWebsiteContent("hero_title", e.target.value)} />
          </div>
          <div>
            <label>About Title</label>
            <input style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.about_title} onChange={(e) => updateWebsiteContent("about_title", e.target.value)} />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <label>Hero Subtitle</label>
            <textarea rows="3" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.hero_subtitle} onChange={(e) => updateWebsiteContent("hero_subtitle", e.target.value)} />
          </div>

          <div style={{ gridColumn: "1 / -1" }}>
            <label>Apply Now Intro</label>
            <textarea rows="3" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.admissions_intro} onChange={(e) => updateWebsiteContent("admissions_intro", e.target.value)} />
          </div>
          <div>
            <label>Address</label>
            <input style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.address} onChange={(e) => updateWebsiteContent("address", e.target.value)} />
          </div>
          <div>
            <label>Public Email</label>
            <input type="email" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.contact_email} onChange={(e) => updateWebsiteContent("contact_email", e.target.value)} />
          </div>
          <div>
            <label>Public Phone</label>
            <input style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.contact_phone} onChange={(e) => updateWebsiteContent("contact_phone", e.target.value)} />
          </div>
          <div>
            <label>Primary Color</label>
            <input type="color" style={{ width: "100%", height: 44, marginTop: 6 }} value={websiteContent.primary_color} onChange={(e) => updateWebsiteContent("primary_color", e.target.value)} />
          </div>
          <div>
            <label>Accent Color</label>
            <input type="color" style={{ width: "100%", height: 44, marginTop: 6 }} value={websiteContent.accent_color} onChange={(e) => updateWebsiteContent("accent_color", e.target.value)} />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <label>About Us</label>
            <textarea rows="4" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.about_text} onChange={(e) => updateWebsiteContent("about_text", e.target.value)} placeholder="Tell visitors about your school" />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <label>Core Values</label>
            <textarea rows="4" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.core_values_text} onChange={(e) => updateWebsiteContent("core_values_text", e.target.value)} placeholder="Enter the school's core values" />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <label>Mission</label>
            <textarea rows="4" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.mission_text} onChange={(e) => updateWebsiteContent("mission_text", e.target.value)} placeholder="Enter the school's mission" />
          </div>
        </div>

        <div style={{ display: "flex", gap: 12, flexWrap: "wrap", marginTop: 16 }}>
          <label><input type="checkbox" checked={websiteContent.show_apply_now} onChange={(e) => updateWebsiteContent("show_apply_now", e.target.checked)} /> Show Apply Now</label>
          <label><input type="checkbox" checked={websiteContent.show_entrance_exam} onChange={(e) => updateWebsiteContent("show_entrance_exam", e.target.checked)} /> Show Entrance Exam</label>
          <label><input type="checkbox" checked={websiteContent.show_verify_score} onChange={(e) => updateWebsiteContent("show_verify_score", e.target.checked)} /> Show Verify Score</label>
        </div>
      </section>

      <section style={{ background: "#fff", border: "1px solid #dbeafe", borderRadius: 14, padding: 18 }}>
        <div style={{ display: "flex", justifyContent: "space-between", gap: 16, flexWrap: "wrap" }}>
          <div>
            <h2 style={{ margin: 0 }}>Entrance Exam Details</h2>
            <p style={{ marginTop: 8, color: "#475569" }}>Set exam availability, per-class instructions, and CBT questions.</p>
          </div>
          <div style={{ borderRadius: 999, background: "#eff6ff", color: "#1d4ed8", padding: "8px 12px", fontWeight: 700 }}>
            Total Questions: {questionCount}
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

