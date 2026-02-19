import { useEffect, useState } from "react";
import api from "../../../services/api";

export default function StudentTopicsHome() {
  const [subjects, setSubjects] = useState([]);
  const [materials, setMaterials] = useState([]);
  const [termSubjectId, setTermSubjectId] = useState("");
  const [loading, setLoading] = useState(true);

  const loadSubjects = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/topics/subjects");
      const items = res.data?.data || [];
      setSubjects(items);
      if (items.length > 0) {
        setTermSubjectId(String(items[0].term_subject_id));
      } else {
        setTermSubjectId("");
        setMaterials([]);
      }
    } catch {
      alert("Failed to load topics subjects");
    } finally {
      setLoading(false);
    }
  };

  const loadMaterials = async (termId) => {
    if (!termId) {
      setMaterials([]);
      return;
    }
    try {
      const res = await api.get("/api/student/topics", {
        params: { term_subject_id: termId },
      });
      setMaterials(res.data?.data || []);
    } catch {
      setMaterials([]);
    }
  };

  useEffect(() => {
    loadSubjects();
  }, []);

  useEffect(() => {
    loadMaterials(termSubjectId);
  }, [termSubjectId]);

  return (
    <div>
      {loading ? (
        <p>Loading...</p>
      ) : subjects.length === 0 ? (
        <p>No topic subjects available for your current class and current term.</p>
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
                <th>File</th>
              </tr>
            </thead>
            <tbody>
              {materials.map((m, idx) => (
                <tr key={m.id}>
                  <td>{idx + 1}</td>
                  <td>{m.title || m.original_name || "-"}</td>
                  <td>
                    <a href={m.file_url} target="_blank" rel="noreferrer">
                      View / Download
                    </a>
                  </td>
                </tr>
              ))}
              {materials.length === 0 ? (
                <tr>
                  <td colSpan="3">No topic materials posted yet.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}
