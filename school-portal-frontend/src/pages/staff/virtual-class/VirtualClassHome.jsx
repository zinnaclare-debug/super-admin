import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import aiResponseArt from "../../../assets/virtual-class/ai-response.svg";
import onlineMeetingsArt from "../../../assets/virtual-class/online-meetings.svg";
import vrChatArt from "../../../assets/virtual-class/vr-chat.svg";
import "../../shared/VirtualClassShowcase.css";

const PROVIDER_OPTIONS = [
  { value: "external", label: "External Link" },
  { value: "100ms", label: "100ms" },
  { value: "zoom", label: "Zoom" },
  { value: "google_meet", label: "Google Meet" },
  { value: "microsoft_teams", label: "Microsoft Teams" },
  { value: "livekit", label: "LiveKit" },
  { value: "jitsi", label: "Jitsi" },
  { value: "other", label: "Other" },
];

const createVirtualForm = () => ({
  term_subject_id: "",
  title: "",
  description: "",
  meeting_link: "",
  starts_at: "",
  provider: "external",
});

const createLiveForm = () => ({
  term_subject_id: "",
  title: "",
  description: "",
  meeting_link: "",
  starts_at: "",
  ends_at: "",
  provider: "100ms",
});

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

function providerLabel(value) {
  return PROVIDER_OPTIONS.find((option) => option.value === value)?.label || value || "External Link";
}

function typeLabel(value) {
  if (value === "live") return "Live Class";
  return "Virtual Class";
}

function statusLabel(value) {
  if (value === "scheduled") return "Scheduled";
  if (value === "ended") return "Ended";
  return "Live";
}

function toIso(value) {
  return value ? new Date(value).toISOString() : null;
}

export default function VirtualClassHome() {
  const [items, setItems] = useState([]);
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [virtualSaving, setVirtualSaving] = useState(false);
  const [liveSaving, setLiveSaving] = useState(false);
  const [deletingId, setDeletingId] = useState(null);
  const [actioningId, setActioningId] = useState(null);
  const [subjectId, setSubjectId] = useState("");
  const [typeFilter, setTypeFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [virtualForm, setVirtualForm] = useState(createVirtualForm());
  const [liveForm, setLiveForm] = useState(createLiveForm());

  const load = async (nextSubjectId = subjectId, nextTypeFilter = typeFilter, nextStatusFilter = statusFilter) => {
    setLoading(true);
    try {
      const params = {};
      if (nextSubjectId) params.subject_id = nextSubjectId;
      if (nextTypeFilter) params.class_type = nextTypeFilter;
      if (nextStatusFilter) params.status = nextStatusFilter;

      const [listRes, subjectsRes] = await Promise.all([
        api.get("/api/staff/virtual-classes", { params }),
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
    load("", "", "");
  }, []);

  const submitVirtual = async (e) => {
    e.preventDefault();
    if (!virtualForm.term_subject_id) return alert("Select an assigned subject");
    if (!virtualForm.meeting_link) return alert("Enter a meeting link");

    setVirtualSaving(true);
    try {
      await api.post("/api/staff/virtual-classes", {
        term_subject_id: Number(virtualForm.term_subject_id),
        class_type: "virtual",
        provider: virtualForm.provider,
        title: virtualForm.title,
        description: virtualForm.description || null,
        meeting_link: virtualForm.meeting_link,
        starts_at: toIso(virtualForm.starts_at),
      });

      setVirtualForm(createVirtualForm());
      await load();
      alert("Virtual class saved");
    } catch (err) {
      alert(err?.response?.data?.message || "Save failed");
    } finally {
      setVirtualSaving(false);
    }
  };

  const submitLive = async (mode) => {
    if (!liveForm.term_subject_id) return alert("Select an assigned subject");
    if (liveForm.provider !== "100ms" && !liveForm.meeting_link) return alert("Enter a meeting link");

    setLiveSaving(true);
    try {
      await api.post("/api/staff/virtual-classes", {
        term_subject_id: Number(liveForm.term_subject_id),
        class_type: "live",
        provider: liveForm.provider,
        status: mode === "schedule" ? "scheduled" : "live",
        title: liveForm.title,
        description: liveForm.description || null,
        meeting_link: liveForm.meeting_link || null,
        starts_at: toIso(liveForm.starts_at),
        ends_at: toIso(liveForm.ends_at),
      });

      setLiveForm(createLiveForm());
      await load();
      alert(mode === "schedule" ? "Live class scheduled" : "Live class started");
    } catch (err) {
      alert(err?.response?.data?.message || "Save failed");
    } finally {
      setLiveSaving(false);
    }
  };

  const triggerStatus = async (id, action) => {
    setActioningId(id);
    try {
      await api.post(`/api/staff/virtual-classes/${id}/${action}`);
      await load();
      alert(action === "start" ? "Live class started" : "Class ended");
    } catch (err) {
      alert(err?.response?.data?.message || "Action failed");
    } finally {
      setActioningId(null);
    }
  };

  const remove = async (item) => {
    if (!window.confirm(`Delete this ${item.class_type === "live" ? "live class" : "virtual class"}?`)) return;
    setDeletingId(item.id);
    try {
      await api.delete(`/api/staff/virtual-classes/${item.id}`);
      await load();
      alert("Deleted");
    } catch (err) {
      alert(err?.response?.data?.message || "Delete failed");
    } finally {
      setDeletingId(null);
    }
  };

  const uniqueSubjects = Array.from(new Map(subjects.map((subject) => [subject.subject_id, subject])).values());

  return (
    <StaffFeatureLayout title="Virtual Class (Staff)" showHeader={false}>
      <div className="vcx-page vcx-page--staff">
        <section className="vcx-hero">
          <div>
            <span className="vcx-pill">Staff Virtual Class</span>
            <h2 className="vcx-title">Manage virtual links and live classes by subject</h2>
            <p className="vcx-subtitle">
              Keep your normal virtual class links, then use the live class card below to start or schedule subject sessions
              using the same teaching setup.
            </p>
            <div className="vcx-meta">
              <span>{loading ? "Loading..." : `${items.length} session${items.length === 1 ? "" : "s"}`}</span>
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

        <div className="vcx-panel-grid">
          <section className="vcx-panel">
            <h3 style={{ marginTop: 0 }}>Virtual Class Link</h3>
            <p className="vcx-panel-copy">
              Use this for your regular class link. It stays simple and no longer forces Zoom only.
            </p>
            <form onSubmit={submitVirtual} className="vcx-form">
              <select
                className="vcx-field"
                value={virtualForm.term_subject_id}
                onChange={(e) => setVirtualForm((prev) => ({ ...prev, term_subject_id: e.target.value }))}
                required
              >
                <option value="">Select assigned subject</option>
                {subjects.map((s) => (
                  <option key={s.term_subject_id} value={s.term_subject_id}>
                    {s.subject_name} - {s.class_name} ({s.term_name})
                  </option>
                ))}
              </select>

              <input
                className="vcx-field"
                value={virtualForm.title}
                onChange={(e) => setVirtualForm((prev) => ({ ...prev, title: e.target.value }))}
                placeholder="Class title"
                required
              />
              <select
                className="vcx-field"
                value={virtualForm.provider}
                onChange={(e) => setVirtualForm((prev) => ({ ...prev, provider: e.target.value }))}
              >
                {PROVIDER_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <input
                className="vcx-field"
                value={virtualForm.meeting_link}
                onChange={(e) => setVirtualForm((prev) => ({ ...prev, meeting_link: e.target.value }))}
                placeholder="Meeting link (https://...)"
                required
              />
              <input
                className="vcx-field"
                type="datetime-local"
                value={virtualForm.starts_at}
                onChange={(e) => setVirtualForm((prev) => ({ ...prev, starts_at: e.target.value }))}
                placeholder="Start date/time"
              />
              <input
                className="vcx-field"
                value={virtualForm.description}
                onChange={(e) => setVirtualForm((prev) => ({ ...prev, description: e.target.value }))}
                placeholder="Description (optional)"
              />
              <button className="vcx-btn" type="submit" disabled={virtualSaving}>
                {virtualSaving ? "Saving..." : "Save Virtual Class"}
              </button>
            </form>
          </section>

          <section className="vcx-panel">
            <h3 style={{ marginTop: 0 }}>Live Class</h3>
            <p className="vcx-panel-copy">
              Start a subject session immediately or save it as scheduled, while still using the same class configuration.
            </p>
            <div className="vcx-badge-row">
              <span className="vcx-badge vcx-badge--100ms">Phase 2 Ready for 100ms</span>
            </div>
            {liveForm.provider === "100ms" ? (
              <p className="vcx-panel-copy">
                100ms rooms are created automatically for this live class. You can leave the link box empty and still start
                the classroom inside the app.
              </p>
            ) : null}
            <div className="vcx-form">
              <select
                className="vcx-field"
                value={liveForm.term_subject_id}
                onChange={(e) => setLiveForm((prev) => ({ ...prev, term_subject_id: e.target.value }))}
                required
              >
                <option value="">Select assigned subject</option>
                {subjects.map((s) => (
                  <option key={s.term_subject_id} value={s.term_subject_id}>
                    {s.subject_name} - {s.class_name} ({s.term_name})
                  </option>
                ))}
              </select>

              <input
                className="vcx-field"
                value={liveForm.title}
                onChange={(e) => setLiveForm((prev) => ({ ...prev, title: e.target.value }))}
                placeholder="Live class title"
                required
              />
              <select
                className="vcx-field"
                value={liveForm.provider}
                onChange={(e) => setLiveForm((prev) => ({ ...prev, provider: e.target.value }))}
              >
                {PROVIDER_OPTIONS.filter((option) => option.value !== "external").map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <input
                className="vcx-field"
                value={liveForm.meeting_link}
                onChange={(e) => setLiveForm((prev) => ({ ...prev, meeting_link: e.target.value }))}
                placeholder={liveForm.provider === "100ms" ? "Optional external fallback link" : "Live session link (https://...)"}
                required={liveForm.provider !== "100ms"}
              />
              <input
                className="vcx-field"
                type="datetime-local"
                value={liveForm.starts_at}
                onChange={(e) => setLiveForm((prev) => ({ ...prev, starts_at: e.target.value }))}
                placeholder="Start date/time"
              />
              <input
                className="vcx-field"
                type="datetime-local"
                value={liveForm.ends_at}
                onChange={(e) => setLiveForm((prev) => ({ ...prev, ends_at: e.target.value }))}
                placeholder="End date/time"
              />
              <input
                className="vcx-field"
                value={liveForm.description}
                onChange={(e) => setLiveForm((prev) => ({ ...prev, description: e.target.value }))}
                placeholder="Description (optional)"
              />
              <div className="vcx-form-actions">
                <button className="vcx-btn vcx-btn--soft" type="button" disabled={liveSaving} onClick={() => submitLive("schedule")}>
                  {liveSaving ? "Saving..." : "Save as Scheduled"}
                </button>
                <button className="vcx-btn" type="button" disabled={liveSaving} onClick={() => submitLive("start")}>
                  {liveSaving ? "Starting..." : "Start Live Class"}
                </button>
              </div>
            </div>
          </section>
        </div>

        <section className="vcx-panel">
          <div className="vcx-filter vcx-filter--triple">
            <div>
              <label htmlFor="staff-vc-filter">Filter by subject</label>
              <select
                id="staff-vc-filter"
                className="vcx-field"
                value={subjectId}
                onChange={async (e) => {
                  const value = e.target.value;
                  setSubjectId(value);
                  await load(value, typeFilter, statusFilter);
                }}
              >
                <option value="">All subjects</option>
                {uniqueSubjects.map((s) => (
                  <option key={s.subject_id} value={s.subject_id}>
                    {s.subject_name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label htmlFor="staff-vc-type-filter">Filter by type</label>
              <select
                id="staff-vc-type-filter"
                className="vcx-field"
                value={typeFilter}
                onChange={async (e) => {
                  const value = e.target.value;
                  setTypeFilter(value);
                  await load(subjectId, value, statusFilter);
                }}
              >
                <option value="">All types</option>
                <option value="virtual">Virtual Class</option>
                <option value="live">Live Class</option>
              </select>
            </div>

            <div>
              <label htmlFor="staff-vc-status-filter">Filter by status</label>
              <select
                id="staff-vc-status-filter"
                className="vcx-field"
                value={statusFilter}
                onChange={async (e) => {
                  const value = e.target.value;
                  setStatusFilter(value);
                  await load(subjectId, typeFilter, value);
                }}
              >
                <option value="">All statuses</option>
                <option value="live">Live</option>
                <option value="scheduled">Scheduled</option>
                <option value="ended">Ended</option>
              </select>
            </div>
          </div>

          {loading ? (
            <p className="vcx-state vcx-state--loading">Loading class sessions...</p>
          ) : (
            <div className="vcx-table-wrap">
              <table className="vcx-table">
                <thead>
                  <tr>
                    <th style={{ width: 70 }}>S/N</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Provider</th>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th style={{ width: 120 }}>Join</th>
                    <th style={{ width: 240 }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item, idx) => (
                    <tr key={item.id}>
                      <td>{idx + 1}</td>
                      <td>{item.title}</td>
                      <td>
                        <span className={`vcx-badge vcx-badge--${item.class_type || "virtual"}`}>{typeLabel(item.class_type)}</span>
                      </td>
                      <td>
                        <span className={`vcx-badge vcx-badge--${item.status || "live"}`}>{statusLabel(item.status)}</span>
                      </td>
                      <td>{providerLabel(item.provider)}</td>
                      <td>{item.subject_name || "-"}</td>
                      <td>{item.class_name || "-"}</td>
                      <td>{formatDate(item.starts_at || item.live_started_at)}</td>
                      <td>{formatDate(item.ends_at || item.live_ended_at)}</td>
                      <td>
                        {item.provider === "100ms" && item.class_type === "live" ? (
                          <Link className="vcx-link" to={`/staff/virtual-class/live/${item.id}`}>
                            {item.status === "scheduled" ? "Open Room" : "Enter Room"}
                          </Link>
                        ) : (
                          <a className="vcx-link" href={item.meeting_link} target="_blank" rel="noreferrer">
                            Join
                          </a>
                        )}
                      </td>
                      <td>
                        <div className="vcx-action-row">
                          {item.class_type === "live" && item.status === "scheduled" ? (
                            <button
                              className="vcx-btn vcx-btn--soft"
                              onClick={() => triggerStatus(item.id, "start")}
                              disabled={actioningId === item.id}
                            >
                              {actioningId === item.id ? "Starting..." : "Start"}
                            </button>
                          ) : null}
                          {item.class_type === "live" && item.status === "live" ? (
                            <button className="vcx-btn" onClick={() => triggerStatus(item.id, "end")} disabled={actioningId === item.id}>
                              {actioningId === item.id ? "Ending..." : "End"}
                            </button>
                          ) : null}
                          <button className="vcx-btn vcx-btn--soft" onClick={() => remove(item)} disabled={deletingId === item.id}>
                            {deletingId === item.id ? "Deleting..." : "Delete"}
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {items.length === 0 ? (
                    <tr>
                      <td colSpan="11">No virtual or live class sessions added yet.</td>
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
