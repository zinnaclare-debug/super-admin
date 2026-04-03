import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import { getStoredUser } from "../../../utils/authStorage";
import dataReportsArt from "../../../assets/teacher-report/data-reports.svg";
import meetingArt from "../../../assets/teacher-report/meeting.svg";
import reportArt from "../../../assets/teacher-report/report.svg";
import "../../shared/PaymentsShowcase.css";
import "./TeacherReport.css";

const isMissingCurrentSessionTerm = (message = "") =>
  String(message).toLowerCase().includes("no current academic session/term configured");

const fileNameFromHeaders = (headers, fallback) => {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "teacher_report.pdf";
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
  const [downloadingPdf, setDownloadingPdf] = useState(false);
  const [sessionConfigError, setSessionConfigError] = useState("");

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
      const message = e?.response?.data?.message || "Failed to load teacher report.";
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

  useEffect(() => {
    load(termId);
  }, [termId]);

  const selectedTermValue = termId || String(context?.selected_term?.id || "");

  const downloadPdf = async () => {
    if (rows.length === 0) return;

    setDownloadingPdf(true);
    try {
      const params = {};
      if (selectedTermValue) params.term_id = selectedTermValue;

      const res = await api.get("/api/school-admin/reports/teacher/download", {
        params,
        responseType: "blob",
      });

      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const message = await messageFromBlobError(res.data, "Failed to download teacher report PDF.");
        throw new Error(message);
      }

      const pdfBlob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const blobUrl = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, "teacher_report.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const message = await messageFromBlobError(e.response.data, "Failed to download teacher report PDF.");
        alert(message);
      } else {
        alert(e?.response?.data?.message || e?.message || "Failed to download teacher report PDF.");
      }
    } finally {
      setDownloadingPdf(false);
    }
  };

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
              <select id="teacher-report-term" value={selectedTermValue} onChange={(e) => setTermId(e.target.value)}>
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
            <button className="payx-btn teacher-report-download-btn" onClick={downloadPdf} disabled={loading || downloadingPdf || rows.length === 0}>
              {downloadingPdf ? "Downloading PDF..." : "Download PDF"}
            </button>
          </div>

          <p className="teacher-report-meta">
            {schoolName} | Session: {context?.current_session?.session_name || context?.current_session?.academic_year || "-"} | Term: {context?.selected_term?.name || "-"}
          </p>
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
