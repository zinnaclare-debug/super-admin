import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";
import usersArt from "../../assets/users/people.svg";

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
    <div className="sa-page sa-page--users">
      <section className="sa-page-hero">
        <div>
          <span className="sa-page-eyebrow">People directory</span>
          <h1>Platform Users</h1>
          <p>Open a school to review its students by education level and manage administrator login records.</p>
        </div>
        <img className="sa-page-art" src={usersArt} alt="" aria-hidden="true" />
      </section>

      <div style={{ marginTop: 8 }}>
        <button onClick={() => navigate("/super-admin/users/login-details")}>
          School Admin Login Details
        </button>
      </div>

      <div style={{ marginTop: 14 }}>
        <strong>Schools</strong>
        <div style={{ display: "flex", flexDirection: "column", gap: 8, marginTop: 8, maxWidth: 420 }}>
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
                  padding: "10px 12px",
                  borderRadius: 6,
                  border: "1px solid #ccc",
                  background: "#fff",
                  textAlign: "left",
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
