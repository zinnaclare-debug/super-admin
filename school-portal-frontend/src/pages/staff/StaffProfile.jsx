import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";

export default function StaffProfile() {
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [photoPreview, setPhotoPreview] = useState(null);
  const [selectedFile, setSelectedFile] = useState(null);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    loadProfile();
  }, []);

  const loadProfile = async () => {
    setLoading(true);
    setError("");
    try {
      const res = await api.get("/api/staff/profile");
      if (!res?.data?.data) {
        setError(res?.data?.message || "No profile data returned");
        setProfile(null);
      } else {
        setProfile(res.data.data);
      }
    } catch (err) {
      setError(err?.response?.data?.message || "Failed to load profile");
      console.error("Error loading profile:", err);
      setProfile(null);
    } finally {
      setLoading(false);
    }
  };

  const handlePhotoSelect = (e) => {
    const file = e.target.files?.[0];
    if (file) {
      setSelectedFile(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setPhotoPreview(reader.result);
      };
      reader.readAsDataURL(file);
    }
  };

  const handlePhotoUpload = async () => {
    if (!selectedFile) return;

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append("photo", selectedFile);
      const res = await api.post("/api/staff/profile/photo", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      alert("Photo uploaded successfully");
      
      // Update profile state immediately with new photo URL
      if (res.data?.data?.photo_url) {
        setProfile(prev => ({
          ...prev,
          staff: {
            ...prev.staff,
            photo_path: res.data.data.photo_path,
            photo_url: res.data.data.photo_url,
          }
        }));
      }
      
      setSelectedFile(null);
      setPhotoPreview(null);
      
      // Reload full profile to ensure sync with backend
      await loadProfile();
    } catch (err) {
      console.error("Photo upload error:", err);
      alert(err?.response?.data?.message || "Failed to upload photo");
    } finally {
      setUploading(false);
    }
  };

  if (loading) {
    return <div style={{ padding: "20px", textAlign: "center" }}>Loading profile...</div>;
  }

  if (error) {
    return (
      <div style={{ padding: "20px", color: "red" }}>
        <p>{error}</p>
        <button onClick={() => navigate(-1)}>Go Back</button>
      </div>
    );
  }

  if (!profile) {
    return (
      <div style={{ padding: "20px" }}>
        <p>No profile data found.</p>
        <button onClick={() => navigate(-1)}>Go Back</button>
      </div>
    );
  }

  const { user, staff, classes } = profile;

  return (
    <div style={{ padding: "20px", maxWidth: "900px", margin: "0 auto" }}>
      {/* Navbar */}
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          marginBottom: "20px",
          borderBottom: "1px solid #ddd",
          paddingBottom: "10px",
        }}
      >
        <h1>My Profile</h1>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      {/* Profile Photo Section */}
      <div
        style={{
          border: "1px solid #ddd",
          borderRadius: "8px",
          padding: "20px",
          marginBottom: "20px",
          display: "flex",
          gap: "20px",
          alignItems: "flex-start",
        }}
      >
        {/* Photo Widget */}
        <div style={{ textAlign: "center" }}>
          <div
            style={{
              width: "150px",
              height: "150px",
              backgroundColor: "#f0f0f0",
              borderRadius: "8px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              marginBottom: "10px",
              overflow: "hidden",
              border: "2px solid #ddd",
            }}
          >
            {photoPreview ? (
              <img
                src={photoPreview}
                alt="Preview"
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
              />
            ) : profile?.staff?.photo_url ? (
              <img
                src={profile.staff.photo_url}
                alt="Profile"
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
              />
            ) : (
              <span style={{ fontSize: "40px", color: "#999" }}>📷</span>
            )}
          </div>
          <input
            type="file"
            accept="image/*"
            onChange={handlePhotoSelect}
            style={{ marginBottom: "10px" }}
          />
          <div style={{ display: "flex", gap: "8px", marginTop: "10px" }}>
            {selectedFile && (
              <>
                <button
                  onClick={handlePhotoUpload}
                  disabled={uploading}
                  style={{ backgroundColor: "#4CAF50", color: "white", padding: "8px 12px" }}
                >
                  {uploading ? "Uploading..." : "Upload"}
                </button>
                <button
                  onClick={() => {
                    setSelectedFile(null);
                    setPhotoPreview(null);
                  }}
                  style={{ backgroundColor: "#f44336", color: "white", padding: "8px 12px" }}
                >
                  Cancel
                </button>
              </>
            )}
          </div>
        </div>

        {/* User Info Section */}
        <div style={{ flex: 1 }}>
          <h2 style={{ marginTop: 0, marginBottom: "10px" }}>{user.name}</h2>
          <table border="0" style={{ width: "100%", borderCollapse: "collapse" }}>
            <tbody>
              <tr style={{ borderBottom: "1px solid #f0f0f0" }}>
                <td style={{ fontWeight: "bold", paddingRight: "10px", paddingBottom: "8px" }}>
                  Email:
                </td>
                <td style={{ paddingBottom: "8px" }}>{user.email}</td>
              </tr>
              <tr style={{ borderBottom: "1px solid #f0f0f0" }}>
                <td style={{ fontWeight: "bold", paddingRight: "10px", paddingBottom: "8px" }}>
                  Username:
                </td>
                <td style={{ paddingBottom: "8px" }}>{user.username || "N/A"}</td>
              </tr>
              <tr style={{ borderBottom: "1px solid #f0f0f0" }}>
                <td style={{ fontWeight: "bold", paddingRight: "10px", paddingBottom: "8px" }}>
                  Education Level:
                </td>
                <td style={{ paddingBottom: "8px", textTransform: "capitalize" }}>
                  {staff.education_level || "N/A"}
                </td>
              </tr>
              <tr>
                <td style={{ fontWeight: "bold", paddingRight: "10px", paddingBottom: "8px" }}>
                  Position:
                </td>
                <td style={{ paddingBottom: "8px" }}>{staff.position || "N/A"}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      {/* Staff Details Section */}
      <div
        style={{
          border: "1px solid #ddd",
          borderRadius: "8px",
          padding: "20px",
          marginBottom: "20px",
        }}
      >
        <h3>Staff Details</h3>
        <table border="0" style={{ width: "100%", borderCollapse: "collapse" }}>
          <tbody>
            <tr style={{ borderBottom: "1px solid #f0f0f0" }}>
              <td style={{ fontWeight: "bold", paddingRight: "20px", paddingBottom: "10px" }}>
                Gender:
              </td>
              <td style={{ paddingBottom: "10px", textTransform: "capitalize" }}>
                {staff.sex || "N/A"}
              </td>
            </tr>
            <tr style={{ borderBottom: "1px solid #f0f0f0" }}>
              <td style={{ fontWeight: "bold", paddingRight: "20px", paddingBottom: "10px" }}>
                Date of Birth:
              </td>
              <td style={{ paddingBottom: "10px" }}>
                {staff.dob ? new Date(staff.dob).toLocaleDateString() : "N/A"}
              </td>
            </tr>
            <tr>
              <td style={{ fontWeight: "bold", paddingRight: "20px", paddingBottom: "10px" }}>
                Address:
              </td>
              <td style={{ paddingBottom: "10px" }}>{staff.address || "N/A"}</td>
            </tr>
          </tbody>
        </table>
      </div>

      {/* Classes Section */}
      <div style={{ border: "1px solid #ddd", borderRadius: "8px", padding: "20px" }}>
        <h3>Classes Taught</h3>
        {classes && classes.length > 0 ? (
          <table border="1" cellPadding="10" width="100%" style={{ borderCollapse: "collapse" }}>
            <thead style={{ backgroundColor: "#f5f5f5" }}>
              <tr>
                <th style={{ textAlign: "left" }}>Class Name</th>
                <th style={{ textAlign: "left" }}>Level</th>
              </tr>
            </thead>
            <tbody>
              {classes.map((cls) => (
                <tr key={cls.id} style={{ borderBottom: "1px solid #f0f0f0" }}>
                  <td>{cls.name}</td>
                  <td style={{ textTransform: "capitalize" }}>{cls.level}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p style={{ color: "#666" }}>Not assigned to any class as class teacher.</p>
        )}
      </div>
    </div>
  );
}
