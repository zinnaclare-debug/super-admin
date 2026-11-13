import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../services/api";
import sessionsArt from "../../assets/academic-session/city-girl.svg";
import { requestSuperAdminDeleteCode } from "./requestSuperAdminDeleteCode";

const formatSessionStatus = (status) => {
  const value = String(status || "").toLowerCase();
  if (value === "current") return "Current";
  if (value === "completed") return "Completed";
  return "Pending";
};

export default function SchoolAcademicSessions() {
  const navigate = useNavigate();
  const { schoolId } = useParams();
  const [loading, setLoading] = useState(true);
  const [school, setSchool] = useState(null);
  const [sessions, setSessions] = useState([]);
  const [deletingId, setDeletingId] = useState(null);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/super-admin/schools/${schoolId}/academic-sessions`);
      setSchool(res.data.school || null);
      setSessions(res.data.data || []);
    } catch (err) {
      alert(err.response?.data?.message || "Failed to load school academic sessions.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [schoolId]);

  const deleteSession = async (session) => {
    const deleteCode = requestSuperAdminDeleteCode(`the ${session.session_name || "selected"} academic session`, "delete");
    if (!deleteCode) return;

    setDeletingId(session.id);
    try {
      const res = await api.delete(`/api/super-admin/schools/${schoolId}/academic-sessions/${session.id}`, { data: { delete_code: deleteCode } });
      setSessions((current) => current.filter((item) => Number(item.id) !== Number(session.id)));
      alert(res.data?.message || "Academic session deleted successfully.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to delete academic session.");
    } finally {
      setDeletingId(null);
    }
  };

  return (
    <div className="sa-page sa-page--sessions">
      <section className="sa-page-hero">
        <div><span className="sa-page-eyebrow">Academic overview</span><h1>Academic Sessions</h1><p>Review each school cycle and its current session. Session controls are managed by the school.</p></div>
        <img className="sa-page-art" src={sessionsArt} alt="" aria-hidden="true" />
      </section>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
        <div><h2 style={{ margin: 0 }}>School Academic Sessions</h2><p style={{ margin: "6px 0 0", opacity: 0.75 }}>{school?.name ? `School: ${school.name}` : "School sessions"}</p></div>
        <div style={{ display: "flex", gap: 8 }}><button onClick={() => navigate(`/super-admin/schools/${schoolId}/information`)}>Information</button><button onClick={() => navigate(-1)}>Back</button></div>
      </div>
      {loading ? <p>Loading...</p> : <div className="sa-table-wrap"><table className="sa-table" border="1" cellPadding="10" cellSpacing="0" width="100%"><thead><tr><th>S/N</th><th>Session</th><th>Academic Year</th><th>Status</th><th>Action</th></tr></thead><tbody>{sessions.map((session, idx) => <tr key={session.id}><td>{idx + 1}</td><td>{session.session_name || "-"}</td><td>{session.academic_year || "-"}</td><td><span className={`sa-status ${session.status === "current" ? "sa-status--current" : session.status === "completed" ? "sa-status--completed" : "sa-status--danger"}`}>{formatSessionStatus(session.status)}</span></td><td><button type="button" className="sai-danger-btn" onClick={() => deleteSession(session)} disabled={deletingId === session.id}>{deletingId === session.id ? "Deleting..." : "Delete"}</button></td></tr>)}{sessions.length === 0 && <tr><td colSpan="5" style={{ textAlign: "center" }}>No academic sessions yet.</td></tr>}</tbody></table></div>}
    </div>
  );
}