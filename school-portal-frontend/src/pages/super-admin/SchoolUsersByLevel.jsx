import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../services/api";
import studentsArt from "../../assets/dashboard/students.svg";

function SchoolUsersByLevel() {
  const { schoolId } = useParams();
  const navigate = useNavigate();
  const [school, setSchool] = useState(null);
  const [levels, setLevels] = useState([]);
  const [selectedLevel, setSelectedLevel] = useState("");
  const [students, setStudents] = useState([]);
  const [requestMode, setRequestMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [approvingId, setApprovingId] = useState(null);
  const [loading, setLoading] = useState(true);

  const load = async (level = "") => {
    setLoading(true);
    try {
      const res = await api.get(`/api/super-admin/schools/${schoolId}/students-by-level`, { params: level ? { level } : {} });
      const data = res.data?.data || {};
      setSchool(data.school || null);
      setLevels(data.levels || []);
      setStudents(data.students || []);
      setRequestMode(false);
      setSelectedIds(new Set());
    } catch {
      alert("Failed to load school students");
      setStudents([]);
    } finally {
      setLoading(false);
    }
  };

  const loadRequests = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/super-admin/schools/${schoolId}/reactivation-requests`);
      setStudents(res.data?.data?.students || []);
      setRequestMode(true);
      setSelectedLevel("");
      setSelectedIds(new Set());
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load reactivation requests.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(""); }, [schoolId]);

  const approve = async (studentId) => {
    setApprovingId(studentId);
    try {
      const res = await api.post(`/api/super-admin/students/${studentId}/approve-reactivation`);
      setStudents((current) => current.filter((student) => Number(student.student_id) !== Number(studentId)));
      setSelectedIds((current) => { const next = new Set(current); next.delete(studentId); return next; });
      alert(res.data?.message || "Student access enabled.");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to approve reactivation.");
    } finally {
      setApprovingId(null);
    }
  };

  const approveSelected = async () => {
    if (selectedIds.size === 0) return;
    if (!window.confirm(`Enable ${selectedIds.size} selected student(s)?`)) return;
    let completed = 0;
    for (const studentId of selectedIds) {
      try { await api.post(`/api/super-admin/students/${studentId}/approve-reactivation`); completed += 1; } catch { /* Continue so one bad record does not block the batch. */ }
    }
    await loadRequests();
    alert(`${completed} student(s) enabled.`);
  };

  const allSelected = students.length > 0 && students.every((student) => selectedIds.has(student.student_id));
  const toggle = (studentId) => setSelectedIds((current) => {
    const next = new Set(current); if (next.has(studentId)) next.delete(studentId); else next.add(studentId); return next;
  });
  const toggleAll = () => setSelectedIds(allSelected ? new Set() : new Set(students.map((student) => student.student_id)));

  return (
    <div className="sa-page sa-page--students">
      <section className="sa-page-hero">
        <div><span className="sa-page-eyebrow">School records</span><h1>Students by Level</h1><p>Review registered students and approve requests to restore left-school student access.</p></div>
        <img className="sa-page-art" src={studentsArt} alt="" aria-hidden="true" />
      </section>
      <div className="sa-toolbar" style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h2 style={{ marginBottom: 6 }}>{school ? `${school.name} - Students` : "School Students"}</h2>
        <button onClick={() => navigate("/super-admin/users")}>Back to Schools</button>
      </div>
      <div className="sa-filter-list">
        <button onClick={() => { setSelectedLevel(""); load(""); }} className={`sa-filter-button ${!requestMode && selectedLevel === "" ? "is-selected" : ""}`}>All</button>
        {levels.map((level) => <button key={level.key} onClick={() => { setSelectedLevel(level.key); load(level.key); }} className={`sa-filter-button ${!requestMode && selectedLevel === level.key ? "is-selected" : ""}`}>{level.label} ({level.count})</button>)}
        <button onClick={loadRequests} className={`sa-filter-button ${requestMode ? "is-selected" : ""}`}>Requests</button>
      </div>
      {requestMode ? <div style={{ marginTop: 14 }}><button onClick={approveSelected} disabled={selectedIds.size === 0 || approvingId !== null}>Confirm Selected ({selectedIds.size})</button></div> : null}
      <div style={{ marginTop: 16 }}>
        {loading ? <p>Loading students...</p> : <div style={{ width: "100%", overflowX: "auto" }}><table border="1" cellPadding="10" width="100%" style={{ minWidth: requestMode ? 650 : 540 }}><thead><tr>{requestMode ? <th><input type="checkbox" checked={allSelected} onChange={toggleAll} /></th> : null}<th style={{ width: 70 }}>S/N</th><th>Name</th><th>Level</th>{requestMode ? <th>Action</th> : null}</tr></thead><tbody>{students.map((student, index) => <tr key={student.student_id}>{requestMode ? <td><input type="checkbox" checked={selectedIds.has(student.student_id)} onChange={() => toggle(student.student_id)} /></td> : null}<td>{student.sn || index + 1}</td><td>{student.name}</td><td>{student.level}</td>{requestMode ? <td><button onClick={() => approve(student.student_id)} disabled={approvingId !== null}>{approvingId === student.student_id ? "Confirming..." : "Confirm"}</button></td> : null}</tr>)}{students.length === 0 ? <tr><td colSpan={requestMode ? 5 : 3}>No {requestMode ? "reactivation requests" : "students found for this selection"}.</td></tr> : null}</tbody></table></div>}
      </div>
    </div>
  );
}

export default SchoolUsersByLevel;