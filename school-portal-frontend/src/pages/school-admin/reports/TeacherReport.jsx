import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import { getStoredUser } from "../../../utils/authStorage";

const isMissingCurrentSessionTerm = (message = "") =>
  String(message).toLowerCase().includes("no current academic session/term configured");

export default function TeacherReport() {
  const [rows, setRows] = useState([]);
  const [context, setContext] = useState(null);
  const [termId, setTermId] = useState("");
  const [loading, setLoading] = useState(true);
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
