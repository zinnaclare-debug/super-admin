import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../services/api";
import { requestSuperAdminDeleteCode } from "./requestSuperAdminDeleteCode";
import sessionsArt from "../../assets/academic-session/city-girl.svg";

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
  }, [schoolId]);

  const updateStatus = async (sessionId, status) => {
    let payload = { status };

    if (status === "current") {
      const currentSelectionCode = window.prompt("Enter current selection code (4722):");
      if (currentSelectionCode === null) {
        return;
      }
      payload = { status, current_selection_code: currentSelectionCode.trim() };
    }

    setUpdatingId(sessionId);
    try {
      await api.patch(
        `/api/super-admin/schools/${schoolId}/academic-sessions/${sessionId}/status`,
        payload
      );
      await load();
    } catch (err) {
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || err.response?.data?.message || "Failed to update session status.");
    } finally {
      setUpdatingId(null);
    }
  };

  const deleteSession = async (sessionId) => {
    const deleteCode = requestSuperAdminDeleteCode("this academic session", "delete");
    if (!deleteCode) {
      return;
    }

    setUpdatingId(sessionId);
    try {
      await api.delete(`/api/super-admin/schools/${schoolId}/academic-sessions/${sessionId}`, {
        data: { delete_code: deleteCode },
      });
      await load();
    } catch (err) {
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || err.response?.data?.message || "Failed to delete session.");
    } finally {
      setUpdatingId(null);
    }
  };

  return (
    <div className="sa-page sa-page--sessions">
      <section className="sa-page-hero">
        <div>
          <span className="sa-page-eyebrow">Academic control</span>
          <h1>Academic Sessions</h1>
          <p>Review the school cycle, select its current session, and protect its historical records.</p>
        </div>
        <img className="sa-page-art" src={sessionsArt} alt="" aria-hidden="true" />
      </section>
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
        <div style={{ display: "flex", gap: 8 }}>
          <button onClick={() => navigate(`/super-admin/schools/${schoolId}/information`)}>
            Information
          </button>
          <button onClick={() => navigate(-1)}>Back</button>
        </div>
      </div>

      {loading ? (
        <p>Loading...</p>
      ) : (
        <div className="sa-table-wrap"><table className="sa-table" border="1" cellPadding="10" cellSpacing="0" width="100%">
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
                  <span className={`sa-status ${session.status === "current" ? "sa-status--current" : session.status === "completed" ? "sa-status--completed" : "sa-status--danger"}`}>{formatSessionStatus(session.status)}</span>
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

                  {session.status === "completed" && (
                    <>
                      <button
                        onClick={() => updateStatus(session.id, "current")}
                        disabled={updatingId === session.id}
                      >
                        {updatingId === session.id ? "Updating..." : "Set Current"}
                      </button>
                      <span style={{ marginLeft: 8 }}>Is Completed</span>
                    </>
                  )}

                  <button
                    onClick={() => deleteSession(session.id)}
                    disabled={updatingId === session.id}
                    style={{ marginLeft: 8, color: "#b91c1c" }}
                  >
                    {updatingId === session.id ? "Deleting..." : "Delete"}
                  </button>
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
        </table></div>
      )}
    </div>
  );
}

