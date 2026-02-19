import { useEffect, useRef, useState } from "react";
import api from "../../services/api";

const toAbsoluteUrl = (u) => {
  if (!u) return "";
  if (/^(https?:\/\/|blob:|data:)/i.test(u)) return u;
  const base = (api.defaults.baseURL || "").replace(/\/$/, "");
  return `${base}${u.startsWith("/") ? "" : "/"}${u}`;
};

function SchoolDashboard() {
  const [stats, setStats] = useState({
    school_name: "",
    school_logo_url: null,
    head_of_school_name: "",
    head_signature_url: null,
    students: 0,
    staff: 0,
    enabled_modules: 0,
  });
  const [loading, setLoading] = useState(true);
  const [logoFile, setLogoFile] = useState(null);
  const [logoPreview, setLogoPreview] = useState(null);
  const [headSignatureFile, setHeadSignatureFile] = useState(null);
  const [headSignaturePreview, setHeadSignaturePreview] = useState(null);
  const [headName, setHeadName] = useState("");
  const [savingBranding, setSavingBranding] = useState(false);
  const logoInputRef = useRef(null);
  const signatureInputRef = useRef(null);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get("/api/school-admin/stats");
        setStats({
          school_name: res.data?.school_name ?? "",
          school_logo_url: res.data?.school_logo_url ?? null,
          head_of_school_name: res.data?.head_of_school_name ?? "",
          head_signature_url: res.data?.head_signature_url ?? null,
          students: res.data?.students ?? 0,
          staff: res.data?.staff ?? 0,
          enabled_modules: res.data?.enabled_modules ?? 0,
        });
        setHeadName(res.data?.head_of_school_name ?? "");
      } catch {
        setStats((prev) => ({
          ...prev,
          school_name: "",
          school_logo_url: null,
          head_of_school_name: "",
          head_signature_url: null,
          students: 0,
          staff: 0,
          enabled_modules: 0,
        }));
        setHeadName("");
      } finally {
        setLoading(false);
      }
    };

    load();
  }, []);

  const saveBranding = async () => {
    const normalizedName = (headName || "").trim();
    const existingName = (stats.head_of_school_name || "").trim();
    const hasNameChange = normalizedName !== existingName;
    const hasLogo = !!logoFile;
    const hasSignature = !!headSignatureFile;

    if (!hasNameChange && !hasLogo && !hasSignature) {
      return alert("No branding changes to save.");
    }

    setSavingBranding(true);
    try {
      const fd = new FormData();
      fd.append("head_of_school_name", normalizedName);
      if (logoFile) fd.append("logo", logoFile);
      if (headSignatureFile) fd.append("head_signature", headSignatureFile);

      const res = await api.post("/api/school-admin/branding", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      const data = res.data?.data || {};
      setStats((prev) => ({
        ...prev,
        school_name: data.school_name ?? prev.school_name,
        school_logo_url: data.school_logo_url ?? prev.school_logo_url,
        head_of_school_name: data.head_of_school_name ?? prev.head_of_school_name,
        head_signature_url: data.head_signature_url ?? prev.head_signature_url,
      }));
      setHeadName(data.head_of_school_name ?? normalizedName);
      setLogoFile(null);
      setLogoPreview(null);
      setHeadSignatureFile(null);
      setHeadSignaturePreview(null);
      alert("Branding updated");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to update branding");
    } finally {
      setSavingBranding(false);
    }
  };

  const onPickLogo = (e) => {
    const file = e.target.files?.[0] || null;
    setLogoFile(file);
    setLogoPreview(file ? URL.createObjectURL(file) : null);
  };

  const onPickSignature = (e) => {
    const file = e.target.files?.[0] || null;
    setHeadSignatureFile(file);
    setHeadSignaturePreview(file ? URL.createObjectURL(file) : null);
  };

  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
        <h1 style={{ margin: 0 }}>{stats.school_name || "SCHOOL"}</h1>
        {(logoPreview || stats.school_logo_url) ? (
          <img
            src={toAbsoluteUrl(logoPreview || stats.school_logo_url)}
            alt="School Logo"
            style={{
              width: 54,
              height: 54,
              borderRadius: 8,
              objectFit: "cover",
              border: "1px solid #ddd",
            }}
          />
        ) : (
          <span style={{ opacity: 0.7 }}>No logo uploaded</span>
        )}
      </div>

      <div style={{ marginTop: 14, maxWidth: 760, border: "1px solid #ddd", borderRadius: 10, padding: 14 }}>
        <h3 style={{ marginTop: 0 }}>School Branding</h3>

        <div style={{ display: "grid", gap: 12 }}>
          <div>
            <label style={{ fontSize: 13, opacity: 0.8 }}>School Logo</label>
            <div style={uploadWidgetStyle}>
              {(logoPreview || stats.school_logo_url) ? (
                <img
                  src={toAbsoluteUrl(logoPreview || stats.school_logo_url)}
                  alt="School logo preview"
                  style={previewImageStyle}
                />
              ) : (
                <span style={{ opacity: 0.7 }}>No logo uploaded</span>
              )}
              <input
                ref={logoInputRef}
                type="file"
                accept="image/*"
                onChange={onPickLogo}
                style={{ display: "none" }}
              />
              <button type="button" onClick={() => logoInputRef.current?.click()}>
                Select Logo Image
              </button>
            </div>
          </div>

          <div>
            <label style={{ fontSize: 13, opacity: 0.8 }}>Head of School Name</label>
            <input
              type="text"
              value={headName}
              onChange={(e) => setHeadName(e.target.value)}
              placeholder="Enter head of school name"
              style={{ width: "100%", marginTop: 6, padding: 10 }}
            />
          </div>

          <div>
            <label style={{ fontSize: 13, opacity: 0.8 }}>Head of School Signature</label>
            <div style={uploadWidgetStyle}>
              {(headSignaturePreview || stats.head_signature_url) ? (
                <img
                  src={toAbsoluteUrl(headSignaturePreview || stats.head_signature_url)}
                  alt="Head signature preview"
                  style={previewImageStyle}
                />
              ) : (
                <span style={{ opacity: 0.7 }}>No signature uploaded</span>
              )}
              <input
                ref={signatureInputRef}
                type="file"
                accept="image/*"
                onChange={onPickSignature}
                style={{ display: "none" }}
              />
              <button type="button" onClick={() => signatureInputRef.current?.click()}>
                Select Signature Image
              </button>
            </div>
          </div>

          <div>
            <button onClick={saveBranding} disabled={savingBranding}>
              {savingBranding ? "Saving..." : "Save Branding"}
            </button>
          </div>
        </div>
      </div>

      <div style={{ display: "flex", gap: 20, marginTop: 30 }}>
        <div style={cardStyle}>
          <h3>Students</h3>
          <p style={numberStyle}>{loading ? "..." : String(stats.students)}</p>
        </div>

        <div style={cardStyle}>
          <h3>Staff</h3>
          <p style={numberStyle}>{loading ? "..." : String(stats.staff)}</p>
        </div>

        <div style={cardStyle}>
          <h3>Enabled Modules</h3>
          <p style={numberStyle}>{loading ? "..." : String(stats.enabled_modules)}</p>
        </div>
      </div>

      <p style={{ marginTop: 30, color: "#6b7280" }}>
        Use the menu on the left to manage school modules.
      </p>
    </div>
  );
}

const cardStyle = {
  background: "#fff",
  padding: 20,
  borderRadius: 8,
  width: 200,
  boxShadow: "0 1px 4px rgba(0,0,0,0.1)",
};

const numberStyle = {
  fontSize: 28,
  fontWeight: "bold",
};

const uploadWidgetStyle = {
  marginTop: 6,
  border: "1px dashed #cbd5e1",
  borderRadius: 10,
  padding: 12,
  display: "flex",
  alignItems: "center",
  justifyContent: "space-between",
  gap: 12,
  background: "#f8fafc",
};

const previewImageStyle = {
  width: 90,
  height: 55,
  objectFit: "contain",
  border: "1px solid #ddd",
  borderRadius: 6,
  background: "#fff",
};

export default SchoolDashboard;
