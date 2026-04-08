import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";

const emptyWebsiteContent = {
  hero_title: "",
  hero_subtitle: "",
  about_title: "",
  about_text: "",
  vision_text: "",
  mission_text: "",
  core_functions_title: "",
  contact_section_title: "",
  contact_section_intro: "",
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

export default function SchoolWebsiteSection({ schoolId, classTemplates }) {
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
        api.get(`/api/super-admin/schools/${schoolId}/information/website`),
        api.get(`/api/super-admin/schools/${schoolId}/information/website/applications`),
      ]);

      const normalized = normalizeData(websiteRes.data || {});
      setAvailableClasses(normalized.availableClasses);
      setWebsiteContent(normalized.websiteContent);
      setExamConfig(coerceExamConfig(normalized.entranceExamConfig, normalized.availableClasses));
      setApplications(Array.isArray(applicationsRes.data?.data) ? applicationsRes.data.data : []);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load school website content.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [schoolId, classTemplates]);

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
      const res = await api.put(`/api/super-admin/schools/${schoolId}/information/website`, payload);
      const normalized = normalizeData(res.data?.data || {});
      setAvailableClasses(normalized.availableClasses);
      setWebsiteContent(normalized.websiteContent);
      setExamConfig(coerceExamConfig(normalized.entranceExamConfig, normalized.availableClasses));
      alert("School website content saved.");
      await load();
    } catch (err) {
      const validationError = Object.values(err?.response?.data?.errors || {}).flat()[0];
      alert(validationError || err?.response?.data?.message || "Failed to save school website content.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <section className="sai-card">
        <h3>Website</h3>
        <p>Loading website content...</p>
      </section>
    );
  }

  return (
    <section className="sai-card">
      <div className="sai-section-head">
        <div>
          <h3>Website</h3>
          <p className="sai-note">
            Manage what each school subdomain shows on its public home page, including About Us, Vision, Mission, admissions, entrance exam, and score verification content.
          </p>
        </div>
        <button type="button" onClick={saveAll} disabled={saving}>
          {saving ? "Saving..." : "Save Website Content"}
        </button>
      </div>

      <div className="sai-grid sai-website-grid">
        <div className="sai-field sai-field--wide">
          <label>Hero Title</label>
          <input value={websiteContent.hero_title} onChange={(e) => updateWebsiteContent("hero_title", e.target.value)} />
        </div>
        <div className="sai-field sai-field--wide">
          <label>Hero Subtitle</label>
          <textarea rows="3" value={websiteContent.hero_subtitle} onChange={(e) => updateWebsiteContent("hero_subtitle", e.target.value)} />
        </div>
        <div className="sai-field">
          <label>About Title</label>
          <input value={websiteContent.about_title} onChange={(e) => updateWebsiteContent("about_title", e.target.value)} />
        </div>
        <div className="sai-field">
          <label>School Address</label>
          <input value={websiteContent.address} onChange={(e) => updateWebsiteContent("address", e.target.value)} />
        </div>
        <div className="sai-field sai-field--wide">
          <label>About Us</label>
          <textarea rows="5" value={websiteContent.about_text} onChange={(e) => updateWebsiteContent("about_text", e.target.value)} />
        </div>
        <div className="sai-field sai-field--wide">
          <label>Vision</label>
          <textarea rows="5" value={websiteContent.vision_text} onChange={(e) => updateWebsiteContent("vision_text", e.target.value)} />
        </div>
        <div className="sai-field sai-field--wide">
          <label>Mission</label>
          <textarea rows="5" value={websiteContent.mission_text} onChange={(e) => updateWebsiteContent("mission_text", e.target.value)} />
        </div>
        <div className="sai-field">
          <label>Core Functions Title</label>
          <input value={websiteContent.core_functions_title} onChange={(e) => updateWebsiteContent("core_functions_title", e.target.value)} />
        </div>
        <div className="sai-field">
          <label>Contact Section Title</label>
          <input value={websiteContent.contact_section_title} onChange={(e) => updateWebsiteContent("contact_section_title", e.target.value)} />
        </div>
        <div className="sai-field sai-field--wide">
          <label>Contact Section Intro</label>
          <textarea rows="3" value={websiteContent.contact_section_intro} onChange={(e) => updateWebsiteContent("contact_section_intro", e.target.value)} />
        </div>
        <div className="sai-field sai-field--wide">
          <label>Apply Now Intro</label>
          <textarea rows="3" value={websiteContent.admissions_intro} onChange={(e) => updateWebsiteContent("admissions_intro", e.target.value)} />
        </div>
        <div className="sai-field">
          <label>Public Contact Email</label>
          <input type="email" value={websiteContent.contact_email} onChange={(e) => updateWebsiteContent("contact_email", e.target.value)} />
        </div>
        <div className="sai-field">
          <label>Public Contact Phone</label>
          <input value={websiteContent.contact_phone} onChange={(e) => updateWebsiteContent("contact_phone", e.target.value)} />
        </div>
        <div className="sai-field">
          <label>Primary Color</label>
          <input type="color" value={websiteContent.primary_color} onChange={(e) => updateWebsiteContent("primary_color", e.target.value)} />
        </div>
        <div className="sai-field">
          <label>Accent Color</label>
          <input type="color" value={websiteContent.accent_color} onChange={(e) => updateWebsiteContent("accent_color", e.target.value)} />
        </div>
        <div className="sai-field sai-field--wide">
          <label>Public Navigation</label>
          <div className="sai-toggle-row">
            <label className="sai-check-card">
              <input type="checkbox" checked={websiteContent.show_apply_now} onChange={(e) => updateWebsiteContent("show_apply_now", e.target.checked)} />
              <span>Show Apply Now</span>
            </label>
            <label className="sai-check-card">
              <input type="checkbox" checked={websiteContent.show_entrance_exam} onChange={(e) => updateWebsiteContent("show_entrance_exam", e.target.checked)} />
              <span>Show Entrance Exam</span>
            </label>
            <label className="sai-check-card">
              <input type="checkbox" checked={websiteContent.show_verify_score} onChange={(e) => updateWebsiteContent("show_verify_score", e.target.checked)} />
              <span>Show Verify Score</span>
            </label>
          </div>
        </div>
      </div>

      <div className="sai-subsection">
        <div className="sai-section-head">
          <div>
            <h4>Entrance Exam Details</h4>
            <p className="sai-note">Configure public application, exam, and verification behavior for each class on the school website.</p>
          </div>
          <div className="sai-meta-chip">Total Questions: {questionCount}</div>
        </div>

        <div className="sai-grid sai-website-grid">
          <div className="sai-field sai-field--wide">
            <label>Portal Availability</label>
            <div className="sai-toggle-row">
              <label className="sai-check-card">
                <input type="checkbox" checked={examConfig.enabled} onChange={(e) => updateExamConfig("enabled", e.target.checked)} />
                <span>Enable Entrance Exam Portal</span>
              </label>
              <label className="sai-check-card">
                <input type="checkbox" checked={examConfig.application_open} onChange={(e) => updateExamConfig("application_open", e.target.checked)} />
                <span>Open Applications</span>
              </label>
              <label className="sai-check-card">
                <input type="checkbox" checked={examConfig.verification_open} onChange={(e) => updateExamConfig("verification_open", e.target.checked)} />
                <span>Open Score Verification</span>
              </label>
            </div>
          </div>
          <div className="sai-field sai-field--wide">
            <label>Apply Now Page Intro</label>
            <textarea rows="3" value={examConfig.apply_intro} onChange={(e) => updateExamConfig("apply_intro", e.target.value)} />
          </div>
          <div className="sai-field sai-field--wide">
            <label>Entrance Exam Page Intro</label>
            <textarea rows="3" value={examConfig.exam_intro} onChange={(e) => updateExamConfig("exam_intro", e.target.value)} />
          </div>
          <div className="sai-field sai-field--wide">
            <label>Verify Score Page Intro</label>
            <textarea rows="3" value={examConfig.verify_intro} onChange={(e) => updateExamConfig("verify_intro", e.target.value)} />
          </div>
        </div>

        <div className="sai-class-exam-list">
          {examConfig.class_exams.map((exam, examIndex) => (
            <article key={exam.class_name || examIndex} className="sai-class-exam-card">
              <div className="sai-class-exam-head">
                <div>
                  <h5>{exam.class_name || `Class ${examIndex + 1}`}</h5>
                  <small>{exam.questions.length} question{exam.questions.length === 1 ? "" : "s"}</small>
                </div>
                <label className="sai-check-card sai-check-card--compact">
                  <input type="checkbox" checked={exam.enabled} onChange={(e) => updateClassExam(examIndex, "enabled", e.target.checked)} />
                  <span>Enable Exam</span>
                </label>
              </div>

              <div className="sai-grid sai-website-grid">
                <div className="sai-field">
                  <label>Duration (minutes)</label>
                  <input type="number" min="5" max="180" value={exam.duration_minutes} onChange={(e) => updateClassExam(examIndex, "duration_minutes", Number(e.target.value || 0))} />
                </div>
                <div className="sai-field">
                  <label>Pass Mark</label>
                  <input type="number" min="0" max="100" value={exam.pass_mark} onChange={(e) => updateClassExam(examIndex, "pass_mark", Number(e.target.value || 0))} />
                </div>
                <div className="sai-field sai-field--wide">
                  <label>Instructions</label>
                  <textarea rows="3" value={exam.instructions} onChange={(e) => updateClassExam(examIndex, "instructions", e.target.value)} />
                </div>
              </div>

              <div className="sai-question-list">
                {exam.questions.map((question, questionIndex) => (
                  <div key={`${exam.class_name}-${questionIndex}`} className="sai-question-card">
                    <div className="sai-question-head">
                      <strong>Question {questionIndex + 1}</strong>
                      <button type="button" className="sai-link-btn" onClick={() => removeQuestion(examIndex, questionIndex)}>
                        Remove
                      </button>
                    </div>
                    <textarea rows="2" value={question.question} onChange={(e) => updateQuestion(examIndex, questionIndex, "question", e.target.value)} placeholder="Enter question text" />
                    <div className="sai-question-grid">
                      <input value={question.option_a} onChange={(e) => updateQuestion(examIndex, questionIndex, "option_a", e.target.value)} placeholder="Option A" />
                      <input value={question.option_b} onChange={(e) => updateQuestion(examIndex, questionIndex, "option_b", e.target.value)} placeholder="Option B" />
                      <input value={question.option_c} onChange={(e) => updateQuestion(examIndex, questionIndex, "option_c", e.target.value)} placeholder="Option C" />
                      <input value={question.option_d} onChange={(e) => updateQuestion(examIndex, questionIndex, "option_d", e.target.value)} placeholder="Option D" />
                    </div>
                    <div className="sai-field">
                      <label>Correct Option</label>
                      <select value={question.correct_option} onChange={(e) => updateQuestion(examIndex, questionIndex, "correct_option", e.target.value)}>
                        <option value="A">Option A</option>
                        <option value="B">Option B</option>
                        <option value="C">Option C</option>
                        <option value="D">Option D</option>
                      </select>
                    </div>
                  </div>
                ))}
              </div>

              <div className="sai-actions">
                <button type="button" onClick={() => addQuestion(examIndex)}>Add Question</button>
              </div>
            </article>
          ))}
        </div>
      </div>

      <div className="sai-subsection">
        <div className="sai-section-head">
          <div>
            <h4>Registered Applicants</h4>
            <p className="sai-note">These are the candidates who have applied through the public school website.</p>
          </div>
          <div className="sai-meta-chip">{applications.length} candidate{applications.length === 1 ? "" : "s"}</div>
        </div>

        <div className="sai-grade-table-wrap">
          <table className="sai-grade-table sai-app-table">
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
      </div>
    </section>
  );
}



