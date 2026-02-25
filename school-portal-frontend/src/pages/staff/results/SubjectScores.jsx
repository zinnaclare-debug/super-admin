import { useEffect, useMemo, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

const DEFAULT_SCHEMA = {
  ca_maxes: [30, 0, 0, 0, 0],
  exam_max: 70,
  total_max: 100,
};

const gradeFromTotal = (total) => {
  if (total >= 70) return "A";
  if (total >= 60) return "B";
  if (total >= 50) return "C";
  if (total >= 40) return "D";
  if (total >= 30) return "E";
  return "F";
};

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

const normalizeSchema = (schema) => {
  const raw = schema || {};
  const caMaxes = Array.isArray(raw.ca_maxes) ? raw.ca_maxes : DEFAULT_SCHEMA.ca_maxes;
  const normalizedCa = Array.from({ length: 5 }, (_, idx) =>
    clamp(Number(caMaxes[idx] || 0), 0, 100)
  );
  const caTotal = normalizedCa.reduce((sum, v) => sum + v, 0);
  const requestedExam = Number(raw.exam_max ?? 100 - caTotal);
  const examMax = caTotal + requestedExam === 100 ? clamp(requestedExam, 0, 100) : clamp(100 - caTotal, 0, 100);

  return {
    ca_maxes: normalizedCa,
    exam_max: examMax,
    total_max: 100,
  };
};

const activeCaIndices = (schema) => {
  const out = [];
  schema.ca_maxes.forEach((max, idx) => {
    if (Number(max) > 0) out.push(idx);
  });
  return out.length ? out : [0];
};

const normalizeRow = (row, schema) => {
  const rawBreakdown = Array.isArray(row.ca_breakdown) ? row.ca_breakdown : [];
  const breakdown = Array.from({ length: 5 }, (_, idx) =>
    clamp(Number(rawBreakdown[idx] || 0), 0, schema.ca_maxes[idx] || 0)
  );
  const ca = breakdown.reduce((sum, score) => sum + score, 0);
  const exam = clamp(Number(row.exam || 0), 0, schema.exam_max);
  const total = ca + exam;

  return {
    ...row,
    ca_breakdown: breakdown,
    ca,
    exam,
    total,
    grade: gradeFromTotal(total),
  };
};

export default function SubjectScores() {
  const { termSubjectId } = useParams();

  const [schema, setSchema] = useState(DEFAULT_SCHEMA);
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const caIndices = useMemo(() => activeCaIndices(schema), [schema]);
  const caPattern = useMemo(
    () =>
      caIndices.map((idx) => `CA${idx + 1}(${schema.ca_maxes[idx]})`).join(" | ") +
      ` | EXAM(${schema.exam_max})`,
    [caIndices, schema]
  );

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/staff/results/subjects/${termSubjectId}/students`);
      const normalizedSchema = normalizeSchema(res.data?.data?.assessment_schema);
      setSchema(normalizedSchema);
      setRows((res.data?.data?.students || []).map((row) => normalizeRow(row, normalizedSchema)));
    } catch (e) {
      alert("Failed to load students for this subject");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [termSubjectId]);

  const updateCa = (studentId, caIndex, value) => {
    const score = clamp(Number(value || 0), 0, schema.ca_maxes[caIndex] || 0);
    setRows((prev) =>
      prev.map((row) => {
        if (row.student_id !== studentId) return row;
        const breakdown = Array.isArray(row.ca_breakdown) ? [...row.ca_breakdown] : [0, 0, 0, 0, 0];
        breakdown[caIndex] = score;
        const ca = breakdown.reduce((sum, item) => sum + Number(item || 0), 0);
        const exam = clamp(Number(row.exam || 0), 0, schema.exam_max);
        const total = ca + exam;

        return { ...row, ca_breakdown: breakdown, ca, exam, total, grade: gradeFromTotal(total) };
      })
    );
  };

  const updateExam = (studentId, value) => {
    const exam = clamp(Number(value || 0), 0, schema.exam_max);
    setRows((prev) =>
      prev.map((row) => {
        if (row.student_id !== studentId) return row;
        const ca = Number(row.ca || 0);
        const total = ca + exam;
        return { ...row, exam, total, grade: gradeFromTotal(total) };
      })
    );
  };

  const saveAll = async () => {
    setSaving(true);
    try {
      await api.post(`/api/staff/results/subjects/${termSubjectId}/scores`, {
        scores: rows.map((row) => ({
          student_id: row.student_id,
          ca_breakdown: Array.from({ length: 5 }, (_, idx) => Number(row.ca_breakdown?.[idx] || 0)),
          exam: Number(row.exam || 0),
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
        Assessment pattern: {caPattern}. Total max: 100.
      </div>

      <div style={{ marginTop: 12, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div>
          <strong>Students:</strong> {rows.length}
        </div>
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
              {caIndices.map((idx) => (
                <th key={`ca-head-${idx}`} style={{ width: 110 }}>
                  CA{idx + 1} ({schema.ca_maxes[idx]})
                </th>
              ))}
              <th style={{ width: 110 }}>CA Total</th>
              <th style={{ width: 120 }}>Exam ({schema.exam_max})</th>
              <th style={{ width: 110 }}>Total</th>
              <th style={{ width: 90 }}>Grade</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, rowIndex) => (
              <tr key={row.student_id}>
                <td>{rowIndex + 1}</td>
                <td>{row.student_name}</td>
                {caIndices.map((idx) => (
                  <td key={`ca-input-${row.student_id}-${idx}`}>
                    <input
                      type="number"
                      value={Number(row.ca_breakdown?.[idx] || 0)}
                      min="0"
                      max={schema.ca_maxes[idx]}
                      onChange={(e) => updateCa(row.student_id, idx, e.target.value)}
                      style={{ width: 80 }}
                    />
                  </td>
                ))}
                <td>
                  <strong>{row.ca}</strong>
                </td>
                <td>
                  <input
                    type="number"
                    value={row.exam}
                    min="0"
                    max={schema.exam_max}
                    onChange={(e) => updateExam(row.student_id, e.target.value)}
                    style={{ width: 80 }}
                  />
                </td>
                <td>
                  <strong>{row.total}</strong>
                </td>
                <td>
                  <strong>{row.grade}</strong>
                </td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr>
                <td colSpan={6 + caIndices.length}>No enrolled students found for this class and term.</td>
              </tr>
            )}
          </tbody>
        </table>
      )}
    </StaffFeatureLayout>
  );
}

