import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../services/api";

const parseFileName = (headers, fallback = "school_admin_login_details.csv") => {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback;
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
};

export default function SuperAdminLoginDetails() {
  const navigate = useNavigate();
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [downloading, setDownloading] = useState(false);
  const [q, setQ] = useState("");

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/super-admin/users/login-details");
      setRows(Array.isArray(res.data?.data) ? res.data.data : []);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load school admin login details.");
      setRows([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const filtered = useMemo(() => {
    const needle = q.trim().toLowerCase();
    if (!needle) return rows;
    return rows.filter((row) => {
      const school = String(row.school_name || "").toLowerCase();
      const name = String(row.name || "").toLowerCase();
      const email = String(row.email || "").toLowerCase();
      return school.includes(needle) || name.includes(needle) || email.includes(needle);
    });
  }, [q, rows]);

  const download = async () => {
    setDownloading(true);
    try {
      const res = await api.get("/api/super-admin/users/login-details/download", {
        responseType: "blob",
      });
      const blob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "text/csv" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = parseFileName(res.headers);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to download login details.");
    } finally {
      setDownloading(false);
    }
  };

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
        <h1 style={{ marginBottom: 6 }}>School Admin Login Details</h1>
        <button onClick={() => navigate("/super-admin/users")}>Back</button>
      </div>

      <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginTop: 10 }}>
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search school, admin name, email..."
          style={{ padding: 8, width: 320 }}
        />
        <button onClick={download} disabled={downloading}>
          {downloading ? "Downloading..." : "Download CSV"}
        </button>
      </div>

      <p style={{ marginTop: 10, opacity: 0.8 }}>
        Password is available only after school admin creation/reset through super admin actions.
      </p>

      <div style={{ marginTop: 12 }}>
        {loading ? (
          <p>Loading login details...</p>
        ) : (
          <table border="1" cellPadding="10" cellSpacing="0" width="100%">
            <thead>
              <tr>
                <th>S/N</th>
                <th>School</th>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Password</th>
                <th>Last Password Set</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((row, idx) => (
                <tr key={row.user_id}>
                  <td>{idx + 1}</td>
                  <td>{row.school_name || "-"}</td>
                  <td>{row.name || "-"}</td>
                  <td>{row.username || "-"}</td>
                  <td>{row.email || "-"}</td>
                  <td>{row.password || "-"}</td>
                  <td>{row.last_password_set_at || "-"}</td>
                </tr>
              ))}
              {filtered.length === 0 && (
                <tr>
                  <td colSpan="7" style={{ textAlign: "center", opacity: 0.7 }}>
                    No login details found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

