import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import examPrepArt from "../../../assets/results/exam-prep.svg";
import onlineSurveyArt from "../../../assets/results/online-survey.svg";
import certificateArt from "../../../assets/results/certificate.svg";
import "../../shared/ResultsShowcase.css";

const DEFAULT_SCHEMA = {
  ca_maxes: [30, 0, 0, 0, 0],
  exam_max: 70,
  total_max: 100,
};

const normalizeSchema = (schema) => {
  const raw = schema || {};
  const caMaxes = Array.isArray(raw.ca_maxes) ? raw.ca_maxes : DEFAULT_SCHEMA.ca_maxes;
  const normalizedCa = Array.from({ length: 5 }, (_, idx) => {
    const n = Number(caMaxes[idx] || 0);
    return Number.isFinite(n) ? Math.max(0, Math.min(100, Math.round(n))) : 0;
  });
  const caTotal = normalizedCa.reduce((sum, value) => sum + value, 0);
  const requestedExam = Number(raw.exam_max ?? 100 - caTotal);
  const examMax = Number.isFinite(requestedExam) ? Math.max(0, Math.min(100, Math.round(requestedExam))) : 0;

  return {
    ca_maxes: normalizedCa,
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
  const [loadingClasses, setLoadingClasses] = useState(true);
  const [loadingResults, setLoadingResults] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState("");

  const caIndices = useMemo(() => activeCaIndices(assessmentSchema), [assessmentSchema]);

  const loadClasses = async () => {
    setLoadingClasses(true);
    setError("");
    try {
      const res = await api.get("/api/student/results/classes");
      const items = res.data?.data || [];
      setClasses(items);
      setSelected(items.length > 0 ? items[0] : null);
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
      return;
    }

    setLoadingResults(true);
    setError("");
    try {
      const res = await api.get("/api/student/results", {
        params: {
          class_id: item.class_id,
          term_id: item.term_id,
        },
      });
      setResults(res.data?.data || []);
      setAssessmentSchema(normalizeSchema(res.data?.assessment_schema));
    } catch (e) {
      setError(e?.response?.data?.message || "Failed to load results");
      setResults([]);
      setAssessmentSchema(DEFAULT_SCHEMA);
    } finally {
      setLoadingResults(false);
    }
  };

  useEffect(() => {
    loadClasses();
  }, []);

  useEffect(() => {
    loadResults(selected);
  }, [selected]);

  const downloadResultPdf = async () => {
    if (!selected) return;
    setDownloading(true);
    try {
      const res = await api.get("/api/student/results/download", {
        params: {
          class_id: selected.class_id,
          term_id: selected.term_id,
        },
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
        `${(selected.class_name || "class").replace(/\s+/g, "_")}_${(selected.term_name || "term").replace(/\s+/g, "_")}_result.pdf`
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

  return (
    <div className="rs-page rs-page--student">
      <section className="rs-hero">
        <div>
          <span className="rs-pill">Student Results</span>
          <h2 className="rs-title">Track your performance by class and term</h2>
          <p className="rs-subtitle">
            Switch across assigned class records, review CA and exam scores, and download your result PDF instantly.
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
          <div className="rs-cards">
            {classes.map((item) => {
              const isActive =
                selected?.class_id === item.class_id && selected?.term_id === item.term_id;
              return (
                <button
                  key={`${item.class_id}-${item.term_id}`}
                  className={`rs-card-btn${isActive ? " rs-card-btn--active" : ""}`}
                  onClick={() => setSelected(item)}
                >
                  <h3 className="rs-card-title">{item.class_name}</h3>
                  <p className="rs-card-meta">
                    {item.class_level} | {item.term_name}
                  </p>
                </button>
              );
            })}
          </div>
        ) : null}

        {error ? <p className="rs-state rs-state--error" style={{ marginTop: 10 }}>{error}</p> : null}

        {selected ? (
          <>
            <div className="rs-results-head">
              <h3 className="rs-results-title">
                {selected.class_name} - {selected.term_name}
              </h3>
              <button className="rs-btn" onClick={downloadResultPdf} disabled={loadingResults || downloading}>
                {downloading ? "Downloading..." : "Download Result PDF"}
              </button>
            </div>

            {loadingResults ? (
              <p className="rs-state rs-state--loading" style={{ marginTop: 10 }}>Loading results...</p>
            ) : (
              <div className="rs-table-wrap">
                <table className="rs-table">
                  <thead>
                    <tr>
                      <th style={{ width: 70 }}>S/N</th>
                      <th>Subject</th>
                      {caIndices.map((idx) => (
                        <th key={`ca-head-${idx}`} style={{ width: 80 }}>
                          CA{idx + 1}
                        </th>
                      ))}
                      <th style={{ width: 80 }}>CA Total</th>
                      <th style={{ width: 90 }}>Exam ({assessmentSchema.exam_max})</th>
                      <th style={{ width: 80 }}>Total</th>
                      <th style={{ width: 80 }}>Grade</th>
                    </tr>
                  </thead>
                  <tbody>
                    {results.map((row, idx) => (
                      <tr key={row.term_subject_id}>
                        <td>{idx + 1}</td>
                        <td>{row.subject_name}</td>
                        {caIndices.map((caIdx) => (
                          <td key={`ca-cell-${row.term_subject_id}-${caIdx}`}>
                            {displayScore(row.ca_breakdown?.[caIdx])}
                          </td>
                        ))}
                        <td>{displayScore(row.ca)}</td>
                        <td>{displayScore(row.exam)}</td>
                        <td>{displayScore(row.total)}</td>
                        <td>{row.grade}</td>
                      </tr>
                    ))}
                    {results.length === 0 ? (
                      <tr>
                        <td colSpan={6 + caIndices.length}>
                          No result records for this class and term.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            )}
          </>
        ) : null}
      </section>
    </div>
  );
}
