import { useEffect, useState } from "react";
import api from "../../../services/api";

export default function StudentELibrary() {
  const [books, setBooks] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/e-library");
      setBooks(res.data.data || []);
    } catch (e) {
      alert("Failed to load e-library");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  return (
    <div>
      {loading ? (
        <p>Loading...</p>
      ) : (
        <table border="1" cellPadding="10" width="100%" style={{ marginTop: 12 }}>
          <thead>
            <tr>
              <th style={{ width: 70 }}>S/N</th>
              <th>Title</th>
              <th>Author</th>
              <th>Level</th>
              <th style={{ width: 160 }}>Download</th>
            </tr>
          </thead>
          <tbody>
            {books.map((b, idx) => (
              <tr key={b.id}>
                <td>{idx + 1}</td>
                <td>{b.title}</td>
                <td>{b.author || "-"}</td>
                <td>{b.education_level || "All"}</td>
                <td>
                  <a href={b.file_url} target="_blank" rel="noreferrer">Download</a>
                </td>
              </tr>
            ))}
            {books.length === 0 && (
              <tr><td colSpan="5">No textbooks available.</td></tr>
            )}
          </tbody>
        </table>
      )}
    </div>
  );
}
