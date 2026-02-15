import { useEffect, useState } from "react";
import api from "../../services/api";
import FeatureTable from "../../components/FeatureTable";
import { FEATURE_DEFINITIONS } from "../../config/features";

function Schools() {
  const [schools, setSchools] = useState([]);

  // ✅ CREATE SCHOOL + ADMIN
  const [schoolName, setSchoolName] = useState("");
    const [deleteMessage, setDeleteMessage] = useState(null);
    const [deleteError, setDeleteError] = useState(null);
  const [schoolEmail, setSchoolEmail] = useState("");
  const [newAdminName, setNewAdminName] = useState("");
  const [newAdminEmail, setNewAdminEmail] = useState("");
  const [generatedPassword, setGeneratedPassword] = useState(null);

  // UI state for creating
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState(null);

  // ✏️ EDIT SCHOOL
  const [editingId, setEditingId] = useState(null);
  const [editName, setEditName] = useState("");
  const [editEmail, setEditEmail] = useState("");

  // 🔧 FEATURES
  const [showFeatureModal, setShowFeatureModal] = useState(false);
  const [featureSchool, setFeatureSchool] = useState(null);
  const [schoolFeatures, setSchoolFeatures] = useState([]);
  const [resettingAdminId, setResettingAdminId] = useState(null);

  const loadSchools = async () => {
    const res = await api.get("/api/super-admin/schools");
    setSchools(res.data.data);
  };

  useEffect(() => {
    loadSchools();
  }, []);

  // ✅ CREATE SCHOOL + ADMIN (MAIN ENTRY POINT)
  const createSchoolWithAdmin = async (e) => {
    e.preventDefault();

    setCreateError(null);

    // Basic client-side validation
    if (!schoolName || !schoolEmail || !newAdminName || !newAdminEmail) {
      setCreateError("Please fill in all fields.");
      return;
    }

    setCreating(true);

    try {
      const res = await api.post(
        "/api/super-admin/schools/create-with-admin",
        {
          school_name: schoolName,
          school_email: schoolEmail,
          admin_name: newAdminName,
          admin_email: newAdminEmail,
        }
      );

      console.log("CREATE RESPONSE:", res.data); // 🔥 DEBUG

      setGeneratedPassword(res.data.password);

      // 👇 force refresh AFTER password state update
      await loadSchools();

      setSchoolName("");
      setSchoolEmail("");
      setNewAdminName("");
      setNewAdminEmail("");
    } catch (err) {
      console.error("Create school failed:", err);
      setCreateError(
        err.response?.data?.message || err.response?.data?.errors || err.message || "Request failed"
      );
      // If unauthorized, redirect to login (optional)
      if (err.response?.status === 401) {
        window.location.href = "/login";
      }
    } finally {
      setCreating(false);
    }
  };

  const toggleSchool = async (id) => {
    await api.patch(`/api/super-admin/schools/${id}/toggle`);
    loadSchools();
  };

  const toggleResultsPublish = async (id) => {
    await api.patch(`/api/super-admin/schools/${id}/toggle-results`);
    loadSchools();
  };

  const startEdit = (school) => {
    setEditingId(school.id);
    setEditName(school.name);
    setEditEmail(school.email);
  };

  const cancelEdit = () => {
    setEditingId(null);
  };

  const updateSchool = async (id) => {
    await api.put(`/api/super-admin/schools/${id}`, {
      name: editName,
      email: editEmail,
    });
    cancelEdit();
    loadSchools();
  };

  const deleteSchool = async (id) => {
    if (!window.confirm("Delete this school?")) return;
    await api.delete(`/api/super-admin/schools/${id}`);
    loadSchools();
  };

  const resetAdminPassword = async (school) => {
    const admin = school?.admin;
    if (!admin?.id) {
      alert("This school does not have an assigned admin.");
      return;
    }

    const password = window.prompt(`Enter new password for ${admin.name}:`);
    if (!password) return;
    if (password.length < 6) {
      alert("Password must be at least 6 characters.");
      return;
    }

    const confirmPassword = window.prompt("Confirm new password:");
    if (confirmPassword !== password) {
      alert("Passwords do not match.");
      return;
    }

    setResettingAdminId(admin.id);
    try {
      await api.post(`/api/super-admin/users/${admin.id}/reset-password`, { password });
      alert("School admin password reset successfully.");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to reset school admin password.");
    } finally {
      setResettingAdminId(null);
    }
  };

  // 🔧 FEATURES
  const labelMap = FEATURE_DEFINITIONS.reduce((acc, cur) => {
    acc[cur.key] = cur.label;
    return acc;
  }, {});

  const openFeatureModal = async (school) => {
    setFeatureSchool(school);
    await refreshSchoolFeatures(school.id);
    setShowFeatureModal(true);
  };

  const isFeatureEnabled = (feature) =>
    schoolFeatures.some((f) => f.feature === feature && f.enabled);

  const refreshSchoolFeatures = async (schoolId) => {
    const res = await api.get(`/api/super-admin/schools/${schoolId}/features`);
    setSchoolFeatures(res.data.data || []);
  };

  const toggleFeature = async (featureKey, enabled) => {
    await api.post(
      `/api/super-admin/schools/${featureSchool.id}/features/toggle`,
      { feature: featureKey, enabled }
    );

    await loadSchools();
    await refreshSchoolFeatures(featureSchool.id);
  };

  return (
    <div>
      <h1>Schools</h1>

      {/* ✅ CREATE SCHOOL + ADMIN */}
<form onSubmit={createSchoolWithAdmin} style={{ marginBottom: 20 }}>
  <input
    placeholder="School Name"
    value={schoolName}
    onChange={(e) => setSchoolName(e.target.value)}
  />
  <input
    placeholder="School Email"
    value={schoolEmail}
    onChange={(e) => setSchoolEmail(e.target.value)}
  />
  <input
    placeholder="Admin Name"
    value={newAdminName}
    onChange={(e) => setNewAdminName(e.target.value)}
  />
  <input
    placeholder="Admin Email"
    value={newAdminEmail}
    onChange={(e) => setNewAdminEmail(e.target.value)}
  />

  {createError && (
    <div style={{ color: "red", marginTop: 8 }}>{createError}</div>
  )}

  <button type="submit" disabled={creating}>
    {creating ? "Creating..." : "Create School & Admin"}
  </button>
</form>


      {/* 🔐 GENERATED PASSWORD */}
      {generatedPassword && (
        <div style={{ background: "#fef3c7", padding: 15, marginBottom: 20 }}>
          <strong>School Admin Password (show once):</strong>
          <p>{generatedPassword}</p>
        </div>
      )}

      {/* 📋 TABLE */}
      <table border="1" cellPadding="10" cellSpacing="0" width="100%">
        <thead>
          <tr>
            <th>School Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Admin</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          {schools.map((s) => (
            <tr key={s.id}>
              <td>
                {editingId === s.id ? (
                  <input
                    value={editName}
                    onChange={(e) => setEditName(e.target.value)}
                  />
                ) : (
                  s.name
                )}
              </td>

              <td>
                {editingId === s.id ? (
                  <input
                    value={editEmail}
                    onChange={(e) => setEditEmail(e.target.value)}
                  />
                ) : (
                  s.email
                )}
              </td>

              <td>
                <strong>{s.status === "active" ? "Active" : "Suspended"}</strong>
              </td>

              <td>
                {s.admin ? (
                  <strong>{s.admin.name}</strong>
                ) : (
                  <span style={{ color: "red" }}>None</span>
                )}
              </td>

              <td>
                {editingId === s.id ? (
                  <>
                    <button onClick={() => updateSchool(s.id)}>Save</button>
                    <button onClick={cancelEdit}>Cancel</button>
                  </>
                ) : (
                  <>
                    <button onClick={() => toggleSchool(s.id)}>
                      {s.status === "active" ? "Suspend" : "Activate"}
                    </button>

                    <button onClick={() => startEdit(s)}>Edit</button>

                    <button
                      onClick={() => deleteSchool(s.id)}
                      style={{ color: "red" }}
                    >
                      Delete
                    </button>

                    <button onClick={() => openFeatureModal(s)}>
                      Manage Features
                    </button>

                    {s.admin && (
                      <button
                        onClick={() => resetAdminPassword(s)}
                        disabled={resettingAdminId === s.admin?.id}
                      >
                        {resettingAdminId === s.admin?.id ? "Resetting..." : "Reset Admin Password"}
                      </button>
                    )}

                    <button onClick={() => toggleResultsPublish(s.id)}>
                      {s.results_published ? "Unpublish Results" : "Publish Results"}
                    </button>
                  </>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {/* 🔧 FEATURE MODAL */}
      {showFeatureModal && (
        <div style={{ border: "1px solid #000", padding: 20 }}>
          <h3>Manage Features — {featureSchool.name}</h3>

          <div style={{ marginTop: 12 }}>
            <FeatureTable
              features={schoolFeatures}
              onToggle={async (featureKey, enabled) => {
                await toggleFeature(featureKey, enabled);
              }}
              labelMap={labelMap}
              showDescription={false}
            />
          </div>

          <button onClick={() => setShowFeatureModal(false)}>Close</button>
        </div>
      )}
    </div>
  );
}

export default Schools;
