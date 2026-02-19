import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";

export default function AcademicSessions() {
  const navigate = useNavigate();
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    try {
      // TODO: replace with your real endpoint
      const res = await api.get("/api/school-admin/academic-sessions");
      setSessions(res.data.data || []);
    } catch (e) {
      alert("Failed to load academic sessions");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  if (loading) return <p>Loading sessions...</p>;

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <button onClick={() => navigate('/school/admin/academic_session/manage')}>
          + Create Session
        </button>
      </div>

      <table border="1" cellPadding="10" cellSpacing="0" width="100%" style={{ marginTop: 14 }}>
        <thead>
          <tr>
            <th>S/N</th>
            <th>Academic Year</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          {sessions.map((s, idx) => (
            <tr key={s.id}>
              <td>{idx + 1}</td>
              <td>
                <button
                  onClick={() => navigate(`/school/admin/academic_session/${s.id}`)}
                  style={{ background: "transparent", border: "none", color: "#2563eb", cursor: "pointer", fontWeight: "bold" }}
                >
                  {s.academic_year || s.session_name || "N/A"}
                </button>
              </td>
              <td>
                <strong>{s.status === "current" ? "Current" : "Completed"}</strong>
              </td>
              <td>
                <button
                  onClick={() =>
                    navigate("/school/admin/academic_session/manage", { state: { edit: s } })
                  }
                >
                  Edit
                </button>{" "}
                <button
                  onClick={async () => {
                    if (!window.confirm("Delete this academic session?")) return;
                    try {
                      await api.delete(`/api/school-admin/academic-sessions/${s.id}`);
                      await load();
                    } catch (e) {
                      alert(e.response?.data?.message || "Failed to delete session");
                    }
                  }}
                  style={{ color: "red" }}
                >
                  Delete
                </button>{" "}
                <button
                  onClick={async () => {
                    try {
                      await api.patch(`/api/school-admin/academic-sessions/${s.id}/status`, {
                        status: s.status === "current" ? "completed" : "current",
                      });
                      await load();
                    } catch (e) {
                      alert("Failed to toggle status");
                    }
                  }}
                >
                  {s.status === "current" ? "Set Completed" : "Set Current"}
                </button>
              </td>
            </tr>
          ))}

          {sessions.length === 0 && (
            <tr>
              <td colSpan="4">No sessions yet.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
