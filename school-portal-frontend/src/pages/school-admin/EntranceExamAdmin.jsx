import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";
import socialFriendsArt from "../../assets/entrance-exam/social-friends.svg";
import coWorkingArt from "../../assets/entrance-exam/co-working.svg";
import readingNotesArt from "../../assets/entrance-exam/reading-notes.svg";
import "../shared/PaymentsShowcase.css";
import "./EntranceExamAdmin.css";

const emptyExamConfig = {
  enabled: false,
  application_open: true,
  verification_open: true,
  apply_intro: "",
  exam_intro: "",
  verify_intro: "",
  class_exams: [],
};

function normalizeClassExam(className, exam = {}) {
  return {
    class_name: String(exam.class_name || className || ""),
    enabled: Boolean(exam.enabled),
    duration_minutes: Number(exam.duration_minutes || 30),
    pass_mark: Number(exam.pass_mark || 50),
    instructions: String(exam.instructions || ""),
    questions: Array.isArray(exam.questions) ? exam.questions : [],
  };
}

function normalizeData(payload = {}) {
  return {
    availableClasses: Array.isArray(payload.available_classes) ? payload.available_classes : [],
    availableClassGroups: Array.isArray(payload.available_class_groups) ? payload.available_class_groups : [],
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

  return {
    ...emptyExamConfig,
    ...config,
    class_exams: availableClasses.map((className) => {
      const key = String(className || "").toLowerCase();
      return existing.get(key) || normalizeClassExam(className);
    }),
  };
}

function examQuestionCount(exam) {
  return Array.isArray(exam?.questions) ? exam.questions.length : 0;
}

export default function EntranceExamAdmin() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [availableClasses, setAvailableClasses] = useState([]);
  const [availableClassGroups, setAvailableClassGroups] = useState([]);
  const [applications, setApplications] = useState([]);
  const [examConfig, setExamConfig] = useState(emptyExamConfig);

  const classGroups = useMemo(() => {
    const baseGroups = Array.isArray(availableClassGroups) && availableClassGroups.length
      ? availableClassGroups
      : [{ key: "all", label: "All Classes", classes: availableClasses }];

    return baseGroups
      .map((group) => {
        const exams = (group.classes || [])
          .map((className) => examConfig.class_exams.find((exam) => exam.class_name === className))
          .filter(Boolean);

        return {
          key: String(group.key || group.label || "level"),
          label: String(group.label || "Level"),
          exams,
        };
      })
      .filter((group) => group.exams.length > 0);
  }, [availableClassGroups, availableClasses, examConfig.class_exams]);

  const totalQuestions = useMemo(
    () => examConfig.class_exams.reduce((sum, exam) => sum + examQuestionCount(exam), 0),
    [examConfig.class_exams]
  );
  const enabledClasses = useMemo(
    () => examConfig.class_exams.filter((exam) => exam.enabled).length,
    [examConfig.class_exams]
  );

  const loadSettings = async () => {
    const response = await api.get("/api/school-admin/entrance-exam");
    const normalized = normalizeData(response.data || {});
    setAvailableClasses(normalized.availableClasses);
    setAvailableClassGroups(normalized.availableClassGroups);
    setExamConfig(coerceExamConfig(normalized.entranceExamConfig, normalized.availableClasses));
  };

  const loadApplications = async () => {
    const response = await api.get("/api/school-admin/entrance-exam/applications");
    setApplications(Array.isArray(response.data?.data) ? response.data.data : []);
  };

  const load = async () => {
    setLoading(true);
    try {
      await Promise.all([loadSettings(), loadApplications()]);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load entrance exam settings.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const updateExamConfig = (field, value) => {
    setExamConfig((prev) => ({ ...prev, [field]: value }));
  };

  const updateClassExam = (className, field, value) => {
    setExamConfig((prev) => ({
      ...prev,
      class_exams: prev.class_exams.map((exam) =>
        exam.class_name === className ? { ...exam, [field]: value } : exam
      ),
    }));
  };

  const saveExamConfig = async () => {
    setSaving(true);
    try {
      const response = await api.put("/api/school-admin/entrance-exam", {
        entrance_exam_config: examConfig,
      });
      const normalized = normalizeData(response.data?.data || {});
      setAvailableClasses(normalized.availableClasses);
    setAvailableClassGroups(normalized.availableClassGroups);
      setExamConfig(coerceExamConfig(normalized.entranceExamConfig, normalized.availableClasses));
      alert("Entrance exam settings saved.");
    } catch (err) {
      const validationError = Object.values(err?.response?.data?.errors || {}).flat()[0];
      alert(validationError || err?.response?.data?.message || "Failed to save entrance exam settings.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <p className="payx-state payx-state--loading">Loading entrance exam settings...</p>;
  }

  return (
    <div className="examadmin-page payx-page payx-page--admin">
      <section className="payx-hero">
        <div>
          <span className="payx-pill">School Admin Entrance Exam</span>
          <h2 className="payx-title">Manage class entrance exams and admission question flow</h2>
          <p className="payx-subtitle">
            Control application visibility, class-level exam settings, and open a dedicated questions workspace for each class.
          </p>
          <div className="payx-meta">
            <span>{availableClasses.length} class{availableClasses.length === 1 ? "" : "es"}</span>
            <span>{enabledClasses} enabled exam{enabledClasses === 1 ? "" : "s"}</span>
            <span>{totalQuestions} total entrance question{totalQuestions === 1 ? "" : "s"}</span>
          </div>
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main">
            <img src={socialFriendsArt} alt="" />
          </div>
          <div className="payx-art payx-art--card examadmin-art--side">
            <img src={coWorkingArt} alt="" />
          </div>
          <div className="payx-art payx-art--online examadmin-art--corner">
            <img src={readingNotesArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel">
        <div className="examadmin-panel-head">
          <div>
            <h3>Portal Settings</h3>
            <p className="examadmin-note">These settings control the public school admission exam flow.</p>
          </div>
          <button className="payx-btn" onClick={saveExamConfig} disabled={saving}>
            {saving ? "Saving..." : "Save Entrance Exam"}
          </button>
        </div>

        <div className="examadmin-switches">
          <label className="examadmin-switch">
            <input
              type="checkbox"
              checked={examConfig.enabled}
              onChange={(e) => updateExamConfig("enabled", e.target.checked)}
            />
            <span>Enable Entrance Exam Portal</span>
          </label>
          <label className="examadmin-switch">
            <input
              type="checkbox"
              checked={examConfig.application_open}
              onChange={(e) => updateExamConfig("application_open", e.target.checked)}
            />
            <span>Open Applications</span>
          </label>
          <label className="examadmin-switch">
            <input
              type="checkbox"
              checked={examConfig.verification_open}
              onChange={(e) => updateExamConfig("verification_open", e.target.checked)}
            />
            <span>Open Score Verification</span>
          </label>
        </div>

        <div className="payx-grid-2 examadmin-copy-grid">
          <label className="examadmin-field">
            <span>Apply Now Intro</span>
            <textarea
              className="payx-input examadmin-textarea"
              rows="4"
              value={examConfig.apply_intro}
              onChange={(e) => updateExamConfig("apply_intro", e.target.value)}
            />
          </label>
          <label className="examadmin-field">
            <span>Entrance Exam Intro</span>
            <textarea
              className="payx-input examadmin-textarea"
              rows="4"
              value={examConfig.exam_intro}
              onChange={(e) => updateExamConfig("exam_intro", e.target.value)}
            />
          </label>
          <label className="examadmin-field examadmin-field--full">
            <span>Verify Score Intro</span>
            <textarea
              className="payx-input examadmin-textarea"
              rows="4"
              value={examConfig.verify_intro}
              onChange={(e) => updateExamConfig("verify_intro", e.target.value)}
            />
          </label>
        </div>
      </section>

      <section className="payx-panel">
        <div className="examadmin-panel-head">
          <div>
            <h3>Class Entrance Exam Setup</h3>
            <p className="examadmin-note">Open each class question workspace to import from question bank or add manual entrance exam questions.</p>
          </div>
        </div>

        <div className="examadmin-levels">
          {classGroups.map((group) => (
            <div key={group.key} className="examadmin-level-card">
              <div className="examadmin-level-head">
                <div>
                  <h4>{group.label}</h4>
                  <p>{group.exams.length} class{group.exams.length === 1 ? "" : "es"} in this level</p>
                </div>
              </div>

              <div className="examadmin-class-grid">
                {group.exams.map((exam) => (
                  <article key={exam.class_name} className="examadmin-class-card">
                    <div className="examadmin-class-head">
                      <div>
                        <h5>{exam.class_name}</h5>
                        <p>
                          {examQuestionCount(exam)} question{examQuestionCount(exam) === 1 ? "" : "s"} configured
                        </p>
                      </div>
                      <label className="examadmin-switch examadmin-switch--compact">
                        <input
                          type="checkbox"
                          checked={exam.enabled}
                          onChange={(e) => updateClassExam(exam.class_name, "enabled", e.target.checked)}
                        />
                        <span>Enable</span>
                      </label>
                    </div>

                    <div className="examadmin-class-fields">
                      <label className="examadmin-field">
                        <span>Duration (minutes)</span>
                        <input
                          className="payx-input"
                          type="number"
                          min="5"
                          max="180"
                          value={exam.duration_minutes}
                          onChange={(e) => updateClassExam(exam.class_name, "duration_minutes", Number(e.target.value || 0))}
                        />
                      </label>
                      <label className="examadmin-field">
                        <span>Pass Mark</span>
                        <input
                          className="payx-input"
                          type="number"
                          min="0"
                          max="100"
                          value={exam.pass_mark}
                          onChange={(e) => updateClassExam(exam.class_name, "pass_mark", Number(e.target.value || 0))}
                        />
                      </label>
                    </div>

                    <label className="examadmin-field examadmin-field--full">
                      <span>Instructions</span>
                      <textarea
                        className="payx-input examadmin-textarea"
                        rows="3"
                        value={exam.instructions}
                        onChange={(e) => updateClassExam(exam.class_name, "instructions", e.target.value)}
                      />
                    </label>

                    <div className="examadmin-class-footer">
                      <div className="examadmin-class-stats">
                        <span>{exam.enabled ? "Live for admissions" : "Currently disabled"}</span>
                        <span>{exam.pass_mark}% pass mark</span>
                      </div>
                      <button
                        type="button"
                        className="payx-btn payx-btn--soft"
                        onClick={() => navigate(`/school/admin/entrance-exam/classes/${encodeURIComponent(exam.class_name)}/questions`)}
                      >
                        Questions
                      </button>
                    </div>
                  </article>
                ))}
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="payx-panel">
        <div className="examadmin-panel-head">
          <div>
            <h3>Registered Applicants</h3>
            <p className="examadmin-note">Candidates who applied through your public school website will appear here.</p>
          </div>
        </div>

        <div className="payx-table-wrap">
          <table className="payx-table">
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
                  <td colSpan="7" className="examadmin-empty-cell">No applicants yet.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}


