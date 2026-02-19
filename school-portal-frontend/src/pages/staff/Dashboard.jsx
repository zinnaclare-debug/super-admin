import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";

export default function StaffDashboard() {
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const toAbsoluteUrl = (url) => {
    if (!url) return null;

    const base = (api.defaults.baseURL || "").replace(/\/$/, "");
    const apiOrigin = base ? new URL(base).origin : window.location.origin;

    if (/^(blob:|data:)/i.test(url)) return url;

    if (/^https?:\/\//i.test(url)) {
      try {
        const parsed = new URL(url);
        if (parsed.pathname.startsWith("/storage/")) {
          return `${apiOrigin}${parsed.pathname}${parsed.search}`;
        }
      } catch {
        return url;
      }
      return url;
    }

    return `${apiOrigin}${url.startsWith("/") ? "" : "/"}${url}`;
  };

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError("");
      try {
        const res = await api.get("/api/staff/profile");
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
  const staff = profile?.staff || {};
  const classes = profile?.classes || [];
  const isClassTeacher = classes.length > 0;
  const staffPhotoUrl = toAbsoluteUrl(
    staff.photo_url || (staff.photo_path ? `/storage/${staff.photo_path}` : "")
  );

  return (
    <div>
      <h1>Staff Dashboard</h1>

      {/* Quick Access Buttons */}
      <div style={{ marginTop: 16, display: "flex", gap: 8, flexWrap: "wrap" }}>
        <button 
          onClick={() => navigate("/staff/results")}
          style={{ padding: "10px 16px", borderRadius: 8, background: "#2563eb", color: "#fff", cursor: "pointer", border: "none" }}
        >
          View My Results/Courses
        </button>
      </div>

      <section style={{ marginTop: 24 }}>
        <h2>Profile Details</h2>

        {loading ? (
          <p>Loading profile...</p>
        ) : error ? (
          <p style={{ color: "red" }}>{error}</p>
        ) : (
          <div style={{ border: "1px solid #ddd", borderRadius: 10, padding: 14 }}>
            {staffPhotoUrl ? (
              <div style={{ marginBottom: 16 }}>
                <img
                  src={staffPhotoUrl}
                  alt="Staff profile"
                  style={{ width: 110, height: 110, borderRadius: 8, objectFit: "cover", border: "1px solid #ddd" }}
                />
              </div>
            ) : null}
            <table cellPadding="8" style={{ width: "100%" }}>
              <tbody>
                <tr><td style={{ width: 180, opacity: 0.75 }}>Name</td><td><strong>{user.name || "-"}</strong></td></tr>
                <tr><td style={{ opacity: 0.75 }}>Email</td><td>{user.email || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Username</td><td>{user.username || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Education Level</td><td>{staff.education_level || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Position</td><td>{staff.position || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Sex</td><td>{staff.sex || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>DOB</td><td>{staff.dob || "-"}</td></tr>
                <tr><td style={{ opacity: 0.75 }}>Address</td><td>{staff.address || "-"}</td></tr>
              </tbody>
            </table>
          </div>
        )}
      </section>

      {isClassTeacher ? (
        <section style={{ marginTop: 24 }}>
          <h2>Class Teacher</h2>
          <p style={{ marginTop: 0, opacity: 0.8 }}>You are assigned as class teacher to the class(es) below.</p>
          <table border="1" cellPadding="8" width="100%">
            <thead>
              <tr>
                <th>S/N</th>
                <th>Class</th>
                <th>Level</th>
              </tr>
            </thead>
            <tbody>
              {classes.map((c, idx) => (
                <tr key={c.id}>
                  <td>{idx + 1}</td>
                  <td>{c.name}</td>
                  <td>{c.level}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      ) : null}
    </div>
  );
}
