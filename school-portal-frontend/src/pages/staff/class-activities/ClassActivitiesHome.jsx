import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import workingTogetherArt from "../../../assets/class-activities/working-together.svg";
import gradingPapersArt from "../../../assets/class-activities/grading-papers.svg";
import learningSketchArt from "../../../assets/class-activities/learning-to-sketch.svg";
import "../../student/class-activities/ClassActivitiesHome.css";

function fileNameFromHeaders(headers, fallback) {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "download";
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
}

function formatDate(value) {
  if (!value) return null;
  try {
    return new Date(value).toLocaleString();
  } catch {
    return null;
  }
}

export default function ClassActivitiesHome() {
  const [activities, setActivities] = useState([]);
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [termSubjectId, setTermSubjectId] = useState("");
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [file, setFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [deletingId, setDeletingId] = useState(null);
  const [downloadingId, setDownloadingId] = useState(null);

  const load = async () => {
    setLoading(true);
    try {
      const [activitiesRes, subjectsRes] = await Promise.all([
        api.get("/api/staff/class-activities"),
        api.get("/api/staff/class-activities/assigned-subjects"),
      ]);
      setActivities(activitiesRes.data?.data || []);
      setSubjects(subjectsRes.data?.data || []);
    } catch {
      alert("Failed to load class activities");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const upload = async (e) => {
    e.preventDefault();
    if (!termSubjectId) return alert("Select an assigned subject");
    if (!file) return alert("Choose a file");

    const fd = new FormData();
    fd.append("term_subject_id", termSubjectId);
    fd.append("title", title);
    if (description) fd.append("description", description);
    fd.append("file", file);

    setUploading(true);
    try {
      await api.post("/api/staff/class-activities", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      setTermSubjectId("");
      setTitle("");
      setDescription("");
      setFile(null);
      await load();
      alert("Uploaded");
    } catch (err) {
      alert(err?.response?.data?.message || "Upload failed");
    } finally {
      setUploading(false);
    }
  };

  const remove = async (id) => {
    if (!window.confirm("Delete this activity?")) return;
    setDeletingId(id);
    try {
      await api.delete(`/api/staff/class-activities/${id}`);
      await load();
      alert("Deleted");
    } catch (err) {
      alert(err?.response?.data?.message || "Delete failed");
    } finally {
      setDeletingId(null);
    }
  };

  const download = async (a) => {
    setDownloadingId(a.id);
    try {
      const res = await api.get(`/api/staff/class-activities/${a.id}/download`, {
        responseType: "blob",
      });
      const blobUrl = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, a.original_name || a.title || "activity");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
    } catch {
      if (a.file_url) {
        window.open(a.file_url, "_blank", "noopener,noreferrer");
        return;
      }
      alert("Download failed");
    } finally {
      setDownloadingId(null);
    }
  };

  return (
    <StaffFeatureLayout title="Class Activities (Staff)">
      <div className="sca-page sca-page--staff">
        <section className="sca-hero">
          <div>
            <span className="sca-pill">Staff Class Activities</span>
            <h2>Create and manage class activities with confidence</h2>
            <p className="sca-subtitle">
              Upload assignments by subject, keep files organized, and manage activity downloads from one clean workspace.
            </p>

            <div className="sca-metrics">
              <span>{loading ? "Loading..." : `${subjects.length} subject${subjects.length === 1 ? "" : "s"} assigned`}</span>
              <span>{loading ? "Syncing..." : `${activities.length} activit${activities.length === 1 ? "y" : "ies"} uploaded`}</span>
            </div>
          </div>

          <div className="sca-hero-art" aria-hidden="true">
            <div className="sca-art sca-art--main">
              <img src={workingTogetherArt} alt="" />
            </div>
            <div className="sca-art sca-art--grading">
              <img src={gradingPapersArt} alt="" />
            </div>
            <div className="sca-art sca-art--sketch">
              <img src={learningSketchArt} alt="" />
            </div>
          </div>
        </section>

        <section className="sca-panel">
          <h3>Upload Activity</h3>
          <form onSubmit={upload} className="sca-form">
            <select className="sca-field" value={termSubjectId} onChange={(e) => setTermSubjectId(e.target.value)} required>
              <option value="">Select assigned subject</option>
              {subjects.map((s) => (
                <option key={s.term_subject_id} value={s.term_subject_id}>
                  {s.subject_name} - {s.class_name} ({s.term_name})
                </option>
              ))}
            </select>

            <input className="sca-field" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Title" required />
            <input
              className="sca-field"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Description (optional)"
            />
            <input
              className="sca-field"
              type="file"
              accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png"
              onChange={(e) => setFile(e.target.files?.[0] || null)}
            />

            <button className="sca-btn" type="submit" disabled={uploading}>
              {uploading ? "Uploading..." : "Upload Activity"}
            </button>
          </form>
        </section>

        <section className="sca-panel">
          {loading ? (
            <p className="sca-state sca-state--loading">Loading class activities...</p>
          ) : (
            <div className="sca-table-wrap">
              <table className="sca-table">
                <thead>
                  <tr>
                    <th style={{ width: 70 }}>S/N</th>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Term</th>
                    <th>Posted</th>
                    <th style={{ width: 150 }}>File</th>
                    <th style={{ width: 120 }}>Delete</th>
                  </tr>
                </thead>
                <tbody>
                  {activities.map((a, idx) => (
                    <tr key={a.id}>
                      <td>{idx + 1}</td>
                      <td>{a.title}</td>
                      <td>{a.subject_name || "-"}</td>
                      <td>{a.class_name || "-"}</td>
                      <td>{a.term_name || "-"}</td>
                      <td>{formatDate(a.created_at || a.updated_at) || "-"}</td>
                      <td>
                        <button type="button" className="sca-btn sca-btn--soft" onClick={() => download(a)} disabled={downloadingId === a.id}>
                          {downloadingId === a.id ? "Downloading..." : "Download"}
                        </button>
                      </td>
                      <td>
                        <button type="button" className="sca-btn sca-btn--danger" onClick={() => remove(a.id)} disabled={deletingId === a.id}>
                          {deletingId === a.id ? "Deleting..." : "Delete"}
                        </button>
                      </td>
                    </tr>
                  ))}
                  {activities.length === 0 && (
                    <tr>
                      <td colSpan="8">No class activities uploaded yet.</td>
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
