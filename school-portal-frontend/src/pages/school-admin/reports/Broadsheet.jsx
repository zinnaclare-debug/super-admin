import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import photocopyArt from "../../../assets/broadsheet/photocopy.svg";
import dataAtWorkArt from "../../../assets/broadsheet/data-at-work.svg";
import spreadsheetsArt from "../../../assets/broadsheet/spreadsheets.svg";
import "../../shared/PaymentsShowcase.css";
import "./Broadsheet.css";

function fileNameFromHeaders(headers, fallback) {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "broadsheet.pdf";
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

const levelLabel = (value) => {
  const normalized = String(value || "").trim().toLowerCase();
  if (!normalized) return "-";
  return normalized.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
};

const formatNumber = (value) => {
  if (value == null) return "-";
  const num = Number(value);
  if (!Number.isFinite(num)) return "-";
  return Number.isInteger(num) ? String(num) : num.toFixed(2);
};

const reportScopeOptions = [
  { value: "annual", label: "Annual" },
  { value: "first_term", label: "First Term" },
  { value: "second_term", label: "Second Term" },
  { value: "third_term", label: "Third Term" },
];

const reportScopeLabel = (value) =>
  reportScopeOptions.find((item) => item.value === value)?.label || "Annual";

export default function Broadsheet() {
  const [sessions, setSessions] = useState([]);
  const [sessionId, setSessionId] = useState("");
  const [levelOptions, setLevelOptions] = useState([]);
  const [level, setLevel] = useState("");
  const [departmentOptions, setDepartmentOptions] = useState([]);
  const [department, setDepartment] = useState("");
  const [classOptions, setClassOptions] = useState([]);
  const [classId, setClassId] = useState("");
  const [reportScope, setReportScope] = useState("annual");
  const [subjects, setSubjects] = useState([]);
  const [rows, setRows] = useState([]);
  const [context, setContext] = useState(null);
  const [loadingOptions, setLoadingOptions] = useState(true);
  const [searching, setSearching] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [previewing, setPreviewing] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");

  const selectedSession = useMemo(() => {
    if (sessions.length === 0) return null;
    return sessions.find((item) => String(item.id) === String(sessionId)) || sessions[0];
  }, [sessions, sessionId]);

  const canSearch = Boolean(selectedSession) && Boolean(level);

  const loadOptions = async ({
    nextSessionId = "",
    nextLevel = "",
    nextDepartment = "",
    nextClassId = "",
    nextReportScope = "annual",
  } = {}) => {
    setLoadingOptions(true);
    setError("");
    try {
      const params = {};
      if (nextSessionId) params.academic_session_id = nextSessionId;
      if (nextLevel) params.level = nextLevel;
      if (nextDepartment) params.department = nextDepartment;
      if (nextClassId) params.class_id = nextClassId;
      if (nextReportScope) params.report_scope = nextReportScope;

      const res = await api.get("/api/school-admin/reports/broadsheet/options", { params });
      const payload = res.data?.data || {};
      const loadedSessions = payload.sessions || [];
      const loadedLevels = payload.levels || [];
      const loadedDepartments = payload.departments || [];
      const loadedClasses = payload.classes || [];
      setSessions(loadedSessions);
      setLevelOptions(loadedLevels);
      setDepartmentOptions(loadedDepartments);
      setClassOptions(loadedClasses);

      if (loadedSessions.length > 0) {
        const selected = payload.selected_session_id || loadedSessions[0].id;
        setSessionId(String(selected));
      } else {
        setSessionId("");
      }
      setLevel(payload.selected_level || loadedLevels[0] || "");
      setDepartment(payload.selected_department || "");
      setClassId(payload.selected_class_id ? String(payload.selected_class_id) : "");
      setReportScope(payload.selected_report_scope || nextReportScope || "annual");
    } catch (e) {
      setSessions([]);
      setLevelOptions([]);
      setDepartmentOptions([]);
      setClassOptions([]);
      setSessionId("");
      setLevel("");
      setDepartment("");
      setClassId("");
      setReportScope("annual");
      setError(e?.response?.data?.message || "Failed to load broadsheet options.");
    } finally {
      setLoadingOptions(false);
    }
  };

  useEffect(() => {
    loadOptions();
  }, []);

  const requestParams = () => {
    const params = {};
    if (selectedSession?.id) params.academic_session_id = selectedSession.id;
    if (level) params.level = level;
    if (department) params.department = department;
    if (classId) params.class_id = classId;
    if (reportScope) params.report_scope = reportScope;
    return params;
  };

  const searchBroadsheet = async () => {
    if (!canSearch) return;
    setSearching(true);
    setError("");
    setMessage("");
    try {
      const res = await api.get("/api/school-admin/reports/broadsheet", {
        params: requestParams(),
      });
      const data = res.data?.data || {};
      setSubjects(data.subjects || []);
      setRows(data.rows || []);
      setContext(res.data?.context || null);
      setLevelOptions(res.data?.context?.levels || levelOptions);
      setDepartmentOptions(res.data?.context?.departments || departmentOptions);
      setClassOptions(res.data?.context?.classes || classOptions);
      setDepartment(res.data?.context?.selected_department || "");
      setClassId(res.data?.context?.selected_class_id ? String(res.data.context.selected_class_id) : "");
      setReportScope(res.data?.context?.selected_report_scope || reportScope);
      setMessage((data.rows || []).length === 0 ? "No broadsheet data found for this filter." : "");
    } catch (e) {
      setSubjects([]);
      setRows([]);
      setContext(null);
      setError(e?.response?.data?.message || "Failed to load broadsheet.");
    } finally {
      setSearching(false);
    }
  };

  const downloadBroadsheet = async () => {
    if (!canSearch) return;
    setDownloading(true);
    setError("");
    try {
      const res = await api.get("/api/school-admin/reports/broadsheet/download", {
        params: requestParams(),
        responseType: "blob",
      });

      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const msg = await messageFromBlobError(res.data, "Failed to download broadsheet PDF.");
        throw new Error(msg);
      }

      const pdfBlob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const blobUrl = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, `${reportScope}_broadsheet.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const msg = await messageFromBlobError(e.response.data, "Failed to download broadsheet PDF.");
        setError(msg);
      } else {
        setError(e?.response?.data?.message || e?.message || "Failed to download broadsheet PDF.");
      }
    } finally {
      setDownloading(false);
    }
  };

  const previewBroadsheetPdf = async () => {
    if (!canSearch) return;
    setPreviewing(true);
    setError("");
    try {
      const res = await api.get("/api/school-admin/reports/broadsheet/download", {
        params: requestParams(),
        responseType: "blob",
      });

      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const msg = await messageFromBlobError(res.data, "Failed to preview broadsheet PDF.");
        throw new Error(msg);
      }

      const pdfBlob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const blobUrl = window.URL.createObjectURL(pdfBlob);
      window.open(blobUrl, "_blank", "noopener,noreferrer");
      setTimeout(() => {
        window.URL.revokeObjectURL(blobUrl);
      }, 15000);
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const msg = await messageFromBlobError(e.response.data, "Failed to preview broadsheet PDF.");
        setError(msg);
      } else {
        setError(e?.response?.data?.message || e?.message || "Failed to preview broadsheet PDF.");
      }
    } finally {
      setPreviewing(false);
    }
  };

  return (
    <div className="payx-page payx-page--admin broadsheet-page">
      <section className="payx-hero broadsheet-hero">
        <div>
          <span className="payx-pill">School Admin Broadsheet</span>
          <h2 className="payx-title">Review class-wide performance with a clearer broadsheet workspace.</h2>
          <p className="payx-subtitle">
            Filter by session, level, department, class, and report scope, then preview or download the full broadsheet from one polished page.
          </p>
          <div className="payx-meta">
            <span>{selectedSession?.session_name || selectedSession?.academic_year || "Session -"}</span>
            <span>{levelLabel(level)}</span>
            <span>{reportScopeLabel(reportScope)}</span>
          </div>
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main broadsheet-art--main">
            <img src={photocopyArt} alt="" />
          </div>
          <div className="payx-art payx-art--card broadsheet-art--card">
            <img src={dataAtWorkArt} alt="" />
          </div>
          <div className="payx-art payx-art--online broadsheet-art--online">
            <img src={spreadsheetsArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel broadsheet-panel">
        <div className="payx-card broadsheet-card">
          <div className="broadsheet-grid">
            <div className="broadsheet-field">
              <label htmlFor="broadsheet-session">Academic Session</label>
              <select
                id="broadsheet-session"
                value={sessionId}
                onChange={(e) => {
                  const value = e.target.value;
                  setSessionId(value);
                  setDepartment("");
                  setClassId("");
                  loadOptions({ nextSessionId: value, nextReportScope: reportScope });
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

            <div className="broadsheet-field">
              <label htmlFor="broadsheet-level">Level</label>
              <select
                id="broadsheet-level"
                value={level}
                onChange={(e) => {
                  const nextLevel = e.target.value;
                  setLevel(nextLevel);
                  setDepartment("");
                  setClassId("");
                  loadOptions({
                    nextSessionId: sessionId || selectedSession?.id || "",
                    nextLevel,
                    nextReportScope: reportScope,
                  });
                }}
                disabled={loadingOptions}
              >
                {(levelOptions || []).map((value) => (
                  <option key={value} value={value}>
                    {levelLabel(value)}
                  </option>
                ))}
              </select>
            </div>

            <div className="broadsheet-field">
              <label htmlFor="broadsheet-department">Department</label>
              <select
                id="broadsheet-department"
                value={department}
                onChange={(e) => {
                  const nextDepartment = e.target.value;
                  setDepartment(nextDepartment);
                  setClassId("");
                  loadOptions({
                    nextSessionId: sessionId || selectedSession?.id || "",
                    nextLevel: level,
                    nextDepartment,
                    nextReportScope: reportScope,
                  });
                }}
                disabled={loadingOptions}
              >
                <option value="">All Departments</option>
                {(departmentOptions || []).map((value) => (
                  <option key={value} value={value}>
                    {value}
                  </option>
                ))}
              </select>
            </div>

            <div className="broadsheet-field">
              <label htmlFor="broadsheet-class">Class</label>
              <select
                id="broadsheet-class"
                value={classId}
                onChange={(e) => setClassId(e.target.value)}
                disabled={loadingOptions}
              >
                <option value="">All Classes</option>
                {(classOptions || []).map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="broadsheet-field">
              <label htmlFor="broadsheet-report-scope">Report Scope</label>
              <select
                id="broadsheet-report-scope"
                value={reportScope}
                onChange={(e) => {
                  const nextReportScope = e.target.value;
                  setReportScope(nextReportScope);
                  loadOptions({
                    nextSessionId: sessionId || selectedSession?.id || "",
                    nextLevel: level,
                    nextDepartment: department,
                    nextClassId: classId,
                    nextReportScope,
                  });
                }}
                disabled={loadingOptions}
              >
                {reportScopeOptions.map((item) => (
                  <option key={item.value} value={item.value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="broadsheet-actions">
            <button className="payx-btn" onClick={searchBroadsheet} disabled={!canSearch || searching || loadingOptions}>
              {searching ? "Searching..." : "Search"}
            </button>
            <button className="payx-btn payx-btn--soft" onClick={previewBroadsheetPdf} disabled={!canSearch || previewing}>
              {previewing ? "Opening Preview..." : "Preview PDF"}
            </button>
            <button className="payx-btn broadsheet-download-btn" onClick={downloadBroadsheet} disabled={!canSearch || downloading}>
              {downloading ? "Downloading..." : "Download Broadsheet"}
            </button>
          </div>

          {context?.session ? (
            <p className="broadsheet-meta">
              Session: {context.session.session_name || context.session.academic_year || "-"} | Scope:{" "}
              {context.selected_report_scope_label || reportScopeLabel(reportScope)} | Level: {levelLabel(context.level)} |
              Department: {context.selected_department || "All"} | Class: {context.selected_class_name || "All"}
            </p>
          ) : null}

          {error ? <p className="broadsheet-error">{error}</p> : null}
          {message ? <p className="broadsheet-message">{message}</p> : null}
        </div>

        <div className="payx-card broadsheet-table-card">
          <div className="broadsheet-table-wrap">
            <table className="broadsheet-table">
              <thead>
                <tr>
                  <th>S/N</th>
                  <th className="broadsheet-student-head">Student Name</th>
                  <th className="broadsheet-class-head">Class</th>
                  {(subjects || []).map((subject) => (
                    <th key={subject.id} className="broadsheet-subject-head-horizontal">
                      {subject.header_name || subject.name || subject.short_code || subject.code || "-"}
                    </th>
                  ))}
                  <th className="broadsheet-summary-head">Total</th>
                  <th className="broadsheet-summary-head">Average</th>
                  <th className="broadsheet-summary-head">Position</th>
                </tr>
              </thead>
              <tbody>
                {(rows || []).map((row, index) => (
                  <tr key={row.student_id}>
                    <td>{index + 1}</td>
                    <td>{row.name}</td>
                    <td>{row.class_name || "-"}</td>
                    {(subjects || []).map((subject) => {
                      const value = row.scores?.[String(subject.id)] ?? null;
                      return <td key={`${row.student_id}-${subject.id}`}>{formatNumber(value)}</td>;
                    })}
                    <td>{formatNumber(row.total)}</td>
                    <td>{formatNumber(row.average)}</td>
                    <td>{row.position_label || "-"}</td>
                  </tr>
                ))}
                {(rows || []).length === 0 ? (
                  <tr>
                    <td colSpan={(subjects || []).length + 6}>No broadsheet data found.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  );
}
