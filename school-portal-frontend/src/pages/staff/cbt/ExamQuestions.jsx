import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import "../../shared/CbtShowcase.css";

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export default function ExamQuestions() {
  const { examId } = useParams();
  const [loading, setLoading] = useState(true);
  const [exam, setExam] = useState(null);
  const [questions, setQuestions] = useState([]);

  const load = async () => {
    setLoading(true);
    const [examsRes, questionsRes] = await Promise.allSettled([
      api.get("/api/staff/cbt/exams"),
      api.get(`/api/staff/cbt/exams/${examId}/questions`),
    ]);

    if (examsRes.status === "fulfilled") {
      const allExams = examsRes.value?.data?.data || [];
      setExam(allExams.find((x) => String(x.id) === String(examId)) || null);
    } else {
      setExam(null);
    }

    if (questionsRes.status === "fulfilled") {
      setQuestions(questionsRes.value?.data?.data || []);
    } else {
      setQuestions([]);
      alert("Failed to load exam questions");
    }

    setLoading(false);
  };

  useEffect(() => {
    load();
  }, [examId]);

  return (
    <StaffFeatureLayout title="View Exam">
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
        <div>
          <div style={{ fontWeight: 700, fontSize: 18 }}>{exam?.title || "Exam Questions"}</div>
          <div style={{ opacity: 0.8, fontSize: 13 }}>
            {(exam?.subject_name || "-")} | {(exam?.class_name || "-")} | {(exam?.term_name || "-")}
          </div>
          <div style={{ opacity: 0.75, fontSize: 12 }}>
            {formatDate(exam?.starts_at)} - {formatDate(exam?.ends_at)}
          </div>
        </div>
      </div>

      <section className="cbx-panel">
        {loading ? <p className="cbx-state cbx-state--loading">Loading exam questions...</p> : null}
        {!loading ? (
          <div className="cbx-table-wrap">
            <table className="cbx-table">
              <thead>
                <tr>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Question</th>
                  <th>Options</th>
                  <th style={{ width: 90 }}>Correct</th>
                  <th style={{ width: 130 }}>Image</th>
                </tr>
              </thead>
              <tbody>
                {questions.map((q, idx) => (
                  <tr key={q.id}>
                    <td>{idx + 1}</td>
                    <td>{q.question_text || "-"}</td>
                    <td>
                      A. {q.option_a || "-"}
                      <br />
                      B. {q.option_b || "-"}
                      <br />
                      C. {q.option_c || "-"}
                      <br />
                      D. {q.option_d || "-"}
                    </td>
                    <td>{q.correct_option || "-"}</td>
                    <td>
                      {q.media_url ? (
                        <a href={q.media_url} target="_blank" rel="noreferrer">
                          {q.media_type === "image" ? "View image" : "View file"}
                        </a>
                      ) : (
                        "-"
                      )}
                    </td>
                  </tr>
                ))}
                {!questions.length ? (
                  <tr>
                    <td colSpan="5">No questions exported to this exam yet.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>
    </StaffFeatureLayout>
  );
}
