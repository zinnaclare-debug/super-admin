import { useEffect, useMemo, useState } from "react";
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
  const [sessions, setSessions] = useState([]);
  const [email, setEmail] = useState("");
  const [sessionId, setSessionId] = useState("");
  const [scope, setScope] = useState("single");
  const [termId, setTermId] = useState("");
  const [entries, setEntries] = useState([]);
  const [context, setContext] = useState(null);
  const [loadingOptions, setLoadingOptions] = useState(true);
  const [searching, setSearching] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");

  const selectedSession = useMemo(() => {
    if (sessions.length === 0) return null;
    return sessions.find((item) => String(item.id) === String(sessionId)) || sessions[0];
  }, [sessions, sessionId]);

  const selectedTerm = useMemo(() => {
    const terms = selectedSession?.terms || [];
    if (terms.length === 0) return null;
    return terms.find((item) => String(item.id) === String(termId)) || terms[0];
  }, [selectedSession, termId]);

  const canSearch = email.trim().length > 0 && selectedSession;
  const canDownload = entries.length > 0 && canSearch;

  const syncDefaultTerm = (session, activeScope) => {
    if (!session) {
      setTermId("");
      return;
    }
    if (activeScope === "all") {
      setTermId("");
      return;
    }
    const terms = session.terms || [];
    if (terms.length === 0) {
      setTermId("");
      return;
    }
    const preferred = terms.find((item) => item.is_current) || terms[0];
    setTermId(String(preferred.id));
  };

  const loadOptions = async () => {
    setLoadingOptions(true);
    setError("");
    try {
      const res = await api.get("/api/school-admin/transcript/options");
      const payload = res.data?.data || {};
      const loadedSessions = payload.sessions || [];
      setSessions(loadedSessions);

      if (loadedSessions.length === 0) {
        setSessionId("");
        setTermId("");
        return;
      }

      const preferredSessionId = payload.selected_session_id || loadedSessions[0].id;
      const preferredSession = loadedSessions.find((item) => item.id === preferredSessionId) || loadedSessions[0];
      setSessionId(String(preferredSession.id));
      syncDefaultTerm(preferredSession, "single");
    } catch (e) {
      setError(e?.response?.data?.message || "Failed to load transcript filters.");
      setSessions([]);
      setSessionId("");
      setTermId("");
    } finally {
      setLoadingOptions(false);
    }
  };

  useEffect(() => {
    loadOptions();
  }, []);

  useEffect(() => {
    if (!selectedSession) return;
    if ((selectedSession.terms || []).length === 0) {
      setTermId("");
      return;
    }
    if (scope === "all") {
      setTermId("");
      return;
    }
    const hasCurrentTerm = (selectedSession.terms || []).some((item) => String(item.id) === String(termId));
    if (!hasCurrentTerm) {
      const preferred = selectedSession.terms.find((item) => item.is_current) || selectedSession.terms[0];
      setTermId(String(preferred.id));
    }
  }, [selectedSession, scope, termId]);

  const requestParams = () => {
    const params = {
      email: email.trim(),
      scope,
    };
    if (selectedSession?.id) {
      params.academic_session_id = selectedSession.id;
    }
    if (scope === "single" && selectedTerm?.id) {
      params.term_id = selectedTerm.id;
    }
    return params;
  };

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
    if (!canDownload) return;
    setDownloading(true);
    setError("");
    try {
      const res = await api.get("/api/school-admin/transcript/download", {
        params: requestParams(),
        responseType: "blob",
      });

      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const message = await messageFromBlobError(res.data, "Failed to download transcript PDF.");
        throw new Error(message);
      }

      const pdfBlob = res.data instanceof Blob
        ? res.data
        : new Blob([res.data], { type: "application/pdf" });
      const blobUrl = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, "student_transcript.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const message = await messageFromBlobError(e.response.data, "Failed to download transcript PDF.");
        setError(message);
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

          <div className="transcript-field">
            <label htmlFor="transcript-session">Academic Session</label>
            <select
              id="transcript-session"
              value={sessionId}
              onChange={(e) => {
                const value = e.target.value;
                setSessionId(value);
                const nextSession = sessions.find((item) => String(item.id) === value) || null;
                syncDefaultTerm(nextSession, scope);
              }}
              disabled={loadingOptions}
            >
              {(sessions || []).map((item) => (
                <option key={item.id} value={item.id}>
                  {item.session_name || item.academic_year}
                  {item.is_current ? " (Current)" : ""}
                </option>
              ))}
            </select>
          </div>

          <div className="transcript-field">
            <label htmlFor="transcript-scope">Result Scope</label>
            <select
              id="transcript-scope"
              value={scope}
              onChange={(e) => {
                const value = e.target.value;
                setScope(value);
                syncDefaultTerm(selectedSession, value);
              }}
            >
              <option value="single">Single Term</option>
              <option value="all">All Terms</option>
            </select>
          </div>

          <div className="transcript-field">
            <label htmlFor="transcript-term">Term</label>
            <select
              id="transcript-term"
              value={scope === "all" ? "" : termId}
              onChange={(e) => setTermId(e.target.value)}
              disabled={scope === "all"}
            >
              {(selectedSession?.terms || []).map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                  {item.is_current ? " (Current)" : ""}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="transcript-actions">
          <button onClick={searchTranscript} disabled={!canSearch || searching || loadingOptions}>
            {searching ? "Searching..." : "Search"}
          </button>
          <button className="secondary" onClick={downloadTranscript} disabled={!canDownload || downloading}>
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
            <strong>Session:</strong>{" "}
            {context.selected_session?.session_name || context.selected_session?.academic_year || "-"}
          </p>
        </div>
      ) : null}

      {entries.map((entry) => (
        <div className="transcript-entry" key={`${entry.term?.id || "term"}-${entry.class?.id || "class"}`}>
          <div className="transcript-entry-head">
            <h3>{entry.term?.name || "Term Result"}</h3>
            <p>
              {entry.class?.name || "-"} | Average: {Number(entry.summary?.average_score || 0).toFixed(2)} | Grade:{" "}
              {entry.summary?.overall_grade || "-"}
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
