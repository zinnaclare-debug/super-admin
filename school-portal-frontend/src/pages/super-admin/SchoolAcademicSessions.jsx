import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../services/api";

const formatSessionStatus = (status) => {
  const value = String(status || "").toLowerCase();
  if (value === "current") return "Current";
  if (value === "completed") return "Completed";
  if (value === "pending") return "Pending";
  return "Pending";
};

export default function SchoolAcademicSessions() {
  const navigate = useNavigate();
  const { schoolId } = useParams();
  const [loading, setLoading] = useState(true);
  const [updatingId, setUpdatingId] = useState(null);
  const [school, setSchool] = useState(null);
  const [sessions, setSessions] = useState([]);

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

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [schoolId]);

  const updateStatus = async (sessionId, status) => {
    setUpdatingId(sessionId);
    try {
      await api.patch(
        `/api/super-admin/schools/${schoolId}/academic-sessions/${sessionId}/status`,
        { status }
      );
      await load();
    } catch (err) {
      alert(err.response?.data?.message || "Failed to update session status.");
    } finally {
      setUpdatingId(null);
    }
  };

  return (
    <div>
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          marginBottom: 12,
        }}
      >
        <div>
          <h2 style={{ margin: 0 }}>School Academic Sessions</h2>
          <p style={{ margin: "6px 0 0", opacity: 0.75 }}>
            {school?.name ? `School: ${school.name}` : "School sessions"}
          </p>
        </div>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      {loading ? (
        <p>Loading...</p>
      ) : (
        <table border="1" cellPadding="10" cellSpacing="0" width="100%">
          <thead>
            <tr>
              <th>S/N</th>
              <th>Session</th>
              <th>Academic Year</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            {sessions.map((session, idx) => (
              <tr key={session.id}>
                <td>{idx + 1}</td>
                <td>{session.session_name || "-"}</td>
                <td>{session.academic_year || "-"}</td>
                <td>
                  <strong>{formatSessionStatus(session.status)}</strong>
                </td>
                <td>
                  {session.status === "pending" && (
                    <button
                      onClick={() => updateStatus(session.id, "current")}
                      disabled={updatingId === session.id}
                    >
                      {updatingId === session.id ? "Updating..." : "Set Current"}
                    </button>
                  )}

                  {session.status === "current" && (
                    <button
                      onClick={() => updateStatus(session.id, "completed")}
                      disabled={updatingId === session.id}
                    >
                      {updatingId === session.id ? "Updating..." : "Set Completed"}
                    </button>
                  )}

                  {session.status === "completed" && <span>Is Completed</span>}
                </td>
              </tr>
            ))}

            {sessions.length === 0 && (
              <tr>
                <td colSpan="5" style={{ textAlign: "center" }}>
                  No academic sessions yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      )}
    </div>
  );
}
