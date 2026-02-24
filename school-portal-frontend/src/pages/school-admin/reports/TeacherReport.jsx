import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import { getStoredUser } from "../../../utils/authStorage";

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

      const pdfBlob = res.data instanceof Blob
        ? res.data
        : new Blob([res.data], { type: "application/pdf" });
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
    <div>
      {sessionConfigError ? (
        <p style={{ marginTop: 10, color: "#b45309" }}>{sessionConfigError}</p>
      ) : null}
      <p style={{ marginTop: 6, opacity: 0.8 }}>
        {schoolName} | Session:{" "}
        {context?.current_session?.session_name || context?.current_session?.academic_year || "-"}
      </p>

      <div style={{ marginTop: 12 }}>
        <label htmlFor="teacher-report-term">Term: </label>
        <select
          id="teacher-report-term"
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
              </tr>
            ))}
            {rows.length === 0 && (
              <tr>
                <td colSpan="10">No teacher grading data for this term.</td>
              </tr>
            )}
          </tbody>
        </table>
      )}
    </div>
  );
}
