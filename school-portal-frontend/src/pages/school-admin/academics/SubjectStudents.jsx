import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

export default function SubjectStudents() {
  const { classId, termId, subjectId } = useParams();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(true);
  const [students, setStudents] = useState([]);
  const [meta, setMeta] = useState(null);
  const [savingSubjectStudentId, setSavingSubjectStudentId] = useState(0);
  const [q, setQ] = useState("");

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(
        `/api/school-admin/classes/${classId}/terms/${termId}/subjects/${subjectId}/students`
      );
      setStudents(res.data?.data || []);
      setMeta(res.data?.meta || null);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load subject students");
      setStudents([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [classId, termId, subjectId]);

  const setStudentOffering = async (student, offering) => {
    if (!student?.student_id) return;

    if (!offering) {
      const ok = window.confirm(
        `Remove ${student.student_name} from ${meta?.subject_name || "this subject"} for this class session?`
      );
      if (!ok) return;
    }

    setSavingSubjectStudentId(Number(student.student_id));
    try {
      const res = await api.patch(
        `/api/school-admin/classes/${classId}/terms/${termId}/subjects/${subjectId}/students/${student.student_id}/offering`,
        { offering }
      );
      await load();
      alert(res.data?.message || "Student subject offering updated");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to update student subject offering");
    } finally {
      setSavingSubjectStudentId(0);
    }
  };

  const filteredStudents = useMemo(() => {
    const query = q.trim().toLowerCase();
    if (!query) return students;

    return students.filter((student) => {
      const name = (student.student_name || "").toLowerCase();
      const username = (student.student_username || "").toLowerCase();
      const department = (student.department_name || "").toLowerCase();
      return name.includes(query) || username.includes(query) || department.includes(query);
    });
  }, [students, q]);

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", gap: 12, flexWrap: "wrap" }}>
        <div>
          <h3 style={{ margin: 0 }}>
            {meta?.subject_name || "Subject"} Students
          </h3>
          <p style={{ margin: "6px 0 0", opacity: 0.75 }}>
            {meta?.class_name || "Class"} | {meta?.term_name || "Term"}
          </p>
        </div>
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
          <button onClick={() => navigate(-1)}>Back</button>
          <button onClick={load} disabled={loading}>
            {loading ? "Refreshing..." : "Refresh"}
          </button>
        </div>
      </div>

      <div style={{ marginTop: 12 }}>
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search student name, username, department..."
          style={{ padding: 8, width: "min(420px, 100%)" }}
        />
      </div>

      <div style={{ marginTop: 14, overflowX: "auto" }}>
        {loading ? (
          <p>Loading students...</p>
        ) : (
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
              {filteredStudents.map((student, idx) => (
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
              {filteredStudents.length === 0 && (
                <tr>
                  <td colSpan="6">No students found for this class and term.</td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
