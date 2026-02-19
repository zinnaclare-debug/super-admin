import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

export default function ClassTermEnroll() {
  const { classId, termId } = useParams();
  const navigate = useNavigate();

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
    if (departments.length > 0 && !getDepartmentId(row.id)) {
      return alert("Select a department for this student");
    }

    try {
      const res = await api.post(`/api/school-admin/classes/${classId}/terms/${termId}/enroll/bulk`, {
        enrollments: [
          {
            student_id: row.student_id,
            department_id: getDepartmentId(row.id),
          },
        ],
      });
      const count = Number(res.data?.count || 0);
      const skipped = res.data?.skipped_duplicates || [];
      if (count > 0) {
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
        department_id: getDepartmentId(u.id),
      }));

    if (enrollments.length === 0) {
      return alert("No valid student records found");
    }

    if (departments.length > 0 && enrollments.some((r) => !r.department_id)) {
      return alert("Select department for all selected students");
    }

    setSubmitting(true);
    try {
      const res = await api.post(`/api/school-admin/classes/${classId}/terms/${termId}/enroll/bulk`, {
        enrollments,
      });
      const count = Number(res.data?.count || 0);
      const skipped = res.data?.skipped_duplicates || [];
      if (count > 0) {
        alert(
          skipped.length > 0
            ? `Enrollment successful for ${count} student(s). ${skipped.length} skipped due to duplicate name/email in this term.`
            : "Enrollment successful"
        );
      } else if (skipped.length > 0) {
        alert("All selected students were skipped due to duplicate name/email in this term.");
      } else {
        alert("No enrollment was created.");
      }
      navigate(`/school/admin/classes/${classId}/terms/${termId}/students`);
    } catch (e) {
      alert(e?.response?.data?.message || "Enrollment failed");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
      </div>

      <div style={{ marginTop: 12, display: "flex", gap: 8, alignItems: "center" }}>
        <input
          placeholder="Search students"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
        />
        <button
          onClick={() => {
            setSearch("");
            setPage(1);
          }}
        >
          Clear
        </button>
      </div>

      {loading ? (
        <p>Loading students...</p>
      ) : (
        <div style={{ marginTop: 12 }}>
          <table border="1" cellPadding="8" width="100%">
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
                <th style={{ width: 220 }}>Department</th>
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
                  <td>
                    <select
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
                  <td>
                    <button onClick={() => enrollOne(u)}>Enroll One</button>
                  </td>
                </tr>
              ))}
              {available.length === 0 && (
                <tr>
                  <td colSpan="6">No students available.</td>
                </tr>
              )}
            </tbody>
          </table>

          {meta && (
            <div style={{ marginTop: 12, display: "flex", gap: 8, alignItems: "center" }}>
              <button disabled={page <= 1} onClick={() => setPage(page - 1)}>
                Prev
              </button>
              <div>
                Page {meta.current_page} / {meta.last_page}
              </div>
              <button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>
                Next
              </button>
            </div>
          )}

          <div style={{ marginTop: 12 }}>
            <button onClick={submitBulk} disabled={submitting || selected.size === 0}>
              {submitting ? "Enrolling..." : `Enroll Selected (${selected.size})`}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
