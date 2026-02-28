import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import "./StudentResult.css";

function fileNameFromHeaders(headers, fallback) {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "student_result.pdf";
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

export default function StudentResult() {
  const [sessions, setSessions] = useState([]);
  const [studentSearch, setStudentSearch] = useState("");
  const [sessionId, setSessionId] = useState("");
  const [termId, setTermId] = useState("");
  const [entry, setEntry] = useState(null);
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
    return terms.find((item) => String(item.id) === String(termId)) ||
      terms.find((item) => item.is_current) ||
      terms[0];
  }, [selectedSession, termId]);

  const canDownload = studentSearch.trim().length > 0 && selectedSession && selectedTerm;

  const loadOptions = async () => {
    setLoadingOptions(true);
    setError("");
    try {
      const res = await api.get("/api/school-admin/reports/student-result/options");
      const payload = res.data?.data || {};
      const loadedSessions = payload.sessions || [];
      setSessions(loadedSessions);

      if (loadedSessions.length === 0) {
        setSessionId("");
        setTermId("");
        return;
      }

      const preferredSessionId = payload.selected_session_id || loadedSessions[0].id;
      const preferredSession =
        loadedSessions.find((item) => item.id === preferredSessionId) || loadedSessions[0];
      setSessionId(String(preferredSession.id));
      const preferredTerm = (preferredSession.terms || []).find((item) => item.is_current) ||
        (preferredSession.terms || [])[0];
      setTermId(preferredTerm ? String(preferredTerm.id) : "");
    } catch (e) {
      setError(e?.response?.data?.message || "Failed to load student result filters.");
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
    if (!selectedSession) {
      setTermId("");
      return;
    }

    const terms = selectedSession.terms || [];
    if (terms.length === 0) {
      setTermId("");
      return;
    }

    const hasCurrent = terms.some((item) => String(item.id) === String(termId));
    if (!hasCurrent) {
      const preferred = terms.find((item) => item.is_current) || terms[0];
      setTermId(String(preferred.id));
    }
  }, [selectedSession, termId]);

  const requestParams = () => {
    const params = {
      student: studentSearch.trim(),
    };
    if (selectedSession?.id) params.academic_session_id = selectedSession.id;
    if (selectedTerm?.id) params.term_id = selectedTerm.id;
    return params;
  };

  const searchResult = async () => {
    if (!canDownload) return;
    setSearching(true);
    setError("");
    setMessage("");
    try {
      const res = await api.get("/api/school-admin/reports/student-result", {
        params: requestParams(),
      });
      const data = res.data?.data || [];
      setEntry(data[0] || null);
      setContext(res.data?.context || null);
      setMessage(res.data?.message || (data.length === 0 ? "No result record found." : ""));
    } catch (e) {
      setEntry(null);
      setContext(null);
      setMessage("");
      setError(e?.response?.data?.message || "Failed to load student result.");
    } finally {
      setSearching(false);
    }
  };

  const downloadPdf = async () => {
    if (!canDownload) return;
    setDownloading(true);
    setError("");
    setMessage("");
    try {
      const res = await api.get("/api/school-admin/reports/student-result/download", {
        params: requestParams(),
        responseType: "blob",
      });

      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const msg = await messageFromBlobError(res.data, "Failed to download student result PDF.");
        throw new Error(msg);
      }

      const pdfBlob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const blobUrl = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, "student_result.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
      setMessage("Student result downloaded successfully.");
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const msg = await messageFromBlobError(e.response.data, "Failed to download student result PDF.");
        setError(msg);
      } else {
        setError(e?.response?.data?.message || e?.message || "Failed to download student result PDF.");
      }
    } finally {
      setDownloading(false);
    }
  };

  return (
    <div className="student-result-page">
      <div className="student-result-card">
        <div className="student-result-grid">
          <div className="student-result-field">
            <label htmlFor="student-result-email">Student Email or Name</label>
            <input
              id="student-result-email"
              type="text"
              placeholder="student@example.com or full name"
              value={studentSearch}
              onChange={(e) => setStudentSearch(e.target.value)}
            />
          </div>

          <div className="student-result-field">
            <label htmlFor="student-result-session">Academic Session</label>
            <select
              id="student-result-session"
              value={sessionId}
              onChange={(e) => {
                const value = e.target.value;
                setSessionId(value);
                const next = sessions.find((item) => String(item.id) === value) || null;
                const preferred = (next?.terms || []).find((item) => item.is_current) || (next?.terms || [])[0];
                setTermId(preferred ? String(preferred.id) : "");
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

          <div className="student-result-field">
            <label htmlFor="student-result-term">Term</label>
            <select
              id="student-result-term"
              value={String(selectedTerm?.id || "")}
              onChange={(e) => setTermId(e.target.value)}
              disabled={loadingOptions}
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

        <div className="student-result-actions">
          <button onClick={searchResult} disabled={!canDownload || searching || loadingOptions}>
            {searching ? "Searching..." : "Search"}
          </button>
          <button className="secondary" onClick={downloadPdf} disabled={!canDownload || downloading || loadingOptions}>
            {downloading ? "Downloading..." : "Download Result"}
          </button>
        </div>

        {error ? <p className="student-result-error">{error}</p> : null}
        {message ? <p className="student-result-message">{message}</p> : null}
      </div>

      {context?.student ? (
        <div className="student-result-meta">
          <p>
            <strong>Student:</strong> {context.student.name} ({context.student.email})
          </p>
          <p>
            <strong>Session:</strong>{" "}
            {context.selected_session?.session_name || context.selected_session?.academic_year || "-"}
          </p>
        </div>
      ) : null}

      {entry ? (
        <div className="student-result-entry">
          <div className="student-result-entry-head">
            <h3>{entry.term?.name || "Term Result"}</h3>
            <p>
              {entry.class?.name || "-"} | Average:{" "}
              {entry.summary?.average_score === null || entry.summary?.average_score === undefined
                ? "-"
                : Number(entry.summary?.average_score || 0).toFixed(2)}{" "}
              | Grade:{" "}
              {entry.summary?.overall_grade || "-"}
            </p>
          </div>

          <div className="student-result-table-wrap">
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
                    <td colSpan="10">No records found for this student and term.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}
    </div>
  );
}
