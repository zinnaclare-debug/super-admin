import { useState } from "react";
import api from "../../../services/api";
import "./Transcript.css";

function fileNameFromHeaders(headers, fallback) {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "transcript.pdf";
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

export default function Transcript() {
  const [email, setEmail] = useState("");
  const [entries, setEntries] = useState([]);
  const [context, setContext] = useState(null);
  const [searching, setSearching] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");

  const canSearch = email.trim().length > 0;

  const requestParams = () => ({
    email: email.trim(),
  });

  const searchTranscript = async () => {
    if (!canSearch) return;
    setSearching(true);
    setError("");
    setMessage("");
    try {
      const res = await api.get("/api/school-admin/transcript", {
        params: requestParams(),
      });
      const data = res.data?.data || [];
      setEntries(data);
      setContext(res.data?.context || null);
      setMessage(res.data?.message || (data.length === 0 ? "No result records found." : ""));
    } catch (e) {
      setEntries([]);
      setContext(null);
      setMessage("");
      setError(e?.response?.data?.message || "Failed to load transcript.");
    } finally {
      setSearching(false);
    }
  };

  const downloadTranscript = async () => {
    if (!canSearch) return;
    setDownloading(true);
    setError("");
    setMessage("");
    try {
      const res = await api.get("/api/school-admin/transcript/download", {
        params: requestParams(),
        responseType: "blob",
      });

      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const msg = await messageFromBlobError(res.data, "Failed to download transcript PDF.");
        throw new Error(msg);
      }

      const pdfBlob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const blobUrl = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, "student_transcript.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
      setMessage("Transcript downloaded successfully.");
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const msg = await messageFromBlobError(e.response.data, "Failed to download transcript PDF.");
        setError(msg);
      } else {
        setError(e?.response?.data?.message || e?.message || "Failed to download transcript PDF.");
      }
    } finally {
      setDownloading(false);
    }
  };

  return (
    <div className="transcript-page">
      <div className="transcript-card">
        <div className="transcript-grid">
          <div className="transcript-field">
            <label htmlFor="transcript-email">Student Email</label>
            <input
              id="transcript-email"
              type="email"
              placeholder="student@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>
        </div>

        <div className="transcript-actions">
          <button onClick={searchTranscript} disabled={!canSearch || searching}>
            {searching ? "Searching..." : "Search"}
          </button>
          <button className="secondary" onClick={downloadTranscript} disabled={!canSearch || downloading}>
            {downloading ? "Downloading..." : "Download Transcript"}
          </button>
        </div>

        {error ? <p className="transcript-error">{error}</p> : null}
        {message ? <p className="transcript-message">{message}</p> : null}
      </div>

      {context?.student ? (
        <div className="transcript-meta">
          <p>
            <strong>Student:</strong> {context.student.name} ({context.student.email})
          </p>
          <p>
            <strong>Total Results:</strong> {Number(context.entries_count || entries.length || 0)}
          </p>
        </div>
      ) : null}

      {entries.map((entry) => (
        <div className="transcript-entry" key={`${entry.session?.id || "s"}-${entry.class?.id || "c"}-${entry.term?.id || "t"}`}>
          <div className="transcript-entry-head">
            <h3>{entry.term?.name || "Term Result"}</h3>
            <p>
              {entry.class?.name || "-"} | {entry.session?.academic_year || entry.session?.session_name || "-"} | Average:{" "}
              {Number(entry.summary?.average_score || 0).toFixed(2)} | Grade: {entry.summary?.overall_grade || "-"}
            </p>
          </div>

          <div className="transcript-table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Subject</th>
                  <th>CA</th>
                  <th>Exam</th>
                  <th>Total</th>
                  <th>Min</th>
                  <th>Max</th>
                  <th>Class Ave</th>
                  <th>Position</th>
                  <th>Grade</th>
                  <th>Remark</th>
                </tr>
              </thead>
              <tbody>
                {(entry.rows || []).map((row) => (
                  <tr key={row.term_subject_id}>
                    <td>{row.subject_name}</td>
                    <td>{row.ca}</td>
                    <td>{row.exam}</td>
                    <td>{row.total}</td>
                    <td>{row.min_score}</td>
                    <td>{row.max_score}</td>
                    <td>{Number(row.class_average || 0).toFixed(2)}</td>
                    <td>{row.position_label || "-"}</td>
                    <td>{row.grade}</td>
                    <td>{row.remark}</td>
                  </tr>
                ))}
                {(entry.rows || []).length === 0 ? (
                  <tr>
                    <td colSpan="10">No records found for this term.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </div>
      ))}
    </div>
  );
}
