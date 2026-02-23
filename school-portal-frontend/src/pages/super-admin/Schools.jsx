import { useEffect, useState } from "react";
import api from "../../services/api";
import FeatureTable from "../../components/FeatureTable";
import { FEATURE_DEFINITIONS } from "../../config/features";

const TENANCY_BASE_DOMAIN = "lyt.com.ng";

function Schools() {
  const [schools, setSchools] = useState([]);

  // Create school + admin
  const [schoolName, setSchoolName] = useState("");
  const [schoolSubdomain, setSchoolSubdomain] = useState("");
  const [subdomainTouched, setSubdomainTouched] = useState(false);
  const [schoolEmail, setSchoolEmail] = useState("");
  const [newAdminName, setNewAdminName] = useState("");
  const [newAdminEmail, setNewAdminEmail] = useState("");
  const [generatedPassword, setGeneratedPassword] = useState(null);
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState(null);

  // Edit school
  const [editingId, setEditingId] = useState(null);
  const [editName, setEditName] = useState("");
  const [editEmail, setEditEmail] = useState("");

  // Features
  const [showFeatureModal, setShowFeatureModal] = useState(false);
  const [featureSchool, setFeatureSchool] = useState(null);
  const [schoolFeatures, setSchoolFeatures] = useState([]);
  const [resettingAdminId, setResettingAdminId] = useState(null);
  const [actionValues, setActionValues] = useState({});

  const slugifySubdomain = (value) =>
    value
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]/g, "");

  const formatApiError = (err) => {
    const data = err?.response?.data;
    if (data?.errors && typeof data.errors === "object") {
      const first = Object.values(data.errors).flat()[0];
      if (first) return first;
    }

    return data?.message || err?.message || "Request failed";
  };

  const getBaseDomain = () => TENANCY_BASE_DOMAIN;

  const schoolWebAddress = (subdomain) => {
    if (!subdomain) return "-";
    const baseDomain = getBaseDomain();
    return `https://${subdomain}.${baseDomain}`;
  };

  const loadSchools = async () => {
    const res = await api.get("/api/super-admin/schools");
    setSchools(res.data.data);
  };

  useEffect(() => {
    loadSchools();
  }, []);

  const createSchoolWithAdmin = async (e) => {
    e.preventDefault();
    setCreateError(null);

    if (!schoolName || !schoolSubdomain || !schoolEmail || !newAdminName || !newAdminEmail) {
      setCreateError("Please fill in all fields.");
      return;
    }

    setCreating(true);

    try {
      const res = await api.post("/api/super-admin/schools/create-with-admin", {
        school_name: schoolName,
        subdomain: schoolSubdomain,
        school_email: schoolEmail,
        admin_name: newAdminName,
        admin_email: newAdminEmail,
      });

      setGeneratedPassword(res.data.password);
      await loadSchools();

      setSchoolName("");
      setSchoolSubdomain("");
      setSubdomainTouched(false);
      setSchoolEmail("");
      setNewAdminName("");
      setNewAdminEmail("");
    } catch (err) {
      setCreateError(formatApiError(err));
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

  const labelMap = FEATURE_DEFINITIONS.reduce((acc, cur) => {
    acc[cur.key] = cur.label;
    return acc;
  }, {});

  const openFeatureModal = async (school) => {
    setFeatureSchool(school);
    await refreshSchoolFeatures(school.id);
    setShowFeatureModal(true);
  };

  const refreshSchoolFeatures = async (schoolId) => {
    const res = await api.get(`/api/super-admin/schools/${schoolId}/features`);
    setSchoolFeatures(res.data.data || []);
  };

  const toggleFeature = async (featureKey, enabled) => {
    await api.post(`/api/super-admin/schools/${featureSchool.id}/features/toggle`, {
      feature: featureKey,
      enabled,
    });

    await loadSchools();
    await refreshSchoolFeatures(featureSchool.id);
  };

  const runSchoolAction = async (school, action) => {
    if (!action) return;

    setActionValues((prev) => ({ ...prev, [school.id]: action }));

    try {
      switch (action) {
        case "toggle":
          await toggleSchool(school.id);
          break;
        case "edit":
          startEdit(school);
          break;
        case "delete":
          await deleteSchool(school.id);
          break;
        case "features":
          await openFeatureModal(school);
          break;
        case "reset_admin":
          await resetAdminPassword(school);
          break;
        case "toggle_results":
          await toggleResultsPublish(school.id);
          break;
        default:
          break;
      }
    } finally {
      setActionValues((prev) => ({ ...prev, [school.id]: "" }));
    }
  };

  const formInputStyle = {
    width: "100%",
    boxSizing: "border-box",
    minHeight: 36,
    padding: "8px 10px",
  };

  return (
    <div style={{ width: "100%", maxWidth: "100%" }}>
      <h1 style={{ marginTop: 0, fontSize: "clamp(1.7rem, 3vw, 2.4rem)" }}>Schools</h1>

      <form
        onSubmit={createSchoolWithAdmin}
        style={{
          marginBottom: 20,
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))",
          gap: 8,
        }}
      >
        <input
          placeholder="School Name"
          value={schoolName}
          style={formInputStyle}
          onChange={(e) => {
            const value = e.target.value;
            setSchoolName(value);
            if (!subdomainTouched) {
              setSchoolSubdomain(slugifySubdomain(value));
            }
          }}
        />
        <input
          placeholder="Subdomain (e.g. firstschool)"
          value={schoolSubdomain}
          style={formInputStyle}
          onChange={(e) => {
            setSubdomainTouched(true);
            setSchoolSubdomain(slugifySubdomain(e.target.value));
          }}
        />
        <input
          placeholder="School Email"
          value={schoolEmail}
          style={formInputStyle}
          onChange={(e) => setSchoolEmail(e.target.value)}
        />
        <input
          placeholder="Admin Name"
          value={newAdminName}
          style={formInputStyle}
          onChange={(e) => setNewAdminName(e.target.value)}
        />
        <input
          placeholder="Admin Email"
          value={newAdminEmail}
          style={formInputStyle}
          onChange={(e) => setNewAdminEmail(e.target.value)}
        />

        {createError && (
          <div style={{ color: "red", marginTop: 8, gridColumn: "1 / -1" }}>{createError}</div>
        )}

        <button
          type="submit"
          disabled={creating}
          style={{ gridColumn: "1 / -1", justifySelf: "start" }}
        >
          {creating ? "Creating..." : "Create School & Admin"}
        </button>
      </form>

      {generatedPassword && (
        <div style={{ background: "#fef3c7", padding: 15, marginBottom: 20, wordBreak: "break-word" }}>
          <strong>School Admin Password (show once):</strong>
          <p>{generatedPassword}</p>
        </div>
      )}

      <div style={{ width: "100%", overflowX: "auto" }}>
        <table border="1" cellPadding="10" cellSpacing="0" style={{ width: "100%", minWidth: 980 }}>
          <thead>
            <tr>
              <th>School Name</th>
              <th>Subdomain</th>
              <th>School Web Address</th>
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
                    <input style={formInputStyle} value={editName} onChange={(e) => setEditName(e.target.value)} />
                  ) : (
                    s.name
                  )}
                </td>

                <td>{s.subdomain || "-"}</td>

                <td style={{ wordBreak: "break-word" }}>
                  {s.subdomain ? (
                    <a href={schoolWebAddress(s.subdomain)} target="_blank" rel="noreferrer">
                      {schoolWebAddress(s.subdomain)}
                    </a>
                  ) : (
                    "-"
                  )}
                </td>

                <td>
                  {editingId === s.id ? (
                    <input style={formInputStyle} value={editEmail} onChange={(e) => setEditEmail(e.target.value)} />
                  ) : (
                    s.email
                  )}
                </td>

                <td>
                  <strong>{s.status === "active" ? "Active" : "Suspended"}</strong>
                </td>

                <td>
                  {s.admin ? <strong>{s.admin.name}</strong> : <span style={{ color: "red" }}>None</span>}
                </td>

                <td>
                  {editingId === s.id ? (
                    <>
                      <button onClick={() => updateSchool(s.id)}>Save</button>
                      <button onClick={cancelEdit}>Cancel</button>
                    </>
                  ) : (
                    <select
                      style={{ width: "100%", minWidth: 190 }}
                      value={actionValues[s.id] || ""}
                      onChange={(e) => runSchoolAction(s, e.target.value)}
                    >
                      <option value="">Select Action</option>
                      <option value="toggle">
                        {s.status === "active" ? "Suspend School" : "Activate School"}
                      </option>
                      <option value="edit">Edit School</option>
                      <option value="delete">Delete School</option>
                      <option value="features">Manage Features</option>
                      <option value="toggle_results">
                        {s.results_published ? "Unpublish Results" : "Publish Results"}
                      </option>
                      <option value="reset_admin" disabled={!s.admin || resettingAdminId === s.admin?.id}>
                        {resettingAdminId === s.admin?.id ? "Resetting Admin Password..." : "Reset Admin Password"}
                      </option>
                    </select>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {showFeatureModal && (
        <div style={{ border: "1px solid #000", padding: 20 }}>
          <h3>Manage Features - {featureSchool.name}</h3>

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
