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

  const toAbsoluteUrl = (url) => {
    if (!url) return "";

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

  const user = profile?.user || {};
  const student = profile?.student || {};
  const currentSession = profile?.current_session || null;
  const currentTerm = profile?.current_term || null;
  const currentClass = profile?.current_class || null;
  const currentDepartment = profile?.current_department || null;
  const rawPhotoUrl =
    profile?.photo_url ||
    (profile?.photo_path ? `/storage/${profile.photo_path}` : "") ||
    (student?.photo_path ? `/storage/${student.photo_path}` : "");
  const photoUrl = toAbsoluteUrl(rawPhotoUrl);

  return (
    <div>
      {loading ? (
        <p>Loading profile...</p>
      ) : error ? (
        <p style={{ color: "red" }}>{error}</p>
      ) : (
        <div style={{ border: "1px solid #ddd", borderRadius: 10, padding: 14, marginTop: 10 }}>
          <div style={{ marginBottom: 16, display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 16 }}>
            <div style={{ flex: 1, minWidth: 0 }}>
              <h3 style={{ marginTop: 0 }}>Student Details</h3>
              <table cellPadding="8" style={{ width: "100%" }}>
                <tbody>
                  <tr><td style={{ width: 180, opacity: 0.75 }}>Name</td><td><strong>{user.name || "-"}</strong></td></tr>
                  <tr><td style={{ opacity: 0.75 }}>Sex</td><td>{student.sex || "-"}</td></tr>
                  <tr><td style={{ opacity: 0.75 }}>DOB</td><td>{student.dob || "-"}</td></tr>
                  <tr><td style={{ opacity: 0.75 }}>Address</td><td>{student.address || "-"}</td></tr>
                </tbody>
              </table>
            </div>
            {photoUrl ? (
              <img
                src={photoUrl}
                alt="Student profile"
                style={{ width: 130, height: 130, borderRadius: 8, objectFit: "cover", border: "1px solid #ddd", flexShrink: 0 }}
              />
            ) : (
              <div
                style={{
                  width: 130,
                  height: 130,
                  borderRadius: 8,
                  border: "1px solid #ddd",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  opacity: 0.7,
                  flexShrink: 0,
                }}
              >
                No Photo
              </div>
            )}
          </div>

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

        </div>
      )}
    </div>
  );
}
