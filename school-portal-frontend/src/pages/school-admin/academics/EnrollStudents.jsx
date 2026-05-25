import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import AcademicPageShell from "./AcademicPageShell";

export default function EnrollStudents() {
  const { classId } = useParams();
  const navigate = useNavigate();

  const [search, setSearch] = useState("");
  const [students, setStudents] = useState([]);
  const [selected, setSelected] = useState({});
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/school-admin/classes/${classId}/students`, {
        params: { search },
      });
      setStudents(res.data.data || []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [classId, search]);

  const toggle = (id) => {
    setSelected((prev) => ({ ...prev, [id]: !prev[id] }));
  };

  const enroll = async () => {
    const ids = Object.keys(selected)
      .filter((k) => selected[k])
      .map((k) => Number(k));

    if (ids.length === 0) return alert("Select at least 1 student");

    await api.post(`/api/school-admin/classes/${classId}/enroll`, {
      student_user_ids: ids,
    });

    alert("Students enrolled");
    navigate(-1);
  };

  return (
    <AcademicPageShell
      pill="Enroll Students"
      title="Bulk enroll students"
      subtitle="Search available students, select the right records, and enroll them into the class in a mobile-friendly table."
      meta={[`${students.length} students`, `${Object.values(selected).filter(Boolean).length} selected`]}
    >
      <div className="payx-card academic-inner__card">
      <div className="academic-inner__actions">
        <input
          className="academic-inner__input"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search by name or email..."
        />
      </div>
      </div>

      {loading ? (
        <p className="payx-state payx-state--loading">Loading students...</p>
      ) : (
        <div className="payx-card academic-inner__card">
          <div className="academic-inner__table-wrap">
          <table className="payx-table academic-inner__table">
            <thead>
              <tr>
                <th>Select</th>
                <th>S/N</th>
                <th>Name</th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>
              {students.map((s, idx) => (
                <tr key={s.id}>
                  <td>
                    <input
                      type="checkbox"
                      checked={!!selected[s.id]}
                      onChange={() => toggle(s.id)}
                    />
                  </td>
                  <td>{idx + 1}</td>
                  <td>{s.name}</td>
                  <td>{s.email || ""}</td>
                </tr>
              ))}
              {students.length === 0 && (
                <tr>
                  <td colSpan="4">No students found.</td>
                </tr>
              )}
            </tbody>
          </table>
          </div>

          <div className="academic-inner__actions" style={{ marginTop: 12 }}>
            <button className="payx-btn" onClick={enroll}>Enroll Selected Students</button>
          </div>
        </div>
      )}
    </AcademicPageShell>
  );
}
