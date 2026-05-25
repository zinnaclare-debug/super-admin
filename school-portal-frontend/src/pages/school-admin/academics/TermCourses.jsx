import { Fragment, useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import AcademicPageShell from "./AcademicPageShell";

export default function TermCourses() {
  const { classId, termId } = useParams();
  const navigate = useNavigate();

  const [courses, setCourses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [teachers, setTeachers] = useState([]);
  const [assigningSubjectId, setAssigningSubjectId] = useState(null);
  const [selectedTeacherId, setSelectedTeacherId] = useState("");
  const [assigning, setAssigning] = useState(false);

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
      console.error("Failed to load teachers", e);
    }
  };

  useEffect(() => {
    load();
    loadTeachers();
  }, [classId, termId]);

  const openAssignRow = (course) => {
    setAssigningSubjectId(course.subject_id);
    setSelectedTeacherId(course.teacher_user_id ? String(course.teacher_user_id) : "");
  };

  const closeAssignRow = () => {
    setAssigningSubjectId(null);
    setSelectedTeacherId("");
  };

  const assignTeacher = async (subjectId) => {
    if (!selectedTeacherId) return alert("Select a teacher");

    setAssigning(true);
    try {
      await api.patch(`/api/school-admin/classes/${classId}/terms/${termId}/subjects/${subjectId}/assign-teacher`, {
        teacher_user_id: Number(selectedTeacherId),
      });
      alert("Teacher assigned to subject");
      closeAssignRow();
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to assign teacher");
    } finally {
      setAssigning(false);
    }
  };

  return (
    <AcademicPageShell
      pill="Term Courses"
      title="Assign teachers to term courses"
      subtitle="Review every course in this term, attach the right teacher, and open enrolled students without leaving the academic setup flow."
      meta={[`${courses.length} courses`, assigningSubjectId ? "Assigning teacher" : "Ready"]}
    >
      {loading ? (
        <p className="payx-state payx-state--loading">Loading courses...</p>
      ) : (
        <div className="payx-card academic-inner__card">
          <div className="academic-inner__table-wrap">
          <table className="payx-table academic-inner__table">
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
                <Fragment key={c.term_subject_id || c.id || idx}>
                  <tr>
                    <td>{idx + 1}</td>
                    <td>{c.name}</td>
                    <td>
                      {c.teacher_name ? (
                        <strong>{c.teacher_name}</strong>
                      ) : (
                        <span className="academic-inner__muted">Not assigned</span>
                      )}
                    </td>
                    <td>
                      <div className="academic-inner__action-cell">
                      <button className="payx-btn payx-btn--soft" onClick={() => openAssignRow(c)}>Assign</button>
                      <button
                        className="payx-btn"
                        onClick={() =>
                          navigate(`/school/admin/classes/${classId}/terms/${termId}/subjects/${c.subject_id}/students`)
                        }
                      >
                        Students
                      </button>
                      {c.teacher_user_id && (
                        <button
                          className="payx-btn academic-inner__danger"
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
                      </div>
                    </td>
                  </tr>

                  {assigningSubjectId === c.subject_id && (
                    <tr>
                      <td colSpan="4">
                        <div className="payx-card">
                          <h3 className="academic-inner__card-title">Assign Teacher to Course</h3>
                          <p className="academic-inner__muted">
                            Only teachers with matching education level will appear.
                          </p>

                          <div style={{ marginTop: 12 }}>
                            <select className="academic-inner__select" value={selectedTeacherId} onChange={(e) => setSelectedTeacherId(e.target.value)}>
                              <option value="">Select Teacher</option>
                              {teachers.map((t) => (
                                <option key={t.id} value={t.id}>
                                  {t.name} {t.email ? `(${t.email})` : ""}
                                </option>
                              ))}
                            </select>
                          </div>

                          <div className="academic-inner__actions" style={{ marginTop: 12 }}>
                            <button className="payx-btn" onClick={() => assignTeacher(assigningSubjectId)} disabled={!selectedTeacherId || assigning}>
                              {assigning ? "Assigning..." : "Assign"}
                            </button>
                            <button className="payx-btn payx-btn--soft" onClick={closeAssignRow}>Cancel</button>
                          </div>

                          {teachers.length === 0 && (
                            <p className="payx-state payx-state--error" style={{ marginTop: 8 }}>No eligible teachers found for this level.</p>
                          )}
                        </div>
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}

              {courses.length === 0 && (
                <tr>
                  <td colSpan="4">No courses yet for this term.</td>
                </tr>
              )}
            </tbody>
          </table>
          </div>
        </div>
      )}
    </AcademicPageShell>
  );
}
