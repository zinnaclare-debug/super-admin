import { useEffect, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import api from "../../../services/api";
import AcademicPageShell from "./AcademicPageShell";

export default function ClassTermStudents() {
  const { classId, termId } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const departmentId = searchParams.get("department_id") || "";
  const departmentName = searchParams.get("department_name") || "";

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
      const params = { search, page };
      if (departmentId) params.department_id = Number(departmentId);
      const res = await api.get(`/api/school-admin/classes/${classId}/terms/${termId}/students`, { params });
      setStudents(res.data.data || []);
      setMeta(res.data.meta || null);
    } catch (e) {
      alert("Failed to load enrolled students");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [classId, termId, departmentId, search, page]);

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
    <AcademicPageShell
      pill="Enrolled Students"
      title="Students enrolled in this term"
      subtitle="Review enrolled students, search records, and remove selected students when enrollment needs to be corrected."
      meta={[
        `${students.length} visible students`,
        `${selected.size} selected`,
        departmentName || "All departments",
      ]}
    >
      <div className="academic-inner__toolbar">
        <div>
          {departmentName ? (
            <p className="academic-inner__muted">
              Showing enrolled students for department: <strong>{departmentName}</strong>
            </p>
          ) : null}
          <button
            className="payx-btn"
            onClick={() =>
              navigate(
                `/school/admin/classes/${classId}/terms/${termId}/enroll` +
                  (departmentId ? `?department_id=${encodeURIComponent(departmentId)}&department_name=${encodeURIComponent(departmentName)}` : "")
              )
            }
          >
            Open Enroll Table
          </button>
        </div>
      </div>

      <div className="payx-card academic-inner__card">
        <div className="academic-inner__actions">
          <input className="academic-inner__input" placeholder="Search students" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
          <button className="payx-btn payx-btn--soft" onClick={() => { setSearch(''); setPage(1); }}>Clear</button>
        </div>
      </div>

        {loading ? (
          <p className="payx-state payx-state--loading">Loading...</p>
        ) : (
          <div className="payx-card academic-inner__card">
            <div className="academic-inner__table-wrap">
            <table className="payx-table academic-inner__table">
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
            </div>
            
            {students.length > 0 && (
              <div className="academic-inner__actions" style={{ marginTop: 12 }}>
                <button 
                  className="payx-btn academic-inner__danger"
                  onClick={unenroll} 
                  disabled={selected.size === 0 || unrolling}
                >
                  {unrolling ? 'Unenrolling...' : `Unenroll Selected (${selected.size})`}
                </button>
              </div>
            )}
            {meta && (
              <div className="academic-inner__actions" style={{ marginTop: 12 }}>
                <button className="payx-btn payx-btn--soft" disabled={page <= 1} onClick={() => setPage(page - 1)}>Prev</button>
                <div>Page {meta.current_page} / {meta.last_page}</div>
                <button className="payx-btn payx-btn--soft" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Next</button>
              </div>
            )}
          </div>
        )}
    </AcademicPageShell>
  );
}
