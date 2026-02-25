import { useEffect, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import api from "../../services/api";

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
    // eslint-disable-next-line react-hooks/exhaustive-deps
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

  const createSession = async (e) => {
    e.preventDefault();
    try {
      await api.post("/api/school-admin/academic-sessions", {
        session_name: sessionName,
        academic_year: academicYear,
      });
      setShowCreate(false);
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
      setShowEdit(false);
      setEditing(null);
      await load();
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to update session");
    }
  };

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div>
          <p style={{ marginTop: 6, opacity: 0.75 }}>
            Manage academic sessions. Class structure now comes from Branding - Create Class.
          </p>
        </div>
        <button onClick={openCreate} style={{ padding: "10px 14px", borderRadius: 8 }}>
          + Create Session
        </button>
      </div>

      {loading ? (
        <p>Loading...</p>
      ) : (
        <table border="1" cellPadding="10" cellSpacing="0" width="100%" style={{ marginTop: 16 }}>
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
                    style={{
                      background: "transparent",
                      border: "none",
                      color: "#2563eb",
                      cursor: "pointer",
                      fontWeight: "bold",
                    }}
                  >
                    {row.session_name}
                  </button>
                </td>
                <td>{row.academic_year || "N/A"}</td>
                <td>
                  <strong>{formatSessionStatus(row.status)}</strong>
                </td>
                <td>
                  <button onClick={() => openEdit(row)}>Edit</button>
                </td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr>
                <td colSpan="5" style={{ textAlign: "center", opacity: 0.7 }}>
                  No academic sessions yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      )}

      {showCreate && (
        <div style={{ marginTop: 20, border: "1px solid #ddd", padding: 16, borderRadius: 10 }}>
          <h3>Create Academic Session</h3>
          <form onSubmit={createSession}>
            <input
              value={sessionName}
              onChange={(e) => setSessionName(e.target.value)}
              placeholder="e.g. 2026/2027"
              style={{ width: 260, padding: 10 }}
              required
            />
            <input
              value={academicYear}
              onChange={(e) => setAcademicYear(e.target.value)}
              placeholder="e.g. 2026-2027"
              style={{ width: 260, padding: 10, marginLeft: 10 }}
            />
            <div style={{ marginTop: 14, display: "flex", gap: 10 }}>
              <button type="submit">Create</button>
              <button type="button" onClick={() => setShowCreate(false)}>
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {showEdit && editing && (
        <div style={{ marginTop: 20, border: "1px solid #ddd", padding: 16, borderRadius: 10 }}>
          <h3>Edit Academic Session</h3>
          <form onSubmit={updateSession}>
            <input
              value={sessionName}
              onChange={(e) => setSessionName(e.target.value)}
              placeholder="e.g. 2026/2027"
              style={{ width: 260, padding: 10 }}
              required
            />
            <input
              value={academicYear}
              onChange={(e) => setAcademicYear(e.target.value)}
              placeholder="e.g. 2026-2027"
              style={{ width: 260, padding: 10, marginLeft: 10 }}
            />
            <div style={{ marginTop: 14, display: "flex", gap: 10 }}>
              <button type="submit">Save</button>
              <button
                type="button"
                onClick={() => {
                  setShowEdit(false);
                  setEditing(null);
                }}
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}

