import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import { saveDownload } from "../../../utils/downloadFile";
import examPrepArt from "../../../assets/results/exam-prep.svg";
import onlineSurveyArt from "../../../assets/results/online-survey.svg";
import certificateArt from "../../../assets/results/certificate.svg";
import "../../shared/ResultsShowcase.css";

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

const normalizeSchema = (schema) => {
  const raw = schema || {};
  const number = (value, fallback) => {
    const parsed = Number(value ?? fallback);
    return Number.isFinite(parsed) ? Math.max(0, Math.min(100, Math.round(parsed))) : fallback;
  };
  const values = (field, fallback) => Array.from({ length: 5 }, (_, index) => number(raw[field]?.[index], fallback[index]));
  const labels = (field, fallback) => Array.from({ length: 5 }, (_, index) => String(raw[field]?.[index] || fallback[index]).trim() || fallback[index]);

  return {
    ca_maxes: values("ca_maxes", DEFAULT_SCHEMA.ca_maxes),
    ca_labels: labels("ca_labels", DEFAULT_SCHEMA.ca_labels),
    exam_max: number(raw.exam_max, DEFAULT_SCHEMA.exam_max),
    total_max: 100,
    layout: raw.layout === "classic" ? "classic" : "compact",
    psychomotor_maxes: values("psychomotor_maxes", DEFAULT_SCHEMA.psychomotor_maxes),
    psychomotor_labels: labels("psychomotor_labels", DEFAULT_SCHEMA.psychomotor_labels),
    quiz_max: number(raw.quiz_max, DEFAULT_SCHEMA.quiz_max),
    quiz_label: String(raw.quiz_label || DEFAULT_SCHEMA.quiz_label).trim() || DEFAULT_SCHEMA.quiz_label,
    note_copying_max: number(raw.note_copying_max, DEFAULT_SCHEMA.note_copying_max),
    note_copying_label: String(raw.note_copying_label || DEFAULT_SCHEMA.note_copying_label).trim() || DEFAULT_SCHEMA.note_copying_label,
    comments_only: Boolean(raw.comments_only),
  };
};
const classicComponentCells = (row) => Array.from(
  { length: 5 },
  (_, index) => displayScore(row?.classic_components?.psychomotor?.[index])
);


const activeCaIndices = (schema) => {
  const indices = [];
  schema.ca_maxes.forEach((max, idx) => {
    if (Number(max) > 0) indices.push(idx);
  });
  return indices.length ? indices : [0];
};

const displayScore = (value) => {
  if (value === null || value === undefined || value === "") return "-";
  if (value === "-") return "-";
  const n = Number(value);
  return Number.isFinite(n) ? n : "-";
};

const isThirdTermName = (name = "") => {
  const value = String(name).toLowerCase();
  return value.includes("third") || value.includes("three") || /(^|\D)3(rd)?(\D|$)/.test(value);
};

const sessionLabel = (item) =>
  item?.academic_year || item?.session_name || `Session ${item?.academic_session_id || ""}`.trim();

const itemSupportsCumulative = (item) =>
  Boolean(item?.supports_cumulative || isThirdTermName(item?.term_name));

function fileNameFromHeaders(headers, fallback) {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "result.pdf";
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
}

async function messageFromBlobError(blob, fallback) {
  try {
    const text = await blob.text();
    if (!text) return fallback;
    try {
      const parsed = JSON.parse(text);
      return parsed?.message || fallback;
    } catch {
      return text;
    }
  } catch {
    return fallback;
  }
}

export default function StudentResultsHome() {
  const [classes, setClasses] = useState([]);
  const [selected, setSelected] = useState(null);
  const [results, setResults] = useState([]);
  const [assessmentSchema, setAssessmentSchema] = useState(DEFAULT_SCHEMA);
  const [resultTemplate, setResultTemplate] = useState(null);
  const [loadingClasses, setLoadingClasses] = useState(true);
  const [loadingResults, setLoadingResults] = useState(false);
  const [requestingPdf, setRequestingPdf] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [pdfJob, setPdfJob] = useState(null);
  const [resultType, setResultType] = useState("term");
  const [error, setError] = useState("");

  const caIndices = useMemo(() => activeCaIndices(assessmentSchema), [assessmentSchema]);
  const commentsOnly = Boolean(assessmentSchema.comments_only);
  const isPdfProcessing = pdfJob && ["pending", "processing"].includes(pdfJob.status);
  const supportsCumulative = itemSupportsCumulative(selected);
  const isCumulative = supportsCumulative && resultType === "cumulative";
  const showThirdTermPreviousTotals =
    !isCumulative &&
    Boolean(
      resultTemplate?.show_third_term_previous_totals ||
        results.some((row) =>
          Object.prototype.hasOwnProperty.call(row || {}, "first_term_total") ||
          Object.prototype.hasOwnProperty.call(row || {}, "second_term_total") ||
          Object.prototype.hasOwnProperty.call(row || {}, "third_term_total")
        )
    );
  const usesClassicPreview = !commentsOnly && !isCumulative && resultTemplate?.layout === "classic";
  const currentItems = useMemo(() => classes.filter((item) => item.is_current_period), [classes]);
  const groupSessionItems = (items) => {
    const grouped = new Map();

    items.forEach((item) => {
      const key = String(item.academic_session_id || "unknown");
      if (!grouped.has(key)) {
        grouped.set(key, {
          id: key,
          label: sessionLabel(item),
          status: item.session_status,
          items: [],
        });
      }
      grouped.get(key).items.push(item);
    });

    return Array.from(grouped.values());
  };

  const currentSessionGroups = useMemo(
    () => groupSessionItems(classes.filter((item) => item.session_status === "current" && !item.is_current_period)),
    [classes]
  );
  const pastSessionGroups = useMemo(
    () => groupSessionItems(classes.filter((item) => item.session_status === "completed")),
    [classes]
  );
  const loadClasses = async () => {
    setLoadingClasses(true);
    setError("");
    try {
      const res = await api.get("/api/student/results/classes");
      const items = res.data?.data || [];
      setClasses(items);
      setSelected(
        items.find((item) => item.is_current_period && item.results_open) ||
          items.find((item) => item.is_current_period) ||
          items.find((item) => item.results_open) ||
          items[0] ||
          null
      );
    } catch (e) {
      setError(e?.response?.data?.message || "Failed to load assigned classes");
      setClasses([]);
      setSelected(null);
    } finally {
      setLoadingClasses(false);
    }
  };

  const loadResults = async (item) => {
    if (!item) {
      setResults([]);
      setAssessmentSchema(DEFAULT_SCHEMA);
      setResultTemplate(null);
      return;
    }

    if (!item.results_open) {
      setResults([]);
      setAssessmentSchema(DEFAULT_SCHEMA);
      setResultTemplate(null);
      setError("");
      return;
    }

    setLoadingResults(true);
    setError("");
    try {
      const res = await api.get("/api/student/results", {
        params: {
          class_id: item.class_id,
          term_id: item.term_id,
          result_type: itemSupportsCumulative(item) ? resultType : "term",
        },
      });
      setResults(res.data?.data || []);
      setAssessmentSchema(normalizeSchema(res.data?.assessment_schema));
      setResultTemplate(res.data?.result_template || null);
    } catch (e) {
      setError(e?.response?.data?.message || "Failed to load results");
      setResults([]);
      setAssessmentSchema(DEFAULT_SCHEMA);
      setResultTemplate(null);
    } finally {
      setLoadingResults(false);
    }
  };

  useEffect(() => {
    loadClasses();
  }, []);

  useEffect(() => {
    setPdfJob(null);
    loadResults(selected);
  }, [selected, resultType]);

  useEffect(() => {
    if (!supportsCumulative && resultType !== "term") {
      setResultType("term");
    }
  }, [supportsCumulative, resultType]);

  useEffect(() => {
    if (!pdfJob?.id || !["pending", "processing"].includes(pdfJob.status)) {
      return undefined;
    }

    let cancelled = false;
    const timer = window.setTimeout(async () => {
      try {
        const res = await api.get(`/api/student/results/download-jobs/${pdfJob.id}`);
        if (!cancelled) {
          setPdfJob(res.data?.data || null);
        }
      } catch (e) {
        if (!cancelled) {
          setPdfJob((current) =>
            current
              ? {
                  ...current,
                  status: "failed",
                  error_message:
                    e?.response?.data?.message || "Failed to refresh PDF status. Please try again.",
                }
              : current
          );
        }
      }
    }, 2500);

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [pdfJob]);

  const startResultPdfGeneration = async () => {
    if (!selected || !selected.results_open) return;
    setRequestingPdf(true);
    setError("");
    try {
      const res = await api.post("/api/student/results/download-jobs", {
        class_id: selected.class_id,
        term_id: selected.term_id,
        result_type: isCumulative ? "cumulative" : "term",
      });
      setPdfJob(res.data?.data || null);
    } catch (e) {
      alert(e?.response?.data?.message || e?.message || "Failed to start result PDF generation");
    } finally {
      setRequestingPdf(false);
    }
  };

  const downloadGeneratedResultPdf = async () => {
    if (!selected || !selected.results_open || !pdfJob?.id) return;
    setDownloading(true);
    try {
      const res = await api.get(`/api/student/results/download-jobs/${pdfJob.id}/file`, {
        responseType: "blob",
      });
      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const message = await messageFromBlobError(res.data, "Failed to download result PDF");
        throw new Error(message);
      }

      const pdfBlob =
        res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });

      const saved = await saveDownload(
        pdfBlob,
        fileNameFromHeaders(
          res.headers,
          pdfJob.file_name || `${(selected.class_name || "class").replace(/\s+/g, "_")}_${(selected.term_name || "term").replace(/\s+/g, "_")}_result.pdf`
        )
      );
      if (saved.native) alert(saved.message);
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const message = await messageFromBlobError(e.response.data, "Failed to download result PDF");
        alert(message);
      } else {
        alert(e?.response?.data?.message || e?.message || "Failed to download result PDF");
      }
    } finally {
      setDownloading(false);
    }
  };

  const pdfActionLabel = (() => {
    if (requestingPdf) return "Starting...";
    if (downloading) return "Downloading...";
    if (pdfJob?.status === "completed") return "Download Result PDF";
    if (pdfJob?.status === "failed") return "Retry Result PDF";
    if (pdfJob?.status === "processing") return "Processing PDF...";
    if (pdfJob?.status === "pending") return "Queued...";
    return "Generate Result PDF";
  })();

  const handlePdfAction = () => {
    if (!selected?.results_open || isPdfProcessing || requestingPdf) {
      return;
    }

    if (pdfJob?.status === "completed") {
      downloadGeneratedResultPdf();
      return;
    }

    startResultPdfGeneration();
  };

  const renderSelectedResultPanel = () => {
    if (!selected) return null;

    if (!selected.results_open) {
      return (
        <p className="rs-state rs-state--empty" style={{ marginTop: 14 }}>
          {selected.locked_message || "This result is not available yet."}
        </p>
      );
    }

    return (
      <>
        <div className="rs-results-head">
          <h3 className="rs-results-title">
            {selected.class_name} - {sessionLabel(selected)} - {selected.term_name}
            {isCumulative ? " Cumulative" : ""}
          </h3>

          <button
            className="rs-btn"
            onClick={handlePdfAction}
            disabled={loadingResults || requestingPdf || downloading || isPdfProcessing}
          >
            {pdfActionLabel}
          </button>
        </div>

        {supportsCumulative ? (
          <div className="rs-meta" style={{ marginTop: 10 }}>
            <button
              type="button"
              className="rs-btn"
              style={{ opacity: resultType === "term" ? 1 : 0.72 }}
              onClick={() => {
                setResultType("term");
                setPdfJob(null);
              }}
            >
              Term Result
            </button>
            <button
              type="button"
              className="rs-btn"
              style={{ opacity: resultType === "cumulative" ? 1 : 0.72 }}
              onClick={() => {
                setResultType("cumulative");
                setPdfJob(null);
              }}
            >
              Cumulative Result
            </button>
          </div>
        ) : null}

        {pdfJob?.status === "pending" || pdfJob?.status === "processing" ? (
          <p className="rs-state rs-state--loading" style={{ marginTop: 10 }}>
            {pdfJob.status === "processing"
              ? "Your result PDF is being prepared for this school. We will keep checking until it is ready."
              : "Your result PDF request is in queue. Processing will begin shortly."}
          </p>
        ) : null}

        {pdfJob?.status === "completed" ? (
          <p className="rs-state" style={{ marginTop: 10 }}>
            Your result PDF is ready. Click the button above to download it.
          </p>
        ) : null}

        {pdfJob?.status === "failed" ? (
          <p className="rs-state rs-state--error" style={{ marginTop: 10 }}>
            {pdfJob.error_message || "Result PDF generation failed. Please try again."}
          </p>
        ) : null}

        {loadingResults ? (
          <p className="rs-state rs-state--loading" style={{ marginTop: 10 }}>Loading results...</p>
        ) : (
          <div className="rs-table-wrap">
            <table className={`rs-table${commentsOnly ? " rs-table--comments" : ""}${usesClassicPreview ? " rs-table--classic" : ""}`}>
              <thead>
                <tr>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Subject</th>
                  {commentsOnly ? (
                    <th>Teacher's Observation</th>
                  ) : isCumulative ? (
                    <>
                      <th style={{ width: 100 }}>First Term</th>
                      <th style={{ width: 110 }}>Second Term</th>
                      <th style={{ width: 100 }}>Third Term</th>
                      <th style={{ width: 90 }}>Average</th>
                    </>
                  ) : (
                    <>
                      {caIndices.map((idx) => (
                        <th key={`ca-head-${idx}`} style={{ width: 80 }}>
                          {assessmentSchema.ca_labels?.[idx] || `CA${idx + 1}`}
                        </th>
                      ))}
                      {showThirdTermPreviousTotals ? (
                        <>
                          <th style={{ width: 100 }}>Third Term Exam ({assessmentSchema.exam_max})</th>
                          {usesClassicPreview ? (
                            <>
                              {assessmentSchema.psychomotor_labels.map((label, index) => <th key={`preview-psy-head-${index}`}>{label} ({assessmentSchema.psychomotor_maxes[index]})</th>)}<th>{assessmentSchema.quiz_label} ({assessmentSchema.quiz_max})</th><th>{assessmentSchema.note_copying_label} ({assessmentSchema.note_copying_max})</th>
                            </>
                          ) : null}
                          <th style={{ width: 110 }}>First Term Total</th>
                          <th style={{ width: 120 }}>Second Term Total</th>
                          <th style={{ width: 100 }}>Total Score</th>
                          <th style={{ width: 110 }}>Total Average</th>
                        </>
                      ) : (
                        <>
                          <th style={{ width: 80 }}>CA Total</th>
                          <th style={{ width: 90 }}>Exam ({assessmentSchema.exam_max})</th>
                          {usesClassicPreview ? (
                            <>
                              {assessmentSchema.psychomotor_labels.map((label, index) => <th key={`preview-psy-head-${index}`}>{label} ({assessmentSchema.psychomotor_maxes[index]})</th>)}<th>{assessmentSchema.quiz_label} ({assessmentSchema.quiz_max})</th><th>{assessmentSchema.note_copying_label} ({assessmentSchema.note_copying_max})</th>
                            </>
                          ) : null}
                          <th style={{ width: 80 }}>Total</th>
                        </>
                      )}
                    </>
                  )}
                  {!commentsOnly ? <th style={{ width: 80 }}>Grade</th> : null}
                </tr>
              </thead>
              <tbody>
                {results.map((row, idx) => (
                  <tr key={row.term_subject_id}>
                    <td>{idx + 1}</td>
                    <td>{row.subject_name}</td>
                    {commentsOnly ? (
                      <td className="rs-teacher-comment">{row.teacher_comment || "No observation entered yet."}</td>
                    ) : isCumulative ? (
                      <>
                        <td>{displayScore(row.first_term_total)}</td>
                        <td>{displayScore(row.second_term_total)}</td>
                        <td>{displayScore(row.third_term_total)}</td>
                        <td>{displayScore(row.average)}</td>
                      </>
                    ) : (
                      <>
                        {caIndices.map((caIdx) => (
                          <td key={`ca-cell-${row.term_subject_id}-${caIdx}`}>
                            {displayScore(row.ca_breakdown?.[caIdx])}
                          </td>
                        ))}
                        {showThirdTermPreviousTotals ? (
                          <>
                            <td>{displayScore(row.exam)}</td>
                            {usesClassicPreview ? (
                              <>
                                {classicComponentCells(row).map((value, componentIndex) => <td key={`classic-psy-${row.term_subject_id}-${componentIndex}`}>{value}</td>)}
                                <td>{displayScore(row.classic_components?.quiz)}</td>
                                <td>{displayScore(row.classic_components?.note_copying)}</td>
                              </>
                            ) : null}
                            <td>{displayScore(row.first_term_total)}</td>
                            <td>{displayScore(row.second_term_total)}</td>
                            <td>{displayScore(row.combined_total_score)}</td>
                            <td>{displayScore(row.combined_average)}</td>
                          </>
                        ) : (
                          <>
                            <td>{displayScore(row.ca)}</td>
                            <td>{displayScore(row.exam)}</td>
                            {usesClassicPreview ? (
                              <>
                                {classicComponentCells(row).map((value, componentIndex) => <td key={`classic-psy-${row.term_subject_id}-${componentIndex}`}>{value}</td>)}
                                <td>{displayScore(row.classic_components?.quiz)}</td>
                                <td>{displayScore(row.classic_components?.note_copying)}</td>
                              </>
                            ) : null}
                            <td>{displayScore(row.total)}</td>
                          </>
                        )}
                      </>
                    )}
                    {!commentsOnly ? <td>{showThirdTermPreviousTotals ? row.third_term_combined_grade || row.grade : row.grade}</td> : null}
                  </tr>
                ))}
                {results.length === 0 ? (
                  <tr>
                    <td colSpan={commentsOnly ? 3 : isCumulative ? 7 : caIndices.length + (showThirdTermPreviousTotals ? 8 : 6) + (usesClassicPreview ? 7 : 0)}>
                      No result records for this class and term.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}
      </>
    );
  };

  return (
    <div className="rs-page rs-page--student">
      <section className="rs-hero">
        <div>
          <span className="rs-pill">Student Results</span>
          <h2 className="rs-title">Track your performance by class and term</h2>
          <p className="rs-subtitle">
            Switch across assigned class records, review CA and exam scores, then generate your school-specific PDF when you are ready.
          </p>
          <div className="rs-meta">
            <span>{loadingClasses ? "Loading..." : `${classes.length} class record${classes.length === 1 ? "" : "s"}`}</span>
            <span>{selected?.term_name || "Term not selected"}</span>
          </div>
        </div>

        <div className="rs-hero-art" aria-hidden="true">
          <div className="rs-art rs-art--main">
            <img src={examPrepArt} alt="" />
          </div>
          <div className="rs-art rs-art--survey">
            <img src={onlineSurveyArt} alt="" />
          </div>
          <div className="rs-art rs-art--cert">
            <img src={certificateArt} alt="" />
          </div>
        </div>
      </section>

      <section className="rs-panel">
        {loadingClasses ? <p className="rs-state rs-state--loading">Loading assigned classes...</p> : null}
        {!loadingClasses && classes.length === 0 ? (
          <p className="rs-state rs-state--empty">No class assignment found for the current session.</p>
        ) : null}

        {!loadingClasses && classes.length > 0 ? (
          <>
            <div className="rs-section-block">
              <div className="rs-results-head">
                <h3 className="rs-results-title">Current Term Result</h3>
                <span className="rs-card-meta">
                  {currentItems.length ? currentItems[0].term_name : "No current term"}
                </span>
              </div>
              <div className="rs-cards">
                {currentItems.length > 0 ? currentItems.map((item) => {
                  const isActive =
                    selected?.class_id === item.class_id && selected?.term_id === item.term_id;
                  return (
                    <div
                      className={`rs-period-block${isActive ? " rs-period-block--active" : ""}`}
                      key={`current-${item.class_id}-${item.term_id}`}
                    >
                      <button
                        type="button"
                        className={`rs-card-btn${isActive ? " rs-card-btn--active" : ""}${!item.results_open ? " rs-card-btn--locked" : ""}`}
                        onClick={() => {
                          setPdfJob(null);
                          setSelected(item);
                        }}
                      >
                        <h3 className="rs-card-title">{item.class_name}</h3>
                        <p className="rs-card-meta">
                          {item.class_level} | {sessionLabel(item)} | {item.term_name}
                        </p>
                        <p className="rs-card-meta" style={{ marginTop: 6 }}>
                          {item.results_open ? "Published and ready" : item.locked_message || "Awaiting publication"}
                        </p>
                      </button>
                      {isActive ? <div className="rs-inline-result">{renderSelectedResultPanel()}</div> : null}
                    </div>
                  );
                }) : (
                  <p className="rs-state rs-state--empty">No current term result record found.</p>
                )}
              </div>
            </div>

            <div className="rs-section-block">
              <h3 className="rs-results-title">Current Session</h3>
              <p className="rs-card-meta">Other terms in the active academic session remain here until the session is completed.</p>
              {currentSessionGroups.length > 0 ? currentSessionGroups.map((group) => (
                <details className="rs-accordion" key={group.id}>
                  <summary><span>{group.label}</span><small>{group.items.length} term record{group.items.length === 1 ? "" : "s"}</small></summary>
                  <div className="rs-term-list">
                    {group.items.map((item) => {
                      const isActive = selected?.class_id === item.class_id && selected?.term_id === item.term_id;
                      return <details className="rs-term-accordion" key={`${item.class_id}-${item.term_id}`} open={isActive || undefined}>
                        <summary><span>{item.term_name}</span><small>{item.class_name}</small></summary>
                        <button type="button" className={`rs-card-btn rs-card-btn--compact${isActive ? " rs-card-btn--active" : ""}${!item.results_open ? " rs-card-btn--locked" : ""}`} onClick={() => { setPdfJob(null); setSelected(item); }}>
                          <h3 className="rs-card-title">{item.class_name}</h3>
                          <p className="rs-card-meta">{item.class_level} | {item.term_name}</p>
                          {itemSupportsCumulative(item) ? <p className="rs-card-meta" style={{ marginTop: 6 }}>Cumulative Result available</p> : null}
                        </button>
                        {isActive ? <div className="rs-inline-result">{renderSelectedResultPanel()}</div> : null}
                      </details>;
                    })}
                  </div>
                </details>
              )) : <p className="rs-state rs-state--empty">No other term records are available in the current session yet.</p>}
            </div>

            <div className="rs-section-block">
              <h3 className="rs-results-title">Past Records</h3>
              <p className="rs-card-meta">Completed academic sessions appear here after the student has participated in at least one term.</p>
              {pastSessionGroups.length > 0 ? pastSessionGroups.map((group) => (
                <details className="rs-accordion" key={group.id}>
                  <summary><span>{group.label}</span><small>{group.items.length} term record{group.items.length === 1 ? "" : "s"}</small></summary>
                  <div className="rs-term-list">
                    {group.items.map((item) => {
                      const isActive = selected?.class_id === item.class_id && selected?.term_id === item.term_id;
                      return <details className="rs-term-accordion" key={`${item.class_id}-${item.term_id}`} open={isActive || undefined}>
                        <summary><span>{item.term_name}</span><small>{item.class_name}</small></summary>
                        <button type="button" className={`rs-card-btn rs-card-btn--compact${isActive ? " rs-card-btn--active" : ""}${!item.results_open ? " rs-card-btn--locked" : ""}`} onClick={() => { setPdfJob(null); setSelected(item); }}>
                          <h3 className="rs-card-title">{item.class_name}</h3>
                          <p className="rs-card-meta">{item.class_level} | {item.term_name}</p>
                          {itemSupportsCumulative(item) ? <p className="rs-card-meta" style={{ marginTop: 6 }}>Cumulative Result available</p> : null}
                        </button>
                        {isActive ? <div className="rs-inline-result">{renderSelectedResultPanel()}</div> : null}
                      </details>;
                    })}
                  </div>
                </details>
              )) : <p className="rs-state rs-state--empty">Past records will appear after a completed session contains a student term record.</p>}
            </div>
          </>
        ) : null}

        {error ? <p className="rs-state rs-state--error" style={{ marginTop: 10 }}>{error}</p> : null}
      </section>
    </div>
  );
}
