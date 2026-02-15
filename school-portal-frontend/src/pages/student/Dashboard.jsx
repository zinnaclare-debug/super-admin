import { useEffect, useState } from "react";
import api from "../../services/api";

export default function StudentDashboard() {
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError("");
      try {
        const res = await api.get("/api/student/profile");
        setProfile(res.data?.data || null);
      } catch (e) {
        setError(e?.response?.data?.message || "Failed to load profile");
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  const user = profile?.user || {};
  const student = profile?.student || {};
  const guardian = profile?.guardian || null;
  const currentSession = profile?.current_session || null;
  const currentTerm = profile?.current_term || null;
  const currentClass = profile?.current_class || null;
  const currentDepartment = profile?.current_department || null;
  const photoUrl = profile?.photo_url || "";

  return (
    <div>
      <h1>Student Dashboard</h1>

      {loading ? (
        <p>Loading profile...</p>
      ) : error ? (
        <p style={{ color: "red" }}>{error}</p>
      ) : (
        <div style={{ border: "1px solid #ddd", borderRadius: 10, padding: 14, marginTop: 10 }}>
          {photoUrl ? (
            <div style={{ marginBottom: 16 }}>
              <img
                src={photoUrl}
                alt="Student profile"
                style={{ width: 110, height: 110, borderRadius: 8, objectFit: "cover", border: "1px solid #ddd" }}
              />
            </div>
          ) : null}

          <h3 style={{ marginTop: 0 }}>Student Details</h3>
          <table cellPadding="8" style={{ width: "100%" }}>
            <tbody>
              <tr><td style={{ width: 180, opacity: 0.75 }}>Name</td><td><strong>{user.name || "-"}</strong></td></tr>
              <tr><td style={{ opacity: 0.75 }}>Email</td><td>{user.email || "-"}</td></tr>
              <tr><td style={{ opacity: 0.75 }}>Username</td><td>{user.username || "-"}</td></tr>
              <tr><td style={{ opacity: 0.75 }}>Sex</td><td>{student.sex || "-"}</td></tr>
              <tr><td style={{ opacity: 0.75 }}>Religion</td><td>{student.religion || "-"}</td></tr>
              <tr><td style={{ opacity: 0.75 }}>DOB</td><td>{student.dob || "-"}</td></tr>
              <tr><td style={{ opacity: 0.75 }}>Address</td><td>{student.address || "-"}</td></tr>
            </tbody>
          </table>

          <h3 style={{ marginTop: 18 }}>Current Academic Info</h3>
          <table cellPadding="8" style={{ width: "100%" }}>
            <tbody>
              <tr>
                <td style={{ width: 180, opacity: 0.75 }}>Current Session</td>
                <td>{currentSession?.session_name || currentSession?.academic_year || "-"}</td>
              </tr>
              <tr>
                <td style={{ opacity: 0.75 }}>Current Term</td>
                <td>{currentTerm?.name || "-"}</td>
              </tr>
              <tr>
                <td style={{ opacity: 0.75 }}>Current Class</td>
                <td>{currentClass ? `${currentClass.name} (${currentClass.level})` : "-"}</td>
              </tr>
              <tr>
                <td style={{ opacity: 0.75 }}>Department</td>
                <td>{currentDepartment?.name || "-"}</td>
              </tr>
            </tbody>
          </table>

          <h3 style={{ marginTop: 18 }}>Guardian Details</h3>
          {guardian ? (
            <table cellPadding="8" style={{ width: "100%" }}>
              <tbody>
                <tr><td style={{ width: 180, opacity: 0.75 }}>Name</td><td><strong>{guardian.name || "-"}</strong></td></tr>
                <tr><td style={{ opacity: 0.75 }}>Relationship</td><td>{guardian.relationship || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Email</td><td>{guardian.email || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Mobile</td><td>{guardian.mobile || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Occupation</td><td>{guardian.occupation || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Location</td><td>{guardian.location || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>State of Origin</td><td>{guardian.state_of_origin || "-"}</td></tr>
              </tbody>
            </table>
          ) : (
            <p style={{ opacity: 0.8 }}>No guardian details found.</p>
          )}
        </div>
      )}
    </div>
  );
}
