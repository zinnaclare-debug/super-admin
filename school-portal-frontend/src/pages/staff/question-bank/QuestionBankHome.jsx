import { useEffect, useMemo, useRef, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import faqArt from "../../../assets/question-bank/faq.svg";
import questionsArt from "../../../assets/question-bank/questions.svg";
import searchingArt from "../../../assets/question-bank/searching.svg";
import { compressBrandingImage } from "../../../utils/profileImage";
import "./QuestionBankHome.css";

const defaultQuestion = {
  question_text: "",
  option_a: "",
  option_b: "",
  option_c: "",
  option_d: "",
  correct_option: "A",
  explanation: "",
  media: null,
  media_type: "image",
};
const PAGE_SIZE = 30;

export default function QuestionBankHome() {
  const [subjectsRaw, setSubjectsRaw] = useState([]);
  const [questions, setQuestions] = useState([]);
  const [exams, setExams] = useState([]);
  const [loading, setLoading] = useState(true);
  const [termId, setTermId] = useState("");
  const [subjectId, setSubjectId] = useState("");
  const [termSubjectId, setTermSubjectId] = useState("");
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
  const [selectedQuestionIds, setSelectedQuestionIds] = useState([]);
  const [exportExamId, setExportExamId] = useState("");
  const [draft, setDraft] = useState(defaultQuestion);
  const [aiPrompt, setAiPrompt] = useState("");
  const [aiCount, setAiCount] = useState(3);
  const [aiImport, setAiImport] = useState(true);
  const [aiFallbackMessage, setAiFallbackMessage] = useState("");
  const [processingMedia, setProcessingMedia] = useState(false);
  const manualCreateRef = useRef(null);
  const questionTextRef = useRef(null);

  const loadQuestions = async (sid = "", tsid = "", page = 1) => {
    if (!sid) {
      setQuestions([]);
      setPagination({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
      return;
    }
    const res = await api.get("/api/staff/question-bank", {
      params: { subject_id: sid, term_subject_id: tsid || undefined, page, per_page: PAGE_SIZE },
    });
    setQuestions(res.data?.data || []);
    setPagination(res.data?.meta || { current_page: page, last_page: 1, total: 0, from: 0, to: 0 });
  };

  const loadAll = async () => {
    setLoading(true);
    const [subjectsRes, examsRes] = await Promise.allSettled([
      api.get("/api/staff/question-bank/subjects"),
      api.get("/api/staff/cbt/exams"),
    ]);

    if (subjectsRes.status === "fulfilled") {
      setSubjectsRaw(subjectsRes.value?.data?.data || []);
    } else {
      setSubjectsRaw([]);
      console.warn("Question bank subjects failed:", subjectsRes.reason?.response?.data || subjectsRes.reason?.message);
    }

    if (examsRes.status === "fulfilled") {
      setExams(examsRes.value?.data?.data || []);
    } else {
      setExams([]);
      console.warn("Question bank CBT exams failed:", examsRes.reason?.response?.data || examsRes.reason?.message);
    }

    setLoading(false);
  };

  useEffect(() => {
    loadAll();
  }, []);

  const terms = useMemo(
    () => Array.from(new Map(subjectsRaw.map((s) => [s.term_id, s])).values()),
    [subjectsRaw]
  );

  const subjects = useMemo(() => {
    const filtered = termId ? subjectsRaw.filter((s) => String(s.term_id) === String(termId)) : subjectsRaw;
    return filtered;
  }, [subjectsRaw, termId]);

  const selectedSubject = useMemo(
    () => subjects.find((s) => String(s.term_subject_id) === String(termSubjectId)) || subjects.find((s) => String(s.subject_id) === String(subjectId)) || null,
    [subjects, subjectId, termSubjectId]
  );

  useEffect(() => {
    if (!subjects.length) {
      setSubjectId("");
      setQuestions([]);
      return;
    }

    const exists = subjects.some((s) => String(s.term_subject_id) === String(termSubjectId));
    const nextSubject = exists ? selectedSubject : subjects[0];
    const nextId = String(nextSubject?.subject_id || "");
    const nextTermSubjectId = String(nextSubject?.term_subject_id || "");

    if (nextId !== String(subjectId) || nextTermSubjectId !== String(termSubjectId)) {
      setSubjectId(nextId);
      setTermSubjectId(nextTermSubjectId);
      return;
    }

    loadQuestions(nextId, nextTermSubjectId, 1).catch((err) => {
      setQuestions([]);
      console.warn("Question bank questions failed:", err?.response?.data || err?.message);
    });
  }, [subjects, subjectId, termSubjectId, selectedSubject]);

  const handleSubjectSelect = async (subject) => {
    const sid = String(subject?.subject_id || "");
    const tsid = String(subject?.term_subject_id || "");
    setSubjectId(sid);
    setTermSubjectId(tsid);
    setSelectedQuestionIds([]);
    await loadQuestions(sid, tsid, 1);
  };

  const createQuestion = async (e) => {
    e.preventDefault();
    if (!subjectId) return alert("Select a subject button first");

    const fd = new FormData();
    fd.append("subject_id", subjectId);
    fd.append("question_text", draft.question_text);
    fd.append("option_a", draft.option_a);
    fd.append("option_b", draft.option_b);
    if (draft.option_c) fd.append("option_c", draft.option_c);
    if (draft.option_d) fd.append("option_d", draft.option_d);
    fd.append("correct_option", draft.correct_option);
    if (draft.explanation) fd.append("explanation", draft.explanation);
    if (draft.media) {
      fd.append("media", draft.media);
      fd.append("media_type", draft.media_type);
    }

    try {
      await api.post("/api/staff/question-bank", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      setDraft(defaultQuestion);
      await loadQuestions(subjectId, termSubjectId, pagination.current_page || 1);
      alert("Question saved");
    } catch (err) {
      alert(err?.response?.data?.message || "Save failed");
    }
  };

  const generateByAI = async () => {
    if (!subjectId) return alert("Select a subject button first");
    if (!aiPrompt.trim()) return alert("Type prompt");
    setAiFallbackMessage("");
    try {
      const res = await api.post("/api/staff/question-bank/ai-generate", {
        subject_id: Number(subjectId),
        prompt: aiPrompt,
        count: Number(aiCount),
        import_to_bank: aiImport,
      });
      if (aiImport) await loadQuestions(subjectId, termSubjectId, 1);
      alert(`Generated ${res.data?.data?.length || 0} questions for ${selectedSubject?.subject_name || "selected subject"}`);
    } catch (err) {
      const code = err?.response?.data?.code || err?.response?.data?.details?.error?.code;
      const msg = err?.response?.data?.message || "AI generate failed";
      const providerMessage =
        err?.response?.data?.provider_message ||
        err?.response?.data?.details?.error?.message ||
        err?.response?.data?.details?.message ||
        "";
      if (code === "insufficient_quota") {
        setAiFallbackMessage("AI quota exceeded. Switched to manual question creation mode.");
        manualCreateRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
        setTimeout(() => questionTextRef.current?.focus(), 350);
        return;
      }
      alert(providerMessage ? `${msg}\n${providerMessage}` : msg);
    }
  };

  const removeQuestion = async (id) => {
    if (!window.confirm("Delete this question?")) return;
    try {
      await api.delete(`/api/staff/question-bank/${id}`);
      await loadQuestions(subjectId, termSubjectId, pagination.current_page || 1);
      setSelectedQuestionIds((prev) => prev.filter((x) => x !== id));
    } catch (err) {
      alert(err?.response?.data?.message || "Delete failed");
    }
  };

  const exportToCBT = async () => {
    if (!exportExamId) return alert("Select CBT exam");
    if (!selectedQuestionIds.length) return alert("Select at least one question");
    try {
      await api.post(`/api/staff/cbt/exams/${exportExamId}/export-question-bank`, {
        question_ids: selectedQuestionIds,
      });
      alert("Exported to CBT");
    } catch (err) {
      alert(err?.response?.data?.message || "Export failed");
    }
  };

  const bulkDelete = async () => {
    if (!selectedQuestionIds.length) return alert("Select at least one question to delete.");
    if (!window.confirm(`Delete ${selectedQuestionIds.length} selected question(s)?`)) return;
    try {
      await api.post("/api/staff/question-bank/bulk-delete", {
        question_ids: selectedQuestionIds,
      });
      setSelectedQuestionIds([]);
      await loadQuestions(subjectId, termSubjectId, pagination.current_page || 1);
    } catch (err) {
      alert(err?.response?.data?.message || "Bulk delete failed");
    }
  };

  const toggleSelect = (id, checked) => {
    setSelectedQuestionIds((prev) => (checked ? [...new Set([...prev, id])] : prev.filter((x) => x !== id)));
  };

  const toggleSelectPage = (checked) => {
    const pageIds = questions.map((q) => q.id);
    setSelectedQuestionIds((prev) => {
      if (checked) return [...new Set([...prev, ...pageIds])];
      return prev.filter((id) => !pageIds.includes(id));
    });
  };

  const goToPage = async (page) => {
    const nextPage = Math.max(1, Math.min(Number(page || 1), Number(pagination.last_page || 1)));
    await loadQuestions(subjectId, termSubjectId, nextPage);
  };

  const cleanQuestionText = (text) =>
    String(text || "").replace(/^\s*(?:\(?\d+\)?[\).\-\:]*|[ivxlcdm]+[\).\-\:])\s*/i, "").trim();

  const handleMediaPick = async (event) => {
    const file = event.target.files?.[0] || null;
    if (!file) {
      setDraft((prev) => ({ ...prev, media: null }));
      event.target.value = "";
      return;
    }

    if (draft.media_type !== "image" || !String(file.type || "").startsWith("image/")) {
      setDraft((prev) => ({ ...prev, media: file }));
      event.target.value = "";
      return;
    }

    setProcessingMedia(true);
    try {
      const compressed = await compressBrandingImage(file, {
        maxWidth: 1600,
        maxHeight: 1600,
        maxBytes: 150 * 1024,
      });
      setDraft((prev) => ({ ...prev, media: compressed }));
    } catch (error) {
      alert(error?.message || "Failed to process image.");
      setDraft((prev) => ({ ...prev, media: null }));
    } finally {
      setProcessingMedia(false);
      event.target.value = "";
    }
  };

  return (
    <StaffFeatureLayout title="Question Bank (Staff)" showHeader={false}>
      <div className="qbx-page">
        <section className="qbx-hero">
          <div>
            <span className="qbx-pill">Staff Question Bank</span>
            <h2>Build subject questions and move them into CBT faster</h2>
            <p className="qbx-subtitle">
              Pick a taught subject button, generate or create questions directly for that subject, then export selected questions into CBT.
            </p>
            <div className="qbx-metrics">
              <span>{loading ? "Loading..." : `${subjects.length} subject${subjects.length === 1 ? "" : "s"} available`}</span>
              <span>{loading ? "Syncing..." : `${pagination.total || 0} question${Number(pagination.total || 0) === 1 ? "" : "s"} in selected subject`}</span>
            </div>
          </div>

          <div className="qbx-hero-art" aria-hidden="true">
            <div className="qbx-art qbx-art--main">
              <img src={faqArt} alt="" />
            </div>
            <div className="qbx-art qbx-art--questions">
              <img src={questionsArt} alt="" />
            </div>
            <div className="qbx-art qbx-art--searching">
              <img src={searchingArt} alt="" />
            </div>
          </div>
        </section>

        <section className="qbx-panel">
          <div className="qbx-filter-row">
            <div className="qbx-filter">
              <label htmlFor="qbx-term">Term</label>
              <select
                id="qbx-term"
                className="qbx-field"
                value={termId}
                onChange={(e) => {
                  setTermId(e.target.value);
                  setSelectedQuestionIds([]);
                  setTermSubjectId("");
                }}
                disabled={loading}
              >
                <option value="">All terms</option>
                {terms.map((t) => (
                  <option key={t.term_id} value={t.term_id}>
                    {t.term_name}
                  </option>
                ))}
              </select>
            </div>
            <div className="qbx-selected">
              <span>Selected Subject</span>
              <strong>{selectedSubject ? `${selectedSubject.subject_name} - ${selectedSubject.class_name}` : "None selected"}</strong>
            </div>
          </div>

          <div className="qbx-subject-buttons">
            {!subjects.length ? (
              <p className="qbx-state qbx-state--warn">No assigned subject found for this term.</p>
            ) : (
              subjects.map((s) => (
                <button
                  key={s.term_subject_id}
                  type="button"
                  className={`qbx-subject-btn ${String(termSubjectId) === String(s.term_subject_id) ? "is-active" : ""}`}
                  onClick={() => handleSubjectSelect(s)}
                >
                  <span>{s.subject_name}</span>
                  <small className="qbx-subject-meta">
                    {s.subject_code ? <b>{s.subject_code}</b> : null}
                    <em>{s.class_name || "Class not set"}</em>
                  </small>
                </button>
              ))
            )}
          </div>
        </section>

        <section className="qbx-panel">
          <h3>Generate by AI</h3>
          {aiFallbackMessage ? (
            <div className="qbx-alert">{aiFallbackMessage}</div>
          ) : null}
          <div className="qbx-grid">
            <textarea
              className="qbx-field"
              rows={3}
              value={aiPrompt}
              onChange={(e) => setAiPrompt(e.target.value)}
              placeholder="Type instruction for AI question generation..."
            />
            <div className="qbx-inline">
              <select className="qbx-field" value={aiCount} onChange={(e) => setAiCount(Number(e.target.value))}>
                <option value={3}>3 questions</option>
                <option value={5}>5 questions</option>
                <option value={10}>10 questions</option>
              </select>
              <label className="qbx-check">
                <input type="checkbox" checked={aiImport} onChange={(e) => setAiImport(e.target.checked)} /> Import to question bank
              </label>
              <button className="qbx-btn" onClick={generateByAI}>
                Generate by AI
              </button>
            </div>
          </div>
        </section>

        <section ref={manualCreateRef} className="qbx-panel">
          <h3>Create Question</h3>
          <form onSubmit={createQuestion} className="qbx-grid">
            <textarea
              ref={questionTextRef}
              className="qbx-field"
              rows={2}
              required
              value={draft.question_text}
              onChange={(e) => setDraft({ ...draft, question_text: e.target.value })}
              placeholder="Question"
            />
            <input
              className="qbx-field"
              required
              value={draft.option_a}
              onChange={(e) => setDraft({ ...draft, option_a: e.target.value })}
              placeholder="Option A"
            />
            <input
              className="qbx-field"
              required
              value={draft.option_b}
              onChange={(e) => setDraft({ ...draft, option_b: e.target.value })}
              placeholder="Option B"
            />
            <input className="qbx-field" value={draft.option_c} onChange={(e) => setDraft({ ...draft, option_c: e.target.value })} placeholder="Option C" />
            <input className="qbx-field" value={draft.option_d} onChange={(e) => setDraft({ ...draft, option_d: e.target.value })} placeholder="Option D" />
            <div className="qbx-inline">
              <select
                className="qbx-field"
                value={draft.correct_option}
                onChange={(e) => setDraft({ ...draft, correct_option: e.target.value })}
              >
                <option value="A">Correct: A</option>
                <option value="B">Correct: B</option>
                <option value="C">Correct: C</option>
                <option value="D">Correct: D</option>
              </select>
              <select className="qbx-field" value={draft.media_type} onChange={(e) => setDraft({ ...draft, media_type: e.target.value })}>
                <option value="image">Image</option>
                <option value="video">Video</option>
                <option value="formula">Formula</option>
              </select>
              <input className="qbx-field" type="file" onChange={handleMediaPick} />
            </div>
            <small className="qbx-state">
              {processingMedia
                ? "Processing media..."
                : draft.media_type === "image"
                  ? "Question images are auto-compressed before upload."
                  : "Non-image media uploads are sent unchanged."}
            </small>
            <textarea
              className="qbx-field"
              rows={2}
              value={draft.explanation}
              onChange={(e) => setDraft({ ...draft, explanation: e.target.value })}
              placeholder="Explanation (optional)"
            />
            <button className="qbx-btn" type="submit" disabled={processingMedia}>
              {processingMedia ? "Processing..." : "Save Question"}
            </button>
          </form>
        </section>

        <section className="qbx-panel">
          <h3>Export to CBT</h3>
          <div className="qbx-inline">
            <select className="qbx-field" value={exportExamId} onChange={(e) => setExportExamId(e.target.value)}>
              <option value="">Select CBT exam</option>
              {exams.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.title} ({e.subject_name || "-"})
                </option>
              ))}
            </select>
            <button className="qbx-btn" onClick={exportToCBT}>
              Export Selected Questions
            </button>
          </div>
        </section>

        <section className="qbx-panel">
          <div className="qbx-question-actions">
            <div>
              <h3>Questions</h3>
              <p>{selectedQuestionIds.length} selected from this question bank.</p>
            </div>
            <button type="button" className="qbx-btn qbx-btn--danger" onClick={bulkDelete} disabled={!selectedQuestionIds.length}>
              Delete Selected Questions ({selectedQuestionIds.length})
            </button>
          </div>
          {loading ? (
            <p className="qbx-state qbx-state--loading">Loading question bank...</p>
          ) : (
            <div className="qbx-table-wrap">
              <table className="qbx-table">
                <thead>
                  <tr>
                    <th style={{ width: 40 }}></th>
                    <th style={{ width: 60 }}>S/N</th>
                    <th>Question</th>
                    <th>Options</th>
                    <th style={{ width: 90 }}>Correct</th>
                    <th style={{ width: 140 }}>Image</th>
                    <th style={{ width: 90 }}>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {questions.map((q, idx) => (
                    <tr key={q.id}>
                      <td>
                        <input
                          type="checkbox"
                          checked={selectedQuestionIds.includes(q.id)}
                          onChange={(e) => toggleSelect(q.id, e.target.checked)}
                        />
                      </td>
                      <td>{(pagination.from || 1) + idx}</td>
                      <td>{cleanQuestionText(q.question_text)}</td>
                      <td>
                        A. {q.option_a}
                        <br />
                        B. {q.option_b}
                        <br />
                        {q.option_c ? <>C. {q.option_c}<br /></> : null}
                        {q.option_d ? <>D. {q.option_d}</> : null}
                      </td>
                      <td>{q.correct_option}</td>
                      <td>
                        {q.media_url ? (
                          <a href={q.media_url} target="_blank" rel="noreferrer">
                            {q.media_type === "image" ? "View image" : "View file"}
                          </a>
                        ) : (
                          "-"
                        )}
                      </td>
                      <td>
                        <button className="qbx-btn qbx-btn--danger" onClick={() => removeQuestion(q.id)}>
                          Del
                        </button>
                      </td>
                    </tr>
                  ))}
                  {!questions.length && (
                    <tr>
                      <td colSpan="7">No questions found for the selected subject.</td>
                    </tr>
                  )}
                </tbody>
              </table>
              {questions.length ? (
                <div className="qbx-pagination">
                  <label className="qbx-check">
                    <input
                      type="checkbox"
                      checked={questions.length > 0 && questions.every((q) => selectedQuestionIds.includes(q.id))}
                      onChange={(e) => toggleSelectPage(e.target.checked)}
                    />
                    Select all on this page
                  </label>
                  <span>
                    Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total || 0}
                  </span>
                  <div className="qbx-inline">
                    <button className="qbx-btn qbx-btn--soft" onClick={() => goToPage((pagination.current_page || 1) - 1)} disabled={(pagination.current_page || 1) <= 1}>
                      Previous
                    </button>
                    <strong>Page {pagination.current_page || 1} of {pagination.last_page || 1}</strong>
                    <button className="qbx-btn qbx-btn--soft" onClick={() => goToPage((pagination.current_page || 1) + 1)} disabled={(pagination.current_page || 1) >= (pagination.last_page || 1)}>
                      Next
                    </button>
                  </div>
                </div>
              ) : null}
            </div>
          )}
        </section>
      </div>
    </StaffFeatureLayout>
  );
}

