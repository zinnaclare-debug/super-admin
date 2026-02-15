import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";

export default function VirtualClassHome() {
  const navigate = useNavigate();
  const [items, setItems] = useState([]);
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [deletingId, setDeletingId] = useState(null);
  const [termSubjectId, setTermSubjectId] = useState("");
  const [subjectId, setSubjectId] = useState("");
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [meetingLink, setMeetingLink] = useState("");
  const [startsAt, setStartsAt] = useState("");

  const load = async (selectedSubjectId = "") => {
    setLoading(true);
    try {
      const [listRes, subjectsRes] = await Promise.all([
        api.get("/api/staff/virtual-classes", {
          params: selectedSubjectId ? { subject_id: selectedSubjectId } : {},
        }),
        api.get("/api/staff/virtual-classes/assigned-subjects"),
      ]);
      setItems(listRes.data?.data || []);
      setSubjects(subjectsRes.data?.data || []);
    } catch {
      alert("Failed to load virtual classes");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const submit = async (e) => {
    e.preventDefault();
    if (!termSubjectId) return alert("Select an assigned subject");
    if (!meetingLink) return alert("Enter Zoom link");

    setSaving(true);
    try {
      await api.post("/api/staff/virtual-classes", {
        term_subject_id: Number(termSubjectId),
        title,
        description: description || null,
        meeting_link: meetingLink,
        starts_at: startsAt ? new Date(startsAt).toISOString() : null,
      });

      setTermSubjectId("");
      setTitle("");
      setDescription("");
      setMeetingLink("");
      setStartsAt("");
      await load(subjectId);
      alert("Virtual class saved");
    } catch (err) {
      alert(err?.response?.data?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  };

  const remove = async (id) => {
    if (!window.confirm("Delete this virtual class link?")) return;
    setDeletingId(id);
    try {
      await api.delete(`/api/staff/virtual-classes/${id}`);
      await load(subjectId);
      alert("Deleted");
    } catch (err) {
      alert(err?.response?.data?.message || "Delete failed");
    } finally {
      setDeletingId(null);
    }
  };

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h2>Virtual Class (Staff)</h2>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      <div style={{ marginTop: 14, border: "1px solid #ddd", padding: 12, borderRadius: 10 }}>
        <h3 style={{ marginTop: 0 }}>Create Zoom Class Link</h3>
        <form onSubmit={submit} style={{ display: "grid", gap: 10, maxWidth: 620 }}>
          <select value={termSubjectId} onChange={(e) => setTermSubjectId(e.target.value)} required>
            <option value="">Select assigned subject</option>
            {subjects.map((s) => (
              <option key={s.term_subject_id} value={s.term_subject_id}>
                {s.subject_name} - {s.class_name} ({s.term_name})
              </option>
            ))}
          </select>

          <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Class title" required />
          <input
            value={meetingLink}
            onChange={(e) => setMeetingLink(e.target.value)}
            placeholder="Zoom link (https://...zoom.us/...)"
            required
          />
          <input
            type="datetime-local"
            value={startsAt}
            onChange={(e) => setStartsAt(e.target.value)}
            placeholder="Start date/time"
          />
          <input
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Description (optional)"
          />
          <button type="submit" disabled={saving}>
            {saving ? "Saving..." : "Save Link"}
          </button>
        </form>
      </div>

      <div style={{ marginTop: 16 }}>
        <div style={{ marginBottom: 10 }}>
          <label>
            Filter by subject:{" "}
            <select
              value={subjectId}
              onChange={async (e) => {
                const v = e.target.value;
                setSubjectId(v);
                await load(v);
              }}
            >
              <option value="">All</option>
              {Array.from(new Map(subjects.map((s) => [s.subject_id, s])).values()).map((s) => (
                <option key={s.subject_id} value={s.subject_id}>
                  {s.subject_name}
                </option>
              ))}
            </select>
          </label>
        </div>

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
                <th>Start Time</th>
                <th style={{ width: 160 }}>Meeting Link</th>
                <th style={{ width: 120 }}>Option</th>
              </tr>
            </thead>
            <tbody>
              {items.map((x, idx) => (
                <tr key={x.id}>
                  <td>{idx + 1}</td>
                  <td>{x.title}</td>
                  <td>{x.subject_name || "-"}</td>
                  <td>{x.class_name || "-"}</td>
                  <td>{x.term_name || "-"}</td>
                  <td>{x.starts_at ? new Date(x.starts_at).toLocaleString() : "-"}</td>
                  <td>
                    <a href={x.meeting_link} target="_blank" rel="noreferrer">
                      Join
                    </a>
                  </td>
                  <td>
                    <button onClick={() => remove(x.id)} disabled={deletingId === x.id}>
                      {deletingId === x.id ? "Deleting..." : "Delete"}
                    </button>
                  </td>
                </tr>
              ))}
              {items.length === 0 && (
                <tr>
                  <td colSpan="8">No virtual class links added yet.</td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

