import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";
import cityGirlArt from "../../../assets/academic-session/city-girl.svg";
import familyArt from "../../../assets/academic-session/family.svg";
import trueFriendsArt from "../../../assets/academic-session/true-friends.svg";
import "../../shared/PaymentsShowcase.css";
import "../AcademicSession.css";

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
    <div className="payx-page payx-page--admin">
      <section className="payx-hero academic-session__hero">
        <div>
          <span className="payx-pill">School Admin Academic Session</span>
          <h2 className="payx-title">Keep sessions organized with a clearer academic session workspace.</h2>
          <p className="payx-subtitle">
            Review your academic sessions, move into details, and create or edit sessions from the same polished experience used across the admin pages.
          </p>
          <div className="payx-meta">
            <span>{sessions.length} sessions</span>
            <span>{sessions.filter((row) => String(row.status).toLowerCase() === "current").length} current</span>
            <span>{sessions.filter((row) => String(row.status).toLowerCase() === "completed").length} completed</span>
          </div>
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main academic-session__art-main">
            <img src={cityGirlArt} alt="" />
          </div>
          <div className="payx-art payx-art--card academic-session__art-card">
            <img src={familyArt} alt="" />
          </div>
          <div className="payx-art payx-art--online academic-session__art-online">
            <img src={trueFriendsArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel academic-session__panel">
        <div className="academic-session__toolbar">
          <div>
            <h3>Academic Sessions</h3>
            <p>Manage academic sessions. Class structure now comes from Branding - Create Class.</p>
          </div>
          <button className="payx-btn" onClick={() => navigate("/school/admin/academic_session/manage")}>
            + Create Session
          </button>
        </div>

        <div className="payx-card">
          <div className="payx-table-wrap">
            <table className="payx-table">
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
                        className="academic-session__link-btn"
                      >
                        {session.academic_year || session.session_name || "N/A"}
                      </button>
                    </td>
                    <td>
                      <strong>{formatSessionStatus(session.status)}</strong>
                    </td>
                    <td>
                      <button
                        className="payx-btn payx-btn--soft"
                        onClick={() =>
                          navigate("/school/admin/academic_session/manage", { state: { edit: session } })
                        }
                      >
                        Edit
                      </button>
                    </td>
                  </tr>
                ))}

                {sessions.length === 0 ? (
                  <tr>
                    <td colSpan="4">No sessions yet.</td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  );
}
