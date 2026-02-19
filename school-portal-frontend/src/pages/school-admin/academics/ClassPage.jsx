import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

export default function ClassPage() {
  const { classId } = useParams();
  const navigate = useNavigate();

  const [cls, setCls] = useState(null);
  const [terms, setTerms] = useState([]);
  const [currentTeacher, setCurrentTeacher] = useState(null);

  const load = async () => {
    const [termsRes, teachersRes] = await Promise.all([
      api.get(`/api/school-admin/classes/${classId}/terms`),
      api.get(`/api/school-admin/classes/${classId}/eligible-teachers`),
    ]);
    setCls(termsRes.data.data.class);
    setTerms(termsRes.data.data.terms || []);
    setCurrentTeacher(teachersRes.data?.meta?.current_teacher || null);
  };

  useEffect(() => {
    load();
  }, [classId]);

  const unassign = async () => {
    if (!window.confirm("Unassign current class teacher?")) return;
    await api.patch(`/api/school-admin/classes/${classId}/unassign-teacher`);
    await load();
  };

  return (
    <div>
      {/* Navbar */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
      </div>

      {/* Actions */}
      <div style={{ display: "flex", gap: 10, marginTop: 12, flexWrap: "wrap" }}>
        {!currentTeacher ? (
          <button onClick={() => navigate(`/school/admin/classes/${classId}/assign-teacher`)}>
            Assign Class Teacher
          </button>
        ) : (
          <button onClick={unassign}>
            Unassign Class Teacher
          </button>
        )}

        <button onClick={() => navigate(`/school/admin/classes/${classId}/enroll-students`)}>
          Enroll Students
        </button>
      </div>

      <div style={{ marginTop: 10, opacity: 0.85 }}>
        <strong>Class Teacher:</strong>{" "}
        {currentTeacher ? `${currentTeacher.name}${currentTeacher.email ? ` (${currentTeacher.email})` : ""}` : "Not assigned"}
      </div>

      {/* Terms table */}
      <table border="1" cellPadding="10" width="100%" style={{ marginTop: 16 }}>
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

              {/* ✅ KEEP TERM AS LINK */}
              <td>
                <button
                  onClick={() => navigate(`/school/admin/classes/${classId}/terms/${t.id}`)}
                  style={{
                    background: "transparent",
                    border: "none",
                    color: "#2563eb",
                    cursor: "pointer",
                    fontWeight: 600,
                  }}
                >
                  {t.name}
                </button>
              </td>

              {/* ✅ VIEW ENROLLED STUDENTS */}
              <td>
                <button
                  onClick={() =>
                    navigate(`/school/admin/classes/${classId}/terms/${t.id}/students`)
                  }
                  style={{ padding: "6px 10px", cursor: "pointer" }}
                >
                  View Enrolled
                </button>
              </td>

              {/* ✅ BULK ENROLL BUTTON (PER TERM) */}
              <td>
                <button
                  onClick={() =>
                    navigate(`/school/admin/classes/${classId}/terms/${t.id}/enroll`)
                  }
                >
                  Enroll (Bulk / One-by-One)
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
  );
}
