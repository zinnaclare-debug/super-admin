import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import { getStoredUser } from "../../../utils/authStorage";

const isMissingCurrentSessionTerm = (message = "") =>
  String(message).toLowerCase().includes("no current academic session/term configured");

const fileNameFromHeaders = (headers, fallback) => {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "student_report.pdf";
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
};

const messageFromBlobError = async (blob, fallback) => {
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
};

const toCsvCell = (value) => {
  const raw = value == null ? "" : String(value);
  const escaped = raw.replace(/"/g, '""');
  return `"${escaped}"`;
};

const downloadCsv = (rows, context) => {
  if (!rows?.length) return;

  const headers = [
    "S/N",
    "Name",
    "Email",
    "A",
    "B",
    "C",
    "D",
    "E",
    "F",
    "Total",
  ];

  const lines = [
    headers.map(toCsvCell).join(","),
    ...rows.map((row) =>
      [
        row.sn,
        row.name,
        row.email || "-",
        row.grades?.A ?? 0,
        row.grades?.B ?? 0,
        row.grades?.C ?? 0,
        row.grades?.D ?? 0,
        row.grades?.E ?? 0,
        row.grades?.F ?? 0,
        row.total_graded ?? 0,
      ]
        .map(toCsvCell)
        .join(",")
    ),
  ];

  const csv = "\uFEFF" + lines.join("\n");
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const sessionName =
    context?.current_session?.session_name ||
    context?.current_session?.academic_year ||
    "session";
  const termName = context?.selected_term?.name || "term";
  const safe = (value) => String(value).replace(/[^\w\-]+/g, "_");
  const filename = `student_report_${safe(sessionName)}_${safe(termName)}.csv`;

  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
};

export default function StudentReport() {
  const [rows, setRows] = useState([]);
  const [context, setContext] = useState(null);
  const [termId, setTermId] = useState("");
  const [loading, setLoading] = useState(true);
  const [downloadingPdf, setDownloadingPdf] = useState(false);
  const [sessionConfigError, setSessionConfigError] = useState("");
  const [resultSessions, setResultSessions] = useState([]);
  const [resultSessionId, setResultSessionId] = useState("");
  const [resultTermId, setResultTermId] = useState("");
  const [resultEmail, setResultEmail] = useState("");
  const [resultEntry, setResultEntry] = useState(null);
  const [resultContext, setResultContext] = useState(null);
  const [resultLoadingOptions, setResultLoadingOptions] = useState(true);
  const [resultSearching, setResultSearching] = useState(false);
  const [resultDownloading, setResultDownloading] = useState(false);
  const [resultError, setResultError] = useState("");
  const [resultMessage, setResultMessage] = useState("");

  const schoolName = useMemo(() => {
    const u = getStoredUser();
    return u?.school?.name || u?.school_name || "School";
  }, []);
  const selectedResultSession = useMemo(() => {
    if (resultSessions.length === 0) return null;
    return resultSessions.find((item) => String(item.id) === String(resultSessionId)) || resultSessions[0];
  }, [resultSessions, resultSessionId]);
  const selectedResultTerm = useMemo(() => {
    const terms = selectedResultSession?.terms || [];
    if (terms.length === 0) return null;
    return terms.find((item) => String(item.id) === String(resultTermId)) ||
      terms.find((item) => item.is_current) ||
      terms[0];
  }, [selectedResultSession, resultTermId]);

  const load = async (selectedTermId = "") => {
    setLoading(true);
    try {
      const params = {};
      if (selectedTermId) params.term_id = selectedTermId;
      const res = await api.get("/api/school-admin/reports/student", { params });
      setSessionConfigError("");
      setRows(res.data?.data || []);
      setContext(res.data?.context || null);
    } catch (e) {
      const message = e?.response?.data?.message || "Failed to load student report.";
      if (isMissingCurrentSessionTerm(message)) {
        setSessionConfigError(message);
      } else {
        alert(message);
      }
      setRows([]);
      setContext(null);
    } finally {
      setLoading(false);
    }
  };

  const loadResultOptions = async () => {
    setResultLoadingOptions(true);
    setResultError("");
    try {
      const res = await api.get("/api/school-admin/reports/student-result/options");
      const payload = res.data?.data || {};
      const sessions = payload.sessions || [];
      setResultSessions(sessions);

      if (sessions.length === 0) {
        setResultSessionId("");
        setResultTermId("");
        return;
      }

      const preferredSessionId = payload.selected_session_id || sessions[0].id;
      const session = sessions.find((item) => item.id === preferredSessionId) || sessions[0];
      setResultSessionId(String(session.id));
      const term = (session.terms || []).find((item) => item.is_current) || (session.terms || [])[0];
      setResultTermId(term ? String(term.id) : "");
    } catch (e) {
      setResultSessions([]);
      setResultSessionId("");
      setResultTermId("");
      setResultError(e?.response?.data?.message || "Failed to load student result filters.");
    } finally {
      setResultLoadingOptions(false);
    }
  };

  useEffect(() => {
    load(termId);
  }, [termId]);

  useEffect(() => {
    loadResultOptions();
  }, []);

  useEffect(() => {
    if (!selectedResultSession) {
      setResultTermId("");
      return;
    }
    const terms = selectedResultSession.terms || [];
    if (terms.length === 0) {
      setResultTermId("");
      return;
    }
    const exists = terms.some((item) => String(item.id) === String(resultTermId));
    if (!exists) {
      const preferred = terms.find((item) => item.is_current) || terms[0];
      setResultTermId(String(preferred.id));
    }
  }, [selectedResultSession, resultTermId]);

  const selectedTermValue = termId || String(context?.selected_term?.id || "");
  const selectedResultTermValue = String(selectedResultTerm?.id || "");

  const resultParams = () => {
    const params = {
      email: resultEmail.trim(),
    };
    if (selectedResultSession?.id) params.academic_session_id = selectedResultSession.id;
    if (selectedResultTerm?.id) params.term_id = selectedResultTerm.id;
    return params;
  };

  const searchStudentResult = async () => {
    if (!resultEmail.trim() || !selectedResultSession || !selectedResultTerm) {
      setResultError("Enter student email, session and term.");
      return;
    }

    setResultSearching(true);
    setResultError("");
    setResultMessage("");
    try {
      const res = await api.get("/api/school-admin/reports/student-result", {
        params: resultParams(),
      });
      const entries = res.data?.data || [];
      setResultEntry(entries[0] || null);
      setResultContext(res.data?.context || null);
      setResultMessage(res.data?.message || (entries.length === 0 ? "No result record found." : ""));
    } catch (e) {
      setResultEntry(null);
      setResultContext(null);
      setResultMessage("");
      setResultError(e?.response?.data?.message || "Failed to load student result.");
    } finally {
      setResultSearching(false);
    }
  };

  const downloadStudentResult = async () => {
    if (!resultEmail.trim() || !selectedResultSession || !selectedResultTerm) {
      setResultError("Enter student email, session and term.");
      return;
    }

    setResultDownloading(true);
    setResultError("");
    try {
      const res = await api.get("/api/school-admin/reports/student-result/download", {
        params: resultParams(),
        responseType: "blob",
      });

      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const message = await messageFromBlobError(res.data, "Failed to download student result PDF.");
        throw new Error(message);
      }

      const pdfBlob = res.data instanceof Blob
        ? res.data
        : new Blob([res.data], { type: "application/pdf" });
      const blobUrl = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, "student_result.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const message = await messageFromBlobError(e.response.data, "Failed to download student result PDF.");
        setResultError(message);
      } else {
        setResultError(e?.response?.data?.message || e?.message || "Failed to download student result PDF.");
      }
    } finally {
      setResultDownloading(false);
    }
  };

  const downloadPdf = async () => {
    if (rows.length === 0) return;

    setDownloadingPdf(true);
    try {
      const params = {};
      if (selectedTermValue) params.term_id = selectedTermValue;

      const res = await api.get("/api/school-admin/reports/student/download", {
        params,
        responseType: "blob",
      });

      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const message = await messageFromBlobError(res.data, "Failed to download student report PDF.");
        throw new Error(message);
      }

      const pdfBlob = res.data instanceof Blob
        ? res.data
        : new Blob([res.data], { type: "application/pdf" });
      const blobUrl = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, "student_report.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const message = await messageFromBlobError(e.response.data, "Failed to download student report PDF.");
        alert(message);
      } else {
        alert(e?.response?.data?.message || e?.message || "Failed to download student report PDF.");
      }
    } finally {
      setDownloadingPdf(false);
    }
  };

  return (
    <div>
      <div
        style={{
          marginTop: 8,
          padding: 12,
          borderRadius: 10,
          border: "1px solid #dbeafe",
          background: "#ffffff",
        }}
      >
        <h3 style={{ margin: 0 }}>Student Result Download</h3>
        <p style={{ margin: "6px 0 0", opacity: 0.8 }}>
          Search by student email, session and term, then download the full result sheet PDF.
        </p>

        <div
          style={{
            marginTop: 12,
            display: "grid",
            gap: 10,
            gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
          }}
        >
          <div>
            <label htmlFor="single-result-email">Student Email</label>
            <input
              id="single-result-email"
              type="email"
              placeholder="student@example.com"
              value={resultEmail}
              onChange={(e) => setResultEmail(e.target.value)}
              style={{ width: "100%", marginTop: 4 }}
            />
          </div>

          <div>
            <label htmlFor="single-result-session">Academic Session</label>
            <select
              id="single-result-session"
              value={resultSessionId}
              onChange={(e) => {
                const value = e.target.value;
                setResultSessionId(value);
                const session = resultSessions.find((item) => String(item.id) === value);
                const term = (session?.terms || []).find((item) => item.is_current) || (session?.terms || [])[0];
                setResultTermId(term ? String(term.id) : "");
              }}
              disabled={resultLoadingOptions}
              style={{ width: "100%", marginTop: 4 }}
            >
              {(resultSessions || []).map((item) => (
                <option key={item.id} value={item.id}>
                  {item.session_name || item.academic_year}
                  {item.is_current ? " (Current)" : ""}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="single-result-term">Term</label>
            <select
              id="single-result-term"
              value={selectedResultTermValue}
              onChange={(e) => setResultTermId(e.target.value)}
              disabled={resultLoadingOptions}
              style={{ width: "100%", marginTop: 4 }}
            >
              {(selectedResultSession?.terms || []).map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                  {item.is_current ? " (Current)" : ""}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div style={{ marginTop: 12 }}>
          <button
            onClick={searchStudentResult}
            disabled={resultSearching || resultLoadingOptions}
          >
            {resultSearching ? "Searching..." : "Search"}
          </button>
          <button
            onClick={downloadStudentResult}
            disabled={resultDownloading || resultLoadingOptions || !resultEmail.trim()}
            style={{ marginLeft: 10 }}
          >
            {resultDownloading ? "Downloading..." : "Download Result"}
          </button>
        </div>

        {resultError ? <p style={{ marginTop: 10, color: "#b91c1c" }}>{resultError}</p> : null}
        {resultMessage ? <p style={{ marginTop: 10 }}>{resultMessage}</p> : null}

        {resultContext?.student ? (
          <p style={{ marginTop: 10, opacity: 0.85 }}>
            Student: {resultContext.student.name} ({resultContext.student.email}) | Session:{" "}
            {resultContext.selected_session?.session_name || resultContext.selected_session?.academic_year || "-"}
          </p>
        ) : null}

        {resultEntry ? (
          <div style={{ marginTop: 12, overflowX: "auto" }}>
            <p style={{ margin: 0, fontWeight: 600 }}>
              {resultEntry.term?.name || "-"} | {resultEntry.class?.name || "-"} | Average:{" "}
              {Number(resultEntry.summary?.average_score || 0).toFixed(2)} | Grade:{" "}
              {resultEntry.summary?.overall_grade || "-"}
            </p>
            <table border="1" cellPadding="8" cellSpacing="0" width="100%" style={{ marginTop: 8, minWidth: 860 }}>
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
                {(resultEntry.rows || []).map((row) => (
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
                {(resultEntry.rows || []).length === 0 ? (
                  <tr>
                    <td colSpan="10">No result records found for this student.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        ) : null}
      </div>

      <h3 style={{ marginTop: 20, marginBottom: 0 }}>Student Grade Summary</h3>
      {sessionConfigError ? (
        <p style={{ marginTop: 10, color: "#b45309" }}>{sessionConfigError}</p>
      ) : null}
      <p style={{ marginTop: 6, opacity: 0.8 }}>
        {schoolName} | Session:{" "}
        {context?.current_session?.session_name || context?.current_session?.academic_year || "-"}
      </p>

      <div style={{ marginTop: 12 }}>
        <label htmlFor="student-report-term">Term: </label>
        <select
          id="student-report-term"
          value={selectedTermValue}
          onChange={(e) => setTermId(e.target.value)}
        >
          {(context?.terms || []).map((t) => (
            <option key={t.id} value={t.id}>
              {t.name} {t.is_current ? "(Current)" : ""}
            </option>
          ))}
        </select>
        <button
          onClick={() => downloadCsv(rows, context)}
          disabled={loading || rows.length === 0}
          style={{ marginLeft: 10 }}
        >
          Download CSV
        </button>
        <button
          onClick={downloadPdf}
          disabled={loading || downloadingPdf || rows.length === 0}
          style={{ marginLeft: 10 }}
        >
          {downloadingPdf ? "Downloading PDF..." : "Download PDF"}
        </button>
      </div>

      {loading ? (
        <p style={{ marginTop: 12 }}>Loading report...</p>
      ) : (
        <table border="1" cellPadding="10" cellSpacing="0" width="100%" style={{ marginTop: 12 }}>
          <thead>
            <tr>
              <th style={{ width: 70 }}>S/N</th>
              <th>Name</th>
              <th>Email</th>
              <th>A</th>
              <th>B</th>
              <th>C</th>
              <th>D</th>
              <th>E</th>
              <th>F</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.student_id}>
                <td>{row.sn}</td>
                <td>{row.name}</td>
                <td>{row.email || "-"}</td>
                <td>{row.grades?.A ?? 0}</td>
                <td>{row.grades?.B ?? 0}</td>
                <td>{row.grades?.C ?? 0}</td>
                <td>{row.grades?.D ?? 0}</td>
                <td>{row.grades?.E ?? 0}</td>
                <td>{row.grades?.F ?? 0}</td>
                <td>{row.total_graded ?? 0}</td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr>
                <td colSpan="10">No student grading data for this term.</td>
              </tr>
            )}
          </tbody>
        </table>
      )}
    </div>
  );
}
