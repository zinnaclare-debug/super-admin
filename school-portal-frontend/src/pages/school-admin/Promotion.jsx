import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";
import { getStoredUser } from "../../utils/authStorage";

const isMissingCurrentSessionTerm = (message = "") =>
  String(message).toLowerCase().includes("no current academic session/term configured");

export default function Promotion() {
  const [levels, setLevels] = useState([]);
  const [session, setSession] = useState(null);
  const [term, setTerm] = useState(null);
  const [loadingLevels, setLoadingLevels] = useState(true);
  const [sessionConfigError, setSessionConfigError] = useState("");

  const [selectedClass, setSelectedClass] = useState(null);
  const [students, setStudents] = useState([]);
  const [nextClass, setNextClass] = useState(null);
  const [loadingStudents, setLoadingStudents] = useState(false);
  const [promotingStudentId, setPromotingStudentId] = useState(null);
  const [bulkPromoting, setBulkPromoting] = useState(false);
  const [selectedStudentIds, setSelectedStudentIds] = useState([]);

  const schoolName = useMemo(() => {
    const u = getStoredUser();
    return u?.school?.name || u?.school_name || "School";
  }, []);

  const promotableStudents = useMemo(
    () => students.filter((row) => row.can_promote && !row.already_promoted),
    [students]
  );

  const selectedPromotableIds = useMemo(
    () => selectedStudentIds.filter((id) => promotableStudents.some((row) => row.student_id === id)),
    [selectedStudentIds, promotableStudents]
  );

  const allPromotableSelected =
    promotableStudents.length > 0 && selectedPromotableIds.length === promotableStudents.length;

  const loadClasses = async () => {
    setLoadingLevels(true);
    try {
      const res = await api.get("/api/school-admin/promotion/classes");
      const data = res.data?.data || {};
      setSessionConfigError("");
      setLevels(data.levels || []);
      setSession(data.current_session || null);
      setTerm(data.current_term || null);
    } catch (e) {
      const message = e?.response?.data?.message || "Failed to load classes for promotion.";
      if (isMissingCurrentSessionTerm(message)) {
        setSessionConfigError(message);
      } else {
        alert(message);
      }
      setLevels([]);
      setSession(null);
      setTerm(null);
    } finally {
      setLoadingLevels(false);
    }
  };

  const loadClassStudents = async (classRow) => {
    setSelectedClass(classRow);
    setSelectedStudentIds([]);
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
      await api.post(`/api/school-admin/promotion/classes/${selectedClass.id}/students/${studentId}/promote`);
      await loadClassStudents(selectedClass);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to promote student.");
    } finally {
      setPromotingStudentId(null);
    }
  };

  const toggleStudentSelection = (studentId) => {
    setSelectedStudentIds((prev) =>
      prev.includes(studentId) ? prev.filter((id) => id !== studentId) : [...prev, studentId]
    );
  };

  const toggleSelectAll = () => {
    if (allPromotableSelected) {
      setSelectedStudentIds([]);
      return;
    }
    setSelectedStudentIds(promotableStudents.map((row) => row.student_id));
  };

  const bulkPromoteStudents = async () => {
    if (!selectedClass?.id || selectedPromotableIds.length === 0) return;
    if (!window.confirm(`Promote ${selectedPromotableIds.length} selected student(s) to the next class?`)) return;

    setBulkPromoting(true);
    let promotedCount = 0;
    const failedNames = [];

    try {
      for (const studentId of selectedPromotableIds) {
        const row = students.find((item) => item.student_id === studentId);
        try {
          await api.post(`/api/school-admin/promotion/classes/${selectedClass.id}/students/${studentId}/promote`);
          promotedCount += 1;
        } catch (e) {
          failedNames.push(row?.name || `Student ${studentId}`);
        }
      }

      await loadClassStudents(selectedClass);

      if (failedNames.length === 0) {
        alert(`Promoted ${promotedCount} student(s) successfully.`);
      } else {
        alert(
          `Promoted ${promotedCount} student(s). Failed: ${failedNames.join(", ")}`
        );
      }
    } finally {
      setBulkPromoting(false);
      setSelectedStudentIds([]);
    }
  };

  useEffect(() => {
    loadClasses();
  }, []);

  return (
    <div>
      {sessionConfigError ? (
        <p style={{ marginTop: 10, color: "#b45309" }}>{sessionConfigError}</p>
      ) : null}
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
                      border: selectedClass?.id === cls.id ? "1px solid #2563eb" : "1px solid #ccc",
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
            {nextClass
              ? `Next Class: ${nextClass.name}${students[0]?.next_session?.session_name ? ` | Session: ${students[0].next_session.session_name}` : ""}`
              : "This is the final class for this level."}
          </p>

          {!loadingStudents && promotableStudents.length > 0 && (
            <div style={{ marginBottom: 12, display: "flex", justifyContent: "space-between", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
              <div style={{ opacity: 0.8 }}>
                Selected: <strong>{selectedPromotableIds.length}</strong> of <strong>{promotableStudents.length}</strong> promotable student(s)
              </div>
              <button onClick={bulkPromoteStudents} disabled={bulkPromoting || selectedPromotableIds.length === 0}>
                {bulkPromoting ? "Promoting Selected..." : "Bulk Promote"}
              </button>
            </div>
          )}

          {loadingStudents ? (
            <p>Loading students...</p>
          ) : (
            <table border="1" cellPadding="10" cellSpacing="0" width="100%">
              <thead>
                <tr>
                  <th style={{ width: 60 }}>
                    <input
                      type="checkbox"
                      checked={allPromotableSelected}
                      onChange={toggleSelectAll}
                      disabled={promotableStudents.length === 0 || bulkPromoting}
                    />
                  </th>
                  <th style={{ width: 70 }}>S/N</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th style={{ width: 220 }}>Action</th>
                </tr>
              </thead>
              <tbody>
                {students.map((row) => {
                  const canSelect = row.can_promote && !row.already_promoted;
                  return (
                    <tr key={row.student_id}>
                      <td>
                        <input
                          type="checkbox"
                          checked={selectedStudentIds.includes(row.student_id)}
                          onChange={() => toggleStudentSelection(row.student_id)}
                          disabled={!canSelect || bulkPromoting}
                        />
                      </td>
                      <td>{row.sn}</td>
                      <td>{row.name}</td>
                      <td>{row.email || "-"}</td>
                      <td>
                        <button
                          onClick={() => promoteStudent(row.student_id)}
                          disabled={!row.can_promote || promotingStudentId === row.student_id || bulkPromoting}
                        >
                          {promotingStudentId === row.student_id
                            ? "Promoting..."
                            : row.already_promoted
                              ? "Promoted"
                              : row.can_promote
                                ? "Promote"
                                : "No Next Class"}
                        </button>
                      </td>
                    </tr>
                  );
                })}
                {students.length === 0 && (
                  <tr>
                    <td colSpan="5">No students enrolled in this class.</td>
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
