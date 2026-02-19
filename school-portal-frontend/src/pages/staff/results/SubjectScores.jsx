import { useEffect, useMemo, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

const gradeFromTotal = (total) => {
  if (total >= 70) return "A";
  if (total >= 60) return "B";
  if (total >= 50) return "C";
  if (total >= 40) return "D";
  if (total >= 30) return "E";
  return "F";
};

export default function SubjectScores() {
  const { termSubjectId } = useParams();

  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/staff/results/subjects/${termSubjectId}/students`);
      setRows(res.data.data?.students || []);
    } catch (e) {
      alert("Failed to load students for this subject");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [termSubjectId]);

  const updateScore = (student_id, field, value) => {
    const n = Number(value);
    setRows((prev) =>
      prev.map((r) => {
        if (r.student_id !== student_id) return r;

        const ca = field === "ca" ? (isNaN(n) ? 0 : n) : r.ca;
        const exam = field === "exam" ? (isNaN(n) ? 0 : n) : r.exam;

        // clamp
        const ca2 = Math.max(0, Math.min(30, ca));
        const exam2 = Math.max(0, Math.min(70, exam));

        const total = ca2 + exam2;
        return { ...r, ca: ca2, exam: exam2, total, grade: gradeFromTotal(total) };
      })
    );
  };

  const totals = useMemo(() => {
    const count = rows.length;
    return { count };
  }, [rows]);

  const saveAll = async () => {
    setSaving(true);
    try {
      await api.post(`/api/staff/results/subjects/${termSubjectId}/scores`, {
        scores: rows.map((r) => ({
          student_id: r.student_id,
          ca: r.ca,
          exam: r.exam,
        })),
      });
      alert("Saved successfully");
    } catch (e) {
      alert(e.response?.data?.message || "Failed to save scores");
    } finally {
      setSaving(false);
    }
  };

  return (
    <StaffFeatureLayout title="Enter Results">

      <div style={{ marginTop: 8, opacity: 0.8, fontSize: 13 }}>
        CA: 0–30 • Exam: 0–70 • Total: 100 • Grade:
        {" "}70–100=A, 60–69=B, 50–59=C, 40–49=D, 30–39=E, 0–29=F
      </div>

      <div style={{ marginTop: 12, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div><strong>Students:</strong> {totals.count}</div>
        <button onClick={saveAll} disabled={saving || loading || rows.length === 0}>
          {saving ? "Saving..." : "Save Scores"}
        </button>
      </div>

      {loading ? (
        <p style={{ marginTop: 12 }}>Loading...</p>
      ) : (
        <table border="1" cellPadding="10" width="100%" style={{ marginTop: 12 }}>
          <thead>
            <tr>
              <th style={{ width: 70 }}>S/N</th>
              <th>Student</th>
              <th style={{ width: 110 }}>CA (30)</th>
              <th style={{ width: 120 }}>Exam (70)</th>
              <th style={{ width: 110 }}>Total</th>
              <th style={{ width: 90 }}>Grade</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r, idx) => (
              <tr key={r.student_id}>
                <td>{idx + 1}</td>
                <td>{r.student_name}</td>

                <td>
                  <input
                    type="number"
                    value={r.ca}
                    min="0"
                    max="30"
                    onChange={(e) => updateScore(r.student_id, "ca", e.target.value)}
                    style={{ width: 80 }}
                  />
                </td>

                <td>
                  <input
                    type="number"
                    value={r.exam}
                    min="0"
                    max="70"
                    onChange={(e) => updateScore(r.student_id, "exam", e.target.value)}
                    style={{ width: 80 }}
                  />
                </td>

                <td><strong>{r.total}</strong></td>
                <td><strong>{r.grade}</strong></td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr>
                <td colSpan="6">No enrolled students found for this class + term.</td>
              </tr>
            )}
          </tbody>
        </table>
      )}
    </StaffFeatureLayout>
  );
}
