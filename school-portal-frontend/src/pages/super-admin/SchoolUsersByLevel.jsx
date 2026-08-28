import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../services/api";
import studentsArt from "../../assets/dashboard/students.svg";

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
    <div className="sa-page sa-page--students">
      <section className="sa-page-hero">
        <div>
          <span className="sa-page-eyebrow">School records</span>
          <h1>Students by Level</h1>
          <p>Filter each school&apos;s registered students by the education level configured for that school.</p>
        </div>
        <img className="sa-page-art" src={studentsArt} alt="" aria-hidden="true" />
      </section>
      <div className="sa-toolbar" style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h2 style={{ marginBottom: 6 }}>{school ? `${school.name} - Students` : "School Students"}</h2>
        <button onClick={() => navigate("/super-admin/users")}>Back to Schools</button>
      </div>

      <div className="sa-filter-list">
        <button
          onClick={async () => {
            setSelectedLevel("");
            await load("");
          }} className={`sa-filter-button ${selectedLevel === "" ? "is-selected" : ""}`}
        >
          All
        </button>
        {levels.map((lvl) => (
          <button
            key={lvl.key}
            onClick={async () => {
              setSelectedLevel(lvl.key);
              await load(lvl.key);
            }} className={`sa-filter-button ${selectedLevel === lvl.key ? "is-selected" : ""}`}
          >
            {lvl.label} ({lvl.count})
          </button>
        ))}
      </div>

      <div style={{ marginTop: 16 }}>
        {loading ? (
          <p>Loading students...</p>
        ) : (
          <div style={{ width: "100%", maxWidth: "100%", overflowX: "auto", WebkitOverflowScrolling: "touch" }}>
            <table border="1" cellPadding="10" width="100%" style={{ minWidth: 540 }}>
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
          </div>
        )}
      </div>
    </div>
  );
}

export default SchoolUsersByLevel;
