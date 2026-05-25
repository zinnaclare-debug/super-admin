import { useEffect, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import api from "../../../services/api";
import AcademicPageShell from "./AcademicPageShell";

export default function ClassTermEnroll() {
  const { classId, termId } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const departmentId = searchParams.get("department_id") || "";
  const departmentName = searchParams.get("department_name") || "";
  const isDepartmentContext = departmentId !== "";

  const [available, setAvailable] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [selected, setSelected] = useState(new Set());
  const [rowDepartment, setRowDepartment] = useState({});
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(null);

  const loadAvailable = async () => {
    setLoading(true);
    try {
      const [studentsRes, departmentsRes] = await Promise.all([
        api.get(`/api/school-admin/classes/${classId}/students`, { params: { search, page } }),
        api.get(`/api/school-admin/classes/${classId}/terms/${termId}/departments`),
      ]);
      setAvailable(studentsRes.data.data || []);
      setMeta(studentsRes.data.meta || null);
      setDepartments(departmentsRes.data.data || []);
    } catch (e) {
      alert("Failed to load enrollment data");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAvailable();
  }, [classId, termId, search, page]);

  useEffect(() => {
    setSelected((prev) => {
      const currentIds = new Set(available.map((u) => u.id));
      return new Set(Array.from(prev).filter((id) => currentIds.has(id)));
    });
  }, [available]);

  const toggle = (id) => {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    setSelected(s);
  };

  const setDepartment = (userId, departmentId) => {
    setRowDepartment((prev) => ({ ...prev, [userId]: departmentId || "" }));
  };

  const getDepartmentId = (userId) => {
    const value = rowDepartment[userId];
    if (!value) return null;
    const id = Number(value);
    return Number.isFinite(id) ? id : null;
  };

  const enrollOne = async (row) => {
    if (!row?.student_id) return alert("No valid student profile for this row");
    if (isDepartmentContext && !departmentId) {
      return alert("No department selected");
    }
    if (!isDepartmentContext && departments.length > 0 && !getDepartmentId(row.id)) {
      return alert("Select a department for this student");
    }

    try {
      const res = await api.post(`/api/school-admin/classes/${classId}/terms/${termId}/enroll/bulk`, {
        department_id: isDepartmentContext ? Number(departmentId) : undefined,
        enrollments: [
          {
            student_id: row.student_id,
            department_id: isDepartmentContext ? Number(departmentId) : getDepartmentId(row.id),
          },
        ],
      });
      const count = Number(res.data?.count || 0);
      const updatedRows = Number(res.data?.updated_department_rows || 0);
      const skipped = res.data?.skipped_duplicates || [];
      if (count > 0 || updatedRows > 0) {
        alert("Student enrolled");
      } else if (skipped.length > 0) {
        alert(skipped[0]?.reason || "This student name/email is already enrolled in this session and term.");
      } else {
        alert("No enrollment was created.");
      }
      await loadAvailable();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to enroll");
    }
  };

  const submitBulk = async () => {
    if (selected.size === 0) return alert("Select at least one student");

    const selectedRows = available.filter((u) => selected.has(u.id));
    const enrollments = selectedRows
      .filter((u) => !!u.student_id)
      .map((u) => ({
        student_id: u.student_id,
        department_id: isDepartmentContext ? Number(departmentId) : getDepartmentId(u.id),
      }));

    if (enrollments.length === 0) {
      return alert("No valid student records found");
    }

    if (!isDepartmentContext && departments.length > 0 && enrollments.some((r) => !r.department_id)) {
      return alert("Select department for all selected students");
    }

    setSubmitting(true);
    try {
      const res = await api.post(`/api/school-admin/classes/${classId}/terms/${termId}/enroll/bulk`, {
        department_id: isDepartmentContext ? Number(departmentId) : undefined,
        enrollments,
      });
      const count = Number(res.data?.count || 0);
      const updatedRows = Number(res.data?.updated_department_rows || 0);
      const skipped = res.data?.skipped_duplicates || [];
      if (count > 0 || updatedRows > 0) {
        alert(
          skipped.length > 0
            ? `Enrollment updated for ${count + updatedRows} student(s). ${skipped.length} skipped due to duplicate name/email in this term.`
            : "Enrollment successful"
        );
      } else if (skipped.length > 0) {
        alert("All selected students were skipped due to duplicate name/email in this term.");
      } else {
        alert("No enrollment was created.");
      }
      navigate(
        `/school/admin/classes/${classId}/terms/${termId}/students` +
          (isDepartmentContext
            ? `?department_id=${encodeURIComponent(departmentId)}&department_name=${encodeURIComponent(departmentName)}`
            : "")
      );
    } catch (e) {
      alert(e?.response?.data?.message || "Enrollment failed");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <AcademicPageShell
      pill="Student Enrollment"
      title="Enroll students into this term"
      subtitle="Search, select, assign departments where required, and enroll students in a responsive table that works on small screens."
      meta={[
        `${available.length} visible students`,
        `${selected.size} selected`,
        isDepartmentContext ? departmentName || "Selected department" : "All departments",
      ]}
    >
      <div className="academic-inner__toolbar">
        <div>
          <h3>Available Students</h3>
          <p>Select one or many students to enroll into this class term.</p>
        </div>
        {isDepartmentContext ? (
          <p className="payx-state payx-state--ok">
            Enrolling into department: <strong>{departmentName || "Selected Department"}</strong>
          </p>
        ) : null}
      </div>

      <div className="payx-card academic-inner__card">
      <div className="academic-inner__actions">
        <input
          className="academic-inner__input"
          placeholder="Search students"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
        />
        <button
          className="payx-btn payx-btn--soft"
          onClick={() => {
            setSearch("");
            setPage(1);
          }}
        >
          Clear
        </button>
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
                <th style={{ width: 44 }}>
                  <input
                    type="checkbox"
                    checked={available.length > 0 && selected.size === available.length}
                    onChange={(e) => {
                      if (e.target.checked) {
                        setSelected(new Set(available.map((u) => u.id)));
                      } else {
                        setSelected(new Set());
                      }
                    }}
                  />
                </th>
                <th style={{ width: 70 }}>S/N</th>
                <th>Name</th>
                <th>Email</th>
                {!isDepartmentContext && <th style={{ width: 220 }}>Department</th>}
                <th style={{ width: 140 }}>Action</th>
              </tr>
            </thead>
            <tbody>
              {available.map((u, idx) => (
                <tr key={u.id}>
                  <td style={{ textAlign: "center" }}>
                    <input
                      type="checkbox"
                      checked={selected.has(u.id)}
                      onChange={() => toggle(u.id)}
                    />
                  </td>
                  <td>{((meta?.current_page || 1) - 1) * (meta?.per_page || available.length || 0) + idx + 1}</td>
                  <td>{u.name}</td>
                  <td>{u.email || ""}</td>
                  {!isDepartmentContext && (
                    <td>
                      <select
                        className="academic-inner__select"
                        value={rowDepartment[u.id] || ""}
                        onChange={(e) => setDepartment(u.id, e.target.value)}
                        style={{ width: "100%" }}
                        disabled={departments.length === 0}
                      >
                        <option value="">
                          {departments.length === 0 ? "No department configured" : "Select department"}
                        </option>
                        {departments.map((d) => (
                          <option key={d.id} value={d.id}>
                            {d.name}
                          </option>
                        ))}
                      </select>
                    </td>
                  )}
                  <td>
                    <button className="payx-btn payx-btn--soft" onClick={() => enrollOne(u)}>Enroll One</button>
                  </td>
                </tr>
              ))}
              {available.length === 0 && (
                <tr>
                  <td colSpan={isDepartmentContext ? "5" : "6"}>No students available.</td>
                </tr>
              )}
            </tbody>
          </table>
          </div>

          {meta && (
            <div className="academic-inner__actions" style={{ marginTop: 12 }}>
              <button className="payx-btn payx-btn--soft" disabled={page <= 1} onClick={() => setPage(page - 1)}>
                Prev
              </button>
              <div>
                Page {meta.current_page} / {meta.last_page}
              </div>
              <button className="payx-btn payx-btn--soft" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>
                Next
              </button>
            </div>
          )}

          <div className="academic-inner__actions" style={{ marginTop: 12 }}>
            <button className="payx-btn" onClick={submitBulk} disabled={submitting || selected.size === 0}>
              {submitting ? "Enrolling..." : `Enroll Selected (${selected.size})`}
            </button>
          </div>
        </div>
      )}
    </AcademicPageShell>
  );
}
