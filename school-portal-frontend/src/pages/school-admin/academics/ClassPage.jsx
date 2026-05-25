import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import AcademicPageShell from "./AcademicPageShell";

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
    <AcademicPageShell
      pill="Class Setup"
      title={`Set up ${cls?.name || "Class"}`}
      subtitle="Manage class teachers, departments, term enrollment, and subject entry points from one clean class workspace."
      meta={[
        `${terms.length} terms`,
        departments.length > 0 ? `${departments.length} departments` : "No departments",
        currentTeacher ? "Teacher assigned" : "Teacher not assigned",
      ]}
    >
      <div className="academic-inner__toolbar">
        <div>
          <h3>{cls?.name || "Class Setup"}</h3>
          <p>Choose a department when needed, then manage class teachers and term enrollment.</p>
        </div>
      </div>

      {departments.length > 0 && (
        <div className="payx-card academic-inner__card">
          <h3 className="academic-inner__card-title">Departments</h3>
          <p className="academic-inner__muted">Select the department/sub-class you want to manage.</p>
          <div className="academic-inner__chip-row" style={{ marginTop: 12 }}>
            {departments.map((department) => (
              <button
                key={department.id}
                onClick={() => setSelectedDepartmentId(String(department.id))}
                className={`academic-inner__chip${String(selectedDepartmentId) === String(department.id) ? " academic-inner__chip--active" : ""}`}
              >
                {department.name} ({cls?.name || "Class"})
              </button>
            ))}
          </div>
        </div>
      )}

      <div className="payx-card academic-inner__card">
        <h3 className="academic-inner__card-title">Class Teacher</h3>
        <p className="academic-inner__muted">
          {departments.length > 0
            ? `Department: ${selectedDepartment?.name || "Department"}`
            : "Whole class teacher assignment"}
        </p>
        <p>
          Current Teacher:{" "}
          <strong>
            {currentTeacher
              ? `${currentTeacher.name}${currentTeacher.email ? ` (${currentTeacher.email})` : ""}`
              : "Not assigned"}
          </strong>
        </p>

        <div className="academic-inner__actions">
          <select className="academic-inner__select" value={teacherId} onChange={(e) => setTeacherId(e.target.value)}>
            <option value="">Select Teacher</option>
            {teachers.map((teacher) => (
              <option key={teacher.id} value={teacher.id}>
                {teacher.name} {teacher.email ? `(${teacher.email})` : ""}
              </option>
            ))}
          </select>

          <button className="payx-btn" onClick={assign} disabled={savingTeacher || !teacherId}>
            {savingTeacher ? "Saving..." : "Assign Class Teacher"}
          </button>
          <button className="payx-btn payx-btn--soft" onClick={unassign} disabled={savingTeacher || !currentTeacher}>
            {savingTeacher ? "Saving..." : "Unassign Class Teacher"}
          </button>
        </div>
      </div>

      <div className="payx-card academic-inner__card">
        <h3 className="academic-inner__card-title">Terms</h3>
        <div className="academic-inner__table-wrap">
          <table className="payx-table academic-inner__table">
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
                  <td>
                    <button
                      onClick={() => navigate(`/school/admin/classes/${classId}/terms/${t.id}`)}
                      className="academic-inner__link-btn"
                    >
                      {t.name}
                    </button>
                  </td>
                  <td>
                    <button
                      className="payx-btn payx-btn--soft"
                      onClick={() => navigate(`/school/admin/classes/${classId}/terms/${t.id}/students${departmentQuery}`)}
                    >
                      View Enrolled
                    </button>
                  </td>
                  <td>
                    <button
                      className="payx-btn"
                      onClick={() => navigate(`/school/admin/classes/${classId}/terms/${t.id}/enroll${departmentQuery}`)}
                    >
                      Enroll Students
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
      </div>
    </AcademicPageShell>
  );
}
