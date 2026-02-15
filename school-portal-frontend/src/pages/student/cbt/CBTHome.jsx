import { useEffect, useState } from "react";
import api from "../../../services/api";

export default function StudentCBTHome() {
  const [exams, setExams] = useState([]);
  const [selectedExam, setSelectedExam] = useState(null);
  const [questions, setQuestions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingQuestions, setLoadingQuestions] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/cbt/exams");
      setExams(res.data?.data || []);
    } catch {
      alert("Failed to load CBT");
    } finally {
      setLoading(false);
    }
  };

  const openExam = async (exam) => {
    if (!exam?.is_open) {
      alert("This CBT is not open yet. Check the exam start time.");
      return;
    }

    setSelectedExam(exam);
    setLoadingQuestions(true);
    try {
      const res = await api.get(`/api/student/cbt/exams/${exam.id}/questions`);
      setQuestions(res.data?.data || []);
    } catch (err) {
      setQuestions([]);
      alert(err?.response?.data?.message || "Failed to load exam questions");
    } finally {
      setLoadingQuestions(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  return (
    <div>
      <h2>CBT</h2>

      {loading ? (
        <p>Loading...</p>
      ) : exams.length === 0 ? (
        <p>No published CBT exam for your current class and current term.</p>
      ) : (
        <table border="1" cellPadding="10" width="100%">
          <thead>
            <tr>
              <th style={{ width: 70 }}>S/N</th>
              <th>Title</th>
              <th>Subject</th>
              <th>Window</th>
              <th>Status</th>
              <th>Questions</th>
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
                <td>{x.is_open ? "Open" : "Closed/Upcoming"}</td>
                <td>
                  <button onClick={() => openExam(x)} disabled={!x.is_open}>
                    {x.is_open ? "View" : "Not Open"}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {selectedExam ? (
        <div style={{ marginTop: 14, border: "1px solid #ddd", borderRadius: 10, padding: 12 }}>
          <h3 style={{ marginTop: 0 }}>{selectedExam.title} - Questions</h3>
          {loadingQuestions ? (
            <p>Loading questions...</p>
          ) : (
            <table border="1" cellPadding="8" width="100%">
              <thead>
                <tr>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Question</th>
                  <th>Options</th>
                </tr>
              </thead>
              <tbody>
                {questions.map((q, idx) => (
                  <tr key={q.id}>
                    <td>{idx + 1}</td>
                    <td>{q.question_text}</td>
                    <td>
                      <div>A. {q.option_a}</div>
                      <div>B. {q.option_b}</div>
                      <div>C. {q.option_c}</div>
                      <div>D. {q.option_d}</div>
                    </td>
                  </tr>
                ))}
                {questions.length === 0 ? (
                  <tr>
                    <td colSpan="3">No questions in this exam yet.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          )}
        </div>
      ) : null}
    </div>
  );
}
