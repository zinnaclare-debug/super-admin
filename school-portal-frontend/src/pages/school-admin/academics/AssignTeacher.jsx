import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../../../services/api";

export default function AssignTeacher() {
  const { classId } = useParams();

  const [teachers, setTeachers] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [selectedDepartmentId, setSelectedDepartmentId] = useState("");
  const [teacherId, setTeacherId] = useState("");
  const [currentTeacher, setCurrentTeacher] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = async (departmentId = "") => {
    setLoading(true);
    try {
      const params = {};
      if (departmentId) params.department_id = Number(departmentId);
      const res = await api.get(`/api/school-admin/classes/${classId}/eligible-teachers`, { params });
      setTeachers(res.data.data || []);
      setCurrentTeacher(res.data?.meta?.current_teacher || null);
      const rows = res.data?.meta?.departments || [];
      setDepartments(rows);
      if (rows.length > 0) {
        const backendSelected = res.data?.meta?.selected_department_id
          ? String(res.data.meta.selected_department_id)
          : "";
        setSelectedDepartmentId((prev) => {
          if (prev && rows.some((row) => String(row.id) === String(prev))) return prev;
          if (backendSelected && rows.some((row) => String(row.id) === backendSelected)) return backendSelected;
          return String(rows[0].id);
        });
      } else {
        setSelectedDepartmentId("");
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load(selectedDepartmentId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [classId, selectedDepartmentId]);

  useEffect(() => {
    setTeacherId("");
  }, [selectedDepartmentId]);

  const selectedDepartment = departments.find((department) => String(department.id) === String(selectedDepartmentId));

  const payloadForDepartment = () => (
    departments.length > 0
      ? { department_id: Number(selectedDepartmentId) }
      : {}
  );

  const assign = async () => {
    if (!teacherId) return alert("Select a teacher");
    if (departments.length > 0 && !selectedDepartmentId) return alert("Select department");

    setSaving(true);
    try {
      await api.patch(`/api/school-admin/classes/${classId}/assign-teacher`, {
        teacher_user_id: Number(teacherId),
        ...payloadForDepartment(),
      });
      alert("Teacher assigned");
      await load(selectedDepartmentId);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to assign teacher");
    } finally {
      setSaving(false);
    }
  };

  const unassign = async () => {
    const label = departments.length > 0
      ? "Unassign class teacher for this department?"
      : "Unassign current class teacher?";
    if (!window.confirm(label)) return;
    setSaving(true);
    try {
      await api.patch(`/api/school-admin/classes/${classId}/unassign-teacher`, payloadForDepartment());
      alert("Teacher unassigned");
      await load(selectedDepartmentId);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to unassign teacher");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      {/* Navbar */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
      </div>

      {loading ? (
        <p>Loading teachers...</p>
      ) : (
        <>
          {departments.length > 0 && (
            <div style={{ marginBottom: 12 }}>
              <label style={{ display: "block", marginBottom: 6 }}>Department</label>
              <select
                value={selectedDepartmentId}
                onChange={(e) => setSelectedDepartmentId(e.target.value)}
              >
                {departments.map((department) => (
                  <option key={department.id} value={department.id}>
                    {department.name}
                  </option>
                ))}
              </select>
            </div>
          )}

          <p style={{ opacity: 0.75 }}>
            Only teachers registered for this class level will show here.
          </p>

          <div style={{ marginBottom: 10 }}>
            <strong>
              {departments.length > 0
                ? `Current Class Teacher (${selectedDepartment?.name || "Department"})`
                : "Current Class Teacher"}
              :
            </strong>{" "}
            {currentTeacher ? `${currentTeacher.name}${currentTeacher.email ? ` (${currentTeacher.email})` : ""}` : "Not assigned"}
          </div>

          <select value={teacherId} onChange={(e) => setTeacherId(e.target.value)}>
            <option value="">Select Teacher</option>
            {teachers.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name} {t.email ? `(${t.email})` : ""}
              </option>
            ))}
          </select>

          <div style={{ marginTop: 12 }}>
            {!currentTeacher ? (
              <button onClick={assign} disabled={saving}>
                {saving ? "Saving..." : "Assign"}
              </button>
            ) : (
              <button onClick={unassign} disabled={saving}>
                {saving ? "Saving..." : "Unassign"}
              </button>
            )}
          </div>

          {teachers.length === 0 && <p style={{ color: "red" }}>No eligible teachers found for this level.</p>}
        </>
      )}
    </div>
  );
}
