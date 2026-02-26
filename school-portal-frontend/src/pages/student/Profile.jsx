import { useEffect, useState } from "react";
import api from "../../services/api";
import profileArt from "../../assets/profile/profile-card.svg";
import proudArt from "../../assets/profile/proud-self.svg";
import "../shared/ProfileShowcase.css";

export default function StudentProfile() {
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
  const guardian = profile?.guardian || null;
  const currentSession = profile?.current_session || null;
  const currentTerm = profile?.current_term || null;
  const currentClass = profile?.current_class || null;
  const currentDepartment = profile?.current_department || null;
  const rawPhotoUrl =
    profile?.photo_url ||
    (profile?.photo_path ? `/storage/${profile.photo_path}` : "") ||
    (student?.photo_path ? `/storage/${student.photo_path}` : "");
  const photoUrl = toAbsoluteUrl(rawPhotoUrl);

  const studentRows = [
    ["Name", user.name || "-"],
    ["Email", user.email || "-"],
    ["Username", user.username || "-"],
    ["Sex", student.sex || "-"],
    ["Religion", student.religion || "-"],
    ["Date of Birth", student.dob || "-"],
    ["Address", student.address || "-"],
  ];

  const academicRows = [
    ["Current Session", currentSession?.session_name || currentSession?.academic_year || "-"],
    ["Current Term", currentTerm?.name || "-"],
    ["Current Class", currentClass ? `${currentClass.name} (${currentClass.level})` : "-"],
    ["Department", currentDepartment?.name || "-"],
  ];

  const guardianRows = guardian
    ? [
        ["Name", guardian.name || "-"],
        ["Relationship", guardian.relationship || "-"],
        ["Email", guardian.email || "-"],
        ["Mobile", guardian.mobile || "-"],
        ["Occupation", guardian.occupation || "-"],
        ["Location", guardian.location || "-"],
        ["State of Origin", guardian.state_of_origin || "-"],
      ]
    : [];

  return (
    <div className="pf-page pf-page--student">
      <section className="pf-hero">
        <div>
          <span className="pf-pill">Student Profile</span>
          <h2 className="pf-title">Your learning identity at a glance</h2>
          <p className="pf-subtitle">
            Track your personal details, current academic placement, and guardian contact information in one place.
          </p>
          <div className="pf-meta">
            <span>{user.name || "Student"}</span>
            <span>{currentClass ? `${currentClass.name} (${currentClass.level})` : "Class pending"}</span>
            <span>{currentTerm?.name || "No active term"}</span>
          </div>
        </div>

        <div className="pf-hero-art" aria-hidden="true">
          <div className="pf-art pf-art--main">
            <img src={profileArt} alt="" />
          </div>
          <div className="pf-art pf-art--alt">
            <img src={proudArt} alt="" />
          </div>
        </div>
      </section>

      <section className="pf-panel">
        {loading ? <p className="pf-state pf-state--loading">Loading profile...</p> : null}
        {!loading && error ? <p className="pf-state pf-state--error">{error}</p> : null}

        {!loading && !error ? (
          <>
            <div className="pf-grid pf-grid--top">
              <article className="pf-card">
                <h3>Student Details</h3>
                <div className="pf-kv">
                  {studentRows.map(([label, value]) => (
                    <div className="pf-row" key={label}>
                      <span className="pf-row-label">{label}</span>
                      <span className="pf-row-value">{value}</span>
                    </div>
                  ))}
                </div>
              </article>

              <article className="pf-card">
                <h3>Profile Photo</h3>
                <div className="pf-photo-box">
                  {photoUrl ? <img src={photoUrl} alt="Student profile" /> : <span className="pf-photo-empty">No Photo</span>}
                </div>
              </article>
            </div>

            <div className="pf-grid pf-grid--double">
              <article className="pf-card">
                <h3>Current Academic Info</h3>
                <div className="pf-kv">
                  {academicRows.map(([label, value]) => (
                    <div className="pf-row" key={label}>
                      <span className="pf-row-label">{label}</span>
                      <span className="pf-row-value">{value}</span>
                    </div>
                  ))}
                </div>
              </article>

              <article className="pf-card">
                <h3>Guardian Details</h3>
                {guardianRows.length > 0 ? (
                  <div className="pf-kv">
                    {guardianRows.map(([label, value]) => (
                      <div className="pf-row" key={label}>
                        <span className="pf-row-label">{label}</span>
                        <span className="pf-row-value">{value}</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="pf-state pf-state--empty">No guardian details found.</p>
                )}
              </article>
            </div>
          </>
        ) : null}
      </section>
    </div>
  );
}
