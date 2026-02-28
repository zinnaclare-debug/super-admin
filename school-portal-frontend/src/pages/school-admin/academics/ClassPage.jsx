import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

export default function ClassPage() {
  const { classId } = useParams();
  const navigate = useNavigate();

  const [cls, setCls] = useState(null);
  const [terms, setTerms] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [selectedDepartmentId, setSelectedDepartmentId] = useState("");
  const [teachers, setTeachers] = useState([]);
  const [teacherId, setTeacherId] = useState("");
  const [currentTeacher, setCurrentTeacher] = useState(null);
  const [loading, setLoading] = useState(true);
  const [savingTeacher, setSavingTeacher] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const termsRes = await api.get(`/api/school-admin/classes/${classId}/terms`);
      const loadedClass = termsRes.data?.data?.class || null;
      const loadedTerms = termsRes.data?.data?.terms || [];
      setCls(loadedClass);
      setTerms(loadedTerms);

      let loadedDepartments = [];
      if (loadedTerms.length > 0) {
        const departmentsRes = await api.get(
          `/api/school-admin/classes/${classId}/terms/${loadedTerms[0].id}/departments`
        );
        loadedDepartments = departmentsRes.data?.data || [];
      }

      setDepartments(loadedDepartments);
      setSelectedDepartmentId((prev) => {
        if (loadedDepartments.length === 0) return "";
        if (prev && loadedDepartments.some((d) => String(d.id) === String(prev))) return prev;
        return String(loadedDepartments[0].id);
      });
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load class setup");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [classId]);

  const loadTeachers = async (departmentId) => {
    try {
      const params = {};
      if (departmentId) params.department_id = Number(departmentId);
      const res = await api.get(`/api/school-admin/classes/${classId}/eligible-teachers`, { params });
      setTeachers(res.data?.data || []);
      setCurrentTeacher(res.data?.meta?.current_teacher || null);
    } catch (e) {
      setTeachers([]);
      setCurrentTeacher(null);
      alert(e?.response?.data?.message || "Failed to load teachers");
    }
  };

  useEffect(() => {
    if (departments.length > 0) {
      if (!selectedDepartmentId) return;
      setTeacherId("");
      loadTeachers(selectedDepartmentId);
      return;
    }
    setTeacherId("");
    loadTeachers("");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [classId, selectedDepartmentId, departments.length]);

  const assign = async () => {
    if (!teacherId) return alert("Select a teacher");

    const payload = { teacher_user_id: Number(teacherId) };
    if (departments.length > 0) {
      if (!selectedDepartmentId) return alert("Select department first");
      payload.department_id = Number(selectedDepartmentId);
    }

    setSavingTeacher(true);
    try {
      await api.patch(`/api/school-admin/classes/${classId}/assign-teacher`, payload);
      setTeacherId("");
      await loadTeachers(selectedDepartmentId);
      alert("Class teacher assigned");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to assign teacher");
    } finally {
      setSavingTeacher(false);
    }
  };

  const unassign = async () => {
    const label = departments.length > 0
      ? "Unassign class teacher for this department?"
      : "Unassign current class teacher?";
    if (!window.confirm(label)) return;

    const payload = {};
    if (departments.length > 0) {
      if (!selectedDepartmentId) return alert("Select department first");
      payload.department_id = Number(selectedDepartmentId);
    }

    setSavingTeacher(true);
    try {
      await api.patch(`/api/school-admin/classes/${classId}/unassign-teacher`, payload);
      await loadTeachers(selectedDepartmentId);
      alert("Class teacher unassigned");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to unassign teacher");
    } finally {
      setSavingTeacher(false);
    }
  };

  const selectedDepartment = departments.find((d) => String(d.id) === String(selectedDepartmentId));

  const departmentQuery = selectedDepartmentId
    ? `?department_id=${encodeURIComponent(selectedDepartmentId)}&department_name=${encodeURIComponent(selectedDepartment?.name || "")}`
    : "";

  if (loading) return <p>Loading...</p>;

  return (
    <div>
      {/* Navbar */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
      </div>

      {/* Actions */}
      {departments.length > 0 && (
        <div style={{ marginTop: 12 }}>
          <strong>Departments (Sub Class)</strong>
          <div style={{ display: "flex", gap: 8, marginTop: 8, flexWrap: "wrap" }}>
            {departments.map((department) => (
              <button
                key={department.id}
                onClick={() => setSelectedDepartmentId(String(department.id))}
                style={{
                  padding: "6px 10px",
                  borderRadius: 8,
                  border: "1px solid #ddd",
                  background:
                    String(selectedDepartmentId) === String(department.id) ? "#2563eb" : "#fff",
                  color:
                    String(selectedDepartmentId) === String(department.id) ? "#fff" : "#111",
                }}
              >
                {department.name} ({cls?.name || "Class"})
              </button>
            ))}
          </div>
        </div>
      )}

      <div style={{ marginTop: 12, border: "1px solid #ddd", borderRadius: 10, padding: 12 }}>
        <p style={{ margin: "0 0 10px" }}>
          <strong>
            {departments.length > 0
              ? `Class Teacher (${selectedDepartment?.name || "Department"})`
              : "Class Teacher"}
          </strong>
          :{" "}
          {currentTeacher
            ? `${currentTeacher.name}${currentTeacher.email ? ` (${currentTeacher.email})` : ""}`
            : "Not assigned"}
        </p>

        <div style={{ display: "flex", gap: 10, flexWrap: "wrap", alignItems: "center" }}>
          <select value={teacherId} onChange={(e) => setTeacherId(e.target.value)}>
            <option value="">Select Teacher</option>
            {teachers.map((teacher) => (
              <option key={teacher.id} value={teacher.id}>
                {teacher.name} {teacher.email ? `(${teacher.email})` : ""}
              </option>
            ))}
          </select>

          <button onClick={assign} disabled={savingTeacher || !teacherId}>
            {savingTeacher ? "Saving..." : "Assign Class Teacher"}
          </button>
          <button onClick={unassign} disabled={savingTeacher || !currentTeacher}>
            {savingTeacher ? "Saving..." : "Unassign Class Teacher"}
          </button>
        </div>
      </div>

      {/* Terms table */}
      <table border="1" cellPadding="10" width="100%" style={{ marginTop: 16 }}>
        <thead>
          <tr>
            <th>S/N</th>
            <th>Term</th>
            <th style={{ width: 220 }}>Enrolled Students</th>
            <th>Enrollment</th>
          </tr>
        </thead>

        <tbody>
          {terms.map((t, idx) => (
            <tr key={t.id}>
              <td>{idx + 1}</td>

              {/* ✅ KEEP TERM AS LINK */}
              <td>
                <button
                  onClick={() => navigate(`/school/admin/classes/${classId}/terms/${t.id}`)}
                  style={{
                    background: "transparent",
                    border: "none",
                    color: "#2563eb",
                    cursor: "pointer",
                    fontWeight: 600,
                  }}
                >
                  {t.name}
                </button>
              </td>

              {/* ✅ VIEW ENROLLED STUDENTS */}
              <td>
                <button
                  onClick={() =>
                    navigate(`/school/admin/classes/${classId}/terms/${t.id}/students${departmentQuery}`)
                  }
                  style={{ padding: "6px 10px", cursor: "pointer" }}
                >
                  View Enrolled
                </button>
              </td>

              {/* ✅ BULK ENROLL BUTTON (PER TERM) */}
              <td>
                <button
                  onClick={() =>
                    navigate(`/school/admin/classes/${classId}/terms/${t.id}/enroll${departmentQuery}`)
                  }
                >
                  Enroll (Bulk / One-by-One)
                </button>
              </td>
            </tr>
          ))}

          {terms.length === 0 && (
            <tr>
              <td colSpan="4">No terms found.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
