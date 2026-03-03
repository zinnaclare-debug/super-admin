import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export default function ExamResults() {
  const { examId } = useParams();
  const [loading, setLoading] = useState(true);
  const [exam, setExam] = useState(null);
  const [rows, setRows] = useState([]);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/staff/cbt/exams/${examId}/results`);
      const payload = res.data?.data || {};
      setExam(payload.exam || null);
      setRows(payload.students || []);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load exam results");
      setExam(null);
      setRows([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [examId]);

  return (
    <StaffFeatureLayout title="CBT Exam Results">
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
        <div>
          <div style={{ fontWeight: 700, fontSize: 18 }}>{exam?.title || "Exam"}</div>
          <div style={{ opacity: 0.8, fontSize: 13 }}>
            {(exam?.subject_name || "-")} | {(exam?.class_name || "-")} | {(exam?.term_name || "-")}
          </div>
        </div>
      </div>

      <section className="cbx-panel">
        {loading ? <p className="cbx-state cbx-state--loading">Loading exam results...</p> : null}
        {!loading ? (
          <div className="cbx-table-wrap">
            <table className="cbx-table">
              <thead>
                <tr>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Student Name</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Education Level</th>
                  <th>Status</th>
                  <th style={{ width: 130 }}>Score (%)</th>
                  <th style={{ width: 130 }}>Correct / Total</th>
                  <th>Ended At</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row, idx) => (
                  <tr key={row.id}>
                    <td>{idx + 1}</td>
                    <td>{row.student_name || "-"}</td>
                    <td>{row.student_username || "-"}</td>
                    <td>{row.student_email || "-"}</td>
                    <td>{row.education_level || "-"}</td>
                    <td>{row.status || "-"}</td>
                    <td style={{ fontWeight: 700 }}>{row.score_percent ?? 0}%</td>
                    <td>{row.correct ?? 0}/{row.total_questions ?? 0}</td>
                    <td>{formatDate(row.ended_at)}</td>
                  </tr>
                ))}
                {!rows.length ? (
                  <tr>
                    <td colSpan="9">No student attempt found for this exam yet.</td>
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
