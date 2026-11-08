import { useEffect, useMemo, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import scoreStudioArt from "../../../assets/class-progress-sharing-knowledge.svg";
import "./SubjectScores.css";

const DEFAULT_SCHEMA = {
  ca_maxes: [30, 0, 0, 0, 0],
  ca_labels: ["CA1", "CA2", "CA3", "CA4", "CA5"],
  exam_max: 70,
  total_max: 100,
  layout: "compact",
  psychomotor_maxes: [0, 0, 0, 0, 0],
  psychomotor_labels: ["P1", "P2", "P3", "P4", "P5"],
  quiz_max: 0,
  quiz_label: "Quiz",
  note_copying_max: 0,
  note_copying_label: "Note Copying",
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
  const usesClassicComponents = raw.layout === "classic";
  const numeric = (value, fallback) => {
    const parsed = Number(value ?? fallback);
    return Number.isFinite(parsed) ? clamp(parsed, 0, 100) : fallback;
  };
  const caMaxes = Array.isArray(raw.ca_maxes) ? raw.ca_maxes : DEFAULT_SCHEMA.ca_maxes;
  const caLabels = Array.isArray(raw.ca_labels) ? raw.ca_labels : DEFAULT_SCHEMA.ca_labels;
  const psychomotorMaxes = Array.isArray(raw.psychomotor_maxes) ? raw.psychomotor_maxes : DEFAULT_SCHEMA.psychomotor_maxes;
  const normalizedCa = Array.from({ length: 5 }, (_, idx) => numeric(caMaxes[idx], 0));
  const normalizedLabels = Array.from({ length: 5 }, (_, idx) => String(caLabels[idx] || "").trim() || `CA${idx + 1}`);
  const normalizedPsychomotorMaxes = Array.from({ length: 5 }, (_, idx) => usesClassicComponents ? numeric(psychomotorMaxes[idx], 0) : 0);
  const psychomotorLabels = Array.from({ length: 5 }, (_, idx) => String(raw.psychomotor_labels?.[idx] || "").trim() || `P${idx + 1}`);

  return {
    ca_maxes: normalizedCa,
    ca_labels: normalizedLabels,
    exam_max: numeric(raw.exam_max, 100 - normalizedCa.reduce((sum, value) => sum + value, 0)),
    total_max: 100,
    layout: usesClassicComponents ? "classic" : "compact",
    psychomotor_maxes: normalizedPsychomotorMaxes,
    psychomotor_labels: psychomotorLabels,
    quiz_max: usesClassicComponents ? numeric(raw.quiz_max, 0) : 0,
    quiz_label: String(raw.quiz_label || "Quiz").trim() || "Quiz",
    note_copying_max: usesClassicComponents ? numeric(raw.note_copying_max, 0) : 0,
    note_copying_label: String(raw.note_copying_label || "Note Copying").trim() || "Note Copying",
    comments_only: Boolean(raw.comments_only),
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

const normalizedClassicComponents = (row, schema) => ({
  psychomotor: Array.from({ length: 5 }, (_, index) => clamp(Number(row?.classic_components?.psychomotor?.[index] || 0), 0, Number(schema.psychomotor_maxes?.[index] || 0))),
  quiz: clamp(Number(row?.classic_components?.quiz || 0), 0, Number(schema.quiz_max || 0)),
  note_copying: clamp(Number(row?.classic_components?.note_copying || 0), 0, Number(schema.note_copying_max || 0)),
});
const scoreTotal = (ca, exam, components, usesClassicComponents) => {
  const extras = usesClassicComponents
    ? components.psychomotor.reduce((sum, score) => sum + Number(score || 0), 0) + Number(components.quiz || 0) + Number(components.note_copying || 0)
    : 0;
  return clamp(Number(ca || 0) + Number(exam || 0) + extras, 0, 100);
};

const normalizeRow = (row, schema, gradingSchema, usesClassicComponents = false) => {
  const rawBreakdown = Array.isArray(row.ca_breakdown) ? row.ca_breakdown : [];
  const breakdown = Array.from({ length: 5 }, (_, idx) => clamp(Number(rawBreakdown[idx] || 0), 0, schema.ca_maxes[idx] || 0));
  const ca = breakdown.reduce((sum, score) => sum + score, 0);
  const exam = clamp(Number(row.exam || 0), 0, schema.exam_max);
  const classicComponents = normalizedClassicComponents(row, schema);
  const total = scoreTotal(ca, exam, classicComponents, usesClassicComponents);

  return {
    ...row,
    department_id: row?.department_id ? Number(row.department_id) : null,
    department_name: String(row?.department_name || "").trim(),
    ca_breakdown: breakdown,
    ca,
    exam,
    total,
    grade: gradeFromTotal(total, gradingSchema),
    classic_components: classicComponents,
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
  const [usesClassicComponents, setUsesClassicComponents] = useState(false);
  const [selectedDepartmentId, setSelectedDepartmentId] = useState("all");

  const departmentStorageKey = useMemo(() => `staff-results-department:${termSubjectId}`, [termSubjectId]);
  const caIndices = useMemo(() => activeCaIndices(schema), [schema]);
  const commentsOnly = Boolean(schema.comments_only);
  const caPattern = useMemo(
    () =>
      caIndices
        .map((idx) => `${schema.ca_labels?.[idx] || `CA${idx + 1}`}(${schema.ca_maxes[idx]})`)
        .join(" | ") + ` | EXAM(${schema.exam_max})`,
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
      setUsesClassicComponents(Boolean(res.data?.data?.uses_classic_components));
      setRows((res.data?.data?.students || []).map((row) => normalizeRow(row, normalizedSchema, normalizedGradingSchema, Boolean(res.data?.data?.uses_classic_components))));
    } catch (e) {
      alert("Failed to load students for this subject");
      setSubjectName("");
      setResultsPublished(false);
      setUsesClassicComponents(false);
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
        const total = scoreTotal(ca, exam, row.classic_components, usesClassicComponents);

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
        const total = scoreTotal(ca, exam, row.classic_components, usesClassicComponents);
        return { ...row, exam, total, grade: gradeFromTotal(total, gradingSchema) };
      })
    );
  };

  const updateTeacherComment = (studentId, value) => {
    if (resultsPublished) return;
    setRows((prev) => prev.map((row) => (
      row.student_id === studentId ? { ...row, teacher_comment: value } : row
    )));
  };

  const updateClassicComponent = (studentId, key, value, index = null) => {
    if (resultsPublished) return;
    setRows((prev) => prev.map((row) => {
      if (row.student_id !== studentId) return row;
      const current = row.classic_components || { psychomotor: [0, 0, 0, 0, 0], quiz: 0, note_copying: 0 };
      if (key === "psychomotor") {
        const psychomotor = [...current.psychomotor];
        psychomotor[index] = clamp(Number(value || 0), 0, Number(schema.psychomotor_maxes?.[index] || 0));
        const classic_components = { ...current, psychomotor };
        const total = scoreTotal(row.ca, row.exam, classic_components, true);
        return { ...row, classic_components, total, grade: gradeFromTotal(total, gradingSchema) };
      }
      const maximum = key === "quiz" ? Number(schema.quiz_max || 0) : Number(schema.note_copying_max || 0);
      const classic_components = { ...current, [key]: clamp(Number(value || 0), 0, maximum) };
      const total = scoreTotal(row.ca, row.exam, classic_components, true);
      return { ...row, classic_components, total, grade: gradeFromTotal(total, gradingSchema) };
    }));
  };

  const saveAll = async () => {
    if (resultsPublished) {
      alert("Results have been published. Staff can edit again after results are unpublished.");
      return;
    }

    setSaving(true);
    try {
      await api.post(`/api/staff/results/subjects/${termSubjectId}/scores`, {
        scores: rows.map((row) => (
          commentsOnly
            ? { student_id: row.student_id, teacher_comment: String(row.teacher_comment || "").trim() }
            : {
                student_id: row.student_id,
                ca_breakdown: Array.from({ length: 5 }, (_, idx) => Number(row.ca_breakdown?.[idx] || 0)),
                exam: Number(row.exam || 0),
                ...(usesClassicComponents ? { classic_components: row.classic_components } : {}),
              }
        )),
      });
      alert(commentsOnly ? "Teacher observations saved successfully" : "Saved successfully");
    } catch (e) {
      alert(e.response?.data?.message || "Failed to save scores");
    } finally {
      setSaving(false);
    }
  };

  return (
    <StaffFeatureLayout title={subjectName ? `Enter Results - ${subjectName}` : "Enter Results"}>
      <main className="staff-scores">
        <section className="staff-scores__hero">
          <div className="staff-scores__hero-copy">
            <span className="staff-scores__eyebrow">Score studio</span>
            <h2>{subjectName || "Student assessment"}</h2>
            <p>{commentsOnly ? "Record clear, individual learning observations for every student in this class." : `Enter and review scores with the school's ${usesClassicComponents ? "classic" : "compact"} assessment pattern.`}</p>
            <div className="staff-scores__hero-tags">
              <span>{filteredRows.length} student{filteredRows.length === 1 ? "" : "s"}</span>
              <span>{commentsOnly ? "Observation sheet" : `Total: ${schema.total_max}`}</span>
              {selectedDepartmentId !== "all" ? <span>Filtered class</span> : null}
            </div>
          </div>
          <div className="staff-scores__hero-art" aria-hidden="true">
            <div className="staff-scores__hero-orb staff-scores__hero-orb--one" />
            <div className="staff-scores__hero-orb staff-scores__hero-orb--two" />
            <img src={scoreStudioArt} alt="" />
          </div>
        </section>

        <section className="staff-scores__summary" aria-label="Assessment summary">
          <div className="staff-scores__summary-icon" aria-hidden="true">%</div>
          <div>
            <strong>{commentsOnly ? "Observation-based assessment" : "Assessment pattern"}</strong>
            <p>{commentsOnly ? "Numeric CA and examination scores are disabled for this education level." : `${caPattern}. Total maximum: 100.`}</p>
          </div>
        </section>

        {!commentsOnly && usesClassicComponents ? <p className="staff-scores-classic-note">Classic assessment includes the configured CA, exam, Psychomotor / Affective, Quiz and Note Copying fields. All entries combine to 100.</p> : null}
        {resultsPublished ? <div className="staff-scores__locked" role="status"><span aria-hidden="true">!</span>Results are published. Editing is locked until the school administrator unpublishes them.</div> : null}

        {departmentOptions.length > 0 ? (
          <section className="staff-scores__filters" aria-label="Student subclass filters">
            <span className="staff-scores__filters-label">Show</span>
            <div className="staff-scores__filter-list">
              <button type="button" className={selectedDepartmentId === "all" ? "is-active" : ""} onClick={() => setSelectedDepartmentId("all")}>All students <b>{rows.length}</b></button>
              {departmentOptions.map((option) => <button key={option.id} type="button" className={selectedDepartmentId === option.id ? "is-active" : ""} onClick={() => setSelectedDepartmentId(option.id)}>{option.name} <b>{option.count}</b></button>)}
            </div>
          </section>
        ) : null}

        <section className="staff-scores__workspace">
          <div className="staff-scores__workspace-head">
            <div>
              <span className="staff-scores__eyebrow">Entry register</span>
              <h3>{commentsOnly ? "Student observations" : "Enter student scores"}</h3>
              <p>{selectedDepartmentId === "all" ? `${filteredRows.length} students ready for entry.` : `${filteredRows.length} students shown from ${rows.length}.`}</p>
            </div>
            <button className="staff-scores__save" type="button" onClick={saveAll} disabled={saving || loading || rows.length === 0 || resultsPublished}>{saving ? "Saving..." : commentsOnly ? "Save observations" : "Save scores"}</button>
          </div>

          {loading ? <div className="staff-scores__loading">Loading the score register...</div> : (
            <>
              <p className="staff-scores__scroll-hint"><span aria-hidden="true">←</span> Swipe the score fields to the side. Student names stay in view.</p>
              <div className="table-scroll staff-scores-table-wrap" aria-label="Student score entry table">
                <table className={`staff-scores-table ${usesClassicComponents && !commentsOnly ? "staff-scores-table--classic" : ""}`}>
                  <thead>
                    <tr>
                      <th className="staff-scores-table__serial staff-scores-table__serial--head" style={{ width: 70 }} rowSpan={usesClassicComponents && !commentsOnly ? 2 : 1}>S/N</th>
                      <th className="staff-scores-table__student staff-scores-table__student--head" rowSpan={usesClassicComponents && !commentsOnly ? 2 : 1}>Student</th>
                      {commentsOnly ? <th className="staff-scores-table__observation-head">Teacher&apos;s observation</th> : <>
                        {caIndices.map((idx) => <th key={`ca-head-${idx}`} rowSpan={usesClassicComponents ? 2 : 1}>{schema.ca_labels?.[idx] || `CA${idx + 1}`} <small>/{schema.ca_maxes[idx]}</small></th>)}
                        <th rowSpan={usesClassicComponents ? 2 : 1}>CA total</th>
                        <th rowSpan={usesClassicComponents ? 2 : 1}>Exam <small>/{schema.exam_max}</small></th>
                        {usesClassicComponents ? <th colSpan="5" className="staff-scores-table__group-head">Psychomotor / Affective</th> : null}
                        {usesClassicComponents ? <th colSpan="2" className="staff-scores-table__group-head">Quiz / Note Copying</th> : null}
                        <th rowSpan={usesClassicComponents ? 2 : 1}>Total</th><th rowSpan={usesClassicComponents ? 2 : 1}>Grade</th>
                      </>}
                    </tr>
                    {usesClassicComponents && !commentsOnly ? <tr>
                      {schema.psychomotor_labels.map((label, index) => <th key={`psy-head-${index}`}>{label} <small>/{schema.psychomotor_maxes[index]}</small></th>)}
                      <th>{schema.quiz_label} <small>/{schema.quiz_max}</small></th><th>{schema.note_copying_label} <small>/{schema.note_copying_max}</small></th>
                    </tr> : null}
                  </thead>
                  <tbody>
                    {filteredRows.map((row, rowIndex) => <tr key={row.student_id}>
                      <td className="staff-scores-table__serial">{rowIndex + 1}</td>
                      <td className="staff-scores-table__student"><strong>{row.student_name}</strong>{row.department_name ? <small>{row.department_name}</small> : null}</td>
                      {commentsOnly ? <td className="staff-scores-table__observation-cell"><textarea value={row.teacher_comment || ""} onChange={(e) => updateTeacherComment(row.student_id, e.target.value)} disabled={resultsPublished} maxLength={1000} rows={3} placeholder="Write the student's learning observation" /></td> : <>
                        {caIndices.map((idx) => <td key={`ca-input-${row.student_id}-${idx}`}><input className="staff-score-input" type="number" value={Number(row.ca_breakdown?.[idx] || 0)} min="0" max={schema.ca_maxes[idx]} onChange={(e) => updateCa(row.student_id, idx, e.target.value)} disabled={resultsPublished} aria-label={`${row.student_name} ${schema.ca_labels?.[idx] || `CA${idx + 1}`} score`} /></td>)}
                        <td className="staff-scores-table__calculated">{row.ca}</td>
                        <td><input className="staff-score-input" type="number" value={row.exam} min="0" max={schema.exam_max} onChange={(e) => updateExam(row.student_id, e.target.value)} disabled={resultsPublished} aria-label={`${row.student_name} examination score`} /></td>
                        {usesClassicComponents ? row.classic_components.psychomotor.map((value, index) => <td key={`psy-${row.student_id}-${index}`}><input className="staff-score-input staff-score-input--compact" type="number" value={value} min="0" max={schema.psychomotor_maxes[index]} onChange={(e) => updateClassicComponent(row.student_id, "psychomotor", e.target.value, index)} disabled={resultsPublished} aria-label={`${row.student_name} ${schema.psychomotor_labels[index]} score`} /></td>) : null}
                        {usesClassicComponents ? <td><input className="staff-score-input staff-score-input--compact" type="number" value={row.classic_components.quiz} min="0" max={schema.quiz_max} onChange={(e) => updateClassicComponent(row.student_id, "quiz", e.target.value)} disabled={resultsPublished} aria-label={`${row.student_name} ${schema.quiz_label} score`} /></td> : null}
                        {usesClassicComponents ? <td><input className="staff-score-input staff-score-input--compact" type="number" value={row.classic_components.note_copying} min="0" max={schema.note_copying_max} onChange={(e) => updateClassicComponent(row.student_id, "note_copying", e.target.value)} disabled={resultsPublished} aria-label={`${row.student_name} ${schema.note_copying_label} score`} /></td> : null}
                        <td className="staff-scores-table__total">{row.total}</td><td><span className="staff-scores-table__grade">{row.grade}</span></td>
                      </>}
                    </tr>)}
                    {filteredRows.length === 0 ? <tr><td colSpan={commentsOnly ? 3 : (usesClassicComponents ? 13 + caIndices.length : 6 + caIndices.length)} className="staff-scores-table__empty">{rows.length === 0 ? "No enrolled students found for this class and term." : "No students found for the selected subclass."}</td></tr> : null}
                  </tbody>
                </table>
              </div>
            </>
          )}
        </section>
      </main>
    </StaffFeatureLayout>
  );
}

