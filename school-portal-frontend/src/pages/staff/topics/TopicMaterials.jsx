import { useEffect, useState } from "react";
import { useLocation, useParams } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

export default function TopicMaterials() {
  const { termSubjectId } = useParams();
  const location = useLocation();

  const [materials, setMaterials] = useState([]);
  const [loading, setLoading] = useState(true);
  const [deletingId, setDeletingId] = useState(null);

  const [title, setTitle] = useState("");
  const [file, setFile] = useState(null);
  const [uploading, setUploading] = useState(false);

  const meta = location.state;

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/staff/topics/subjects/${termSubjectId}/materials`);
      setMaterials(res.data.data || []);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load materials");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [termSubjectId]);

  const upload = async (e) => {
    e.preventDefault();
    if (!file) return alert("Choose a file (PDF/DOC/DOCX)");

    const fd = new FormData();
    if (title) fd.append("title", title);
    fd.append("file", file);

    setUploading(true);
    try {
      await api.post(`/api/staff/topics/subjects/${termSubjectId}/materials`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      setTitle("");
      setFile(null);
      await load();
      alert("Uploaded");
    } catch (e2) {
      alert(e2?.response?.data?.message || "Upload failed");
    } finally {
      setUploading(false);
    }
  };

  const deleteMaterial = async (id) => {
    const ok = window.confirm("Delete this material? This cannot be undone.");
    if (!ok) return;

    setDeletingId(id);
    try {
      await api.delete(`/api/staff/topics/subjects/${termSubjectId}/materials/${id}`);
      setMaterials((prev) => prev.filter((m) => m.id !== id));
    } catch (e) {
      alert(e?.response?.data?.message || "Delete failed");
    } finally {
      setDeletingId(null);
    }
  };

  return (
    <StaffFeatureLayout
      title="Subject Materials"
      subtitle={meta ? `${meta.subject_name} - ${meta.class_name} (${meta.term_name})` : ""}
    >
      <div style={{ marginTop: 14, border: "1px solid #ddd", padding: 12, borderRadius: 10 }}>
        <h3 style={{ marginTop: 0 }}>Upload</h3>

        <form onSubmit={upload} style={{ display: "grid", gap: 10, maxWidth: 520 }}>
          <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Title (optional)" />

          <input
            type="file"
            accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            onChange={(e) => setFile(e.target.files?.[0] || null)}
          />

          <button type="submit" disabled={uploading}>
            {uploading ? "Uploading..." : "Upload"}
          </button>
        </form>
      </div>

      <div style={{ marginTop: 16 }}>
        {loading ? (
          <p>Loading materials...</p>
        ) : (
          <table border="1" cellPadding="10" width="100%">
            <thead>
              <tr>
                <th style={{ width: 70 }}>S/N</th>
                <th>Title</th>
                <th>File</th>
                <th style={{ width: 140 }}>Action</th>
              </tr>
            </thead>
            <tbody>
              {materials.map((m, idx) => (
                <tr key={m.id}>
                  <td>{idx + 1}</td>
                  <td>{m.title || m.original_name || "-"}</td>
                  <td>{m.original_name || m.file_path}</td>
                  <td>
                    <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
                      <a href={m.file_url} target="_blank" rel="noreferrer">
                        View / Download
                      </a>
                      <button type="button" onClick={() => deleteMaterial(m.id)} disabled={deletingId === m.id}>
                        {deletingId === m.id ? "Deleting..." : "Delete"}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {materials.length === 0 && (
                <tr>
                  <td colSpan="4">No materials uploaded yet.</td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>
    </StaffFeatureLayout>
  );
}
