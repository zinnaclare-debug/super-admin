import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import mobileDevicesArt from "../../../assets/e-library/mobile-devices.svg";
import onlineReadingArt from "../../../assets/e-library/online-reading.svg";
import audiobookArt from "../../../assets/e-library/audiobook.svg";
import "../../student/e-library/StudentELibrary.css";

const MAX_FINAL_FILE_BYTES = 2 * 1024 * 1024;
const MAX_SOURCE_FILE_BYTES = 20 * 1024 * 1024;

const fileSizeLabel = (bytes) => `${(bytes / (1024 * 1024)).toFixed(bytes < 1024 * 1024 ? 2 : 1)} MB`;

export default function ELibraryHome() {
  const [books, setBooks] = useState([]);
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);

  const [termSubjectId, setTermSubjectId] = useState("");
  const [title, setTitle] = useState("");
  const [author, setAuthor] = useState("");
  const [description, setDescription] = useState("");
  const [file, setFile] = useState(null);
  const [fileNotice, setFileNotice] = useState("");
  const [uploading, setUploading] = useState(false);
  const [deletingId, setDeletingId] = useState(null);

  function formatDate(value) {
    if (!value) return null;
    try {
      return new Date(value).toLocaleString();
    } catch {
      return null;
    }
  }

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

  const selectFile = (event) => {
    const selected = event.target.files?.[0] || null;
    if (!selected) {
      setFile(null);
      setFileNotice("");
      return;
    }

    if (selected.size > MAX_SOURCE_FILE_BYTES) {
      event.target.value = "";
      setFile(null);
      setFileNotice("");
      alert("Choose a PDF no larger than 20 MB. The e-library stores only a final file of 2 MB or less.");
      return;
    }

    setFile(selected);
    setFileNotice(
      selected.size > MAX_FINAL_FILE_BYTES
        ? `${selected.name} is ${fileSizeLabel(selected.size)}. It will be optimized automatically; upload succeeds only if the final PDF is 2 MB or less.`
        : `${selected.name} is ${fileSizeLabel(selected.size)} and already meets the 2 MB limit.`
    );
  };

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
      const response = await api.post("/api/staff/e-library", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      setTermSubjectId("");
      setTitle("");
      setAuthor("");
      setDescription("");
      setFile(null);
      setFileNotice("");
      await load();
      alert(response.data?.message || "Uploaded");
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
    <StaffFeatureLayout title="E-Library (Teacher)" showHeader={false}>
      <div className="sel-page sel-page--staff">
        <section className="sel-hero">
          <div>
            <span className="sel-pill">Staff E-Library</span>
            <h2>Upload and manage textbooks with a clean workspace</h2>
            <p className="sel-subtitle">
              Keep subject resources organized for students. Upload by assigned subject and manage materials from one place.
            </p>
            <div className="sel-metrics">
              <span>{loading ? "Loading..." : `${subjects.length} subject${subjects.length === 1 ? "" : "s"} assigned`}</span>
              <span>{loading ? "Syncing..." : `${books.length} book${books.length === 1 ? "" : "s"} uploaded`}</span>
            </div>
          </div>

          <div className="sel-hero-art" aria-hidden="true">
            <div className="sel-art sel-art--main">
              <img src={mobileDevicesArt} alt="" />
            </div>
            <div className="sel-art sel-art--reading">
              <img src={onlineReadingArt} alt="" />
            </div>
            <div className="sel-art sel-art--audio">
              <img src={audiobookArt} alt="" />
            </div>
          </div>
        </section>

        <section className="sel-panel">
          <h3>Upload Textbook</h3>
          <form onSubmit={upload} className="sel-form">
            <select className="sel-field" value={termSubjectId} onChange={(e) => setTermSubjectId(e.target.value)} required>
              <option value="">Select assigned subject</option>
              {subjects.map((s) => (
                <option key={s.term_subject_id} value={s.term_subject_id}>
                  {s.subject_name} - {s.class_name} ({s.term_name})
                </option>
              ))}
            </select>

            <input className="sel-field" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Title" required />
            <input className="sel-field" value={author} onChange={(e) => setAuthor(e.target.value)} placeholder="Author (optional)" />
            <input
              className="sel-field"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Description (optional)"
            />

            <input
              className="sel-field"
              type="file"
              accept=".pdf,application/pdf"
              onChange={selectFile}
            />
            <p className="sel-file-note">
              Final e-library PDFs are limited to <strong>2 MB</strong>. PDFs above 2 MB are optimized automatically before they are saved.
            </p>
            {fileNotice ? <p className="sel-file-status" role="status">{fileNotice}</p> : null}

            <button className="sel-btn" type="submit" disabled={uploading}>
              {uploading ? "Uploading..." : "Upload"}
            </button>
          </form>
        </section>

        <section className="sel-panel">
          {loading ? (
            <p className="sel-state sel-state--loading">Loading e-library...</p>
          ) : (
            <div className="sel-table-wrap">
              <table className="sel-table">
                <thead>
                  <tr>
                    <th style={{ width: 70 }}>S/N</th>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Term</th>
                    <th>Author</th>
                    <th>Posted</th>
                    <th style={{ width: 120 }}>File</th>
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
                      <td>{formatDate(b.created_at || b.updated_at || b.published_at) || "-"}</td>
                      <td>
                        <a className="sel-link-btn" href={b.file_url} target="_blank" rel="noreferrer">
                          View
                        </a>
                      </td>
                      <td>
                        <button
                          type="button"
                          className="sel-btn sel-btn--danger"
                          onClick={() => removeBook(b.id)}
                          disabled={deletingId === b.id}
                        >
                          {deletingId === b.id ? "Deleting..." : "Delete"}
                        </button>
                      </td>
                    </tr>
                  ))}
                  {books.length === 0 && (
                    <tr>
                      <td colSpan="9">No textbooks uploaded yet.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>
    </StaffFeatureLayout>
  );
}

