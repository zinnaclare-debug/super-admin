import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../services/api";
import "../shared/PaymentsShowcase.css";
import "./EntranceExamClassQuestions.css";

const blankManualQuestion = {
  question: "",
  option_a: "",
  option_b: "",
  option_c: "",
  option_d: "",
  correct_option: "A",
};

function formatSourceType(value) {
  if (value === "question_bank") return "Question Bank";
  if (value === "manual") return "Manual";
  if (value === "ai") return "AI";
  return "-";
}

function buildManualState(subjects = []) {
  return subjects.reduce((acc, subject) => {
    acc[String(subject.subject_id)] = { ...blankManualQuestion };
    return acc;
  }, {});
}

export default function EntranceExamClassQuestions() {
  const navigate = useNavigate();
  const { className = "" } = useParams();
  const decodedClassName = decodeURIComponent(className);

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [submittingSubjectId, setSubmittingSubjectId] = useState("");
  const [removingQuestionId, setRemovingQuestionId] = useState("");
  const [classExam, setClassExam] = useState(null);
  const [subjects, setSubjects] = useState([]);
  const [selectedQuestionIds, setSelectedQuestionIds] = useState([]);
  const [expandedSubjects, setExpandedSubjects] = useState({});
  const [showManualForm, setShowManualForm] = useState({});
  const [manualQuestions, setManualQuestions] = useState({});

  const load = async (mode = "load") => {
    if (mode === "load") {
      setLoading(true);
    } else {
      setRefreshing(true);
    }

    try {
      const response = await api.get(`/api/school-admin/entrance-exam/classes/${encodeURIComponent(decodedClassName)}/questions`);
      const payload = response.data?.data || {};
      const subjectRows = Array.isArray(payload.subjects) ? payload.subjects : [];
      setClassExam(payload.class_exam || null);
      setSubjects(subjectRows);
      setSelectedQuestionIds(
        Array.isArray(payload.selected_question_bank_ids)
          ? payload.selected_question_bank_ids.map((id) => Number(id)).filter((id) => Number.isFinite(id))
          : []
      );
      setExpandedSubjects((prev) => {
        if (Object.keys(prev).length) return prev;
        if (!subjectRows.length) return {};
        return { [String(subjectRows[0].subject_id)]: true };
      });
      setShowManualForm((prev) => {
        const next = {};
        subjectRows.forEach((subject) => {
          const key = String(subject.subject_id);
          next[key] = Boolean(prev[key]);
        });
        return next;
      });
      setManualQuestions((prev) => {
        const next = buildManualState(subjectRows);
        Object.keys(next).forEach((key) => {
          if (prev[key]) next[key] = prev[key];
        });
        return next;
      });
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load class entrance exam questions.");
      navigate("/school/admin/entrance-exam");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    load();
  }, [decodedClassName]);

  const currentQuestions = useMemo(() => (Array.isArray(classExam?.questions) ? classExam.questions : []), [classExam]);

  const totalBankQuestions = useMemo(
    () => subjects.reduce((sum, subject) => sum + (Array.isArray(subject.questions) ? subject.questions.length : 0), 0),
    [subjects]
  );

  const selectedCount = selectedQuestionIds.length;

  const toggleSubject = (subjectId) => {
    const key = String(subjectId);
    setExpandedSubjects((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const toggleManualForm = (subjectId) => {
    const key = String(subjectId);
    setShowManualForm((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const updateManualQuestion = (subjectId, field, value) => {
    const key = String(subjectId);
    setManualQuestions((prev) => ({
      ...prev,
      [key]: {
        ...(prev[key] || { ...blankManualQuestion }),
        [field]: value,
      },
    }));
  };

  const toggleSelection = (questionId, checked) => {
    const numericId = Number(questionId);
    if (!Number.isFinite(numericId)) return;

    setSelectedQuestionIds((prev) => {
      if (checked) {
        return prev.includes(numericId) ? prev : [...prev, numericId];
      }
      return prev.filter((id) => id !== numericId);
    });
  };

  const toggleSubjectSelection = (subject, checked) => {
    const ids = (Array.isArray(subject.questions) ? subject.questions : [])
      .map((question) => Number(question.id))
      .filter((id) => Number.isFinite(id));

    setSelectedQuestionIds((prev) => {
      const next = new Set(prev);
      ids.forEach((id) => {
        if (checked) next.add(id);
        else next.delete(id);
      });
      return Array.from(next);
    });
  };

  const exportSelected = async () => {
    if (!selectedQuestionIds.length) {
      return alert("Select at least one question from the subject banks.");
    }

    setExporting(true);
    try {
      const response = await api.post(
        `/api/school-admin/entrance-exam/classes/${encodeURIComponent(decodedClassName)}/questions/export`,
        { question_ids: selectedQuestionIds }
      );
      setClassExam(response.data?.data?.class_exam || classExam);
      alert(response.data?.message || "Selected questions added to entrance exam.");
      await load("refresh");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to export selected questions.");
    } finally {
      setExporting(false);
    }
  };

  const submitManualQuestion = async (subjectId) => {
    const key = String(subjectId);
    const draft = manualQuestions[key] || { ...blankManualQuestion };
    setSubmittingSubjectId(key);
    try {
      const response = await api.post(
        `/api/school-admin/entrance-exam/classes/${encodeURIComponent(decodedClassName)}/questions`,
        {
          subject_id: Number(subjectId),
          ...draft,
        }
      );
      setClassExam(response.data?.data?.class_exam || classExam);
      setManualQuestions((prev) => ({ ...prev, [key]: { ...blankManualQuestion } }));
      setShowManualForm((prev) => ({ ...prev, [key]: false }));
      alert(response.data?.message || "Question added to entrance exam successfully.");
      await load("refresh");
    } catch (err) {
      const validationError = Object.values(err?.response?.data?.errors || {}).flat()[0];
      alert(validationError || err?.response?.data?.message || "Failed to add question.");
    } finally {
      setSubmittingSubjectId("");
    }
  };

  const removeQuestion = async (questionId) => {
    if (!window.confirm("Remove this question from the entrance exam?")) return;

    setRemovingQuestionId(String(questionId));
    try {
      const response = await api.delete(
        `/api/school-admin/entrance-exam/classes/${encodeURIComponent(decodedClassName)}/questions/${encodeURIComponent(questionId)}`
      );
      setClassExam(response.data?.data?.class_exam || classExam);
      await load("refresh");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to remove entrance exam question.");
    } finally {
      setRemovingQuestionId("");
    }
  };

  if (loading) {
    return <p className="payx-state payx-state--loading">Loading class questions...</p>;
  }

  return (
    <div className="exq-page payx-page payx-page--admin">
      <section className="exq-classbar">
        <div>
          <span className="payx-pill">Entrance Exam Questions</span>
          <h2 className="exq-title">{decodedClassName}</h2>
          <p className="exq-subtitle">Pick from each subject question bank, bulk export to the entrance exam, or add manual questions directly for this class.</p>
        </div>
        <button type="button" className="payx-btn payx-btn--soft" onClick={() => navigate('/school/admin/entrance-exam')}>
          Back
        </button>
      </section>

      <section className="payx-panel">
        <div className="exq-toolbar">
          <div className="payx-meta exq-meta">
            <span>{subjects.length} subject{subjects.length === 1 ? "" : "s"}</span>
            <span>{totalBankQuestions} bank question{totalBankQuestions === 1 ? "" : "s"}</span>
            <span>{currentQuestions.length} entrance exam question{currentQuestions.length === 1 ? "" : "s"}</span>
            <span>{selectedCount} selected</span>
          </div>
          <div className="payx-actions">
            <button type="button" className="payx-btn payx-btn--soft" onClick={() => load("refresh")} disabled={refreshing}>
              {refreshing ? "Refreshing..." : "Refresh"}
            </button>
            <button type="button" className="payx-btn" onClick={exportSelected} disabled={exporting || selectedCount === 0}>
              {exporting ? "Exporting..." : "Export Selected to Entrance Exam"}
            </button>
          </div>
        </div>
      </section>

      <section className="payx-panel">
        <div className="exq-section-head">
          <div>
            <h3>Current Entrance Exam Questions</h3>
            <p className="exq-note">Questions already attached to the {decodedClassName} entrance exam.</p>
          </div>
        </div>

        <div className="exq-table-wrap">
          <table className="exq-table">
            <thead>
              <tr>
                <th>S/N</th>
                <th>Subject</th>
                <th>Question</th>
                <th>Source</th>
                <th>Correct</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {currentQuestions.map((question, index) => (
                <tr key={question.id || `${question.subject_name}-${index}`}>
                  <td>{index + 1}</td>
                  <td>{question.subject_name || "-"}</td>
                  <td>{question.question || "-"}</td>
                  <td>{formatSourceType(question.source_type)}</td>
                  <td>{question.correct_option || "-"}</td>
                  <td>
                    <button
                      type="button"
                      className="exq-link-btn exq-link-btn--danger"
                      onClick={() => removeQuestion(question.id)}
                      disabled={removingQuestionId === String(question.id)}
                    >
                      {removingQuestionId === String(question.id) ? "Removing..." : "Remove"}
                    </button>
                  </td>
                </tr>
              ))}
              {currentQuestions.length === 0 ? (
                <tr>
                  <td colSpan="6" className="exq-empty-cell">No entrance exam questions added yet for this class.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </section>

      <section className="exq-subjects">
        {subjects.map((subject) => {
          const subjectKey = String(subject.subject_id);
          const subjectQuestions = Array.isArray(subject.questions) ? subject.questions : [];
          const selectedInSubject = subjectQuestions.filter((question) => selectedQuestionIds.includes(Number(question.id))).length;
          const allSelected = subjectQuestions.length > 0 && selectedInSubject === subjectQuestions.length;
          const draft = manualQuestions[subjectKey] || { ...blankManualQuestion };
          const isExpanded = Boolean(expandedSubjects[subjectKey]);
          const manualOpen = Boolean(showManualForm[subjectKey]);

          return (
            <article key={subject.subject_id} className="payx-panel exq-subject-card">
              <div className="exq-subject-head">
                <button type="button" className="exq-subject-toggle" onClick={() => toggleSubject(subject.subject_id)}>
                  <span className="exq-subject-title-wrap">
                    <strong>{subject.subject_name}</strong>
                    <small>
                      {subject.question_count || subjectQuestions.length} question{(subject.question_count || subjectQuestions.length) === 1 ? "" : "s"}
                    </small>
                  </span>
                  <span className="exq-subject-toggle-mark">{isExpanded ? "-" : "+"}</span>
                </button>

                <div className="exq-subject-actions">
                  <label className="exq-check">
                    <input
                      type="checkbox"
                      checked={allSelected}
                      onChange={(e) => toggleSubjectSelection(subject, e.target.checked)}
                      disabled={subjectQuestions.length === 0}
                    />
                    <span>Select all</span>
                  </label>
                  <button
                    type="button"
                    className="payx-btn payx-btn--soft"
                    onClick={() => toggleManualForm(subject.subject_id)}
                  >
                    Add Question
                  </button>
                </div>
              </div>

              {isExpanded ? (
                <div className="exq-subject-body">
                  {manualOpen ? (
                    <div className="exq-manual-card">
                      <h4>Manual Question for {subject.subject_name}</h4>
                      <div className="exq-manual-grid">
                        <label className="exq-field exq-field--full">
                          <span>Question</span>
                          <textarea
                            className="payx-input exq-textarea"
                            rows="3"
                            value={draft.question}
                            onChange={(e) => updateManualQuestion(subject.subject_id, "question", e.target.value)}
                          />
                        </label>
                        <label className="exq-field">
                          <span>Option A</span>
                          <input className="payx-input" value={draft.option_a} onChange={(e) => updateManualQuestion(subject.subject_id, "option_a", e.target.value)} />
                        </label>
                        <label className="exq-field">
                          <span>Option B</span>
                          <input className="payx-input" value={draft.option_b} onChange={(e) => updateManualQuestion(subject.subject_id, "option_b", e.target.value)} />
                        </label>
                        <label className="exq-field">
                          <span>Option C</span>
                          <input className="payx-input" value={draft.option_c} onChange={(e) => updateManualQuestion(subject.subject_id, "option_c", e.target.value)} />
                        </label>
                        <label className="exq-field">
                          <span>Option D</span>
                          <input className="payx-input" value={draft.option_d} onChange={(e) => updateManualQuestion(subject.subject_id, "option_d", e.target.value)} />
                        </label>
                        <label className="exq-field exq-field--compact">
                          <span>Correct Option</span>
                          <select className="payx-select" value={draft.correct_option} onChange={(e) => updateManualQuestion(subject.subject_id, "correct_option", e.target.value)}>
                            <option value="A">Option A</option>
                            <option value="B">Option B</option>
                            <option value="C">Option C</option>
                            <option value="D">Option D</option>
                          </select>
                        </label>
                      </div>
                      <div className="payx-actions">
                        <button
                          type="button"
                          className="payx-btn"
                          onClick={() => submitManualQuestion(subject.subject_id)}
                          disabled={submittingSubjectId === subjectKey}
                        >
                          {submittingSubjectId === subjectKey ? "Saving..." : "Save Question"}
                        </button>
                        <button type="button" className="payx-btn payx-btn--soft" onClick={() => toggleManualForm(subject.subject_id)}>
                          Cancel
                        </button>
                      </div>
                    </div>
                  ) : null}

                  <div className="exq-table-wrap">
                    <table className="exq-table">
                      <thead>
                        <tr>
                          <th>Select</th>
                          <th>S/N</th>
                          <th>Question</th>
                          <th>Options</th>
                          <th>Correct</th>
                          <th>Image</th>
                        </tr>
                      </thead>
                      <tbody>
                        {subjectQuestions.map((question, index) => (
                          <tr key={question.id}>
                            <td>
                              <input
                                type="checkbox"
                                checked={selectedQuestionIds.includes(Number(question.id))}
                                onChange={(e) => toggleSelection(question.id, e.target.checked)}
                              />
                            </td>
                            <td>{index + 1}</td>
                            <td>{question.question_text}</td>
                            <td>
                              <div className="exq-options">
                                <span>A. {question.option_a}</span>
                                <span>B. {question.option_b}</span>
                                <span>C. {question.option_c || "-"}</span>
                                <span>D. {question.option_d || "-"}</span>
                              </div>
                            </td>
                            <td>{question.correct_option || "-"}</td>
                            <td>
                              {question.media_url ? (
                                <a className="exq-media-link" href={question.media_url} target="_blank" rel="noreferrer">
                                  View
                                </a>
                              ) : (
                                "-"
                              )}
                            </td>
                          </tr>
                        ))}
                        {subjectQuestions.length === 0 ? (
                          <tr>
                            <td colSpan="6" className="exq-empty-cell">No question bank questions available for this subject yet.</td>
                          </tr>
                        ) : null}
                      </tbody>
                    </table>
                  </div>
                </div>
              ) : null}
            </article>
          );
        })}
      </section>
    </div>
  );
}
