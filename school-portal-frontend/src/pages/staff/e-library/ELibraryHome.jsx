import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

export default function ELibraryHome() {
  const [books, setBooks] = useState([]);
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);

  const [termSubjectId, setTermSubjectId] = useState("");
  const [title, setTitle] = useState("");
  const [author, setAuthor] = useState("");
  const [description, setDescription] = useState("");
  const [file, setFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [deletingId, setDeletingId] = useState(null);

  const load = async () => {
    setLoading(true);
    try {
      const [booksRes, subjectsRes] = await Promise.all([
        api.get("/api/staff/e-library"),
        api.get("/api/staff/e-library/assigned-subjects"),
      ]);
      setBooks(booksRes.data.data || []);
      setSubjects(subjectsRes.data.data || []);
    } catch (e) {
      alert("Failed to load e-library");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const upload = async (e) => {
    e.preventDefault();
    if (!termSubjectId) return alert("Select an assigned subject");
    if (!file) return alert("Choose a file");

    const fd = new FormData();
    fd.append("term_subject_id", termSubjectId);
    fd.append("title", title);
    if (author) fd.append("author", author);
    if (description) fd.append("description", description);
    fd.append("file", file);

    setUploading(true);
    try {
      await api.post("/api/staff/e-library", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      setTermSubjectId("");
      setTitle("");
      setAuthor("");
      setDescription("");
      setFile(null);
      await load();
      alert("Uploaded");
    } catch (e2) {
      alert(e2?.response?.data?.message || "Upload failed");
    } finally {
      setUploading(false);
    }
  };

  const removeBook = async (id) => {
    if (!window.confirm("Delete this textbook?")) return;
    setDeletingId(id);
    try {
      await api.delete(`/api/staff/e-library/${id}`);
      await load();
      alert("Deleted");
    } catch (e) {
      alert(e?.response?.data?.message || "Delete failed");
    } finally {
      setDeletingId(null);
    }
  };

  return (
    <StaffFeatureLayout title="E-Library (Teacher)">

      <div style={{ marginTop: 14, border: "1px solid #ddd", padding: 12, borderRadius: 10 }}>
        <h3 style={{ marginTop: 0 }}>Upload Textbook</h3>
        <form onSubmit={upload} style={{ display: "grid", gap: 10, maxWidth: 520 }}>
          <select value={termSubjectId} onChange={(e) => setTermSubjectId(e.target.value)} required>
            <option value="">Select assigned subject</option>
            {subjects.map((s) => (
              <option key={s.term_subject_id} value={s.term_subject_id}>
                {s.subject_name} - {s.class_name} ({s.term_name})
              </option>
            ))}
          </select>

          <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Title" required />
          <input value={author} onChange={(e) => setAuthor(e.target.value)} placeholder="Author (optional)" />
          <input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Description (optional)" />

          <input
            type="file"
            accept=".pdf,application/pdf"
            onChange={(e) => setFile(e.target.files?.[0] || null)}
          />

          <button type="submit" disabled={uploading}>
            {uploading ? "Uploading..." : "Upload"}
          </button>
        </form>
      </div>

      <div style={{ marginTop: 16 }}>
        {loading ? (
          <p>Loading...</p>
        ) : (
          <table border="1" cellPadding="10" width="100%">
            <thead>
              <tr>
                <th style={{ width: 70 }}>S/N</th>
                <th>Title</th>
                <th>Subject</th>
                <th>Class</th>
                <th>Term</th>
                <th>Author</th>
                <th style={{ width: 140 }}>File</th>
                <th style={{ width: 120 }}>Action</th>
              </tr>
            </thead>
            <tbody>
              {books.map((b, idx) => (
                <tr key={b.id}>
                  <td>{idx + 1}</td>
                  <td>{b.title}</td>
                  <td>{b.subject_name || "-"}</td>
                  <td>{b.class_name || "-"}</td>
                  <td>{b.term_name || "-"}</td>
                  <td>{b.author || "-"}</td>
                  <td>
                    <a href={b.file_url} target="_blank" rel="noreferrer">View</a>
                  </td>
                  <td>
                    <button
                      onClick={() => removeBook(b.id)}
                      disabled={deletingId === b.id}
                    >
                      {deletingId === b.id ? "Deleting..." : "Delete"}
                    </button>
                  </td>
                </tr>
              ))}
              {books.length === 0 && (
                <tr><td colSpan="8">No textbooks uploaded yet.</td></tr>
              )}
            </tbody>
          </table>
        )}
      </div>
    </StaffFeatureLayout>
  );
}
