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
        </>
      )}
    </div>
  );
}
