import { useMemo, useState } from "react";
import api from "../../../services/api";
import researchingArt from "../../../assets/transcript/researching.svg";
import savingNotesArt from "../../../assets/transcript/saving-notes.svg";
import documentWarningArt from "../../../assets/transcript/document-warning.svg";
import { messageFromBlobError, useGeneratedDocumentJob } from "../../../hooks/useGeneratedDocumentJob";
import "../../shared/PaymentsShowcase.css";
import "./Transcript.css";

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
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const { job, setJob, requesting, setRequesting, downloading, isProcessing, downloadGeneratedFile } = useGeneratedDocumentJob();

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

  const startTranscriptPdf = async () => {
    if (!canSearch) return;
    setRequesting(true);
    setError("");
    try {
      const res = await api.post("/api/school-admin/transcript/download-jobs", requestParams());
      setJob(res.data?.data || null);
    } catch (e) {
      setError(e?.response?.data?.message || e?.message || "Failed to start transcript PDF generation.");
    } finally {
      setRequesting(false);
    }
  };

  const downloadTranscript = async () => {
    try {
      await downloadGeneratedFile("student_transcript.pdf");
    } catch (e) {
      if (e?.response?.data instanceof Blob) {
        const msg = await messageFromBlobError(e.response.data, "Failed to download transcript PDF.");
        setError(msg);
      } else {
        setError(e?.response?.data?.message || e?.message || "Failed to download transcript PDF.");
      }
    }
  };

  const handleTranscriptPdfAction = async () => {
    if (job?.status === "completed") {
      await downloadTranscript();
      return;
    }

    await startTranscriptPdf();
  };

  const transcriptActionLabel = (() => {
    if (requesting) return "Starting...";
    if (downloading) return "Downloading...";
    if (job?.status === "completed") return "Download Transcript";
    if (job?.status === "failed") return "Retry Transcript PDF";
    if (job?.status === "processing") return "Processing Transcript...";
    if (job?.status === "pending") return "Queued...";
    return "Generate Transcript PDF";
  })();

  return (
    <div className="payx-page payx-page--admin transcript-page">
      <section className="payx-hero transcript-hero">
        <div>
          <span className="payx-pill">School Admin Transcript</span>
          <h2 className="payx-title">Search and download student transcripts from one clearer review workspace.</h2>
          <p className="payx-subtitle">
            Look up a student by email, review yearly transcript groupings by session and class, then export the official transcript PDF when needed.
          </p>
          <div className="payx-meta">
            <span>{context?.student?.name || "Student lookup"}</span>
            <span>{context?.student?.email || "Email-based search"}</span>
            <span>{groupedEntries.length} session group{groupedEntries.length === 1 ? "" : "s"}</span>
          </div>
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main transcript-art--main">
            <img src={researchingArt} alt="" />
          </div>
          <div className="payx-art payx-art--card transcript-art--card">
            <img src={savingNotesArt} alt="" />
          </div>
          <div className="payx-art payx-art--online transcript-art--online">
            <img src={documentWarningArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel transcript-panel">
        <div className="payx-card transcript-card">
          <div className="transcript-grid">
            <div className="transcript-field">
              <label htmlFor="transcript-email">Student Email</label>
              <input
                id="transcript-email"
                type="email"
                placeholder="student@example.com"
                value={email}
                onChange={(e) => {
                  setEmail(e.target.value);
                  setJob(null);
                }}
              />
            </div>
          </div>

          <div className="transcript-actions">
            <button className="payx-btn" onClick={searchTranscript} disabled={!canSearch || searching}>
              {searching ? "Searching..." : "Search"}
            </button>
            <button className="payx-btn transcript-download-btn" onClick={handleTranscriptPdfAction} disabled={!canSearch || requesting || downloading || isProcessing}>
              {transcriptActionLabel}
            </button>
          </div>

          {context?.student ? (
            <p className="transcript-meta">
              Student: {context.student.name} ({context.student.email}) | Total Sessions: {Number(context.entries_count || groupedEntries.length || 0)}
            </p>
          ) : null}

          {job?.status === "failed" ? <p className="transcript-error">{job.error_message || "Transcript PDF generation failed."}</p> : null}
          {error ? <p className="transcript-error">{error}</p> : null}
          {message ? <p className="transcript-message">{message}</p> : null}
        </div>

        <div className="transcript-session-grid">
          {groupedEntries.map((group, groupIndex) => (
            <div className="payx-card transcript-entry" key={`${group.session?.id || "s"}-${group.class?.id || "c"}-${groupIndex}`}>
              <div className="transcript-entry-head">
                <h3>
                  {group.session?.academic_year || group.session?.session_name || "-"} | {group.class?.name || "-"}
                </h3>
              </div>

              <div className="transcript-table-wrap">
                <table className="transcript-table">
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
      </section>
    </div>
  );
}
