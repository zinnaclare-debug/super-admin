import { useEffect, useMemo, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

const DEFAULT_SCHEMA = {
  ca_maxes: [30, 0, 0, 0, 0],
  exam_max: 70,
  total_max: 100,
};

const DEFAULT_GRADING_SCHEMA = [
  { from: 0, to: 29, grade: "F", remark: "FAIL" },
  { from: 30, to: 39, grade: "E", remark: "POOR" },
  { from: 40, to: 49, grade: "D", remark: "FAIR" },
  { from: 50, to: 59, grade: "C", remark: "GOOD" },
  { from: 60, to: 69, grade: "B", remark: "VERY GOOD" },
  { from: 70, to: 100, grade: "A", remark: "EXCELLENT" },
];

const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

const normalizeSchema = (schema) => {
  const raw = schema || {};
  const caMaxes = Array.isArray(raw.ca_maxes) ? raw.ca_maxes : DEFAULT_SCHEMA.ca_maxes;
  const normalizedCa = Array.from({ length: 5 }, (_, idx) => clamp(Number(caMaxes[idx] || 0), 0, 100));
  const caTotal = normalizedCa.reduce((sum, v) => sum + v, 0);
  const requestedExam = Number(raw.exam_max ?? 100 - caTotal);
  const examMax = caTotal + requestedExam === 100 ? clamp(requestedExam, 0, 100) : clamp(100 - caTotal, 0, 100);

  return {
    ca_maxes: normalizedCa,
    exam_max: examMax,
    total_max: 100,
  };
};

const normalizeGradingSchema = (rows) => {
  const source = Array.isArray(rows) ? rows : [];
  const normalized = source
    .map((row) => ({
      from: Number(row?.from),
      to: Number(row?.to),
      grade: String(row?.grade || "").trim(),
      remark: String(row?.remark || "").trim(),
    }))
    .filter((row) => Number.isFinite(row.from) && Number.isFinite(row.to) && row.grade !== "")
    .sort((a, b) => a.from - b.from);

  return normalized.length ? normalized : DEFAULT_GRADING_SCHEMA;
};

const gradeFromTotal = (total, gradingSchema) => {
  const score = clamp(Number(total || 0), 0, 100);
  const match = gradingSchema.find((row) => score >= row.from && score <= row.to);
  return match?.grade || "-";
};

const activeCaIndices = (schema) => {
  const out = [];
  schema.ca_maxes.forEach((max, idx) => {
    if (Number(max) > 0) out.push(idx);
  });
  return out.length ? out : [0];
};

const normalizeRow = (row, schema, gradingSchema) => {
  const rawBreakdown = Array.isArray(row.ca_breakdown) ? row.ca_breakdown : [];
  const breakdown = Array.from({ length: 5 }, (_, idx) => clamp(Number(rawBreakdown[idx] || 0), 0, schema.ca_maxes[idx] || 0));
  const ca = breakdown.reduce((sum, score) => sum + score, 0);
  const exam = clamp(Number(row.exam || 0), 0, schema.exam_max);
  const total = ca + exam;

  return {
    ...row,
    department_id: row?.department_id ? Number(row.department_id) : null,
    department_name: String(row?.department_name || "").trim(),
    ca_breakdown: breakdown,
    ca,
    exam,
    total,
    grade: gradeFromTotal(total, gradingSchema),
  };
};

export default function SubjectScores() {
  const { termSubjectId } = useParams();

  const [schema, setSchema] = useState(DEFAULT_SCHEMA);
  const [gradingSchema, setGradingSchema] = useState(DEFAULT_GRADING_SCHEMA);
  const [rows, setRows] = useState([]);
  const [subjectName, setSubjectName] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [resultsPublished, setResultsPublished] = useState(false);
  const [selectedDepartmentId, setSelectedDepartmentId] = useState("all");

  const departmentStorageKey = useMemo(() => `staff-results-department:${termSubjectId}`, [termSubjectId]);
  const caIndices = useMemo(() => activeCaIndices(schema), [schema]);
  const caPattern = useMemo(
    () => caIndices.map((idx) => `CA${idx + 1}(${schema.ca_maxes[idx]})`).join(" | ") + ` | EXAM(${schema.exam_max})`,
    [caIndices, schema]
  );

  const departmentOptions = useMemo(() => {
    const map = new Map();
    rows.forEach((row) => {
      if (!row.department_id || !row.department_name) return;
      if (!map.has(row.department_id)) {
        map.set(row.department_id, {
          id: String(row.department_id),
          name: row.department_name,
          count: 0,
        });
      }
      map.get(row.department_id).count += 1;
    });
    return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
  }, [rows]);

  const filteredRows = useMemo(() => {
    if (selectedDepartmentId === "all") return rows;
    return rows.filter((row) => String(row.department_id || "") === String(selectedDepartmentId));
  }, [rows, selectedDepartmentId]);

  useEffect(() => {
    const savedDepartment = window.localStorage.getItem(departmentStorageKey);
    setSelectedDepartmentId(savedDepartment || "all");
  }, [departmentStorageKey]);

  useEffect(() => {
    if (selectedDepartmentId === "all") return;
    if (!departmentOptions.some((option) => option.id === String(selectedDepartmentId))) {
      setSelectedDepartmentId("all");
    }
  }, [departmentOptions, selectedDepartmentId]);

  useEffect(() => {
    if (selectedDepartmentId === "all") {
      window.localStorage.removeItem(departmentStorageKey);
      return;
    }

    window.localStorage.setItem(departmentStorageKey, String(selectedDepartmentId));
  }, [departmentStorageKey, selectedDepartmentId]);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/staff/results/subjects/${termSubjectId}/students`);
      const normalizedSchema = normalizeSchema(res.data?.data?.assessment_schema);
      const normalizedGradingSchema = normalizeGradingSchema(res.data?.data?.grading_schema);
      setSchema(normalizedSchema);
      setGradingSchema(normalizedGradingSchema);
      setSubjectName(String(res.data?.data?.subject_name || "").trim());
      setResultsPublished(Boolean(res.data?.data?.results_published));
      setRows((res.data?.data?.students || []).map((row) => normalizeRow(row, normalizedSchema, normalizedGradingSchema)));
    } catch (e) {
      alert("Failed to load students for this subject");
      setSubjectName("");
      setResultsPublished(false);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [termSubjectId]);

  const updateCa = (studentId, caIndex, value) => {
    if (resultsPublished) return;
    const score = clamp(Number(value || 0), 0, schema.ca_maxes[caIndex] || 0);
    setRows((prev) =>
      prev.map((row) => {
        if (row.student_id !== studentId) return row;
        const breakdown = Array.isArray(row.ca_breakdown) ? [...row.ca_breakdown] : [0, 0, 0, 0, 0];
        breakdown[caIndex] = score;
        const ca = breakdown.reduce((sum, item) => sum + Number(item || 0), 0);
        const exam = clamp(Number(row.exam || 0), 0, schema.exam_max);
        const total = ca + exam;

        return { ...row, ca_breakdown: breakdown, ca, exam, total, grade: gradeFromTotal(total, gradingSchema) };
      })
    );
  };

  const updateExam = (studentId, value) => {
    if (resultsPublished) return;
    const exam = clamp(Number(value || 0), 0, schema.exam_max);
    setRows((prev) =>
      prev.map((row) => {
        if (row.student_id !== studentId) return row;
        const ca = Number(row.ca || 0);
        const total = ca + exam;
        return { ...row, exam, total, grade: gradeFromTotal(total, gradingSchema) };
      })
    );
  };

  const saveAll = async () => {
    if (resultsPublished) {
      alert("Results have been published by the super admin. Staff can edit again after results are unpublished.");
      return;
    }

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
    <StaffFeatureLayout title={subjectName ? `Enter Results - ${subjectName}` : "Enter Results"}>
      <div style={{ marginTop: 8, opacity: 0.8, fontSize: 13 }}>
        Assessment pattern: {caPattern}. Total max: 100.
      </div>

      {resultsPublished ? (
        <div style={{ marginTop: 12, padding: "10px 12px", borderRadius: 10, background: "#fef2f2", border: "1px solid #fecaca", color: "#991b1b", fontSize: 14, fontWeight: 600 }}>
          Results are currently published by the super admin. Staff editing is locked until the results are unpublished.
        </div>
      ) : null}

      {departmentOptions.length > 0 && (
        <div style={{ marginTop: 12, display: "flex", gap: 8, flexWrap: "wrap" }}>
          <button
            type="button"
            onClick={() => setSelectedDepartmentId("all")}
            style={{
              padding: "8px 12px",
              borderRadius: 999,
              border: "1px solid #cbd5e1",
              background: selectedDepartmentId === "all" ? "#1d4ed8" : "#fff",
              color: selectedDepartmentId === "all" ? "#fff" : "#0f172a",
            }}
          >
            All Students ({rows.length})
          </button>
          {departmentOptions.map((option) => (
            <button
              key={option.id}
              type="button"
              onClick={() => setSelectedDepartmentId(option.id)}
              style={{
                padding: "8px 12px",
                borderRadius: 999,
                border: "1px solid #cbd5e1",
                background: selectedDepartmentId === option.id ? "#1d4ed8" : "#fff",
                color: selectedDepartmentId === option.id ? "#fff" : "#0f172a",
              }}
            >
              {option.name} ({option.count})
            </button>
          ))}
        </div>
      )}

      <div style={{ marginTop: 12, display: "flex", justifyContent: "space-between", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
        <div>
          <strong>Students:</strong> {filteredRows.length}
          {selectedDepartmentId !== "all" && <span style={{ opacity: 0.75 }}> filtered from {rows.length}</span>}
        </div>
        <button onClick={saveAll} disabled={saving || loading || rows.length === 0 || resultsPublished}>
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
            {filteredRows.map((row, rowIndex) => (
              <tr key={row.student_id}>
                <td>{rowIndex + 1}</td>
                <td>
                  <div>{row.student_name}</div>
                  {row.department_name && <small style={{ opacity: 0.7 }}>{row.department_name}</small>}
                </td>
                {caIndices.map((idx) => (
                  <td key={`ca-input-${row.student_id}-${idx}`}>
                    <input
                      type="number"
                      value={Number(row.ca_breakdown?.[idx] || 0)}
                      min="0"
                      max={schema.ca_maxes[idx]}
                      onChange={(e) => updateCa(row.student_id, idx, e.target.value)}
                      disabled={resultsPublished}
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
                    disabled={resultsPublished}
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
            {filteredRows.length === 0 && (
              <tr>
                <td colSpan={6 + caIndices.length}>
                  {rows.length === 0
                    ? "No enrolled students found for this class and term."
                    : "No students found for the selected subclass."}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      )}
    </StaffFeatureLayout>
  );
}

