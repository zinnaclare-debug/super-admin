import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";
import socialFriendsArt from "../../assets/entrance-exam/social-friends.svg";
import coWorkingArt from "../../assets/entrance-exam/co-working.svg";
import readingNotesArt from "../../assets/entrance-exam/reading-notes.svg";
import "../shared/PaymentsShowcase.css";
import "./EntranceExamAdmin.css";

const TAX_RATE = 1.6;

const emptyExamConfig = {
  enabled: false,
  application_open: true,
  verification_open: true,
  application_fee_amount: 0,
  application_fee_tax_rate: TAX_RATE,
  application_fee_tax_amount: 0,
  application_fee_total: 0,
  apply_intro: "",
  exam_intro: "",
  verify_intro: "",
  class_exams: [],
};

function computeFee(value, taxRate = TAX_RATE) {
  const amount = Number(value || 0);
  const normalizedTaxRate = Number(taxRate || TAX_RATE);
  const taxAmount = Number((amount * (normalizedTaxRate / 100)).toFixed(2));
  const total = Number((amount + taxAmount).toFixed(2));

  return {
    amount,
    taxRate: normalizedTaxRate,
    taxAmount,
    total,
  };
}

function normalizeClassExam(className, exam = {}) {
  const fee = computeFee(exam.application_fee_amount ?? 0, exam.application_fee_tax_rate ?? TAX_RATE);

  return {
    class_name: String(exam.class_name || className || ""),
    enabled: Boolean(exam.enabled),
    duration_minutes: Number(exam.duration_minutes || 30),
    pass_mark: Number(exam.pass_mark || 50),
    instructions: String(exam.instructions || ""),
    application_fee_amount: fee.amount,
    application_fee_tax_rate: fee.taxRate,
    application_fee_tax_amount: fee.taxAmount,
    application_fee_total: fee.total,
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

  const normalizedClassExams = availableClasses.map((className) => {
    const key = String(className || "").toLowerCase();
    return existing.get(key) || normalizeClassExam(className);
  });

  const rootFee = computeFee(config.application_fee_amount ?? 0, config.application_fee_tax_rate ?? TAX_RATE);

  return {
    ...emptyExamConfig,
    ...config,
    application_fee_amount: rootFee.amount,
    application_fee_tax_rate: rootFee.taxRate,
    application_fee_tax_amount: rootFee.taxAmount,
    application_fee_total: rootFee.total,
    class_exams: normalizedClassExams,
  };
}

function examQuestionCount(exam) {
  return Array.isArray(exam?.questions) ? exam.questions.length : 0;
}

function toDateTimeLocal(value) {
  if (!value) return "";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value).slice(0, 16);
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function EntranceExamAdmin() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [availableClasses, setAvailableClasses] = useState([]);
  const [availableClassGroups, setAvailableClassGroups] = useState([]);
  const [applications, setApplications] = useState([]);
  const [applicantSearchInput, setApplicantSearchInput] = useState("");
  const [applicantSearch, setApplicantSearch] = useState("");
  const [applicationsPage, setApplicationsPage] = useState(1);
  const [updatingStatusId, setUpdatingStatusId] = useState(null);
  const [resettingId, setResettingId] = useState(null);
  const [resetDialog, setResetDialog] = useState({
    open: false,
    applicationId: null,
    applicantName: "",
    rescheduled_for: "",
  });
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
  const filteredApplications = useMemo(() => {
    const query = applicantSearch.trim().toLowerCase();
    if (!query) return applications;

    return applications.filter((application) => {
      const haystacks = [
        application.full_name,
        application.information,
        application.applying_for_class,
        application.application_number,
        application.exam_status,
        application.admin_result_status,
      ];

      return haystacks.some((value) => String(value || "").toLowerCase().includes(query));
    });
  }, [applications, applicantSearch]);
  const totalApplicationPages = Math.max(1, Math.ceil(filteredApplications.length / 15));
  const paginatedApplications = useMemo(() => {
    const start = (applicationsPage - 1) * 15;
    return filteredApplications.slice(start, start + 15);
  }, [filteredApplications, applicationsPage]);

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

  const openResetDialog = (application) => {
    setResetDialog({
      open: true,
      applicationId: application.id,
      applicantName: application.full_name || "Applicant",
      rescheduled_for: application?.exam_rescheduled_for ? toDateTimeLocal(application.exam_rescheduled_for) : "",
    });
  };

  const closeResetDialog = () => {
    if (resettingId) return;
    setResetDialog({
      open: false,
      applicationId: null,
      applicantName: "",
      rescheduled_for: "",
    });
  };

  const submitResetApplication = async (e) => {
    e.preventDefault();
    if (!resetDialog.applicationId || !resetDialog.rescheduled_for) return;

    setResettingId(resetDialog.applicationId);
    try {
      await api.patch(`/api/school-admin/entrance-exam/applications/${resetDialog.applicationId}/reset`, {
        rescheduled_for: resetDialog.rescheduled_for,
      });
      await loadApplications();
      closeResetDialog();
      alert("Entrance exam reset successfully.");
    } catch (err) {
      const validationError = Object.values(err?.response?.data?.errors || {}).flat()[0];
      alert(validationError || err?.response?.data?.message || "Failed to reset entrance exam.");
    } finally {
      setResettingId(null);
    }
  };

  const updateApplicationStatus = async (applicationId, status) => {
    setUpdatingStatusId(applicationId);
    try {
      await api.patch(`/api/school-admin/entrance-exam/applications/${applicationId}/status`, {
        admin_result_status: status || null,
      });
      await loadApplications();
    } catch (err) {
      const validationError = Object.values(err?.response?.data?.errors || {}).flat()[0];
      alert(validationError || err?.response?.data?.message || "Failed to update result status.");
    } finally {
      setUpdatingStatusId(null);
    }
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

  useEffect(() => {
    setApplicationsPage((currentPage) => Math.min(currentPage, totalApplicationPages));
  }, [totalApplicationPages]);

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

  const updateClassFeeAmount = (className, value) => {
    const fee = computeFee(value);

    setExamConfig((prev) => ({
      ...prev,
      class_exams: prev.class_exams.map((exam) =>
        exam.class_name === className
          ? {
              ...exam,
              application_fee_amount: fee.amount,
              application_fee_tax_rate: fee.taxRate,
              application_fee_tax_amount: fee.taxAmount,
              application_fee_total: fee.total,
            }
          : exam
      ),
    }));
  };

  const submitApplicantSearch = () => {
    setApplicantSearch(applicantSearchInput.trim());
    setApplicationsPage(1);
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
          {classGroups.map((group) => {
            const levelQuestionTotal = group.exams.reduce(
              (sum, exam) => sum + examQuestionCount(exam),
              0
            );

            return (
              <div key={group.key} className="examadmin-level-card">
                <div className="examadmin-level-head">
                  <div>
                    <h4>{group.label}</h4>
                    <p>{group.exams.length} class{group.exams.length === 1 ? "" : "es"} in this level</p>
                    <p className="examadmin-level-total">Level total questions: {levelQuestionTotal}</p>
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
            );
          })}
        </div>
      </section>

      <section className="payx-panel">
        <div className="examadmin-panel-head">
          <div>
            <h3>Application Fee by Level</h3>
            <p className="examadmin-note">Each level contains fee boxes for its classes. Processing fee stays at 1.6% automatically.</p>
          </div>
          <button className="payx-btn" onClick={saveExamConfig} disabled={saving}>
            {saving ? "Saving..." : "Save Billing"}
          </button>
        </div>

        <div className="examadmin-levels examadmin-billing-levels">
          {classGroups.map((group) => (
            <div key={`${group.key}-billing`} className="examadmin-level-card examadmin-level-card--billing">
              <div className="examadmin-level-head">
                <div>
                  <h4>{group.label}</h4>
                  <p>Set the application fee for each class in this level.</p>
                </div>
              </div>

              <div className="examadmin-billing-grid">
                {group.exams.map((exam) => (
                  <article key={`${exam.class_name}-fee`} className="examadmin-billing-card">
                    <div className="examadmin-billing-card-head">
                      <h5>{exam.class_name}</h5>
                      <span>{exam.enabled ? "Exam enabled" : "Exam disabled"}</span>
                    </div>

                    <label className="examadmin-field">
                      <span>Application Fee (NGN)</span>
                      <input
                        className="payx-input"
                        type="number"
                        min="0"
                        step="0.01"
                        value={exam.application_fee_amount}
                        onChange={(e) => updateClassFeeAmount(exam.class_name, e.target.value)}
                      />
                    </label>

                    <div className="examadmin-billing-meta">
                      <div>
                        <small>Processing Fee Rate</small>
                        <strong>{Number(exam.application_fee_tax_rate || TAX_RATE).toFixed(1)}%</strong>
                      </div>
                      <div>
                        <small>Processing Fee Amount</small>
                        <strong>NGN {Number(exam.application_fee_tax_amount || 0).toFixed(2)}</strong>
                      </div>
                      <div>
                        <small>Total</small>
                        <strong>NGN {Number(exam.application_fee_total || 0).toFixed(2)}</strong>
                      </div>
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

        <div style={{ display: "flex", gap: 12, flexWrap: "wrap", alignItems: "center", marginBottom: 14 }}>
          <input
            className="payx-input"
            type="text"
            placeholder="Search applicants"
            value={applicantSearchInput}
            onChange={(e) => setApplicantSearchInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                submitApplicantSearch();
              }
            }}
            style={{ maxWidth: 320 }}
          />
          <button type="button" className="payx-btn" onClick={submitApplicantSearch}>
            Search
          </button>
          <span className="examadmin-note">Showing {paginatedApplications.length} of {filteredApplications.length} applicant(s)</span>
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
                <th>Result Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {paginatedApplications.map((application, index) => (
                <tr key={application.id}>
                  <td>{(applicationsPage - 1) * 15 + index + 1}</td>
                  <td>{application.full_name}</td>
                  <td>{application.information || "-"}</td>
                  <td>{application.applying_for_class}</td>
                  <td>{application.application_number}</td>
                  <td>{application.exam_status}</td>
                  <td>{application.score ?? "-"}</td>
                  <td>
                    <select
                      value={application.admin_result_status || ""}
                      onChange={(e) => updateApplicationStatus(application.id, e.target.value)}
                      disabled={updatingStatusId === application.id}
                    >
                      <option value="">Awaiting</option>
                      <option value="passed">Pass</option>
                      <option value="failed">Fail</option>
                    </select>
                  </td>
                  <td>
                    <button
                      type="button"
                      className="payx-btn payx-btn--soft"
                      onClick={() => openResetDialog(application)}
                      disabled={resettingId === application.id}
                    >
                      {resettingId === application.id ? "Resetting..." : "Reset Exam"}
                    </button>
                  </td>
                </tr>
              ))}
              {filteredApplications.length === 0 ? (
                <tr>
                  <td colSpan="9" className="examadmin-empty-cell">No applicants yet.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 12, flexWrap: "wrap", marginTop: 16 }}>
          <span className="examadmin-note">Page {applicationsPage} of {totalApplicationPages}</span>
          <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
            <button
              type="button"
              className="payx-btn payx-btn--soft"
              onClick={() => setApplicationsPage((page) => Math.max(1, page - 1))}
              disabled={applicationsPage === 1}
            >
              Previous
            </button>
            <button
              type="button"
              className="payx-btn payx-btn--soft"
              onClick={() => setApplicationsPage((page) => Math.min(totalApplicationPages, page + 1))}
              disabled={applicationsPage === totalApplicationPages}
            >
              Next
            </button>
          </div>
        </div>
      </section>

      {resetDialog.open ? (
        <div className="examadmin-reset-backdrop" onClick={closeResetDialog}>
          <div className="examadmin-reset-card" onClick={(e) => e.stopPropagation()}>
            <div className="examadmin-reset-head">
              <div>
                <h3>Reset Entrance Exam</h3>
                <p className="examadmin-note">The current exam date and time is already loaded for {resetDialog.applicantName}, so you can adjust it instead of typing from scratch.</p>
              </div>
              <button type="button" className="payx-btn payx-btn--soft" onClick={closeResetDialog} disabled={Boolean(resettingId)}>
                Close
              </button>
            </div>

            <form className="examadmin-reset-form" onSubmit={submitResetApplication}>
              <label className="examadmin-field">
                <span>New Exam Date and Time</span>
                <input
                  className="payx-input"
                  type="datetime-local"
                  value={resetDialog.rescheduled_for}
                  onChange={(e) => setResetDialog((prev) => ({ ...prev, rescheduled_for: e.target.value }))}
                  required
                />
              </label>
              <div className="examadmin-reset-actions">
                <button type="button" className="payx-btn payx-btn--soft" onClick={closeResetDialog} disabled={Boolean(resettingId)}>
                  Cancel
                </button>
                <button type="submit" className="payx-btn" disabled={Boolean(resettingId)}>
                  {resettingId ? "Saving..." : "Save New Exam Time"}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </div>
  );
}

