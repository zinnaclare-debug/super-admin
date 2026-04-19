import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import { getStoredUser } from "../../../utils/authStorage";
import allTheDataArt from "../../../assets/student-report/all-the-data.svg";
import fileAnalysisArt from "../../../assets/student-report/file-analysis.svg";
import visualDataArt from "../../../assets/student-report/visual-data.svg";
import { useGeneratedDocumentJob } from "../../../hooks/useGeneratedDocumentJob";
import "../../shared/PaymentsShowcase.css";
import "./StudentReport.css";

const STUDENT_REPORT_PAGE_SIZE = 50;

const isMissingCurrentSessionTerm = (message = "") =>
  String(message).toLowerCase().includes("no current academic session/term configured");

const toCsvCell = (value) => {
  const raw = value == null ? "" : String(value);
  const escaped = raw.replace(/"/g, '""');
  return `"${escaped}"`;
};

const asDash = (value) => (value == null || value === "" ? "-" : value);

const formatMaybeNumber = (value, digits = 2) => {
  if (value == null || value === "") return "-";
  const num = Number(value);
  if (!Number.isFinite(num)) return "-";
  return num.toFixed(digits);
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
    "Teacher Comment",
    "Behaviour Rating",
  ];

  const lines = [
    headers.map(toCsvCell).join(","),
    ...rows.map((row) =>
      [
        row.sn,
        row.name,
        row.email || "-",
        asDash(row.grades?.A),
        asDash(row.grades?.B),
        asDash(row.grades?.C),
        asDash(row.grades?.D),
        asDash(row.grades?.E),
        asDash(row.grades?.F),
        asDash(row.total_graded),
        row.teacher_comment || "-",
        row.behaviour_rating || "-",
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
  const [classId, setClassId] = useState("");
  const [loading, setLoading] = useState(true);
  const [sessionConfigError, setSessionConfigError] = useState("");
  const [resultSessions, setResultSessions] = useState([]);
  const [resultSessionId, setResultSessionId] = useState("");
  const [resultTermId, setResultTermId] = useState("");
  const [resultEmail, setResultEmail] = useState("");
  const [resultEntry, setResultEntry] = useState(null);
  const [resultContext, setResultContext] = useState(null);
  const [resultLoadingOptions, setResultLoadingOptions] = useState(true);
  const [resultSearching, setResultSearching] = useState(false);
  const [resultError, setResultError] = useState("");
  const [resultMessage, setResultMessage] = useState("");
  const [reportError, setReportError] = useState("");
  const [summaryPage, setSummaryPage] = useState(1);
  const {
    job: resultJob,
    setJob: setResultJob,
    requesting: resultRequesting,
    setRequesting: setResultRequesting,
    downloading: resultDownloading,
    isProcessing: resultIsProcessing,
    downloadGeneratedFile: downloadResultGeneratedFile,
  } = useGeneratedDocumentJob();
  const {
    job: reportJob,
    setJob: setReportJob,
    requesting: reportRequesting,
    setRequesting: setReportRequesting,
    downloading: reportDownloading,
    isProcessing: reportIsProcessing,
    downloadGeneratedFile: downloadReportGeneratedFile,
  } = useGeneratedDocumentJob();

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
    return (
      terms.find((item) => String(item.id) === String(resultTermId)) ||
      terms.find((item) => item.is_current) ||
      terms[0]
    );
  }, [selectedResultSession, resultTermId]);

  const selectedTermValue = termId || String(context?.selected_term?.id || "");
  const selectedClassValue = classId || String(context?.selected_class_id || "");
  const selectedResultTermValue = String(selectedResultTerm?.id || "");
  const canResultDownload = Boolean(resultEmail.trim()) && Boolean(selectedResultSession) && Boolean(selectedResultTerm);
  const totalSummaryPages = Math.max(1, Math.ceil(rows.length / STUDENT_REPORT_PAGE_SIZE));

  const paginatedRows = useMemo(() => {
    const start = (summaryPage - 1) * STUDENT_REPORT_PAGE_SIZE;
    return rows.slice(start, start + STUDENT_REPORT_PAGE_SIZE);
  }, [rows, summaryPage]);

  const load = async (selectedTermId = "", selectedClassId = "") => {
    setLoading(true);
    try {
      const params = {};
      if (selectedTermId) params.term_id = selectedTermId;
      if (selectedClassId) params.class_id = selectedClassId;
      const res = await api.get("/api/school-admin/reports/student", { params });
      setSessionConfigError("");
      setRows(res.data?.data || []);
      setContext(res.data?.context || null);
      setSummaryPage(1);
    } catch (e) {
      const loadedMessage = e?.response?.data?.message || "Failed to load student report.";
      if (isMissingCurrentSessionTerm(loadedMessage)) {
        setSessionConfigError(loadedMessage);
      } else {
        alert(loadedMessage);
      }
      setRows([]);
      setContext(null);
      setSummaryPage(1);
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
    load(termId, classId);
  }, [termId, classId]);

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

  const resultParams = () => {
    const params = {
      email: resultEmail.trim(),
    };
    if (selectedResultSession?.id) params.academic_session_id = selectedResultSession.id;
    if (selectedResultTerm?.id) params.term_id = selectedResultTerm.id;
    return params;
  };

  const searchStudentResult = async () => {
    if (!canResultDownload) {
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

  const startStudentResultPdf = async () => {
    if (!canResultDownload) {
      setResultError("Enter student email, session and term.");
      return;
    }

    setResultRequesting(true);
    setResultError("");
    try {
      const res = await api.post("/api/school-admin/reports/student-result/download-jobs", resultParams());
      setResultJob(res.data?.data || null);
    } catch (e) {
      setResultError(e?.response?.data?.message || e?.message || "Failed to start student result PDF generation.");
    } finally {
      setResultRequesting(false);
    }
  };

  const downloadStudentResult = async () => {
    if (!canResultDownload) {
      setResultError("Enter student email, session and term.");
      return;
    }

    try {
      await downloadResultGeneratedFile("student_result.pdf");
    } catch (e) {
      setResultError(e?.message || "Failed to download student result PDF.");
    }
  };

  const handleStudentResultPdfAction = async () => {
    if (resultIsProcessing || resultRequesting) {
      return;
    }

    await startStudentResultPdf();
  };

  const studentResultActionLabel = (() => {
    if (resultRequesting) return "Starting...";
    if (resultDownloading) return "Downloading...";
    if (resultJob?.status === "completed") return "Generate Result PDF";
    if (resultJob?.status === "failed") return "Retry Result PDF";
    if (resultJob?.status === "processing") return "Processing Result...";
    if (resultJob?.status === "pending") return "Queued...";
    return "Generate Result PDF";
  })();

  const startStudentReportPdf = async () => {
    if (rows.length === 0) return;

    setReportRequesting(true);
    setReportError("");
    try {
      const payload = {};
      if (selectedTermValue) {
        payload.term_id = Number(selectedTermValue);
      }
      if (selectedClassValue) {
        payload.class_id = Number(selectedClassValue);
      }

      const res = await api.post("/api/school-admin/reports/student/download-jobs", payload);
      setReportJob(res.data?.data || null);
    } catch (e) {
      setReportError(e?.response?.data?.message || e?.message || "Failed to start student report PDF generation.");
    } finally {
      setReportRequesting(false);
    }
  };

  const downloadPdf = async () => {
    if (rows.length === 0) return;

    try {
      await downloadReportGeneratedFile("student_report.pdf");
    } catch (e) {
      setReportError(e?.message || "Failed to download student report PDF.");
    }
  };

  const handleStudentReportPdfAction = async () => {
    if (reportJob?.status === "completed") {
      await downloadPdf();
      return;
    }

    await startStudentReportPdf();
  };

  const studentReportActionLabel = (() => {
    if (reportRequesting) return "Starting...";
    if (reportDownloading) return "Downloading...";
    if (reportJob?.status === "completed") return "Download PDF";
    if (reportJob?.status === "failed") return "Retry PDF";
    if (reportJob?.status === "processing") return "Processing PDF...";
    if (reportJob?.status === "pending") return "Queued...";
    return "Generate PDF";
  })();

  return (
    <div className="payx-page payx-page--admin student-report-page">
      <section className="payx-hero student-report-hero">
        <div>
          <span className="payx-pill">School Admin Student Report</span>
          <h2 className="payx-title">Search individual results and review class-wide student grading from one cleaner workspace.</h2>
          <p className="payx-subtitle">
            Download a single student result sheet by email, then switch to the broader student grade summary with the same polished reporting layout.
          </p>
          <div className="payx-meta">
            <span>{schoolName}</span>
            <span>{context?.current_session?.session_name || context?.current_session?.academic_year || "Session -"}</span>
            <span>{rows.length} student{rows.length === 1 ? "" : "s"}</span>
          </div>
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main student-report-art--main">
            <img src={allTheDataArt} alt="" />
          </div>
          <div className="payx-art payx-art--card student-report-art--card">
            <img src={fileAnalysisArt} alt="" />
          </div>
          <div className="payx-art payx-art--online student-report-art--online">
            <img src={visualDataArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel student-report-panel">
        <div className="payx-card student-report-card">
          <div className="student-report-card-head">
            <h3>Student Result Download</h3>
            <p>Search by student email, session, and term, then download the full result sheet PDF.</p>
          </div>

          <div className="student-report-grid">
            <div className="student-report-field">
              <label htmlFor="single-result-email">Student Email</label>
              <input
                id="single-result-email"
                type="email"
                placeholder="student@example.com"
                value={resultEmail}
                onChange={(e) => {
                  setResultEmail(e.target.value);
                  setResultJob(null);
                  setResultError("");
                  setResultMessage("");
                }}
              />
            </div>

            <div className="student-report-field">
              <label htmlFor="single-result-session">Academic Session</label>
              <select
                id="single-result-session"
                value={resultSessionId}
                onChange={(e) => {
                  const value = e.target.value;
                  setResultJob(null);
                  setResultError("");
                  setResultMessage("");
                  setResultSessionId(value);
                  const session = resultSessions.find((item) => String(item.id) === value);
                  const term = (session?.terms || []).find((item) => item.is_current) || (session?.terms || [])[0];
                  setResultTermId(term ? String(term.id) : "");
                }}
                disabled={resultLoadingOptions}
              >
                {(resultSessions || []).map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.session_name || item.academic_year}
                    {item.is_current ? " (Current)" : ""}
                  </option>
                ))}
              </select>
            </div>

            <div className="student-report-field">
              <label htmlFor="single-result-term">Term</label>
              <select
                id="single-result-term"
                value={selectedResultTermValue}
                onChange={(e) => {
                  setResultTermId(e.target.value);
                  setResultJob(null);
                  setResultError("");
                  setResultMessage("");
                }}
                disabled={resultLoadingOptions}
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

          <div className="student-report-actions">
            <button className="payx-btn" onClick={searchStudentResult} disabled={resultSearching || resultLoadingOptions}>
              {resultSearching ? "Searching..." : "Search"}
            </button>
            <button
              className="payx-btn student-report-download-btn"
              onClick={handleStudentResultPdfAction}
              disabled={!canResultDownload || resultLoadingOptions || resultRequesting || resultDownloading || resultIsProcessing}
            >
              {studentResultActionLabel}
            </button>
            {resultJob?.status === "completed" ? (
              <button
                className="payx-btn student-report-download-btn"
                onClick={downloadStudentResult}
                disabled={resultDownloading || resultRequesting}
              >
                {resultDownloading ? "Downloading..." : "Download Current PDF"}
              </button>
            ) : null}
          </div>

          {resultJob?.status === "failed" ? <p className="student-report-error">{resultJob.error_message || "Student result PDF generation failed."}</p> : null}
          {resultError ? <p className="student-report-error">{resultError}</p> : null}
          {resultMessage ? <p className="student-report-message">{resultMessage}</p> : null}

          {resultContext?.student ? (
            <p className="student-report-meta">
              Student: {resultContext.student.name} ({resultContext.student.email}) | Session: {resultContext.selected_session?.session_name || resultContext.selected_session?.academic_year || "-"}
            </p>
          ) : null}

          {resultEntry ? (
            <div className="student-report-result-card">
              <p className="student-report-result-head">
                {resultEntry.term?.name || "-"} | {resultEntry.class?.name || "-"} | Average: {formatMaybeNumber(resultEntry.summary?.average_score)} | Grade: {asDash(resultEntry.summary?.overall_grade)}
              </p>

              <div className="student-report-note-block">
                <div className="student-report-note-label">Teacher Comment</div>
                <div className="student-report-note-box student-report-note-box--tall">{resultEntry.teacher_comment || "-"}</div>
              </div>

              {(resultEntry.behaviour_traits || []).length > 0 ? (
                <div className="student-report-trait-grid">
                  {(resultEntry.behaviour_traits || []).map((trait) => (
                    <div key={trait.label} className="student-report-trait-card">
                      <div className="student-report-trait-label">{trait.label}</div>
                      <div className="student-report-trait-value">{trait.value ?? 0}</div>
                    </div>
                  ))}
                </div>
              ) : null}

              <div className="student-report-table-wrap">
                <table className="student-report-table student-report-table--result">
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
                    {(resultEntry.rows || []).length === 0 ? (
                      <tr>
                        <td colSpan="10">No result records found for this student.</td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>
          ) : null}
        </div>

        <div className="payx-card student-report-card">
          <div className="student-report-card-head">
            <h3>Student Grade Summary</h3>
            <p>Review term-by-term grading coverage by class, then export the broader student report as CSV or PDF.</p>
          </div>

          {sessionConfigError ? <p className="student-report-warning">{sessionConfigError}</p> : null}

          <p className="student-report-meta">
            {schoolName} | Session: {context?.current_session?.session_name || context?.current_session?.academic_year || "-"}
          </p>

          <div className="student-report-grid">
            <div className="student-report-field">
              <label htmlFor="student-report-term">Term</label>
              <select
                id="student-report-term"
                value={selectedTermValue}
                onChange={(e) => {
                  setTermId(e.target.value);
                  setReportJob(null);
                  setReportError("");
                }}
              >
                {(context?.terms || []).map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name} {t.is_current ? "(Current)" : ""}
                  </option>
                ))}
              </select>
            </div>

            <div className="student-report-field">
              <label htmlFor="student-report-class">Class</label>
              <select
                id="student-report-class"
                value={selectedClassValue}
                onChange={(e) => {
                  setClassId(e.target.value);
                  setReportJob(null);
                  setReportError("");
                }}
              >
                <option value="">All Classes</option>
                {(context?.classes || []).map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="student-report-actions">
            <button className="payx-btn payx-btn--soft" onClick={() => downloadCsv(rows, context)} disabled={loading || rows.length === 0}>
              Download CSV
            </button>
            <button
              className="payx-btn student-report-download-btn"
              onClick={handleStudentReportPdfAction}
              disabled={loading || rows.length === 0 || reportRequesting || reportDownloading || reportIsProcessing}
            >
              {studentReportActionLabel}
            </button>
          </div>
          {reportJob?.status === "failed" ? <p className="student-report-error">{reportJob.error_message || "Student report PDF generation failed."}</p> : null}
          {reportError ? <p className="student-report-error">{reportError}</p> : null}

          {loading ? (
            <p className="student-report-loading">Loading report...</p>
          ) : (
            <>
              <div className="student-report-table-wrap">
                <table className="student-report-table">
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
                      <th className="student-report-summary-head">Teacher Comment</th>
                      <th className="student-report-summary-head">Behaviour Rating</th>
                    </tr>
                  </thead>
                  <tbody>
                    {paginatedRows.map((row) => (
                      <tr key={row.student_id}>
                        <td>{row.sn}</td>
                        <td>{row.name}</td>
                        <td>{row.email || "-"}</td>
                        <td>{asDash(row.grades?.A)}</td>
                        <td>{asDash(row.grades?.B)}</td>
                        <td>{asDash(row.grades?.C)}</td>
                        <td>{asDash(row.grades?.D)}</td>
                        <td>{asDash(row.grades?.E)}</td>
                        <td>{asDash(row.grades?.F)}</td>
                        <td>{asDash(row.total_graded)}</td>
                        <td><div className="student-report-note-box">{row.teacher_comment || "-"}</div></td>
                        <td><div className="student-report-note-box">{row.behaviour_rating || "-"}</div></td>
                      </tr>
                    ))}
                    {rows.length === 0 && (
                      <tr>
                        <td colSpan="12">No student grading data for this term.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
              {rows.length > 0 ? (
                <div className="student-report-pagination">
                  <span className="student-report-pagination__status">Page {summaryPage} of {totalSummaryPages}</span>
                  <div className="student-report-pagination__actions">
                    <button
                      type="button"
                      className="student-report-pagination__link"
                      onClick={() => setSummaryPage((page) => Math.min(totalSummaryPages, page + 1))}
                      disabled={summaryPage === totalSummaryPages}
                    >
                      Next
                    </button>
                    <span className="student-report-pagination__divider">|</span>
                    <button
                      type="button"
                      className="student-report-pagination__link"
                      onClick={() => setSummaryPage((page) => Math.max(1, page - 1))}
                      disabled={summaryPage === 1}
                    >
                      Previous
                    </button>
                  </div>
                </div>
              ) : null}
            </>
          )}
        </div>
      </section>
    </div>
  );
}
