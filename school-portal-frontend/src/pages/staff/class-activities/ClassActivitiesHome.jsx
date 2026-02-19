import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

function fileNameFromHeaders(headers, fallback) {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "download";
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
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

      <div style={{ marginTop: 14, border: "1px solid #ddd", padding: 12, borderRadius: 10 }}>
        <h3 style={{ marginTop: 0 }}>Upload Activity</h3>
        <form onSubmit={upload} style={{ display: "grid", gap: 10, maxWidth: 580 }}>
          <select value={termSubjectId} onChange={(e) => setTermSubjectId(e.target.value)} required>
            <option value="">Select assigned subject</option>
            {subjects.map((s) => (
              <option key={s.term_subject_id} value={s.term_subject_id}>
                {s.subject_name} - {s.class_name} ({s.term_name})
              </option>
            ))}
          </select>

          <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Title" required />
          <input
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Description (optional)"
          />
          <input
            type="file"
            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png"
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
                  <td>
                    <button onClick={() => download(a)} disabled={downloadingId === a.id}>
                      {downloadingId === a.id ? "Downloading..." : "Download"}
                    </button>
                  </td>
                  <td>
                    <button onClick={() => remove(a.id)} disabled={deletingId === a.id}>
                      {deletingId === a.id ? "Deleting..." : "Delete"}
                    </button>
                  </td>
                </tr>
              ))}
              {activities.length === 0 && (
                <tr>
                  <td colSpan="7">No class activities uploaded yet.</td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>
    </StaffFeatureLayout>
  );
}
