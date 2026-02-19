import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

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
    <div>
      <div style={{ marginTop: 12 }}>
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search by name or email..."
          style={{ width: 320, padding: 10 }}
        />
      </div>

      {loading ? (
        <p>Loading students...</p>
      ) : (
        <>
          <table border="1" cellPadding="10" width="100%" style={{ marginTop: 12 }}>
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

          <div style={{ marginTop: 12 }}>
            <button onClick={enroll}>Enroll Selected Students</button>
          </div>
        </>
      )}
    </div>
  );
}
