import { useEffect, useState } from "react";
import api from "../../../services/api";

export default function StudentVirtualClassHome() {
  const [subjects, setSubjects] = useState([]);
  const [items, setItems] = useState([]);
  const [termSubjectId, setTermSubjectId] = useState("");
  const [loading, setLoading] = useState(true);

  const loadSubjects = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/virtual-classes/subjects");
      const rows = res.data?.data || [];
      setSubjects(rows);
      if (rows.length > 0) {
        setTermSubjectId(String(rows[0].term_subject_id));
      } else {
        setTermSubjectId("");
        setItems([]);
      }
    } catch {
      alert("Failed to load virtual classes");
    } finally {
      setLoading(false);
    }
  };

  const loadItems = async (termId) => {
    if (!termId) {
      setItems([]);
      return;
    }
    try {
      const res = await api.get("/api/student/virtual-classes", {
        params: { term_subject_id: termId },
      });
      setItems(res.data?.data || []);
    } catch {
      setItems([]);
    }
  };

  useEffect(() => {
    loadSubjects();
  }, []);

  useEffect(() => {
    loadItems(termSubjectId);
  }, [termSubjectId]);

  return (
    <div>
      {loading ? (
        <p>Loading...</p>
      ) : subjects.length === 0 ? (
        <p>No virtual class subjects available for your current class and current term.</p>
      ) : (
        <>
          <div style={{ marginBottom: 12 }}>
            <label>
              Subject:{" "}
              <select value={termSubjectId} onChange={(e) => setTermSubjectId(e.target.value)}>
                {subjects.map((s) => (
                  <option key={s.term_subject_id} value={s.term_subject_id}>
                    {s.subject_name}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <table border="1" cellPadding="10" width="100%">
            <thead>
              <tr>
                <th style={{ width: 70 }}>S/N</th>
                <th>Title</th>
                <th>Start Time</th>
                <th>Join</th>
              </tr>
            </thead>
            <tbody>
              {items.map((x, idx) => (
                <tr key={x.id}>
                  <td>{idx + 1}</td>
                  <td>{x.title}</td>
                  <td>{x.starts_at ? new Date(x.starts_at).toLocaleString() : "-"}</td>
                  <td>
                    <a href={x.meeting_link} target="_blank" rel="noreferrer">
                      Join Class
                    </a>
                  </td>
                </tr>
              ))}
              {items.length === 0 ? (
                <tr>
                  <td colSpan="4">No virtual class links posted yet.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}
