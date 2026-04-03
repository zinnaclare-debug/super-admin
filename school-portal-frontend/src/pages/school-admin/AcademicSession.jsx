import { useEffect, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import api from "../../services/api";
import cityGirlArt from "../../assets/academic-session/city-girl.svg";
import familyArt from "../../assets/academic-session/family.svg";
import trueFriendsArt from "../../assets/academic-session/true-friends.svg";
import "../shared/PaymentsShowcase.css";
import "./AcademicSession.css";

const formatSessionStatus = (status) => {
  const value = String(status || "").toLowerCase();
  if (value === "current") return "Current";
  if (value === "completed") return "Completed";
  return "Pending";
};

export default function AcademicSession() {
  const navigate = useNavigate();
  const location = useLocation();

  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showCreate, setShowCreate] = useState(false);
  const [showEdit, setShowEdit] = useState(false);
  const [editing, setEditing] = useState(null);
  const [sessionName, setSessionName] = useState("");
  const [academicYear, setAcademicYear] = useState("");

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/school-admin/academic-sessions");
      setRows(res.data?.data || []);
    } catch {
      alert("Failed to load academic sessions");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  useEffect(() => {
    if (!location?.pathname?.includes("academic_session/manage")) return;
    const editRow = location?.state?.edit;
    if (editRow) {
      openEdit(editRow);
    } else {
      openCreate();
    }
  }, [location?.pathname, location?.state]);

  const openCreate = () => {
    setSessionName("");
    setAcademicYear("");
    setShowCreate(true);
    setShowEdit(false);
    setEditing(null);
  };

  const openEdit = (row) => {
    setEditing(row);
    setSessionName(row.session_name || "");
    setAcademicYear(row.academic_year || "");
    setShowEdit(true);
    setShowCreate(false);
  };

  const closePanels = () => {
    setShowCreate(false);
    setShowEdit(false);
    setEditing(null);
  };

  const createSession = async (e) => {
    e.preventDefault();
    try {
      await api.post("/api/school-admin/academic-sessions", {
        session_name: sessionName,
        academic_year: academicYear,
      });
      closePanels();
      await load();
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to create session");
    }
  };

  const updateSession = async (e) => {
    e.preventDefault();
    if (!editing?.id) return;

    try {
      await api.put(`/api/school-admin/academic-sessions/${editing.id}`, {
        session_name: sessionName,
        academic_year: academicYear,
      });
      closePanels();
      await load();
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to update session");
    }
  };

  const renderFormPanel = () => {
    if (!showCreate && !showEdit) return null;

    const isEdit = showEdit && editing;

    return (
      <div className="payx-card academic-session__form-card">
        <div className="academic-session__form-head">
          <div>
            <h3>{isEdit ? "Edit Academic Session" : "Create Academic Session"}</h3>
            <p>Set the session label and academic year used across the school workflow.</p>
          </div>
        </div>

        <form onSubmit={isEdit ? updateSession : createSession} className="academic-session__form-grid">
          <input
            value={sessionName}
            onChange={(e) => setSessionName(e.target.value)}
            placeholder="e.g. 2026/2027"
            required
          />
          <input
            value={academicYear}
            onChange={(e) => setAcademicYear(e.target.value)}
            placeholder="e.g. 2026-2027"
          />
          <div className="academic-session__actions">
            <button type="submit" className="payx-btn">{isEdit ? "Save" : "Create"}</button>
            <button type="button" className="payx-btn payx-btn--soft" onClick={closePanels}>
              Cancel
            </button>
          </div>
        </form>
      </div>
    );
  };

  return (
    <div className="payx-page payx-page--admin">
      <section className="payx-hero academic-session__hero">
        <div>
          <span className="payx-pill">School Admin Academic Session</span>
          <h2 className="payx-title">Keep sessions organized with a clearer academic session workspace.</h2>
          <p className="payx-subtitle">
            Create sessions, update academic years, and move into session details from the same polished experience used across the admin pages.
          </p>
          <div className="payx-meta">
            <span>{rows.length} sessions</span>
            <span>{rows.filter((row) => String(row.status).toLowerCase() === "current").length} current</span>
            <span>{rows.filter((row) => String(row.status).toLowerCase() === "completed").length} completed</span>
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
          <button onClick={openCreate} className="payx-btn">+ Create Session</button>
        </div>

        {renderFormPanel()}

        {loading ? (
          <p className="payx-state payx-state--loading">Loading...</p>
        ) : (
          <div className="payx-card">
            <div className="payx-table-wrap">
              <table className="payx-table">
                <thead>
                  <tr>
                    <th>S/N</th>
                    <th>Session</th>
                    <th>Academic Year</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row, idx) => (
                    <tr key={row.id}>
                      <td>{idx + 1}</td>
                      <td>
                        <button
                          onClick={() => navigate(`/school/admin/academic_session/${row.id}`)}
                          className="academic-session__link-btn"
                        >
                          {row.session_name}
                        </button>
                      </td>
                      <td>{row.academic_year || "N/A"}</td>
                      <td>
                        <strong>{formatSessionStatus(row.status)}</strong>
                      </td>
                      <td>
                        <button className="payx-btn payx-btn--soft" onClick={() => openEdit(row)}>Edit</button>
                      </td>
                    </tr>
                  ))}
                  {rows.length === 0 ? (
                    <tr>
                      <td colSpan="5">No academic sessions yet.</td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </section>
    </div>
  );
}
