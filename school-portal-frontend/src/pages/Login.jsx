import { useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../services/api";
import "./Login.css";

function Login() {
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      // 1️⃣ LOGIN
      const res = await api.post("/api/login", {
        email,
        password,
      });

      const { token, user } = res.data;

      // 2️⃣ STORE AUTH
      localStorage.setItem("token", token);
      localStorage.setItem("user", JSON.stringify(user));

      // 3️⃣ ROUTE BASED ON ROLE
      if (user.role === "super_admin") {
        navigate("/super-admin", { replace: true });
      } else if (user.role === "school_admin") {
        const featuresRes = await api.get("/api/schools/features");
        localStorage.setItem(
          "features",
          JSON.stringify(featuresRes.data.data || featuresRes.data)
        );
        navigate("/school/dashboard", { replace: true });
      } else if (user.role === "staff") {
        navigate("/staff/dashboard", { replace: true });
      } else if (user.role === "student") {
        navigate("/student/dashboard", { replace: true });
      } else {
        navigate("/login", { replace: true });
      }

    } catch (err) {
      alert(err.response?.data?.message || "Login failed");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-page">
      <div className="login-shell">
        <section className="login-hero">
          <span className="hero-pill">Education Portal</span>
          <h1>Welcome Back to School</h1>
          <p>
            One digital campus for learning, teaching, and school operations.
            Sign in to access classroom tools, performance records, and your
            school community.
          </p>
          <ul className="hero-list">
            <li>Classes, assessments, and reports in one place</li>
            <li>Secure access for students, staff, and administrators</li>
            <li>Built for daily school activities and communication</li>
          </ul>
        </section>

        <section className="login-card">
          <h2>Sign In to Continue</h2>
          <p className="login-subtitle">
            Use your official school email and password.
          </p>

          <form onSubmit={handleSubmit} className="login-form">
            <label htmlFor="login-email">Email</label>
            <input
              id="login-email"
              type="email"
              placeholder="you@school.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
              required
            />

            <label htmlFor="login-password">Password</label>
            <input
              id="login-password"
              placeholder="Enter password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              required
            />

            <button className="login-btn" disabled={loading}>
              {loading ? "Logging in..." : "Login"}
            </button>
          </form>
        </section>
      </div>
    </div>
  );
}

export default Login;
