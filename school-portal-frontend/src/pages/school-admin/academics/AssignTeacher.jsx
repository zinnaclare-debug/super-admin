import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

export default function AssignTeacher() {
  const { classId } = useParams();
  const navigate = useNavigate();

  const [teachers, setTeachers] = useState([]);
  const [teacherId, setTeacherId] = useState("");
  const [currentTeacher, setCurrentTeacher] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/school-admin/classes/${classId}/eligible-teachers`);
      setTeachers(res.data.data || []);
      setCurrentTeacher(res.data?.meta?.current_teacher || null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [classId]);

  const assign = async () => {
    if (!teacherId) return alert("Select a teacher");
    setSaving(true);
    try {
      await api.patch(`/api/school-admin/classes/${classId}/assign-teacher`, {
        teacher_user_id: Number(teacherId),
      });
      alert("Teacher assigned");
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to assign teacher");
    } finally {
      setSaving(false);
    }
  };

  const unassign = async () => {
    if (!window.confirm("Unassign current class teacher?")) return;
    setSaving(true);
    try {
      await api.patch(`/api/school-admin/classes/${classId}/unassign-teacher`);
      alert("Teacher unassigned");
      await load();
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
        <h2>Assign Class Teacher</h2>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      {loading ? (
        <p>Loading teachers...</p>
      ) : (
        <>
          <p style={{ opacity: 0.75 }}>
            Only teachers registered for this class level will show here.
          </p>

          <div style={{ marginBottom: 10 }}>
            <strong>Current Class Teacher:</strong>{" "}
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
