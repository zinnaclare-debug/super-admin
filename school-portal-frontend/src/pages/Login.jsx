import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../services/api";
import heroArt from "../assets/dashboard/hero.svg";
import graduationArt from "../assets/login/Graduation-cuate.svg";
import brandBanner from "../assets/home/lytebridge-brand.jpg";
import brandLogo from "../assets/home/lytebridge-logo.png";
import "./Login.css";

function Login() {
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [tenantSchool, setTenantSchool] = useState(null);
  const [logoLoadError, setLogoLoadError] = useState(false);

  const toAbsoluteUrl = (url) => {
    if (!url) return "";
    if (/^(https?:\/\/|blob:|data:)/i.test(url)) return url;

    const base = (api.defaults.baseURL || "").replace(/\/$/, "");
    const origin = base ? new URL(base).origin : window.location.origin;
    return `${origin}${url.startsWith("/") ? "" : "/"}${url}`;
  };

  const tenantLogoUrl = useMemo(() => {
    if (tenantSchool?.logo_url) {
      return toAbsoluteUrl(tenantSchool.logo_url);
    }
    if (tenantSchool?.logo_path) {
      return toAbsoluteUrl(`/storage/${tenantSchool.logo_path}`);
    }
    return "";
  }, [tenantSchool]);

  const heroLogoUrl = tenantLogoUrl && !logoLoadError ? tenantLogoUrl : brandBanner;
  const cardLogoUrl = tenantLogoUrl && !logoLoadError ? tenantLogoUrl : brandLogo;

  useEffect(() => {
    let active = true;

    api
      .get("/api/tenant/context")
      .then((res) => {
        if (!active) return;
        if (res?.data?.is_tenant && res?.data?.school) {
          setTenantSchool(res.data.school);
        }
      })
      .catch(() => {
        // Keep generic login page on errors / central domain.
      });

    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    setLogoLoadError(false);
  }, [tenantLogoUrl]);

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
      <div className="login-ambient login-ambient--one" />
      <div className="login-ambient login-ambient--two" />
      <div className="login-shell">
        <section className="login-hero">
          <div className="hero-meta">
            <span className="hero-pill">Smart School Portal</span>
            <span className="hero-domain">{window.location.hostname}</span>
            <img
              className="hero-school-logo"
              src={heroLogoUrl}
              alt={`${tenantSchool?.name || "Lytebridge"} logo`}
              onError={() => {
                if (tenantLogoUrl) setLogoLoadError(true);
              }}
            />
          </div>

          <h1>
            {tenantSchool?.name
              ? `Welcome Back to ${tenantSchool.name}`
              : "Welcome Back to School"}
          </h1>

          <p>
            One digital campus for learning, teaching, and school operations.
            Sign in to access classroom tools, performance records, and your
            school community.
          </p>

          <div className="hero-visual">
            <img className="hero-visual-main" src={heroArt} alt="School management illustration" />
            <img className="hero-visual-accent" src={graduationArt} alt="Graduation illustration" />
            <svg
              className="hero-orbit"
              viewBox="0 0 220 220"
              xmlns="http://www.w3.org/2000/svg"
              aria-hidden="true"
            >
              <circle cx="110" cy="110" r="86" fill="none" stroke="rgba(255,255,255,0.55)" strokeWidth="2" />
              <circle cx="110" cy="110" r="64" fill="none" stroke="rgba(255,255,255,0.35)" strokeWidth="2" />
              <circle cx="34" cy="108" r="6" fill="#f59e0b" />
              <circle cx="186" cy="108" r="6" fill="#10b981" />
              <circle cx="110" cy="24" r="6" fill="#38bdf8" />
              <circle cx="110" cy="196" r="6" fill="#f97316" />
            </svg>
          </div>

          <ul className="hero-list">
            <li>Classes, assessments, and reports in one place</li>
            <li>Secure access for students, staff, and administrators</li>
            <li>Built for daily school activities and communication</li>
          </ul>
        </section>

        <section className="login-card">
          <div className="login-brand">
            <div className="login-mark">
              <img
                src={cardLogoUrl}
                alt={`${tenantSchool?.name || "Lytebridge"} logo`}
                onError={() => {
                  if (tenantLogoUrl) setLogoLoadError(true);
                }}
              />
            </div>
            <div>
              <h2>Sign In to Continue</h2>
              <p className="login-school-name">
                {tenantSchool?.name || "School Portal"}
              </p>
            </div>
          </div>

          <p className="login-subtitle">
            {tenantSchool?.name
              ? `Use your ${tenantSchool.name} account email and password.`
              : "Use your official school email and password."}
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

          <p className="login-help">
            Protected access. Contact school admin if you cannot sign in.
          </p>
        </section>
      </div>
    </div>
  );
}

export default Login;
