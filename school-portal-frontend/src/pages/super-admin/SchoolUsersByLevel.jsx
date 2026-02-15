import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../services/api";

function SchoolUsersByLevel() {
  const { schoolId } = useParams();
  const navigate = useNavigate();

  const [school, setSchool] = useState(null);
  const [levels, setLevels] = useState([]);
  const [selectedLevel, setSelectedLevel] = useState("");
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = async (level = "") => {
    setLoading(true);
    try {
      const res = await api.get(`/api/super-admin/schools/${schoolId}/students-by-level`, {
        params: level ? { level } : {},
      });
      const data = res.data?.data || {};
      setSchool(data.school || null);
      setLevels(data.levels || []);
      setStudents(data.students || []);
    } catch {
      alert("Failed to load school students");
      setSchool(null);
      setLevels([]);
      setStudents([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load("");
  }, [schoolId]);

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h1 style={{ marginBottom: 6 }}>{school ? `${school.name} - Students` : "School Students"}</h1>
        <button onClick={() => navigate("/super-admin/users")}>Back to Schools</button>
      </div>

      <div style={{ marginTop: 8, display: "flex", flexWrap: "wrap", gap: 8 }}>
        <button
          onClick={async () => {
            setSelectedLevel("");
            await load("");
          }}
          style={{
            padding: "8px 10px",
            borderRadius: 6,
            border: selectedLevel === "" ? "1px solid #2563eb" : "1px solid #ccc",
            background: selectedLevel === "" ? "#eff6ff" : "#fff",
          }}
        >
          All
        </button>
        {levels.map((lvl) => (
          <button
            key={lvl.key}
            onClick={async () => {
              setSelectedLevel(lvl.key);
              await load(lvl.key);
            }}
            style={{
              padding: "8px 10px",
              borderRadius: 6,
              border: selectedLevel === lvl.key ? "1px solid #2563eb" : "1px solid #ccc",
              background: selectedLevel === lvl.key ? "#eff6ff" : "#fff",
            }}
          >
            {lvl.label} ({lvl.count})
          </button>
        ))}
      </div>

      <div style={{ marginTop: 16 }}>
        {loading ? (
          <p>Loading students...</p>
        ) : (
          <table border="1" cellPadding="10" width="100%">
            <thead>
              <tr>
                <th style={{ width: 70 }}>S/N</th>
                <th>Name</th>
                <th>Level</th>
              </tr>
            </thead>
            <tbody>
              {students.map((st, idx) => (
                <tr key={st.student_id}>
                  <td>{idx + 1}</td>
                  <td>{st.name}</td>
                  <td>{st.level}</td>
                </tr>
              ))}
              {students.length === 0 ? (
                <tr>
                  <td colSpan="3">No students found for this selection.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

export default SchoolUsersByLevel;
