import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";

const formatSessionStatus = (status) => {
  const value = String(status || "").toLowerCase();
  if (value === "current") return "Current";
  if (value === "completed") return "Completed";
  if (value === "pending") return "Pending";
  return "Pending";
};

export default function AcademicSessions() {
  const navigate = useNavigate();
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    try {
      const res = await api.get("/api/school-admin/academic-sessions");
      setSessions(res.data.data || []);
    } catch {
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
        <button onClick={() => navigate("/school/admin/academic_session/manage")}>
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
          {sessions.map((session, idx) => (
            <tr key={session.id}>
              <td>{idx + 1}</td>
              <td>
                <button
                  onClick={() => navigate(`/school/admin/academic_session/${session.id}`)}
                  style={{
                    background: "transparent",
                    border: "none",
                    color: "#2563eb",
                    cursor: "pointer",
                    fontWeight: "bold",
                  }}
                >
                  {session.academic_year || session.session_name || "N/A"}
                </button>
              </td>
              <td>
                <strong>{formatSessionStatus(session.status)}</strong>
              </td>
              <td>
                <button
                  onClick={() =>
                    navigate("/school/admin/academic_session/manage", { state: { edit: session } })
                  }
                >
                  Edit
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
