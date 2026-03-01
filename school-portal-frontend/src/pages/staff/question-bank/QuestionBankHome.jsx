import { useEffect, useMemo, useRef, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import faqArt from "../../../assets/question-bank/faq.svg";
import questionsArt from "../../../assets/question-bank/questions.svg";
import searchingArt from "../../../assets/question-bank/searching.svg";
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

export default function QuestionBankHome() {
  const [subjectsRaw, setSubjectsRaw] = useState([]);
  const [questions, setQuestions] = useState([]);
  const [exams, setExams] = useState([]);
  const [loading, setLoading] = useState(true);
  const [termId, setTermId] = useState("");
  const [subjectId, setSubjectId] = useState("");
  const [selectedQuestionIds, setSelectedQuestionIds] = useState([]);
  const [exportExamId, setExportExamId] = useState("");
  const [draft, setDraft] = useState(defaultQuestion);
  const [aiPrompt, setAiPrompt] = useState("");
  const [aiCount, setAiCount] = useState(3);
  const [aiImport, setAiImport] = useState(true);
  const [aiFallbackMessage, setAiFallbackMessage] = useState("");
  const manualCreateRef = useRef(null);
  const questionTextRef = useRef(null);

  const loadQuestions = async (sid = "") => {
    if (!sid) {
      setQuestions([]);
      return;
    }
    const res = await api.get("/api/staff/question-bank", {
      params: { subject_id: sid },
    });
    setQuestions(res.data?.data || []);
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
    return Array.from(new Map(filtered.map((s) => [s.subject_id, s])).values());
  }, [subjectsRaw, termId]);

  const selectedSubject = useMemo(
    () => subjects.find((s) => String(s.subject_id) === String(subjectId)) || null,
    [subjects, subjectId]
  );

  useEffect(() => {
    if (!subjects.length) {
      setSubjectId("");
      setQuestions([]);
      return;
    }

    const exists = subjects.some((s) => String(s.subject_id) === String(subjectId));
    const nextId = exists ? String(subjectId) : String(subjects[0].subject_id);

    if (nextId !== String(subjectId)) {
      setSubjectId(nextId);
      return;
    }

    loadQuestions(nextId).catch((err) => {
      setQuestions([]);
      console.warn("Question bank questions failed:", err?.response?.data || err?.message);
    });
  }, [subjects]);

  const handleSubjectSelect = async (nextSubjectId) => {
    const sid = String(nextSubjectId || "");
    setSubjectId(sid);
    setSelectedQuestionIds([]);
    await loadQuestions(sid);
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
      await loadQuestions(subjectId);
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
      if (aiImport) await loadQuestions(subjectId);
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
      await loadQuestions(subjectId);
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

  const toggleSelect = (id, checked) => {
    setSelectedQuestionIds((prev) => (checked ? [...new Set([...prev, id])] : prev.filter((x) => x !== id)));
  };

  const cleanQuestionText = (text) =>
    String(text || "").replace(/^\s*(?:\(?\d+\)?[\).\-\:]*|[ivxlcdm]+[\).\-\:])\s*/i, "").trim();

  return (
    <StaffFeatureLayout title="Question Bank (Staff)">
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
              <span>{loading ? "Syncing..." : `${questions.length} question${questions.length === 1 ? "" : "s"} in selected subject`}</span>
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
              <strong>{selectedSubject?.subject_name || "None selected"}</strong>
            </div>
          </div>

          <div className="qbx-subject-buttons">
            {!subjects.length ? (
              <p className="qbx-state qbx-state--warn">No assigned subject found for this term.</p>
            ) : (
              subjects.map((s) => (
                <button
                  key={s.subject_id}
                  type="button"
                  className={`qbx-subject-btn ${String(subjectId) === String(s.subject_id) ? "is-active" : ""}`}
                  onClick={() => handleSubjectSelect(s.subject_id)}
                >
                  <span>{s.subject_name}</span>
                  <small>{s.subject_code || s.class_name || "Subject"}</small>
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
              <input className="qbx-field" type="file" onChange={(e) => setDraft({ ...draft, media: e.target.files?.[0] || null })} />
            </div>
            <textarea
              className="qbx-field"
              rows={2}
              value={draft.explanation}
              onChange={(e) => setDraft({ ...draft, explanation: e.target.value })}
              placeholder="Explanation (optional)"
            />
            <button className="qbx-btn" type="submit">
              Save Question
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
                      <td>{idx + 1}</td>
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
            </div>
          )}
        </section>
      </div>
    </StaffFeatureLayout>
  );
}
