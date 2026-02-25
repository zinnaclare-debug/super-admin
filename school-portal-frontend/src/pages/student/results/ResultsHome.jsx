import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";

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
    <div>
      {loadingClasses ? (
        <p>Loading assigned classes...</p>
      ) : classes.length === 0 ? (
        <p>No class assignment found for the current session.</p>
      ) : (
        <div style={{ display: "flex", flexWrap: "wrap", gap: 10, marginTop: 10 }}>
          {classes.map((item) => {
            const isActive =
              selected?.class_id === item.class_id && selected?.term_id === item.term_id;
            return (
              <button
                key={`${item.class_id}-${item.term_id}`}
                onClick={() => setSelected(item)}
                style={{
                  padding: "10px 12px",
                  borderRadius: 8,
                  border: isActive ? "1px solid #2563eb" : "1px solid #ddd",
                  background: isActive ? "#eff6ff" : "#fff",
                  cursor: "pointer",
                }}
              >
                <div style={{ fontWeight: 700 }}>{item.class_name}</div>
                <div style={{ fontSize: 12, opacity: 0.8 }}>
                  {item.class_level} | {item.term_name}
                </div>
              </button>
            );
          })}
        </div>
      )}

      {error ? <p style={{ color: "red", marginTop: 10 }}>{error}</p> : null}

      {selected ? (
        <div style={{ marginTop: 16 }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 10 }}>
            <h3 style={{ marginBottom: 8 }}>
              {selected.class_name} - {selected.term_name}
            </h3>
            <button onClick={downloadResultPdf} disabled={loadingResults || downloading}>
              {downloading ? "Downloading..." : "Download Result PDF"}
            </button>
          </div>
          {loadingResults ? (
            <p>Loading results...</p>
          ) : (
            <table border="1" cellPadding="10" width="100%">
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
                        {Number(row.ca_breakdown?.[caIdx] || 0)}
                      </td>
                    ))}
                    <td>{row.ca}</td>
                    <td>{row.exam}</td>
                    <td>{row.total}</td>
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
          )}
        </div>
      ) : null}
    </div>
  );
}

