import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";

export default function Promotion() {
  const [levels, setLevels] = useState([]);
  const [session, setSession] = useState(null);
  const [term, setTerm] = useState(null);
  const [loadingLevels, setLoadingLevels] = useState(true);

  const [selectedClass, setSelectedClass] = useState(null);
  const [students, setStudents] = useState([]);
  const [nextClass, setNextClass] = useState(null);
  const [loadingStudents, setLoadingStudents] = useState(false);
  const [promotingStudentId, setPromotingStudentId] = useState(null);

  const schoolName = useMemo(() => {
    const u = JSON.parse(localStorage.getItem("user") || "null");
    return u?.school?.name || u?.school_name || "School";
  }, []);

  const loadClasses = async () => {
    setLoadingLevels(true);
    try {
      const res = await api.get("/api/school-admin/promotion/classes");
      const data = res.data?.data || {};
      setLevels(data.levels || []);
      setSession(data.current_session || null);
      setTerm(data.current_term || null);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load classes for promotion.");
      setLevels([]);
      setSession(null);
      setTerm(null);
    } finally {
      setLoadingLevels(false);
    }
  };

  const loadClassStudents = async (classRow) => {
    setSelectedClass(classRow);
    setLoadingStudents(true);
    try {
      const res = await api.get(`/api/school-admin/promotion/classes/${classRow.id}/students`);
      const data = res.data?.data || {};
      setStudents(data.students || []);
      setNextClass(data.next_class || null);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load students for selected class.");
      setStudents([]);
      setNextClass(null);
    } finally {
      setLoadingStudents(false);
    }
  };

  const promoteStudent = async (studentId) => {
    if (!selectedClass?.id) return;
    if (!window.confirm("Promote this student to the next class?")) return;

    setPromotingStudentId(studentId);
    try {
      await api.post(
        `/api/school-admin/promotion/classes/${selectedClass.id}/students/${studentId}/promote`
      );
      await loadClassStudents(selectedClass);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to promote student.");
    } finally {
      setPromotingStudentId(null);
    }
  };

  useEffect(() => {
    loadClasses();
  }, []);

  return (
    <div>
      <h2>Promotion</h2>
      <p style={{ marginTop: 6, opacity: 0.8 }}>
        {schoolName} | Session: {session?.session_name || session?.academic_year || "-"} | Term:{" "}
        {term?.name || "-"}
      </p>

      {loadingLevels ? (
        <p>Loading available classes...</p>
      ) : (
        <div style={{ marginTop: 12, display: "grid", gap: 12 }}>
          {levels.map((item) => (
            <div
              key={item.level}
              style={{ border: "1px solid #ddd", borderRadius: 8, padding: 12, background: "#fff" }}
            >
              <strong>{String(item.level || "").toUpperCase()}</strong>
              <div style={{ marginTop: 10, display: "flex", gap: 8, flexWrap: "wrap" }}>
                {(item.classes || []).map((cls) => (
                  <button
                    key={cls.id}
                    onClick={() => loadClassStudents(cls)}
                    style={{
                      padding: "8px 10px",
                      borderRadius: 6,
                      border:
                        selectedClass?.id === cls.id ? "1px solid #2563eb" : "1px solid #ccc",
                      background: selectedClass?.id === cls.id ? "#eff6ff" : "#fff",
                    }}
                  >
                    {cls.name}
                  </button>
                ))}
              </div>
            </div>
          ))}
          {levels.length === 0 && <p>No classes found in the current session.</p>}
        </div>
      )}

      {selectedClass && (
        <div style={{ marginTop: 18 }}>
          <h3 style={{ marginBottom: 6 }}>
            {selectedClass.name} ({selectedClass.level})
          </h3>
          <p style={{ marginTop: 0, opacity: 0.75 }}>
            {nextClass ? `Next Class: ${nextClass.name}` : "This is the final class for this level."}
          </p>

          {loadingStudents ? (
            <p>Loading students...</p>
          ) : (
            <table border="1" cellPadding="10" cellSpacing="0" width="100%">
              <thead>
                <tr>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th style={{ width: 220 }}>Action</th>
                </tr>
              </thead>
              <tbody>
                {students.map((row) => (
                  <tr key={row.student_id}>
                    <td>{row.sn}</td>
                    <td>{row.name}</td>
                    <td>{row.email || "-"}</td>
                    <td>
                      <button
                        onClick={() => promoteStudent(row.student_id)}
                        disabled={!row.can_promote || promotingStudentId === row.student_id}
                      >
                        {promotingStudentId === row.student_id
                          ? "Promoting..."
                          : row.can_promote
                            ? "Promote"
                            : "Promoted"}
                      </button>
                    </td>
                  </tr>
                ))}
                {students.length === 0 && (
                  <tr>
                    <td colSpan="4">No students enrolled in this class.</td>
                  </tr>
                )}
              </tbody>
            </table>
          )}
        </div>
      )}
    </div>
  );
}

