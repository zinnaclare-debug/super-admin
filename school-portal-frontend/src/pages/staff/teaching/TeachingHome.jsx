import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import newsfeedArt from "../../../assets/teaching/newsfeed.svg";
import mobileArt from "../../../assets/teaching/mobile-devices.svg";
import readingArt from "../../../assets/teaching/reading-book.svg";
import "../../shared/Teaching.css";

const CATEGORY_ORDER = ["topic", "exam_question", "lesson_note", "lesson_plan"];

function bytes(value) {
  const size = Number(value || 0);
  if (!size) return "-";
  if (size < 1024) return `${size} B`;
  return `${(size / 1024).toFixed(1)} KB`;
}

function fileNameFromHeaders(headers, fallback) {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "teaching-material";
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
}

export default function StaffTeachingHome() {
  const [loading, setLoading] = useState(true);
  const [summary, setSummary] = useState(null);
  const [termSubjectId, setTermSubjectId] = useState("");
  const [category, setCategory] = useState("topic");
  const [title, setTitle] = useState("");
  const [files, setFiles] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [deletingId, setDeletingId] = useState(null);
  const [downloadingId, setDownloadingId] = useState(null);

  const categories = summary?.categories || {};
  const subjects = Array.isArray(summary?.subjects) ? summary.subjects : [];
  const materials = Array.isArray(summary?.materials) ? summary.materials : [];
  const subjectGroups = useMemo(() => {
    const groups = subjects.map((subject) => ({
      key: String(subject.term_subject_id),
      label: subject.label || subject.subject_name || "Subject",
      subject,
      materials: materials.filter((item) => String(item.term_subject_id || "") === String(subject.term_subject_id)),
    }));

    const unassigned = materials.filter((item) => !item.term_subject_id);
    if (unassigned.length) {
      groups.push({
        key: "unassigned",
        label: "General / Unassigned",
        subject: null,
        materials: unassigned,
      });
    }

    return groups;
  }, [materials, subjects]);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/staff/teaching");
      const data = res.data?.data || null;
      setSummary(data);
      const nextSubjects = Array.isArray(data?.subjects) ? data.subjects : [];
      setTermSubjectId((current) => current || String(nextSubjects[0]?.term_subject_id || ""));
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load teaching page.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const upload = async (event) => {
    event.preventDefault();
    if (!files.length) return alert("Choose at least one PDF/DOC/DOCX file.");
    if (!termSubjectId) return alert("Select the subject you want to upload for.");
    if (category === "exam_question" && files.length > 1) {
      return alert("Exam question accepts only one file and replaces the previous one.");
    }

    const fd = new FormData();
    fd.append("term_subject_id", termSubjectId);
    fd.append("category", category);
    if (title.trim()) fd.append("title", title.trim());
    files.forEach((file) => fd.append("files[]", file));

    setUploading(true);
    try {
      await api.post("/api/staff/teaching", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      setTitle("");
      setFiles([]);
      await load();
      alert("Uploaded. Compression worker will process the file shortly.");
    } catch (e) {
      alert(e?.response?.data?.message || "Upload failed.");
    } finally {
      setUploading(false);
    }
  };

  const remove = async (item) => {
    if (!window.confirm(`Delete ${item.original_name}?`)) return;
    setDeletingId(item.id);
    try {
      await api.delete(`/api/staff/teaching/materials/${item.id}`);
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || "Delete failed.");
    } finally {
      setDeletingId(null);
    }
  };

  const download = async (item) => {
    setDownloadingId(item.id);
    try {
      const res = await api.get(`/api/staff/teaching/materials/${item.id}/download`, {
        responseType: "blob",
      });
      const blobUrl = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, item.original_name);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
      alert(e?.response?.data?.message || "Download failed. File may still be processing.");
    } finally {
      setDownloadingId(null);
    }
  };

  return (
    <StaffFeatureLayout title="Teaching" showHeader={false}>
      <div className="teach-page">
        <section className="teach-hero">
          <div>
            <span className="teach-pill">Staff Teaching Desk</span>
            <h2 className="teach-title">Upload topics, exam questions, lesson notes, and lesson plans</h2>
            <p className="teach-subtitle">
              Staff can only manage the current active term. When the school activates a new term, previous uploads move into school-admin history.
            </p>
            <div className="teach-meta">
              <span>{summary?.current_session?.session_name || summary?.current_session?.academic_year || "Session -"}</span>
              <span>{summary?.current_term?.name || "Term -"}</span>
              <span>{materials.length} uploaded</span>
            </div>
          </div>
          <div className="teach-art" aria-hidden="true">
            <div className="teach-art-card teach-art-card--main"><img src={newsfeedArt} alt="" /></div>
            <div className="teach-art-card teach-art-card--phone"><img src={mobileArt} alt="" /></div>
            <div className="teach-art-card teach-art-card--book"><img src={readingArt} alt="" /></div>
          </div>
        </section>

        <section className="teach-panel">
          {loading ? <p className="teach-state">Loading teaching materials...</p> : null}
          {!loading ? (
            <div className="teach-grid">
              <article className="teach-card">
                <h3>Upload Teaching File</h3>
                <form className="teach-form" onSubmit={upload}>
                  <select className="teach-field" value={termSubjectId} onChange={(e) => setTermSubjectId(e.target.value)}>
                    <option value="">Select Subject</option>
                    {subjects.map((subject) => (
                      <option key={subject.term_subject_id} value={subject.term_subject_id}>
                        {subject.label || `${subject.subject_name} - ${subject.class_name}`}
                      </option>
                    ))}
                  </select>
                  <select className="teach-field" value={category} onChange={(e) => setCategory(e.target.value)}>
                    {CATEGORY_ORDER.map((key) => (
                      <option key={key} value={key}>{categories[key] || key}</option>
                    ))}
                  </select>
                  <input
                    className="teach-field"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="Title (optional)"
                  />
                  <input
                    className="teach-field"
                    type="file"
                    multiple={category !== "exam_question"}
                    accept=".pdf,.doc,.docx"
                    onChange={(e) => setFiles(Array.from(e.target.files || []))}
                  />
                  <small className="teach-small">
                    Topics allow up to 8 files per subject. Lesson notes and lesson plans are unlimited. Exam question accepts one file per subject and overwrites the previous exam file for that subject.
                  </small>
                  {subjects.length === 0 ? (
                    <small className="teach-small">No subject has been assigned to you for the current term yet.</small>
                  ) : null}
                  <button className="teach-btn" type="submit" disabled={uploading || subjects.length === 0}>
                    {uploading ? "Uploading..." : "Upload and Compress"}
                  </button>
                </form>
              </article>

              <article className="teach-card">
                <h3>Current Term Uploads</h3>
                {subjectGroups.map((group, index) => (
                  <details className="teach-collapse" key={group.key} open={index === 0}>
                    <summary>
                      <span>{group.label}</span>
                      <strong>{group.materials.length} file{group.materials.length === 1 ? "" : "s"}</strong>
                    </summary>
                    {CATEGORY_ORDER.map((key) => {
                      const rows = group.materials.filter((item) => item.category === key);
                      return (
                        <div className="teach-category" key={`${group.key}-${key}`}>
                          <h4>{categories[key] || key}</h4>
                          <div className="teach-file-list">
                            {rows.map((item) => (
                              <div className="teach-file-row" key={item.id}>
                                <div className="teach-file-main">
                                  <p className="teach-file-name">{item.title || item.original_name}</p>
                                  <p className="teach-file-meta">
                                    {item.original_name} | {item.status} | {bytes(item.compressed_size || item.file_size)}
                                  </p>
                                  {item.processing_note ? <p className="teach-file-meta">{item.processing_note}</p> : null}
                                </div>
                                <div className="teach-actions">
                                  <button className="teach-btn teach-btn--soft" onClick={() => download(item)} disabled={downloadingId === item.id || item.status !== "ready"}>
                                    {downloadingId === item.id ? "Downloading..." : "Download"}
                                  </button>
                                  <button className="teach-btn teach-btn--danger" onClick={() => remove(item)} disabled={deletingId === item.id}>
                                    {deletingId === item.id ? "Deleting..." : "Delete"}
                                  </button>
                                </div>
                              </div>
                            ))}
                            {rows.length === 0 ? <p className="teach-small">No file uploaded yet.</p> : null}
                          </div>
                        </div>
                      );
                    })}
                  </details>
                ))}
                {subjectGroups.length === 0 ? <p className="teach-small">No current term uploads yet.</p> : null}
              </article>
            </div>
          ) : null}
        </section>
      </div>
    </StaffFeatureLayout>
  );
}
