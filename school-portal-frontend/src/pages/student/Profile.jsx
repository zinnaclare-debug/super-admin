import { useEffect, useState } from "react";
import api from "../../services/api";

export default function StudentProfile() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError("");
      try {
        const res = await api.get("/api/student/profile");
        setData(res.data?.data || null);
      } catch (e) {
        setError(e?.response?.data?.message || "Failed to load profile");
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  const user = data?.user || {};
  const student = data?.student || {};

  return (
    <div>
      <h2>My Profile</h2>

      {loading ? (
        <p>Loading...</p>
      ) : error ? (
        <p style={{ color: "red" }}>{error}</p>
      ) : (
        <div style={{ border: "1px solid #ddd", borderRadius: 10, padding: 14, marginTop: 10 }}>
          {data?.photo_url ? (
            <div style={{ marginBottom: 16 }}>
              <img
                src={data.photo_url}
                alt="Student profile"
                style={{ width: 110, height: 110, borderRadius: 8, objectFit: "cover", border: "1px solid #ddd" }}
              />
            </div>
          ) : null}

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
        </div>
      )}
    </div>
  );
}
