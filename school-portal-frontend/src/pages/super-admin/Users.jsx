import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";

function Users() {
  const navigate = useNavigate();
  const [schools, setSchools] = useState([]);
  const [loadingSchools, setLoadingSchools] = useState(true);

  const loadSchools = async () => {
    setLoadingSchools(true);
    try {
      const res = await api.get("/api/super-admin/schools");
      setSchools(res.data?.data || []);
    } catch {
      alert("Failed to load schools");
      setSchools([]);
    } finally {
      setLoadingSchools(false);
    }
  };

  useEffect(() => {
    loadSchools();
  }, []);

  return (
    <div>
      <h1>Platform Users</h1>
      <p>Select a school to view students by education level.</p>
      <div style={{ marginTop: 8 }}>
        <button onClick={() => navigate("/super-admin/users/login-details")}>
          School Admin Login Details
        </button>
      </div>

      <div style={{ marginTop: 14 }}>
        <strong>Schools</strong>
        <div style={{ display: "flex", flexWrap: "wrap", gap: 8, marginTop: 8 }}>
          {loadingSchools ? (
            <p>Loading schools...</p>
          ) : schools.length === 0 ? (
            <p>No schools found.</p>
          ) : (
            schools.map((s) => (
              <button
                key={s.id}
                onClick={() => navigate(`/super-admin/users/${s.id}`)}
                style={{
                  padding: "8px 10px",
                  borderRadius: 6,
                  border: "1px solid #ccc",
                  background: "#fff",
                }}
              >
                {s.name}
              </button>
            ))
          )}
        </div>
      </div>
    </div>
  );
}

export default Users;
