import { useMemo, useState } from "react";
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

function gradeFromAverage(avg) {
  if (avg >= 70) return "A";
  if (avg >= 60) return "B";
  if (avg >= 50) return "C";
  if (avg >= 40) return "D";
  if (avg >= 30) return "E";
  return "F";
}

function termSlot(termName) {
  const name = String(termName || "").toLowerCase();
  if (name.includes("first") || /\b1(st)?\b/.test(name)) return "first";
  if (name.includes("second") || /\b2(nd)?\b/.test(name)) return "second";
  if (name.includes("third") || /\b3(rd)?\b/.test(name)) return "third";
  return null;
}

function groupTermEntries(entries) {
  if ((entries || []).length > 0 && Array.isArray(entries[0]?.rows) && !entries[0]?.term && !entries[0]?.terms) {
    return entries;
  }

  const map = new Map();

  (entries || []).forEach((entry) => {
    const sessionId = Number(entry?.session?.id || 0);
    const classId = Number(entry?.class?.id || 0);
    const key = `${sessionId}|${classId}`;
    if (!map.has(key)) {
      map.set(key, {
        session: entry?.session || {},
        class: entry?.class || {},
        subjects: new Map(),
      });
    }

    const slot = termSlot(entry?.term?.name);
    if (!slot) return;

    (entry?.rows || []).forEach((row) => {
      if (!row?.has_result) return;
      const subjectName = String(row?.subject_name || "").trim();
      if (!subjectName) return;
      const subjectKey = `${subjectName.toLowerCase()}|${String(row?.subject_code || "").toLowerCase()}`;

      const group = map.get(key);
      if (!group.subjects.has(subjectKey)) {
        group.subjects.set(subjectKey, {
          subject_name: subjectName,
          first_total: null,
          second_total: null,
          third_total: null,
        });
      }

      group.subjects.get(subjectKey)[`${slot}_total`] = Number(row?.total ?? 0);
    });
  });

  return Array.from(map.values()).map((group) => {
    const rows = Array.from(group.subjects.values()).sort((a, b) =>
      String(a.subject_name || "").localeCompare(String(b.subject_name || ""), undefined, { sensitivity: "base" })
    );

    rows.forEach((row) => {
      const values = [row.first_total, row.second_total, row.third_total].filter((v) => v !== null);
      const annualAverage = values.length ? Number((values.reduce((sum, v) => sum + v, 0) / values.length).toFixed(2)) : null;
      row.annual_average = annualAverage;
      row.annual_grade = annualAverage === null ? "-" : gradeFromAverage(Math.round(annualAverage));
    });

    return {
      session: group.session,
      class: group.class,
      rows,
    };
  });
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
  const groupedEntries = useMemo(() => groupTermEntries(entries), [entries]);

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
            <strong>Total Sessions:</strong> {Number(context.entries_count || groupedEntries.length || 0)}
          </p>
        </div>
      ) : null}

      <div className="transcript-session-grid">
        {groupedEntries.map((group, groupIndex) => (
          <div className="transcript-entry" key={`${group.session?.id || "s"}-${group.class?.id || "c"}-${groupIndex}`}>
            <div className="transcript-entry-head">
              <h3>
                {group.session?.academic_year || group.session?.session_name || "-"} | {group.class?.name || "-"}
              </h3>
            </div>

            <div className="transcript-table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Subject</th>
                    <th>First Term</th>
                    <th>Second Term</th>
                    <th>Third Term</th>
                    <th>Annual Average</th>
                    <th>Annual Grade</th>
                  </tr>
                </thead>
                <tbody>
                  {(group.rows || []).map((row, idx) => (
                    <tr key={`${groupIndex}-${idx}-${row.subject_name}`}>
                      <td>{row.subject_name}</td>
                      <td>{row.first_total === null ? "-" : row.first_total}</td>
                      <td>{row.second_total === null ? "-" : row.second_total}</td>
                      <td>{row.third_total === null ? "-" : row.third_total}</td>
                      <td>{row.annual_average === null ? "-" : Number(row.annual_average).toFixed(2)}</td>
                      <td>{row.annual_grade || "-"}</td>
                    </tr>
                  ))}
                  {(group.rows || []).length === 0 ? (
                    <tr>
                      <td colSpan="6">No graded records.</td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
