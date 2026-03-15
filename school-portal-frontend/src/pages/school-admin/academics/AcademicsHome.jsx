import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";

export default function AcademicsHome() {
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [levelFilter, setLevelFilter] = useState("");
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/school-admin/academics");
      setData(res.data.data);
    } catch (e) {
      alert("Failed to load academics");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const activeLevels = data?.active_levels || [];
  const classes = data?.classes || [];

  const filtered = useMemo(() => {
    if (!levelFilter) return classes;
    return classes.filter((c) => c.level === levelFilter);
  }, [classes, levelFilter]);

  if (loading) return <p>Loading...</p>;

  if (!data) {
    return <p>No current academic session. Set a session to CURRENT first.</p>;
  }

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div>
          <p style={{ marginTop: 6, opacity: 0.75 }}>
            Session: <strong>{data.session?.session_name}</strong>
          </p>
        </div>

        <select value={levelFilter} onChange={(e) => setLevelFilter(e.target.value)}>
          <option value="">All Levels</option>
          {activeLevels.map((lvl) => (
            <option key={lvl} value={lvl}>{lvl.toUpperCase()}</option>
          ))}
        </select>
      </div>

      <div style={{ marginTop: 8, opacity: 0.85 }}>
        <small>Levels: {activeLevels.length} - Classes: {classes.length}</small>
      </div>
      <div style={{ display: "flex", gap: 8, marginTop: 12, flexWrap: "wrap" }}>
        {activeLevels.map((lvl) => (
          <button
            key={lvl}
            onClick={() => setLevelFilter(lvl)}
            style={{
              padding: "6px 10px",
              borderRadius: 999,
              border: "1px solid #ddd",
              background: levelFilter === lvl ? "#2563eb" : "#fff",
              color: levelFilter === lvl ? "#fff" : "#111",
            }}
          >
            {lvl.toUpperCase()}
          </button>
        ))}
        {levelFilter && (
          <button onClick={() => setLevelFilter("")} style={{ padding: "6px 10px" }}>
            Clear
          </button>
        )}
      </div>

      <table border="1" cellPadding="10" width="100%" style={{ marginTop: 16 }}>
        <thead>
          <tr>
            <th style={{ width: 70 }}>S/N</th>
            <th>Level</th>
            <th>Class</th>
            <th style={{ width: 220 }}>Action</th>
          </tr>
        </thead>
        <tbody>
          {filtered.map((c, idx) => (
            <tr key={c.id}>
              <td>{idx + 1}</td>
              <td><strong>{c.level.toUpperCase()}</strong></td>
              <td>{c.name}</td>
              <td>
                <button onClick={() => navigate(`/school/admin/academics/classes/${c.id}/subjects`)}>
                  Create / Manage Subjects
                </button>
              </td>
            </tr>
          ))}

          {filtered.length === 0 && (
            <tr>
              <td colSpan="4">No classes found for this level.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
