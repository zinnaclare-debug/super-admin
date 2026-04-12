import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import { getStoredUser } from "../../../utils/authStorage";
import dataReportsArt from "../../../assets/teacher-report/data-reports.svg";
import meetingArt from "../../../assets/teacher-report/meeting.svg";
import reportArt from "../../../assets/teacher-report/report.svg";
import { useGeneratedDocumentJob } from "../../../hooks/useGeneratedDocumentJob";
import "../../shared/PaymentsShowcase.css";
import "./TeacherReport.css";

const isMissingCurrentSessionTerm = (message = "") =>
  String(message).toLowerCase().includes("no current academic session/term configured");

const toCsvCell = (value) => {
  const raw = value == null ? "" : String(value);
  const escaped = raw.replace(/"/g, '""');
  return `"${escaped}"`;
};

const downloadCsv = (rows, context) => {
  if (!rows?.length) return;

  const headers = ["S/N", "Name", "Email", "A", "B", "C", "D", "E", "F", "Total", "Summary"];
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
        row.summary || "",
      ]
        .map(toCsvCell)
        .join(",")
    ),
  ];

  const csv = "\uFEFF" + lines.join("\n");
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const sessionName = context?.current_session?.session_name || context?.current_session?.academic_year || "session";
  const termName = context?.selected_term?.name || "term";
  const safe = (value) => String(value).replace(/[^\w\-]+/g, "_");
  const filename = `teacher_report_${safe(sessionName)}_${safe(termName)}.csv`;

  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
};

export default function TeacherReport() {
  const [rows, setRows] = useState([]);
  const [context, setContext] = useState(null);
  const [termId, setTermId] = useState("");
  const [loading, setLoading] = useState(true);
  const [sessionConfigError, setSessionConfigError] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const { job, setJob, requesting, setRequesting, downloading, isProcessing, downloadGeneratedFile } = useGeneratedDocumentJob();

  const schoolName = useMemo(() => {
    const u = getStoredUser();
    return u?.school?.name || u?.school_name || "School";
  }, []);

  const load = async (selectedTermId = "") => {
    setLoading(true);
    try {
      const params = {};
      if (selectedTermId) params.term_id = selectedTermId;
      const res = await api.get("/api/school-admin/reports/teacher", { params });
      setSessionConfigError("");
      setRows(res.data?.data || []);
      setContext(res.data?.context || null);
    } catch (e) {
      const loadedMessage = e?.response?.data?.message || "Failed to load teacher report.";
      if (isMissingCurrentSessionTerm(loadedMessage)) {
        setSessionConfigError(loadedMessage);
      } else {
        alert(loadedMessage);
      }
      setRows([]);
      setContext(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load(termId);
  }, [termId]);

  const selectedTermValue = termId || String(context?.selected_term?.id || "");

  const startPdfGeneration = async () => {
    if (rows.length === 0) return;

    setRequesting(true);
    setError("");
    setMessage("");
    try {
      const payload = {};
      if (selectedTermValue) {
        payload.term_id = Number(selectedTermValue);
      }
      const res = await api.post("/api/school-admin/reports/teacher/download-jobs", payload);
      setJob(res.data?.data || null);
      setMessage("Teacher report PDF generation started.");
    } catch (e) {
      setError(e?.response?.data?.message || e?.message || "Failed to start teacher report PDF generation.");
    } finally {
      setRequesting(false);
    }
  };

  const downloadPdf = async () => {
    try {
      await downloadGeneratedFile("teacher_report.pdf");
      setMessage("Teacher report downloaded successfully.");
    } catch (e) {
      setError(e?.message || "Failed to download teacher report PDF.");
    }
  };

  const handlePdfAction = async () => {
    if (job?.status === "completed") {
      await downloadPdf();
      return;
    }

    await startPdfGeneration();
  };

  const pdfActionLabel = (() => {
    if (requesting) return "Starting...";
    if (downloading) return "Downloading...";
    if (job?.status === "completed") return "Download PDF";
    if (job?.status === "failed") return "Retry PDF";
    if (job?.status === "processing") return "Processing PDF...";
    if (job?.status === "pending") return "Queued...";
    return "Generate PDF";
  })();

  return (
    <div className="payx-page payx-page--admin teacher-report-page">
      <section className="payx-hero teacher-report-hero">
        <div>
          <span className="payx-pill">School Admin Teacher Report</span>
          <h2 className="payx-title">Track grading coverage and teacher performance from one cleaner report view.</h2>
          <p className="payx-subtitle">
            Review teacher grading counts by term, export the report as CSV or PDF, and keep the whole workflow inside the same polished reporting layout.
          </p>
          <div className="payx-meta">
            <span>{schoolName}</span>
            <span>{context?.current_session?.session_name || context?.current_session?.academic_year || "Session -"}</span>
            <span>{rows.length} teacher{rows.length === 1 ? "" : "s"}</span>
          </div>
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main teacher-report-art--main">
            <img src={dataReportsArt} alt="" />
          </div>
          <div className="payx-art payx-art--card teacher-report-art--card">
            <img src={meetingArt} alt="" />
          </div>
          <div className="payx-art payx-art--online teacher-report-art--online">
            <img src={reportArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel teacher-report-panel">
        <div className="payx-card teacher-report-card">
          {sessionConfigError ? <p className="teacher-report-warning">{sessionConfigError}</p> : null}

          <div className="teacher-report-grid">
            <div className="teacher-report-field">
              <label htmlFor="teacher-report-term">Term</label>
              <select
                id="teacher-report-term"
                value={selectedTermValue}
                onChange={(e) => {
                  setTermId(e.target.value);
                  setJob(null);
                  setError("");
                  setMessage("");
                }}
              >
                {(context?.terms || []).map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name} {t.is_current ? "(Current)" : ""}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="teacher-report-actions">
            <button className="payx-btn payx-btn--soft" onClick={() => downloadCsv(rows, context)} disabled={loading || rows.length === 0}>
              Download CSV
            </button>
            <button
              className="payx-btn teacher-report-download-btn"
              onClick={handlePdfAction}
              disabled={loading || rows.length === 0 || requesting || downloading || isProcessing}
            >
              {pdfActionLabel}
            </button>
          </div>

          <p className="teacher-report-meta">
            {schoolName} | Session: {context?.current_session?.session_name || context?.current_session?.academic_year || "-"} | Term: {context?.selected_term?.name || "-"}
          </p>
          {job?.status === "pending" || job?.status === "processing" ? (
            <p className="teacher-report-message">
              {job.status === "processing" ? "Teacher report PDF is being prepared for this school." : "Teacher report PDF request is queued."}
            </p>
          ) : null}
          {job?.status === "failed" ? <p className="teacher-report-error">{job.error_message || "Teacher report PDF generation failed."}</p> : null}
          {error ? <p className="teacher-report-error">{error}</p> : null}
          {message ? <p className="teacher-report-message">{message}</p> : null}
        </div>

        <div className="payx-card teacher-report-table-card">
          {loading ? (
            <p className="teacher-report-loading">Loading report...</p>
          ) : (
            <div className="teacher-report-table-wrap">
              <table className="teacher-report-table">
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
                    <th className="teacher-report-summary-head">Summary</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.teacher_user_id}>
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
                      <td>
                        <div className="teacher-report-summary-box">{row.summary || "Completed"}</div>
                      </td>
                    </tr>
                  ))}
                  {rows.length === 0 && (
                    <tr>
                      <td colSpan="11">No teacher grading data for this term.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </section>
    </div>
  );
}
