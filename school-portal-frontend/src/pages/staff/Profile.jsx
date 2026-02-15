import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";

export default function StaffProfile() {
  const [me, setMe] = useState(null);
  const [loading, setLoading] = useState(true);

  const [photoFile, setPhotoFile] = useState(null);
  const [uploading, setUploading] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/staff/profile");
      setMe(res.data.data || res.data); // support either {data:{...}} or {...}
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load staff profile");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const toAbsoluteUrl = (url) => {
    if (!url) return null;
    if (url.startsWith("http://") || url.startsWith("https://")) return url;
    const base = (api.defaults.baseURL || "").replace(/\/$/, "");
    if (!base) return url;
    return `${base}${url.startsWith("/") ? "" : "/"}${url}`;
  };

  const photoUrl = useMemo(() => {
    if (!me) return null;

    // Your backend might return photo_url directly
    if (me.photo_url) return toAbsoluteUrl(me.photo_url);

    // Or it might return photo_path like "schools/14/profiles/ABC.png"
    if (me.photo_path) return toAbsoluteUrl(`/storage/${me.photo_path}`);

    // Or nested (depends on your API response)
    if (me.staff?.photo_url) return toAbsoluteUrl(me.staff.photo_url);
    if (me.staff?.photo_path) return toAbsoluteUrl(`/storage/${me.staff.photo_path}`);

    return null;
  }, [me]);

  const uploadPhoto = async () => {
    if (!photoFile) return alert("Select a photo first");

    setUploading(true);
    try {
      const fd = new FormData();
      fd.append("photo", photoFile);

      await api.post("/api/staff/profile/photo", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      alert("Photo uploaded successfully");
      setPhotoFile(null);
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || e?.response?.data?.error || "Upload failed");
    } finally {
      setUploading(false);
    }
  };

  if (loading) return <p>Loading profile...</p>;
  if (!me) return <p>Profile not found.</p>;

  return (
    <div style={{ maxWidth: 900 }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h2 style={{ margin: 0 }}>Staff Profile</h2>
      </div>

      <div style={{ marginTop: 14, display: "flex", gap: 18, alignItems: "flex-start" }}>
        {/* Photo */}
        <div style={{ width: 180 }}>
          <div
            style={{
              width: 160,
              height: 160,
              borderRadius: 12,
              border: "1px solid #ddd",
              overflow: "hidden",
              background: "#fafafa",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            {photoUrl ? (
              <img
                src={photoUrl}
                alt="Profile"
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
              />
            ) : (
              <span style={{ opacity: 0.65 }}>No Photo</span>
            )}
          </div>

          <div style={{ marginTop: 10 }}>
            <input
              type="file"
              accept="image/*"
              onChange={(e) => setPhotoFile(e.target.files?.[0] || null)}
            />
            <div style={{ marginTop: 8 }}>
              <button onClick={uploadPhoto} disabled={!photoFile || uploading}>
                {uploading ? "Uploading..." : "Upload Photo"}
              </button>
            </div>
          </div>
        </div>

        {/* Details */}
        <div style={{ flex: 1 }}>
          <div style={{ border: "1px solid #ddd", borderRadius: 12, padding: 14 }}>
            <h3 style={{ marginTop: 0, marginBottom: 10 }}>Account</h3>

            <table cellPadding="8" style={{ width: "100%" }}>
              <tbody>
                <tr>
                  <td style={{ width: 180, opacity: 0.75 }}>Name</td>
                  <td><strong>{me.name || me.user?.name || "-"}</strong></td>
                </tr>
                <tr>
                  <td style={{ opacity: 0.75 }}>Username</td>
                  <td>{me.username || me.user?.username || "-"}</td>
                </tr>
                <tr>
                  <td style={{ opacity: 0.75 }}>Email</td>
                  <td>{me.email || me.user?.email || "-"}</td>
                </tr>
                <tr>
                  <td style={{ opacity: 0.75 }}>Role</td>
                  <td>{me.role || me.user?.role || "staff"}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div style={{ border: "1px solid #ddd", borderRadius: 12, padding: 14, marginTop: 12 }}>
            <h3 style={{ marginTop: 0, marginBottom: 10 }}>Staff Details</h3>

            <table cellPadding="8" style={{ width: "100%" }}>
              <tbody>
                <tr>
                  <td style={{ width: 180, opacity: 0.75 }}>Position</td>
                  <td>{me.staff_position || me.position || me.staff?.position || "-"}</td>
                </tr>
                <tr>
                  <td style={{ opacity: 0.75 }}>Education Level</td>
                  <td>{me.education_level || me.staff?.education_level || "-"}</td>
                </tr>
                <tr>
                  <td style={{ opacity: 0.75 }}>Sex</td>
                  <td>{me.sex || me.staff?.sex || "-"}</td>
                </tr>
                <tr>
                  <td style={{ opacity: 0.75 }}>DOB</td>
                  <td>{me.dob || me.staff?.dob || "-"}</td>
                </tr>
                <tr>
                  <td style={{ opacity: 0.75 }}>Address</td>
                  <td>{me.address || me.staff?.address || "-"}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <button onClick={load} style={{ marginTop: 12 }}>
            Refresh
          </button>
        </div>
      </div>
    </div>
  );
}
