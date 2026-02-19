import { useEffect, useState } from "react";
import api from "../../../services/api";

export default function StudentSubjectsHome() {
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError("");
      try {
        const res = await api.get("/api/student/topics/subjects");
        setSubjects(res.data?.data || []);
      } catch (e) {
        setError(e?.response?.data?.message || "Failed to load enrolled subjects");
        setSubjects([]);
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  return (
    <div>
      {loading ? (
        <p>Loading subjects...</p>
      ) : error ? (
        <p style={{ color: "red" }}>{error}</p>
      ) : subjects.length === 0 ? (
        <p>No enrolled subjects found for your current class and term.</p>
      ) : (
        <table border="1" cellPadding="10" width="100%">
          <thead>
            <tr>
              <th style={{ width: 70 }}>S/N</th>
              <th>Subject Name</th>
              <th style={{ width: 170 }}>Subject Code</th>
            </tr>
          </thead>
          <tbody>
            {subjects.map((s, idx) => (
              <tr key={s.term_subject_id || `${s.subject_name}-${idx}`}>
                <td>{idx + 1}</td>
                <td>{s.subject_name || "-"}</td>
                <td>{s.subject_code || "-"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
