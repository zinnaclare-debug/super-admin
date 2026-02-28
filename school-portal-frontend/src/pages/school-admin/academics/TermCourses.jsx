import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../../../services/api";

export default function TermCourses() {
  const { classId, termId } = useParams();

  const [courses, setCourses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [teachers, setTeachers] = useState([]);
  const [classLevel, setClassLevel] = useState(null);
  const [assigningSubjectId, setAssigningSubjectId] = useState(null);
  const [selectedTeacherId, setSelectedTeacherId] = useState("");
  const [assigning, setAssigning] = useState(false);
  const [studentsSubject, setStudentsSubject] = useState(null);
  const [subjectStudents, setSubjectStudents] = useState([]);
  const [loadingSubjectStudents, setLoadingSubjectStudents] = useState(false);
  const [savingSubjectStudentId, setSavingSubjectStudentId] = useState(0);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/school-admin/classes/${classId}/terms/${termId}/courses`);
      setCourses(res.data.data || []);
    } finally {
      setLoading(false);
    }
  };

  const loadTeachers = async () => {
    try {
      const res = await api.get(`/api/school-admin/classes/${classId}/eligible-teachers`);
      setTeachers(res.data.data || []);
    } catch (e) {
      console.error('Failed to load teachers', e);
    }
  };

  useEffect(() => {
    load();
    loadTeachers();
  }, [classId, termId]);

  const assignTeacher = async (subjectId) => {
    if (!selectedTeacherId) return alert("Select a teacher");

    setAssigning(true);
    try {
      await api.patch(`/api/school-admin/classes/${classId}/terms/${termId}/subjects/${subjectId}/assign-teacher`, {
        teacher_user_id: Number(selectedTeacherId),
      });
      alert("Teacher assigned to subject");
      setAssigningSubjectId(null);
      setSelectedTeacherId("");
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to assign teacher");
    } finally {
      setAssigning(false);
    }
  };

  const loadSubjectStudents = async (subject) => {
    if (!subject?.subject_id) return;

    setLoadingSubjectStudents(true);
    try {
      const res = await api.get(
        `/api/school-admin/classes/${classId}/terms/${termId}/subjects/${subject.subject_id}/students`
      );
      setSubjectStudents(res.data?.data || []);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load subject students");
      setSubjectStudents([]);
    } finally {
      setLoadingSubjectStudents(false);
    }
  };

  const openSubjectStudents = async (subject) => {
    setStudentsSubject(subject);
    await loadSubjectStudents(subject);
  };

  const closeSubjectStudents = () => {
    setStudentsSubject(null);
    setSubjectStudents([]);
    setSavingSubjectStudentId(0);
  };

  const setStudentOffering = async (student, offering) => {
    if (!studentsSubject?.subject_id || !student?.student_id) return;

    if (!offering) {
      const ok = window.confirm(
        `Remove ${student.student_name} from ${studentsSubject.name} for this class session?`
      );
      if (!ok) return;
    }

    setSavingSubjectStudentId(Number(student.student_id));
    try {
      const res = await api.patch(
        `/api/school-admin/classes/${classId}/terms/${termId}/subjects/${studentsSubject.subject_id}/students/${student.student_id}/offering`,
        { offering }
      );
      await loadSubjectStudents(studentsSubject);
      alert(res.data?.message || "Student subject offering updated");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to update student subject offering");
    } finally {
      setSavingSubjectStudentId(0);
    }
  };

  return (
    <div>
      {loading ? (
        <p>Loading courses...</p>
      ) : (
        <>
          <table border="1" cellPadding="10" width="100%" style={{ marginTop: 12 }}>
           <thead>
  <tr>
    <th style={{ width: 70 }}>S/N</th>
    <th>Course</th>
    <th style={{ width: 220 }}>Teacher</th>
    <th style={{ width: 200 }}>Action</th>
  </tr>
</thead>

<tbody>
  {courses.map((c, idx) => (
    <tr key={c.term_subject_id || c.id || idx}>
      <td>{idx + 1}</td>
      <td>{c.name}</td>

    <td>
  {c.teacher_name ? (
    <strong>{c.teacher_name}</strong>
  ) : (
    <span style={{ opacity: 0.7 }}>Not assigned</span>
  )}
</td>

<td style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
  <button onClick={() => setAssigningSubjectId(c.subject_id)}>Assign</button>
  <button onClick={() => openSubjectStudents(c)}>Students</button>

  {c.teacher_user_id && (
    <button
      style={{ color: "red" }}
      onClick={async () => {
        if (!confirm("Unassign teacher from this course?")) return;
        await api.patch(
         `/api/school-admin/classes/${classId}/terms/${termId}/subjects/${c.subject_id}/unassign-teacher`,
        );
        await load();
      }}
    >
      Unassign
    </button>
  )}
</td>
      
    </tr>
  ))}

  {courses.length === 0 && (
    <tr>
      <td colSpan="4">No courses yet for this term.</td>
    </tr>
  )}
</tbody>

          </table>

          {/* Assign Teacher Modal */}
          {assigningSubjectId && (
            <div style={{ marginTop: 16, border: "1px solid #ddd", padding: 14, borderRadius: 10 }}>
              <h3>Assign Teacher to Course</h3>
              <p style={{ opacity: 0.75 }}>Only teachers with matching education level will appear.</p>

              <select value={selectedTeacherId} onChange={(e) => setSelectedTeacherId(e.target.value)}>
                <option value="">Select Teacher</option>
                {teachers.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name} {t.email ? `(${t.email})` : ""}
                  </option>
                ))}
              </select>

              <div style={{ marginTop: 12, display: "flex", gap: 8 }}>
                <button 
                  onClick={() => assignTeacher(assigningSubjectId)} 
                  disabled={!selectedTeacherId || assigning}
                >
                  {assigning ? "Assigning..." : "Assign"}
                </button>
                <button onClick={() => { setAssigningSubjectId(null); setSelectedTeacherId(""); }}>
                  Cancel
                </button>
              </div>

              {teachers.length === 0 && (
                <p style={{ color: "red", marginTop: 8 }}>No eligible teachers found for this level.</p>
              )}
            </div>
          )}

          {studentsSubject && (
            <div
              role="dialog"
              aria-modal="true"
              style={{
                position: "fixed",
                inset: 0,
                background: "rgba(0,0,0,0.4)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                zIndex: 1200,
                padding: 12,
              }}
            >
              <div style={{ width: "min(900px, 100%)", background: "#fff", borderRadius: 10, padding: 16 }}>
                <h4 style={{ marginTop: 0 }}>Students Offering {studentsSubject.name}</h4>

                {loadingSubjectStudents ? (
                  <p>Loading students...</p>
                ) : (
                  <div style={{ overflowX: "auto" }}>
                    <table border="1" cellPadding="8" width="100%">
                      <thead>
                        <tr>
                          <th style={{ width: 70 }}>S/N</th>
                          <th>Student Name</th>
                          <th style={{ width: 150 }}>Username</th>
                          <th style={{ width: 180 }}>Department</th>
                          <th style={{ width: 120 }}>Status</th>
                          <th style={{ width: 140 }}>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        {subjectStudents.map((student, idx) => (
                          <tr key={student.student_id}>
                            <td>{idx + 1}</td>
                            <td>{student.student_name}</td>
                            <td>{student.student_username || "-"}</td>
                            <td>{student.department_name || "-"}</td>
                            <td>{student.offering ? "Offering" : "Removed"}</td>
                            <td>
                              <button
                                onClick={() => setStudentOffering(student, !student.offering)}
                                disabled={savingSubjectStudentId === Number(student.student_id)}
                                style={
                                  student.offering
                                    ? { background: "#dc2626", border: "1px solid #b91c1c", color: "#fff" }
                                    : undefined
                                }
                              >
                                {savingSubjectStudentId === Number(student.student_id)
                                  ? "Saving..."
                                  : student.offering
                                    ? "Remove"
                                    : "Re-add"}
                              </button>
                            </td>
                          </tr>
                        ))}
                        {subjectStudents.length === 0 && (
                          <tr>
                            <td colSpan="6">No students found for this class and term.</td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                )}

                <div style={{ marginTop: 12 }}>
                  <button onClick={closeSubjectStudents}>Close</button>
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
