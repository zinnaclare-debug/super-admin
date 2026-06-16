import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import examPrepArt from "../../../assets/results/exam-prep.svg";
import onlineSurveyArt from "../../../assets/results/online-survey.svg";
import certificateArt from "../../../assets/results/certificate.svg";
import "../../shared/ResultsShowcase.css";

const DEFAULT_SCHEMA = {
  ca_maxes: [30, 0, 0, 0, 0],
  ca_labels: ["CA1", "CA2", "CA3", "CA4", "CA5"],
  exam_max: 70,
  total_max: 100,
};

const normalizeSchema = (schema) => {
  const raw = schema || {};
  const caMaxes = Array.isArray(raw.ca_maxes) ? raw.ca_maxes : DEFAULT_SCHEMA.ca_maxes;
  const caLabels = Array.isArray(raw.ca_labels) ? raw.ca_labels : DEFAULT_SCHEMA.ca_labels;
  const normalizedCa = Array.from({ length: 5 }, (_, idx) => {
    const n = Number(caMaxes[idx] || 0);
    return Number.isFinite(n) ? Math.max(0, Math.min(100, Math.round(n))) : 0;
  });
  const normalizedLabels = Array.from({ length: 5 }, (_, idx) => {
    const label = String(caLabels[idx] || "").trim();
    return label || `CA${idx + 1}`;
  });
  const caTotal = normalizedCa.reduce((sum, value) => sum + value, 0);
  const requestedExam = Number(raw.exam_max ?? 100 - caTotal);
  const examMax = Number.isFinite(requestedExam) ? Math.max(0, Math.min(100, Math.round(requestedExam))) : 0;

  return {
    ca_maxes: normalizedCa,
    ca_labels: normalizedLabels,
    exam_max: caTotal + examMax === 100 ? examMax : Math.max(0, 100 - caTotal),
    total_max: 100,
  };
};

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
  const currentItems = useMemo(() => classes.filter((item) => item.is_current_period), [classes]);
  const historyGroups = useMemo(() => {
    const historyItems = classes.filter((item) => !item.is_current_period);
    const grouped = new Map();

    historyItems.forEach((item) => {
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
  }, [classes]);

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

      const blobUrl = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(
        res.headers,
        pdfJob.file_name || `${(selected.class_name || "class").replace(/\s+/g, "_")}_${(selected.term_name || "term").replace(/\s+/g, "_")}_result.pdf`
      );
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
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
            <table className="rs-table">
              <thead>
                <tr>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Subject</th>
                  {isCumulative ? (
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
                      <th style={{ width: 80 }}>CA Total</th>
                      <th style={{ width: 90 }}>Exam ({assessmentSchema.exam_max})</th>
                      <th style={{ width: 80 }}>Total</th>
                      {showThirdTermPreviousTotals ? (
                        <>
                          <th style={{ width: 100 }}>First Term</th>
                          <th style={{ width: 110 }}>Second Term</th>
                          <th style={{ width: 100 }}>Third Term</th>
                        </>
                      ) : null}
                    </>
                  )}
                  <th style={{ width: 80 }}>Grade</th>
                </tr>
              </thead>
              <tbody>
                {results.map((row, idx) => (
                  <tr key={row.term_subject_id}>
                    <td>{idx + 1}</td>
                    <td>{row.subject_name}</td>
                    {isCumulative ? (
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
                        <td>{displayScore(row.ca)}</td>
                        <td>{displayScore(row.exam)}</td>
                        <td>{displayScore(row.total)}</td>
                        {showThirdTermPreviousTotals ? (
                          <>
                            <td>{displayScore(row.first_term_total)}</td>
                            <td>{displayScore(row.second_term_total)}</td>
                            <td>{displayScore(row.third_term_total)}</td>
                          </>
                        ) : null}
                      </>
                    )}
                    <td>{row.grade}</td>
                  </tr>
                ))}
                {results.length === 0 ? (
                  <tr>
                    <td colSpan={isCumulative ? 7 : 6 + caIndices.length + (showThirdTermPreviousTotals ? 3 : 0)}>
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
              <h3 className="rs-results-title">Past Results</h3>
              {historyGroups.length > 0 ? historyGroups.map((group) => (
                <details className="rs-accordion" key={group.id}>
                  <summary>
                    <span>{group.label}</span>
                    <small>{group.items.length} term record{group.items.length === 1 ? "" : "s"}</small>
                  </summary>
                  <div className="rs-term-list">
                    {group.items.map((item) => {
                      const isActive =
                        selected?.class_id === item.class_id && selected?.term_id === item.term_id;
                      return (
                        <details
                          className="rs-term-accordion"
                          key={`${item.class_id}-${item.term_id}`}
                          open={isActive || undefined}
                        >
                          <summary>
                            <span>{item.term_name}</span>
                            <small>{item.class_name}</small>
                          </summary>
                          <button
                            type="button"
                            className={`rs-card-btn rs-card-btn--compact${isActive ? " rs-card-btn--active" : ""}${!item.results_open ? " rs-card-btn--locked" : ""}`}
                            onClick={() => {
                              setPdfJob(null);
                              setSelected(item);
                            }}
                          >
                            <h3 className="rs-card-title">{item.class_name}</h3>
                            <p className="rs-card-meta">
                              {item.class_level} | {item.term_name}
                            </p>
                            {itemSupportsCumulative(item) ? (
                              <p className="rs-card-meta" style={{ marginTop: 6 }}>Cumulative Result available</p>
                            ) : null}
                          </button>
                          {isActive ? <div className="rs-inline-result">{renderSelectedResultPanel()}</div> : null}
                        </details>
                      );
                    })}
                  </div>
                </details>
              )) : (
                <p className="rs-state rs-state--empty">Past results will appear here after a term or session becomes past.</p>
              )}
            </div>
          </>
        ) : null}

        {error ? <p className="rs-state rs-state--error" style={{ marginTop: 10 }}>{error}</p> : null}
      </section>
    </div>
  );
}
