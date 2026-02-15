import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";
import FeatureModal from "../../components/FeatureModal";

function SuperAdminSchools() {
  const [schools, setSchools] = useState([]);
  const navigate = useNavigate();

  // ✅ STEP 1: STATE (already correct)
  const [showFeatureModal, setShowFeatureModal] = useState(false);
  const [selectedSchool, setSelectedSchool] = useState(null);

  const loadSchools = async () => {
    try {
      const res = await api.get("/api/super-admin/schools");
      setSchools(res.data.data);
    } catch (err) {
      console.error("LOAD SCHOOLS ERROR:", err.response?.status, err.response?.data);
      const status = err.response?.status;
      if (status === 401) {
        // Unauthorized — token invalid/expired (clear session)
        localStorage.removeItem("token");
        localStorage.removeItem("user");
        navigate("/login", { replace: true });
        return;
      }
      if (status === 403) {
        // Forbidden — keep session but inform user and navigate to their dashboard
        alert("Access denied: you do not have permission to view this page.");
        const current = JSON.parse(localStorage.getItem("user") || "null");
        if (current?.role === "school_admin") {
          navigate("/school/dashboard", { replace: true });
        } else {
          navigate("/login", { replace: true });
        }
        return;
      }
      alert(err.response?.data?.message || "Failed to load schools");
    }
  };
 
  useEffect(() => {
    const token = localStorage.getItem("token");
    let user = null;

    try {
      user = JSON.parse(localStorage.getItem("user"));
    } catch (e) {
      user = null;
    }

    // If no auth info, clear and redirect to login
    if (!token || !user) {
      localStorage.removeItem("token");
      localStorage.removeItem("user");
      navigate("/login", { replace: true });
      return;
    }

    // If not a super admin, redirect to their dashboard but keep session intact
    if (user.role !== "super_admin") {
      if (user.role === "school_admin") {
        navigate("/school/dashboard", { replace: true });
      } else {
        navigate("/login", { replace: true });
      }
      return;
    }

    loadSchools();
  }, [navigate]);

  const toggleSchool = async (id) => {
    try {
      await api.patch(`/api/super-admin/schools/${id}/toggle`);
      loadSchools();
    } catch (err) {
      console.error("TOGGLE SCHOOL ERROR:", err.response?.data);
      alert(err.response?.data?.message || "Failed to toggle school status");
    }
  };

  return (
    <div>
      <h1>Schools Control Panel</h1>

      <table
        border="1"
        cellPadding="10"
        cellSpacing="0"
        width="100%"
        style={{ marginTop: 20 }}
      >
        <thead>
          <tr>
            <th>School Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Features</th>
          </tr>
        </thead>

        <tbody>
          {schools.map((school) => (
            <tr key={school.id}>
              <td>
                <strong>{school.name}</strong>
              </td>

              <td>{school.email}</td>

              <td>
                <strong>
                  {school.status === "active" ? "Active" : "Suspended"}
                </strong>
              </td>

              <td>
                {/* ✅ STEP 3: OPEN FEATURE MODAL */}
                <button
                  onClick={() => {
                    setSelectedSchool(school);
                    setShowFeatureModal(true);
                  }}
                  className="btn btn-sm btn-outline-primary"
                >
                  Manage Features
                </button>

                <button
                  style={{ marginLeft: 10 }}
                  onClick={() => toggleSchool(school.id)}
                >
                  {school.status === "active" ? "Disable" : "Enable"}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {/* ✅ STEP 4: FEATURE MODAL */}
      {showFeatureModal && selectedSchool && (
        <FeatureModal
          school={selectedSchool}
          onClose={() => setShowFeatureModal(false)}
        />
      )}
    </div>
  );
}

export default SuperAdminSchools;
