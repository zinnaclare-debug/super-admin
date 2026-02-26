import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";
import profileArt from "../../assets/profile/profile-card.svg";
import proudArt from "../../assets/profile/proud-self.svg";
import "../shared/ProfileShowcase.css";

export default function StaffProfile() {
  const [me, setMe] = useState(null);
  const [loading, setLoading] = useState(true);

  const [photoFile, setPhotoFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [photoVersion, setPhotoVersion] = useState(0);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/staff/profile");
      const incoming = res.data.data || res.data;
      setMe((prev) => {
        if (!incoming) return prev || null;
        if (!prev?.staff || !incoming?.staff) return incoming;

        if (!incoming.staff.photo_url && prev.staff.photo_url) {
          incoming.staff.photo_url = prev.staff.photo_url;
        }
        if (!incoming.staff.photo_path && prev.staff.photo_path) {
          incoming.staff.photo_path = prev.staff.photo_path;
        }
        return incoming;
      });
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

  const photoUrl = useMemo(() => {
    if (!me) return null;

    let resolved = null;
    if (me.photo_url) resolved = toAbsoluteUrl(me.photo_url);
    else if (me.photo_path) resolved = toAbsoluteUrl(`/storage/${me.photo_path}`);
    else if (me.staff?.photo_url) resolved = toAbsoluteUrl(me.staff.photo_url);
    else if (me.staff?.photo_path) resolved = toAbsoluteUrl(`/storage/${me.staff.photo_path}`);

    if (!resolved) return null;

    const separator = resolved.includes("?") ? "&" : "?";
    return `${resolved}${separator}v=${photoVersion}`;
  }, [me, photoVersion]);

  const uploadPhoto = async () => {
    if (!photoFile) return alert("Select a photo first");

    setUploading(true);
    try {
      const fd = new FormData();
      fd.append("photo", photoFile);

      const res = await api.post("/api/staff/profile/photo", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      const uploadedPath = res?.data?.data?.photo_path || "";
      const uploadedUrl = toAbsoluteUrl(
        res?.data?.data?.photo_url || (uploadedPath ? `/storage/${uploadedPath}` : "")
      );

      if (uploadedUrl) {
        setMe((prev) => ({
          ...(prev || {}),
          staff: {
            ...(prev?.staff || {}),
            photo_path: uploadedPath || prev?.staff?.photo_path || "",
            photo_url: uploadedUrl,
          },
        }));
      }

      setPhotoVersion((v) => v + 1);
      alert("Photo uploaded successfully");
      setPhotoFile(null);
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || e?.response?.data?.error || "Upload failed");
    } finally {
      setUploading(false);
    }
  };

  if (loading) {
    return (
      <div className="pf-page pf-page--staff">
        <p className="pf-state pf-state--loading">Loading profile...</p>
      </div>
    );
  }

  if (!me) {
    return (
      <div className="pf-page pf-page--staff">
        <p className="pf-state pf-state--empty">Profile not found.</p>
      </div>
    );
  }

  const accountRows = [
    ["Name", me.name || me.user?.name || "-"],
    ["Username", me.username || me.user?.username || "-"],
    ["Email", me.email || me.user?.email || "-"],
    ["Role", me.role || me.user?.role || "staff"],
  ];

  const staffRows = [
    ["Position", me.staff_position || me.position || me.staff?.position || "-"],
    ["Education Level", me.education_level || me.staff?.education_level || "-"],
    ["Sex", me.sex || me.staff?.sex || "-"],
    ["Date of Birth", me.dob || me.staff?.dob || "-"],
    ["Address", me.address || me.staff?.address || "-"],
  ];

  return (
    <div className="pf-page pf-page--staff">
      <section className="pf-hero">
        <div>
          <span className="pf-pill">Staff Profile</span>
          <h2 className="pf-title">Professional profile for school operations</h2>
          <p className="pf-subtitle">
            Manage your account details and keep your staff identity up to date for daily school workflow.
          </p>
          <div className="pf-meta">
            <span>{me.name || me.user?.name || "Staff Member"}</span>
            <span>{me.staff_position || me.position || me.staff?.position || "Role not set"}</span>
            <span>{me.education_level || me.staff?.education_level || "Level pending"}</span>
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
        <div className="pf-grid pf-grid--top">
          <article className="pf-card">
            <h3>Profile Photo</h3>
            <div className="pf-photo-box">
              {photoUrl ? <img src={photoUrl} alt="Staff profile" /> : <span className="pf-photo-empty">No Photo</span>}
            </div>

            <div className="pf-photo-tools">
              <input
                className="pf-file-input"
                type="file"
                accept="image/*"
                onChange={(e) => setPhotoFile(e.target.files?.[0] || null)}
              />
              <button className="pf-btn" onClick={uploadPhoto} disabled={!photoFile || uploading}>
                {uploading ? "Uploading..." : "Upload Photo"}
              </button>
            </div>
          </article>

          <article className="pf-card">
            <h3>Account</h3>
            <div className="pf-kv">
              {accountRows.map(([label, value]) => (
                <div className="pf-row" key={label}>
                  <span className="pf-row-label">{label}</span>
                  <span className="pf-row-value">{value}</span>
                </div>
              ))}
            </div>

            <div className="pf-actions">
              <button className="pf-btn" onClick={load}>
                Refresh
              </button>
            </div>
          </article>
        </div>

        <div className="pf-grid" style={{ marginTop: 12 }}>
          <article className="pf-card">
            <h3>Staff Details</h3>
            <div className="pf-kv">
              {staffRows.map(([label, value]) => (
                <div className="pf-row" key={label}>
                  <span className="pf-row-label">{label}</span>
                  <span className="pf-row-value">{value}</span>
                </div>
              ))}
            </div>
          </article>
        </div>
      </section>
    </div>
  );
}
