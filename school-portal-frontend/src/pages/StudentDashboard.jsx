import { useNavigate } from "react-router-dom";
import { useEffect, useState } from "react";
import { clearAuthState, getStoredUser } from "../utils/authStorage";

function StudentDashboard() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);

  useEffect(() => {
    const currentUser = getStoredUser();
    if (!currentUser) {
      navigate("/login", { replace: true });
      return;
    }
    setUser(currentUser);
  }, [navigate]);

  const handleLogout = () => {
    clearAuthState();
    navigate("/login", { replace: true });
  };

  if (!user) return <div>Loading...</div>;

  return (
    <div style={{ padding: 40 }}>
      <h1>Student Dashboard</h1>
      <p>Welcome, {user.name}!</p>
      <p>Role: {user.role}</p>
      
      <button onClick={handleLogout} style={{ marginTop: 20 }}>
        Logout
      </button>
    </div>
  );
}

export default StudentDashboard;
