import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import examPrepArt from "../../../assets/results/exam-prep.svg";
import onlineSurveyArt from "../../../assets/results/online-survey.svg";
import certificateArt from "../../../assets/results/certificate.svg";
import "../../shared/ResultsShowcase.css";

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

const asDash = (value) => (value == null || value === "" ? "-" : value);

const formatMaybeNumber = (value, digits = 2) => {
  if (value == null || value === "") return "-";
  const num = Number(value);
  if (!Number.isFinite(num)) return "-";
  return num.toFixed(digits);
};

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
    return terms.find((item) => String(item.id) === String(termId)) || terms.find((item) => item.is_current) || terms[0];
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
      const preferredSession = loadedSessions.find((item) => item.id === preferredSessionId) || loadedSessions[0];
      setSessionId(String(preferredSession.id));
      const preferredTerm = (preferredSession.terms || []).find((item) => item.is_current) || (preferredSession.terms || [])[0];
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
    const params = { student: studentSearch.trim() };
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
    <div className="rs-page rs-page--staff">
      <section className="rs-hero">
        <div>
          <span className="rs-pill">School Admin Results Desk</span>
          <h2 className="rs-title">Search a student once, then review or download the result cleanly.</h2>
          <p className="rs-subtitle">
            Use the same polished results workspace style already familiar on the student and staff sides.
          </p>
          <div className="rs-meta">
            <span>{loadingOptions ? "Loading filters..." : `${sessions.length} session${sessions.length === 1 ? "" : "s"}`}</span>
            <span>{selectedTerm?.name || "Select a term"}</span>
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
        <div className="rs-cards" style={{ marginBottom: 14 }}>
          <div className="rs-card-btn" style={{ cursor: "default" }}>
            <h3 className="rs-card-title">Student Email or Name</h3>
            <input
              type="text"
              placeholder="student@example.com or full name"
              value={studentSearch}
              onChange={(e) => setStudentSearch(e.target.value)}
              style={{ marginTop: 10, width: "100%", padding: 10, borderRadius: 10, border: "1px solid #cbd5e1", boxSizing: "border-box" }}
            />
          </div>

          <div className="rs-card-btn" style={{ cursor: "default" }}>
            <h3 className="rs-card-title">Academic Session</h3>
            <select
              value={sessionId}
              onChange={(e) => {
                const value = e.target.value;
                setSessionId(value);
                const next = sessions.find((item) => String(item.id) === value) || null;
                const preferred = (next?.terms || []).find((item) => item.is_current) || (next?.terms || [])[0];
                setTermId(preferred ? String(preferred.id) : "");
              }}
              disabled={loadingOptions}
              style={{ marginTop: 10, width: "100%", padding: 10, borderRadius: 10, border: "1px solid #cbd5e1", boxSizing: "border-box" }}
            >
              {(sessions || []).map((item) => (
                <option key={item.id} value={item.id}>
                  {item.session_name || item.academic_year}
                  {item.is_current ? " (Current)" : ""}
                </option>
              ))}
            </select>
          </div>

          <div className="rs-card-btn" style={{ cursor: "default" }}>
            <h3 className="rs-card-title">Term</h3>
            <select
              value={String(selectedTerm?.id || "")}
              onChange={(e) => setTermId(e.target.value)}
              disabled={loadingOptions}
              style={{ marginTop: 10, width: "100%", padding: 10, borderRadius: 10, border: "1px solid #cbd5e1", boxSizing: "border-box" }}
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

        <div className="rs-results-head" style={{ marginTop: 0 }}>
          <h3 className="rs-results-title">Student Result Search</h3>
          <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
            <button className="rs-btn" onClick={searchResult} disabled={!canDownload || searching || loadingOptions}>
              {searching ? "Searching..." : "Search"}
            </button>
            <button className="rs-btn" onClick={downloadPdf} disabled={!canDownload || downloading || loadingOptions}>
              {downloading ? "Downloading..." : "Download Result"}
            </button>
          </div>
        </div>

        {loadingOptions ? <p className="rs-state rs-state--loading" style={{ marginTop: 10 }}>Loading filters...</p> : null}
        {error ? <p className="rs-state rs-state--error" style={{ marginTop: 10 }}>{error}</p> : null}
        {message ? <p className="rs-state rs-state--empty" style={{ marginTop: 10 }}>{message}</p> : null}

        {context?.student ? (
          <div className="rs-cards" style={{ marginTop: 16 }}>
            <div className="rs-card-btn" style={{ cursor: "default" }}>
              <h3 className="rs-card-title">Student</h3>
              <p className="rs-card-meta">{context.student.name} ({context.student.email})</p>
            </div>
            <div className="rs-card-btn" style={{ cursor: "default" }}>
              <h3 className="rs-card-title">Session</h3>
              <p className="rs-card-meta">{context.selected_session?.session_name || context.selected_session?.academic_year || "-"}</p>
            </div>
          </div>
        ) : null}

        {entry ? (
          <>
            <div className="rs-results-head">
              <h3 className="rs-results-title">{entry.term?.name || "Term Result"}</h3>
              <span className="rs-meta" style={{ marginTop: 0 }}>
                <span>{entry.class?.name || "-"}</span>
                <span>Average: {formatMaybeNumber(entry.summary?.average_score)}</span>
                <span>Grade: {entry.summary?.overall_grade || "-"}</span>
              </span>
            </div>

            <div className="rs-cards" style={{ marginBottom: 14 }}>
              <div className="rs-card-btn" style={{ cursor: "default" }}>
                <h3 className="rs-card-title">Teacher Comment</h3>
                <p className="rs-card-meta">{entry.teacher_comment || "-"}</p>
              </div>

              {(entry.behaviour_traits || []).map((trait) => (
                <div key={trait.label} className="rs-card-btn" style={{ cursor: "default" }}>
                  <h3 className="rs-card-title">{trait.label}</h3>
                  <p className="rs-card-meta">{trait.value ?? 0}</p>
                </div>
              ))}
            </div>

            <div className="rs-table-wrap">
              <table className="rs-table">
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
                      <td>{asDash(row.ca)}</td>
                      <td>{asDash(row.exam)}</td>
                      <td>{asDash(row.total)}</td>
                      <td>{asDash(row.min_score)}</td>
                      <td>{asDash(row.max_score)}</td>
                      <td>{row.is_graded ? formatMaybeNumber(row.class_average) : "-"}</td>
                      <td>{row.position_label || "-"}</td>
                      <td>{asDash(row.grade)}</td>
                      <td>{asDash(row.remark)}</td>
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
          </>
        ) : null}
      </section>
    </div>
  );
}
