import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import aiResponseArt from "../../../assets/virtual-class/ai-response.svg";
import onlineMeetingsArt from "../../../assets/virtual-class/online-meetings.svg";
import vrChatArt from "../../../assets/virtual-class/vr-chat.svg";
import "../../shared/VirtualClassShowcase.css";

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export default function VirtualClassHome() {
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
    <StaffFeatureLayout title="Virtual Class (Staff)">
      <div className="vcx-page vcx-page--staff">
        <section className="vcx-hero">
          <div>
            <span className="vcx-pill">Staff Virtual Class</span>
            <h2 className="vcx-title">Create and share live class meeting links</h2>
            <p className="vcx-subtitle">
              Schedule class sessions, publish join links, and manage virtual classes by subject in one workspace.
            </p>
            <div className="vcx-meta">
              <span>{loading ? "Loading..." : `${items.length} live class link${items.length === 1 ? "" : "s"}`}</span>
              <span>{`${subjects.length} assigned subject${subjects.length === 1 ? "" : "s"}`}</span>
            </div>
          </div>

          <div className="vcx-hero-art" aria-hidden="true">
            <div className="vcx-art vcx-art--main">
              <img src={aiResponseArt} alt="" />
            </div>
            <div className="vcx-art vcx-art--meeting">
              <img src={onlineMeetingsArt} alt="" />
            </div>
            <div className="vcx-art vcx-art--chat">
              <img src={vrChatArt} alt="" />
            </div>
          </div>
        </section>

        <section className="vcx-panel">
          <h3 style={{ marginTop: 0 }}>Create Zoom Class Link</h3>
          <form onSubmit={submit} className="vcx-form">
            <select className="vcx-field" value={termSubjectId} onChange={(e) => setTermSubjectId(e.target.value)} required>
              <option value="">Select assigned subject</option>
              {subjects.map((s) => (
                <option key={s.term_subject_id} value={s.term_subject_id}>
                  {s.subject_name} - {s.class_name} ({s.term_name})
                </option>
              ))}
            </select>

            <input className="vcx-field" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Class title" required />
            <input
              className="vcx-field"
              value={meetingLink}
              onChange={(e) => setMeetingLink(e.target.value)}
              placeholder="Zoom link (https://...zoom.us/...)"
              required
            />
            <input
              className="vcx-field"
              type="datetime-local"
              value={startsAt}
              onChange={(e) => setStartsAt(e.target.value)}
              placeholder="Start date/time"
            />
            <input
              className="vcx-field"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Description (optional)"
            />
            <button className="vcx-btn" type="submit" disabled={saving}>
              {saving ? "Saving..." : "Save Link"}
            </button>
          </form>
        </section>

        <section className="vcx-panel">
          <div className="vcx-filter">
            <label htmlFor="staff-vc-filter">Filter by subject</label>
            <select
              id="staff-vc-filter"
              className="vcx-field"
              value={subjectId}
              onChange={async (e) => {
                const v = e.target.value;
                setSubjectId(v);
                await load(v);
              }}
              style={{ minWidth: 260 }}
            >
              <option value="">All</option>
              {Array.from(new Map(subjects.map((s) => [s.subject_id, s])).values()).map((s) => (
                <option key={s.subject_id} value={s.subject_id}>
                  {s.subject_name}
                </option>
              ))}
            </select>
          </div>

          {loading ? (
            <p className="vcx-state vcx-state--loading">Loading virtual classes...</p>
          ) : (
            <div className="vcx-table-wrap">
              <table className="vcx-table">
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
                      <td>{formatDate(x.starts_at)}</td>
                      <td>
                        <a className="vcx-link" href={x.meeting_link} target="_blank" rel="noreferrer">
                          Join
                        </a>
                      </td>
                      <td>
                        <button className="vcx-btn vcx-btn--soft" onClick={() => remove(x.id)} disabled={deletingId === x.id}>
                          {deletingId === x.id ? "Deleting..." : "Delete"}
                        </button>
                      </td>
                    </tr>
                  ))}
                  {items.length === 0 ? (
                    <tr>
                      <td colSpan="8">No virtual class links added yet.</td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>
    </StaffFeatureLayout>
  );
}
