import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";
import { useNavigate, useLocation } from "react-router-dom";

const LEVEL_KEYS = ["nursery", "primary", "secondary"];

const DEFAULT_CLASS_STRUCTURE = {
  nursery: ["Nursery 1", "Nursery 2", "Nursery 3"],
  primary: ["Primary 1", "Primary 2", "Primary 3", "Primary 4", "Primary 5", "Primary 6"],
  secondary: [
    "JS1 (Grade 7)",
    "JS2 (Grade 8)",
    "JS3 (Grade 9)",
    "SS1 (Grade 10)",
    "SS2 (Grade 11)",
    "SS3 (Grade 12)",
  ],
};

const LEVEL_LABELS = {
  nursery: "Nursery",
  primary: "Primary",
  secondary: "Secondary",
};

export default function AcademicSession() {
  const navigate = useNavigate();
  const location = useLocation();

  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);

  // modal
  const [showCreate, setShowCreate] = useState(false);
  const [showEdit, setShowEdit] = useState(false);
  const [editing, setEditing] = useState(null);

  // form
  const [sessionName, setSessionName] = useState("");
  const [academicYear, setAcademicYear] = useState("");
  const [makeCurrent, setMakeCurrent] = useState(false);

  // ✅ checkbox state (what you requested)
  const [selectedLevels, setSelectedLevels] = useState({
    nursery: true,
    primary: true,
    secondary: true,
  });
  const [classStructure, setClassStructure] = useState(DEFAULT_CLASS_STRUCTURE);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/school-admin/academic-sessions");
      setRows(res.data.data || []);
    } catch (e) {
      alert("Failed to load academic sessions");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  useEffect(() => {
    if (location?.pathname?.includes("academic_session/manage")) {
      const editRow = location?.state?.edit;
      if (editRow) {
        openEdit(editRow);
      } else {
        openCreate();
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location?.pathname, location?.state]);

  const currentSession = useMemo(
    () => rows.find((r) => r.status === "current"),
    [rows]
  );

  const getSelectedLevelArray = () =>
    LEVEL_KEYS.filter((k) => selectedLevels[k]);

  const toggleLevel = (key) => {
    setSelectedLevels((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const openCreate = () => {
    setSessionName("");
    setAcademicYear("");
    setMakeCurrent(false);
    setSelectedLevels({ nursery: true, primary: true, secondary: true });
    setClassStructure(DEFAULT_CLASS_STRUCTURE);
    setShowCreate(true);
  };

  const openEdit = (row) => {
    console.log('openEdit', row);
    setEditing(row);
    setSessionName(row.session_name || "");
    setAcademicYear(row.academic_year || "");
    // row.levels is expected like ["nursery","secondary"]
    const rowLevels = Array.isArray(row.levels) ? row.levels : [];
    setSelectedLevels({
      nursery: rowLevels.includes("nursery"),
      primary: rowLevels.includes("primary"),
      secondary: rowLevels.includes("secondary"),
    });
    setShowEdit(true);
  };

  const createSession = async (e) => {
    e.preventDefault();

    const levels = getSelectedLevelArray();
    if (levels.length === 0) {
      alert("Select at least one level (Nursery, Primary or Secondary).");
      return;
    }

    const classStructurePayload = levels.reduce((acc, levelKey) => {
      const classNames = (classStructure[levelKey] || [])
        .map((name) => String(name || "").trim())
        .filter(Boolean);
      acc[levelKey] = classNames;
      return acc;
    }, {});

    try {
      await api.post("/api/school-admin/academic-sessions", {
        session_name: sessionName,
        academic_year: academicYear,
        status: makeCurrent ? "current" : "completed",
        class_structure: classStructurePayload,
        levels, // ✅ SEND ARRAY OF KEYS
      });
      setShowCreate(false);
      await load();
    } catch (e) {
      alert(e.response?.data?.message || "Failed to create session");
    }
  };

  const updateSession = async (e) => {
    e.preventDefault();

    const levels = getSelectedLevelArray();
    if (levels.length === 0) {
      alert("Select at least one level.");
      return;
    }

    try {
      await api.put(`/api/school-admin/academic-sessions/${editing.id}`, {
        session_name: sessionName,
        academic_year: academicYear,
        levels, // ✅ SEND ARRAY OF KEYS
      });
      setShowEdit(false);
      setEditing(null);
      await load();
    } catch (e) {
      alert(e.response?.data?.message || "Failed to update session");
    }
  };

  const deleteSession = async (id) => {
    if (!window.confirm("Delete this academic session?")) return;
    try {
      console.log('deleteSession', id);
      await api.delete(`/api/school-admin/academic-sessions/${id}`);
      await load();
    } catch (e) {
      console.error('deleteSession error', e);
      alert("Failed to delete: " + (e?.response?.data?.message || e.message || e));
    }
  };

  // ⚠️ Your backend routes currently use PATCH status endpoint, not these POST endpoints
  // If you still have set-current/mark-completed routes, keep them.
  // Otherwise replace these with PATCH call (I’ll show below).
  const setCurrent = async (id) => {
    try {
      // ✅ preferred: PATCH /status
      await api.patch(`/api/school-admin/academic-sessions/${id}/status`, {
        status: "current",
      });
      await load();
    } catch (e) {
      alert("Failed to set current");
    }
  };

  const markCompleted = async (id) => {
    try {
      await api.patch(`/api/school-admin/academic-sessions/${id}/status`, {
        status: "completed",
      });
      await load();
    } catch (e) {
      alert("Failed to mark completed");
    }
  };

  const previewLevels = getSelectedLevelArray();
  const updateClassName = (levelKey, index, value) => {
    setClassStructure((prev) => ({
      ...prev,
      [levelKey]: (prev[levelKey] || []).map((name, i) => (i === index ? value : name)),
    }));
  };

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div>
          <p style={{ marginTop: 6, opacity: 0.75 }}>
            Manage school academic years and set a single current session.
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
            {rows.map((r, idx) => (
              <tr key={r.id}>
                <td>{idx + 1}</td>
                <td>
                  <button
                    onClick={() => navigate(`/school/admin/academic_session/${r.id}`)}
                    style={{
                      background: "transparent",
                      border: "none",
                      color: "#2563eb",
                      cursor: "pointer",
                      fontWeight: "bold",
                    }}
                  >
                    {r.session_name}
                  </button>
                </td>
                <td>{r.academic_year || "N/A"}</td>
                <td>
                  <strong>{r.status === "current" ? "Current" : "Completed"}</strong>
                </td>
                <td style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                  <button onClick={() => openEdit(r)}>Edit</button>
                  <button onClick={() => deleteSession(r.id)} style={{ color: "red" }}>
                    Delete
                  </button>

                  {r.status !== "current" && (
                    <button onClick={() => setCurrent(r.id)}>Set Current</button>
                  )}

                  {r.status === "current" && (
                    <button onClick={() => markCompleted(r.id)}>Mark Completed</button>
                  )}
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

      {/* CREATE MODAL */}
      {showCreate && (
        <div style={{ marginTop: 20, border: "1px solid #ddd", padding: 16, borderRadius: 10 }}>
          <h3>Create Academic Session</h3>

          {currentSession && (
            <p style={{ background: "#fff7ed", padding: 10, borderRadius: 8 }}>
              Current session is <strong>{currentSession.session_name}</strong>. If you set this new
              session as current, the old one will automatically become completed.
            </p>
          )}

          <form onSubmit={createSession}>
            <input
              value={sessionName}
              onChange={(e) => setSessionName(e.target.value)}
              placeholder="e.g. 2025/2026"
              style={{ width: 260, padding: 10 }}
              required
            />

            <input
              value={academicYear}
              onChange={(e) => setAcademicYear(e.target.value)}
              placeholder="e.g. 2025-2026"
              style={{ width: 260, padding: 10, marginLeft: 10 }}
            />

            <div style={{ marginTop: 10 }}>
              <label>
                <input
                  type="checkbox"
                  checked={makeCurrent}
                  onChange={(e) => setMakeCurrent(e.target.checked)}
                />{" "}
                Set as Current
              </label>
            </div>

            {/* ✅ LEVEL CHECKBOXES */}
            <div style={{ marginTop: 14 }}>
              <strong>Select Levels</strong>
              <div style={{ marginTop: 8, display: "flex", gap: 16, flexWrap: "nowrap" }}>
                <label style={{ whiteSpace: "nowrap" }}>
                  <input
                    type="checkbox"
                    checked={selectedLevels.nursery}
                    onChange={() => toggleLevel("nursery")}
                  />{" "}
                  Nursery
                </label>

                <label style={{ whiteSpace: "nowrap" }}>
                  <input
                    type="checkbox"
                    checked={selectedLevels.primary}
                    onChange={() => toggleLevel("primary")}
                  />{" "}
                  Primary
                </label>

                <label style={{ whiteSpace: "nowrap" }}>
                  <input
                    type="checkbox"
                    checked={selectedLevels.secondary}
                    onChange={() => toggleLevel("secondary")}
                  />{" "}
                  Secondary
                </label>
              </div>
            </div>

            {/* ✅ PREVIEW OF WHAT WILL BE CREATED */}
            <div style={{ marginTop: 14 }}>
              <strong>Class Structure Preview</strong>
              <div
                style={{
                  marginTop: 8,
                  display: "grid",
                  gridTemplateColumns: "repeat(3, minmax(0, 1fr))",
                  gap: 12,
                  alignItems: "start",
                }}
              >
                {previewLevels.map((key) => (
                  <div
                    key={key}
                    style={{
                      border: "1px solid #e5e7eb",
                      borderRadius: 10,
                      padding: 10,
                    }}
                  >
                    <div style={{ marginBottom: 8 }}>
                      <strong>{LEVEL_LABELS[key]}</strong>
                    </div>
                    <div style={{ display: "grid", gap: 8 }}>
                      {(classStructure[key] || []).map((className, idx) => (
                        <input
                          key={`${key}-${idx}`}
                          value={className}
                          onChange={(e) => updateClassName(key, idx, e.target.value)}
                          placeholder={(DEFAULT_CLASS_STRUCTURE[key] || [])[idx] || "Class name"}
                          style={{ width: "100%", padding: 8, boxSizing: "border-box" }}
                          required
                        />
                      ))}
                    </div>
                  </div>
                ))}
                {previewLevels.length === 0 && (
                  <p style={{ color: "red" }}>Select at least one level.</p>
                )}
              </div>
            </div>

            <div style={{ marginTop: 14, display: "flex", gap: 10 }}>
              <button type="submit">Create</button>
              <button type="button" onClick={() => setShowCreate(false)}>
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {/* EDIT MODAL */}
      {showEdit && editing && (
        <div style={{ marginTop: 20, border: "1px solid #ddd", padding: 16, borderRadius: 10 }}>
          <h3>Edit Academic Session</h3>

          <form onSubmit={updateSession}>
            <input
              value={sessionName}
              onChange={(e) => setSessionName(e.target.value)}
              placeholder="e.g. 2025/2026"
              style={{ width: 260, padding: 10 }}
              required
            />

            <input
              value={academicYear}
              onChange={(e) => setAcademicYear(e.target.value)}
              placeholder="e.g. 2025-2026"
              style={{ width: 260, padding: 10, marginLeft: 10 }}
            />

            <div style={{ marginTop: 14 }}>
              <strong>Levels</strong>
              <div style={{ marginTop: 8, display: "flex", gap: 16, flexWrap: "nowrap" }}>
                <label style={{ whiteSpace: "nowrap" }}>
                  <input
                    type="checkbox"
                    checked={selectedLevels.nursery}
                    onChange={() => toggleLevel("nursery")}
                  />{" "}
                  Nursery
                </label>

                <label style={{ whiteSpace: "nowrap" }}>
                  <input
                    type="checkbox"
                    checked={selectedLevels.primary}
                    onChange={() => toggleLevel("primary")}
                  />{" "}
                  Primary
                </label>

                <label style={{ whiteSpace: "nowrap" }}>
                  <input
                    type="checkbox"
                    checked={selectedLevels.secondary}
                    onChange={() => toggleLevel("secondary")}
                  />{" "}
                  Secondary
                </label>
              </div>
            </div>

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
