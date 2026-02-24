import { useEffect, useState } from "react";
import api from "../../services/api";
import { useLocation, useNavigate } from "react-router-dom";

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

const formatSessionStatus = (status) => {
  const value = String(status || "").toLowerCase();
  if (value === "current") return "Current";
  if (value === "completed") return "Completed";
  if (value === "pending") return "Pending";
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

  const getSelectedLevelArray = () => LEVEL_KEYS.filter((key) => selectedLevels[key]);

  const toggleLevel = (key) => {
    setSelectedLevels((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const openCreate = () => {
    setSessionName("");
    setAcademicYear("");
    setSelectedLevels({ nursery: true, primary: true, secondary: true });
    setClassStructure(DEFAULT_CLASS_STRUCTURE);
    setShowCreate(true);
    setShowEdit(false);
    setEditing(null);
  };

  const openEdit = (row) => {
    setEditing(row);
    setSessionName(row.session_name || "");
    setAcademicYear(row.academic_year || "");

    const rowLevels = Array.isArray(row.levels) ? row.levels : [];
    setSelectedLevels({
      nursery: rowLevels.includes("nursery"),
      primary: rowLevels.includes("primary"),
      secondary: rowLevels.includes("secondary"),
    });

    setShowEdit(true);
    setShowCreate(false);
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
        class_structure: classStructurePayload,
        levels,
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
        levels,
      });
      setShowEdit(false);
      setEditing(null);
      await load();
    } catch (e) {
      alert(e.response?.data?.message || "Failed to update session");
    }
  };

  const updateClassName = (levelKey, index, value) => {
    setClassStructure((prev) => ({
      ...prev,
      [levelKey]: (prev[levelKey] || []).map((name, i) => (i === index ? value : name)),
    }));
  };

  const previewLevels = getSelectedLevelArray();

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div>
          <p style={{ marginTop: 6, opacity: 0.75 }}>
            Manage academic sessions. Super admin controls session status.
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
                {previewLevels.length === 0 && <p style={{ color: "red" }}>Select at least one level.</p>}
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
