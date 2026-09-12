import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import api, { clearMobileSchool, isMobileBuild, selectMobileSchool } from "../services/api";
import SuspendedSchoolNotice from "../components/SuspendedSchoolNotice";
import heroArt from "../assets/dashboard/hero.svg";
import graduationArt from "../assets/login/Graduation-cuate.svg";
import brandBanner from "../assets/home/lytebridge-brand.jpg";
import brandLogo from "../assets/home/lytebridge-logo.png";
import { setAuthState, setStoredFeatures } from "../utils/authStorage";
import { getMobileSchool } from "../utils/mobileSchool";
import "./Login.css";
import PasswordVisibilityToggle from "../components/PasswordVisibilityToggle";

function Login() {
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const isMobileApp = isMobileBuild;
  const [mobileSchool, setMobileSchool] = useState(() => (isMobileBuild ? getMobileSchool() : null));
  const [schoolCode, setSchoolCode] = useState("");
  const [schoolCodeMessage, setSchoolCodeMessage] = useState("");
  const [pendingMobileSchool, setPendingMobileSchool] = useState(null);
  const [tenantSchool, setTenantSchool] = useState(() => (isMobileBuild ? getMobileSchool() : null));
  const [logoLoadError, setLogoLoadError] = useState(false);
  const [graduationMessage, setGraduationMessage] = useState("");
  const [suspendedMessage, setSuspendedMessage] = useState("");

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

  const cardLogoUrl = tenantLogoUrl && !logoLoadError ? tenantLogoUrl : brandLogo;
  const tenantAddress = (tenantSchool?.school_location || tenantSchool?.location || "").trim();
  const tenantContactEmail = (tenantSchool?.contact_email || "").trim();
  const tenantContactPhone = (tenantSchool?.contact_phone || "").trim();
  const tenantDialPhone = tenantContactPhone.replace(/[^\d+]/g, "");
const loginThemeStyle = useMemo(
  () => ({
    '--login-primary':
      tenantSchool?.website_content?.primary_color ||
      tenantSchool?.primary_color ||
      '#0f172a',
    '--login-accent':
      tenantSchool?.website_content?.accent_color ||
      tenantSchool?.accent_color ||
      '#f97316',
  }),
  [tenantSchool]
);



  const isLytCentralDomain = useMemo(() => {
    const host = window.location.hostname.toLowerCase();
    return host === "lyt.com.ng" || host === "www.lyt.com.ng";
  }, []);

  useEffect(() => {
    if (isMobileApp && !mobileSchool) {
      return undefined;
    }

    let active = true;

    api
      .get("/api/tenant/context")
      .then((res) => {
        if (!active) return;
        if (res?.data?.is_tenant && res?.data?.school) {
          setTenantSchool(res.data.school);
        }
      })
      .catch((err) => {
        if (!active) return;
        if (err?.response?.status === 403) {
          setSuspendedMessage(err?.response?.data?.message || "This school account is suspended.");
        }
      });

    return () => {
      active = false;
    };
  }, [isMobileApp, mobileSchool]);

  useEffect(() => {
    setLogoLoadError(false);
  }, [tenantLogoUrl]);

  const goBackToSchoolWebsite = () => {
    navigate("/");
  };
  const handleSchoolCodeSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setSchoolCodeMessage("");

    try {
      const school = await api.post("/api/mobile/schools/resolve", {
        school_code: schoolCode.trim().toUpperCase(),
      });
      setPendingMobileSchool(school.data.data);
      setTenantSchool(school.data.data);
    } catch (err) {
      setSchoolCodeMessage(
        err.response?.data?.errors?.school_code?.[0] ||
          err.response?.data?.message ||
          "We could not find an active school with that code."
      );
    } finally {
      setLoading(false);
    }
  };

  const confirmMobileSchool = () => {
    const selectedSchool = selectMobileSchool(pendingMobileSchool);
    setMobileSchool(selectedSchool);
    setTenantSchool(selectedSchool);
    setPendingMobileSchool(null);
  };

  const useAnotherSchoolCode = () => {
    setPendingMobileSchool(null);
    setTenantSchool(null);
    setSchoolCode("");
    setSchoolCodeMessage("");
  };

  const changeMobileSchool = () => {
    clearMobileSchool();
    setMobileSchool(null);
    setPendingMobileSchool(null);
    setTenantSchool(null);
    setSchoolCode("");
    setEmail("");
    setPassword("");
    setSchoolCodeMessage("");
  };
  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setGraduationMessage("");

    try {
      // 1️⃣ LOGIN
      const res = await api.post("/api/login", {
        email,
        password,
        school_code: isMobileApp ? mobileSchool?.school_code : undefined,
      });

      const { token, user } = res.data;

      // 2️⃣ STORE AUTH
      setAuthState({ token, user });
      setStoredFeatures([]);

      // 3️⃣ ROUTE BASED ON ROLE
      if (user.role === "super_admin") {
        navigate("/super-admin", { replace: true });
      } else if (user.role === "school_admin") {
        const featuresRes = await api.get("/api/schools/features");
        setStoredFeatures(featuresRes.data.data || featuresRes.data);
        navigate("/school/dashboard", { replace: true });
      } else if (user.role === "staff") {
        navigate("/staff/dashboard", { replace: true });
      } else if (user.role === "student") {
        navigate("/student/dashboard", { replace: true });
      } else {
        navigate("/login", { replace: true });
      }

    } catch (err) {
      if (err.response?.data?.code === "graduated") {
        setGraduationMessage(err.response?.data?.message || "Congratulations, you have graduated.");
      } else {
        alert(err.response?.data?.message || "Login failed");
      }
    } finally {
      setLoading(false);
    }
  };

  if (suspendedMessage) {
    return <SuspendedSchoolNotice message={suspendedMessage} />;
  }

  return (
   <div
  className={`login-page${isLytCentralDomain ? " login-page--compact" : ""}`}
  style={loginThemeStyle}
>

      <div className="login-ambient login-ambient--one" />
      <div className="login-ambient login-ambient--two" />
      <div className="login-shell">
        <section className="login-hero">
          <div className="hero-meta">
            <span className="hero-pill">Smart School Portal</span>
            {isMobileApp && mobileSchool ? (
              <button
                type="button"
                className="login-mobile-school-back"
                onClick={changeMobileSchool}
                aria-label="Change school"
                title="Change school"
              >
                &larr;
              </button>
            ) : !isMobileApp ? (
              <span className="hero-domain">{window.location.hostname}</span>
            ) : null}
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
          </div>

          {tenantSchool && !isLytCentralDomain && !isMobileApp ? (
           <button
  type="button"
  className="login-back-link"
  onClick={goBackToSchoolWebsite}
  style={{
    background: "linear-gradient(135deg, var(--login-primary), var(--login-accent))",
    borderColor: "var(--login-primary)",
    color: "#fff",
  }}
>
  Home
</button>

          ) : null}

          {isMobileApp && !mobileSchool ? (
            pendingMobileSchool ? (
              <div className="login-school-match">
                <span>School found</span>
                <strong>{pendingMobileSchool.name}</strong>
                <small>School code: {pendingMobileSchool.school_code}</small>
                <p>Confirm this is your school before entering your email and password.</p>
                <button type="button" className="login-btn" onClick={confirmMobileSchool}>
                  Continue to login
                </button>
                <button type="button" className="login-school-change-button" onClick={useAnotherSchoolCode}>
                  Use another school code
                </button>
              </div>
            ) : (
              <form onSubmit={handleSchoolCodeSubmit} className="login-form">
                <label htmlFor="school-code">ENTER SCHOOL CODE</label>
                <input
                  id="school-code"
                  type="text"
                  placeholder="School code"
                  value={schoolCode}
                  onChange={(e) => {
                    setSchoolCode(e.target.value.toUpperCase());
                    setSchoolCodeMessage("");
                  }}
                  autoComplete="off"
                  autoCapitalize="characters"
                  maxLength={8}
                  required
                />
                {schoolCodeMessage ? <p className="login-school-code-error">{schoolCodeMessage}</p> : null}
                <button className="login-btn" disabled={loading}>
                  {loading ? "Finding school..." : "Find my school"}
                </button>
              </form>
            )
          ) : (
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
              <div className="password-visibility-field">
                <input
                  id="login-password"
                  placeholder="Enter password"
                  type={showPassword ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  autoComplete="current-password"
                  required
                />
                <PasswordVisibilityToggle
                  visible={showPassword}
                  onToggle={() => setShowPassword((value) => !value)}
                />
              </div>

              <button className="login-btn" disabled={loading}>
                {loading ? "Logging in..." : "Login"}
              </button>
            </form>
          )}

          {graduationMessage ? (
            <div className="login-graduation-card" role="status">
              <span className="login-graduation-burst">Congratulations</span>
              <strong>{graduationMessage}</strong>
              <p>Your student portal access has been closed because your academic level is complete. Please contact your school admin for transcript or printed result requests.</p>
            </div>
          ) : null}

          <div className="login-contact-block">
            <p className="login-contact-school-name">
              {tenantSchool?.name || "School Portal"}
            </p>
            {tenantAddress ? (
              <p className="login-contact-address">{tenantAddress}</p>
            ) : null}
            <p className="login-help">
              {tenantContactEmail || tenantContactPhone ? (
                <>
                  Protected access. Contact school admin:
                  {tenantContactEmail ? (
                    <>
                      {" "}
                      <a href={`mailto:${tenantContactEmail}`}>{tenantContactEmail}</a>
                    </>
                  ) : null}
                  {tenantContactEmail && tenantContactPhone ? " | " : " "}
                  {tenantContactPhone ? (
                    <a href={`tel:${tenantDialPhone || tenantContactPhone}`}>{tenantContactPhone}</a>
                  ) : null}
                  .
                </>
              ) : (
                "Protected access. Contact school admin if you cannot sign in."
              )}
            </p>
          </div>
        </section>
      </div>
    </div>
  );
}

export default Login;




