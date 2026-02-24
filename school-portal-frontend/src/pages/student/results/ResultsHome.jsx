import { useEffect, useState } from "react";
import api from "../../../services/api";

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
  const [loadingClasses, setLoadingClasses] = useState(true);
  const [loadingResults, setLoadingResults] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState("");

  const loadClasses = async () => {
    setLoadingClasses(true);
    setError("");
    try {
      const res = await api.get("/api/student/results/classes");
      const items = res.data?.data || [];
      setClasses(items);
      if (items.length > 0) {
        setSelected(items[0]);
      } else {
        setSelected(null);
      }
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
    } catch (e) {
      setError(e?.response?.data?.message || "Failed to load results");
      setResults([]);
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

      const pdfBlob = res.data instanceof Blob
        ? res.data
        : new Blob([res.data], { type: "application/pdf" });

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
          {classes.map((c) => {
            const isActive = selected?.class_id === c.class_id && selected?.term_id === c.term_id;
            return (
              <button
                key={`${c.class_id}-${c.term_id}`}
                onClick={() => setSelected(c)}
                style={{
                  padding: "10px 12px",
                  borderRadius: 8,
                  border: isActive ? "1px solid #2563eb" : "1px solid #ddd",
                  background: isActive ? "#eff6ff" : "#fff",
                  cursor: "pointer",
                }}
              >
                <div style={{ fontWeight: 700 }}>{c.class_name}</div>
                <div style={{ fontSize: 12, opacity: 0.8 }}>
                  {c.class_level} • {c.term_name}
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
                  <th style={{ width: 80 }}>CA</th>
                  <th style={{ width: 80 }}>Exam</th>
                  <th style={{ width: 80 }}>Total</th>
                  <th style={{ width: 80 }}>Grade</th>
                </tr>
              </thead>
              <tbody>
                {results.map((r, idx) => (
                  <tr key={r.term_subject_id}>
                    <td>{idx + 1}</td>
                    <td>{r.subject_name}</td>
                    <td>{r.ca}</td>
                    <td>{r.exam}</td>
                    <td>{r.total}</td>
                    <td>{r.grade}</td>
                  </tr>
                ))}
                {results.length === 0 ? (
                  <tr>
                    <td colSpan="6">No result records for this class and term.</td>
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
