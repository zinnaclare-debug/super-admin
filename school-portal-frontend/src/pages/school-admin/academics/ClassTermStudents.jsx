import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

export default function ClassTermStudents() {
  const { classId, termId } = useParams();
  const navigate = useNavigate();

  const [students, setStudents] = useState([]);
  const [cls, setCls] = useState(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(null);
  const [selected, setSelected] = useState(new Set());
  const [unrolling, setUnrolling] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/school-admin/classes/${classId}/terms/${termId}/students`, { params: { search, page } });
      setStudents(res.data.data || []);
      setMeta(res.data.meta || null);
    } catch (e) {
      alert("Failed to load enrolled students");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [classId, termId, search, page]);

  const toggle = (id) => {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    setSelected(s);
  };

  const unenroll = async () => {
    if (selected.size === 0) return alert("Select at least one student to unenroll");
    if (!window.confirm(`Unenroll ${selected.size} student(s)?`)) return;

    setUnrolling(true);
    try {
      const enrollment_ids = Array.from(selected);
      await api.delete(`/api/school-admin/classes/${classId}/terms/${termId}/enrollments/bulk`, {
        data: { enrollment_ids }
      });
      alert('Students unenrolled successfully');
      setSelected(new Set());
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || 'Failed to unenroll students');
    } finally {
      setUnrolling(false);
    }
  };

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div>
          <button onClick={() => navigate(`/school/admin/classes/${classId}/terms/${termId}/enroll`)} style={{ marginLeft: 8 }}>
            Open Enroll Table
          </button>
        </div>
      </div>

      <div style={{ marginTop: 12 }}>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <input placeholder="Search students" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
          <button onClick={() => { setSearch(''); setPage(1); }}>Clear</button>
        </div>

        {loading ? (
          <p>Loading...</p>
        ) : (
          <>
            <table border="1" cellPadding="8" width="100%" style={{ marginTop: 12 }}>
              <thead>
                <tr>
                  <th style={{ width: 40 }}>
                    <input
                      type="checkbox"
                      checked={selected.size === students.length && students.length > 0}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelected(new Set(students.map(s => s.id)));
                        } else {
                          setSelected(new Set());
                        }
                      }}
                    />
                  </th>
                  <th>S/N</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Department</th>
                </tr>
              </thead>
              <tbody>
                {students.map((s, idx) => (
                  <tr key={s.id}>
                    <td style={{ textAlign: 'center' }}>
                      <input
                        type="checkbox"
                        checked={selected.has(s.id)}
                        onChange={() => toggle(s.id)}
                      />
                    </td>
                    <td>{idx + 1}</td>
                    <td>{s.student?.name || "N/A"}</td>
                    <td>{s.student?.email || ""}</td>
                    <td>{s.department?.name || "-"}</td>
                  </tr>
                ))}

                {students.length === 0 && (
                  <tr>
                    <td colSpan="5">No students enrolled for this term.</td>
                  </tr>
                )}
              </tbody>
            </table>
            
            {students.length > 0 && (
              <div style={{ marginTop: 12, display: 'flex', gap: 8 }}>
                <button 
                  onClick={unenroll} 
                  disabled={selected.size === 0 || unrolling}
                  style={{ color: 'red' }}
                >
                  {unrolling ? 'Unenrolling...' : `Unenroll Selected (${selected.size})`}
                </button>
              </div>
            )}
            {meta && (
              <div style={{ marginTop: 12, display: 'flex', gap: 8, alignItems: 'center' }}>
                <button disabled={page <= 1} onClick={() => setPage(page - 1)}>Prev</button>
                <div>Page {meta.current_page} / {meta.last_page}</div>
                <button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Next</button>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
