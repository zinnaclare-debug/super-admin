import { useEffect, useMemo, useRef, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

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

  const loadSubjects = async () => {
    const res = await api.get("/api/staff/question-bank/subjects");
    setSubjectsRaw(res.data?.data || []);
  };

  const loadQuestions = async (sid = "") => {
    const res = await api.get("/api/staff/question-bank", {
      params: sid ? { subject_id: sid } : {},
    });
    setQuestions(res.data?.data || []);
  };

  const loadExams = async () => {
    const res = await api.get("/api/staff/cbt/exams");
    setExams(res.data?.data || []);
  };

  const loadAll = async () => {
    setLoading(true);
    const [subjectsRes, questionsRes, examsRes] = await Promise.allSettled([
      loadSubjects(),
      loadQuestions(subjectId),
      loadExams(),
    ]);

    if (subjectsRes.status === "rejected") {
      setSubjectsRaw([]);
      console.warn("Question bank subjects failed:", subjectsRes.reason?.response?.data || subjectsRes.reason?.message);
    }

    if (questionsRes.status === "rejected") {
      setQuestions([]);
      console.warn("Question bank questions failed:", questionsRes.reason?.response?.data || questionsRes.reason?.message);
    }

    if (examsRes.status === "rejected") {
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

  const createQuestion = async (e) => {
    e.preventDefault();
    if (!subjectId) return alert("Select subject");

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
    if (!subjectId) return alert("Select subject first");
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
      alert(`Generated ${res.data?.data?.length || 0} questions`);
    } catch (err) {
      const code = err?.response?.data?.code || err?.response?.data?.details?.error?.code;
      const msg = err?.response?.data?.message || "AI generate failed";
      if (code === "insufficient_quota") {
        setAiFallbackMessage("AI quota exceeded. Switched to manual question creation mode.");
        manualCreateRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
        setTimeout(() => questionTextRef.current?.focus(), 350);
        return;
      }
      alert(msg);
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

      <div style={{ marginTop: 12, border: "1px solid #ddd", borderRadius: 10, padding: 12 }}>
        <h3 style={{ marginTop: 0 }}>Filters</h3>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, maxWidth: 760 }}>
          <select value={termId} onChange={(e) => setTermId(e.target.value)}>
            <option value="">All terms</option>
            {terms.map((t) => (
              <option key={t.term_id} value={t.term_id}>
                {t.term_name}
              </option>
            ))}
          </select>
          <select
            value={subjectId}
            onChange={async (e) => {
              const v = e.target.value;
              setSubjectId(v);
              await loadQuestions(v);
            }}
          >
            <option value="">Select subject</option>
            {subjects.map((s) => (
              <option key={s.subject_id} value={s.subject_id}>
                {s.subject_name}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div style={{ marginTop: 12, border: "1px solid #ddd", borderRadius: 10, padding: 12 }}>
        <h3 style={{ marginTop: 0 }}>Generate by AI</h3>
        {aiFallbackMessage ? (
          <div style={{ marginBottom: 8, padding: 8, border: "1px solid #f3c06b", borderRadius: 6, background: "#fff8e8" }}>
            {aiFallbackMessage}
          </div>
        ) : null}
        <div style={{ display: "grid", gap: 8, maxWidth: 900 }}>
          <textarea
            rows={3}
            value={aiPrompt}
            onChange={(e) => setAiPrompt(e.target.value)}
            placeholder="Type instruction for AI question generation..."
          />
          <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
            <select value={aiCount} onChange={(e) => setAiCount(Number(e.target.value))}>
              <option value={3}>3 questions</option>
              <option value={5}>5 questions</option>
              <option value={10}>10 questions</option>
            </select>
            <label>
              <input type="checkbox" checked={aiImport} onChange={(e) => setAiImport(e.target.checked)} /> Import to
              question bank
            </label>
            <button onClick={generateByAI}>Generate by AI</button>
          </div>
        </div>
      </div>

      <div ref={manualCreateRef} style={{ marginTop: 12, border: "1px solid #ddd", borderRadius: 10, padding: 12 }}>
        <h3 style={{ marginTop: 0 }}>Create Question</h3>
        <form onSubmit={createQuestion} style={{ display: "grid", gap: 8, maxWidth: 980 }}>
          <textarea
            ref={questionTextRef}
            rows={2}
            required
            value={draft.question_text}
            onChange={(e) => setDraft({ ...draft, question_text: e.target.value })}
            placeholder="Question"
          />
          <input
            required
            value={draft.option_a}
            onChange={(e) => setDraft({ ...draft, option_a: e.target.value })}
            placeholder="Option A"
          />
          <input
            required
            value={draft.option_b}
            onChange={(e) => setDraft({ ...draft, option_b: e.target.value })}
            placeholder="Option B"
          />
          <input value={draft.option_c} onChange={(e) => setDraft({ ...draft, option_c: e.target.value })} placeholder="Option C" />
          <input value={draft.option_d} onChange={(e) => setDraft({ ...draft, option_d: e.target.value })} placeholder="Option D" />
          <div style={{ display: "flex", gap: 8 }}>
            <select
              value={draft.correct_option}
              onChange={(e) => setDraft({ ...draft, correct_option: e.target.value })}
            >
              <option value="A">Correct: A</option>
              <option value="B">Correct: B</option>
              <option value="C">Correct: C</option>
              <option value="D">Correct: D</option>
            </select>
            <select value={draft.media_type} onChange={(e) => setDraft({ ...draft, media_type: e.target.value })}>
              <option value="image">Image</option>
              <option value="video">Video</option>
              <option value="formula">Formula</option>
            </select>
            <input type="file" onChange={(e) => setDraft({ ...draft, media: e.target.files?.[0] || null })} />
          </div>
          <textarea
            rows={2}
            value={draft.explanation}
            onChange={(e) => setDraft({ ...draft, explanation: e.target.value })}
            placeholder="Explanation (optional)"
          />
          <button type="submit">Save</button>
        </form>
      </div>

      <div style={{ marginTop: 12, border: "1px solid #ddd", borderRadius: 10, padding: 12 }}>
        <h3 style={{ marginTop: 0 }}>Export to CBT</h3>
        <div style={{ display: "flex", gap: 8 }}>
          <select value={exportExamId} onChange={(e) => setExportExamId(e.target.value)}>
            <option value="">Select CBT exam</option>
            {exams.map((e) => (
              <option key={e.id} value={e.id}>
                {e.title} ({e.subject_name || "-"})
              </option>
            ))}
          </select>
          <button onClick={exportToCBT}>Export Selected Questions</button>
        </div>
      </div>

      <div style={{ marginTop: 12 }}>
        {loading ? (
          <p>Loading...</p>
        ) : (
          <table border="1" cellPadding="8" width="100%">
            <thead>
              <tr>
                <th style={{ width: 40 }}></th>
                <th style={{ width: 60 }}>S/N</th>
                <th>Question</th>
                <th>Options</th>
                <th style={{ width: 90 }}>Correct</th>
                <th style={{ width: 120 }}>Source</th>
                <th style={{ width: 80 }}>Action</th>
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
                  <td>{q.media_type || "-"}</td>
                  <td>
                    <button onClick={() => removeQuestion(q.id)}>Del</button>
                  </td>
                </tr>
              ))}
              {!questions.length && (
                <tr>
                  <td colSpan="7">No questions found.</td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>
    </StaffFeatureLayout>
  );
}
