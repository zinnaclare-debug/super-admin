import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";

const parseFileName = (headers, fallback = "user_login_details.csv") => {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback;
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
};

const prettyLevel = (value) =>
  String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

export default function LoginDetails() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [downloading, setDownloading] = useState(false);
  const [downloadingPdf, setDownloadingPdf] = useState(false);
  const [role, setRole] = useState("");
  const [level, setLevel] = useState("");
  const [classId, setClassId] = useState("");
  const [department, setDepartment] = useState("");
  const [levels, setLevels] = useState([]);
  const [classes, setClasses] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [q, setQ] = useState("");

  const load = async () => {
    setLoading(true);
    try {
      const params = {};
      if (role) params.role = role;
      if (level) params.level = level;
      if (classId) params.class_id = classId;
      if (department) params.department = department;

      const res = await api.get("/api/school-admin/users/login-details", {
        params,
      });
      setRows(Array.isArray(res.data?.data) ? res.data.data : []);
      setLevels(Array.isArray(res.data?.meta?.levels) ? res.data.meta.levels : []);
      setClasses(Array.isArray(res.data?.meta?.classes) ? res.data.meta.classes : []);
      setDepartments(Array.isArray(res.data?.meta?.departments) ? res.data.meta.departments : []);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load login details.");
      setRows([]);
      setLevels([]);
      setClasses([]);
      setDepartments([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [role, level, classId, department]);

  useEffect(() => {
    if (role === "staff") {
      if (classId) setClassId("");
      if (department) setDepartment("");
    }
  }, [classId, department, role]);

  useEffect(() => {
    if (!classId) return;
    const exists = classes.some((item) => String(item?.id) === String(classId));
    if (!exists) {
      setClassId("");
    }
  }, [classId, classes]);

  const filtered = useMemo(() => {
    const needle = q.trim().toLowerCase();
    if (!needle) return rows;
    return rows.filter((row) => {
      const name = String(row.name || "").toLowerCase();
      const email = String(row.email || "").toLowerCase();
      const username = String(row.username || "").toLowerCase();
      return name.includes(needle) || email.includes(needle) || username.includes(needle);
    });
  }, [rows, q]);

  const download = async () => {
    setDownloading(true);
    try {
      const params = {};
      if (role) params.role = role;
      if (level) params.level = level;
      if (classId) params.class_id = classId;
      if (department) params.department = department;

      const res = await api.get("/api/school-admin/users/login-details/download", {
        params,
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

  const downloadPdf = async () => {
    setDownloadingPdf(true);
    try {
      const params = {};
      if (role) params.role = role;
      if (level) params.level = level;
      if (classId) params.class_id = classId;
      if (department) params.department = department;

      const res = await api.get("/api/school-admin/users/login-details/download/pdf", {
        params,
        responseType: "blob",
      });

      const blob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = parseFileName(res.headers, "user_login_details.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to download login details PDF.");
    } finally {
      setDownloadingPdf(false);
    }
  };

  return (
    <div style={{ marginTop: 12 }}>
      <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
        <h4 style={{ margin: 0, marginRight: 8 }}>Staff & Student Login Details</h4>
        <select value={role} onChange={(e) => setRole(e.target.value)} style={{ padding: 8 }}>
          <option value="">All</option>
          <option value="staff">Staff only</option>
          <option value="student">Students only</option>
        </select>
        <select value={level} onChange={(e) => setLevel(e.target.value)} style={{ padding: 8 }}>
          <option value="">All levels</option>
          {levels.map((value) => (
            <option key={value} value={value}>
              {prettyLevel(value)}
            </option>
          ))}
        </select>
        <select value={classId} onChange={(e) => setClassId(e.target.value)} style={{ padding: 8 }} disabled={role === "staff"}>
          <option value="">All classes</option>
          {classes.map((item) => (
            <option key={item.id} value={item.id}>
              {item.name}
            </option>
          ))}
        </select>
        <select value={department} onChange={(e) => setDepartment(e.target.value)} style={{ padding: 8 }} disabled={role === "staff"}>
          <option value="">All departments</option>
          {departments.map((value) => (
            <option key={value} value={value}>
              {value}
            </option>
          ))}
        </select>
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search name, email, username..."
          style={{ padding: 8, width: 320 }}
        />
        <button onClick={download} disabled={downloading}>
          {downloading ? "Downloading..." : "Download Excel (CSV)"}
        </button>
        <button onClick={downloadPdf} disabled={downloadingPdf}>
          {downloadingPdf ? "Downloading..." : "Download PDF"}
        </button>
      </div>

      <p style={{ marginTop: 10, opacity: 0.8 }}>
        Password is only available after create/reset/update done by school admin.
      </p>

      {loading ? (
        <p>Loading login details...</p>
      ) : (
        <table border="1" cellPadding="10" cellSpacing="0" width="100%">
          <thead>
            <tr>
              <th>S/N</th>
              <th>Name</th>
              <th>Role</th>
              <th>Education Level</th>
              <th>{role === "staff" ? "Teaching Classes" : "Class"}</th>
              <th>{role === "staff" ? "Assigned Departments" : "Department"}</th>
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
                <td>{row.name || "-"}</td>
                <td>{row.role || "-"}</td>
                <td>{prettyLevel(row.level || "") || "-"}</td>
                <td>{row.class_name || "-"}</td>
                <td>{row.department || "-"}</td>
                <td>{row.username || "-"}</td>
                <td>{row.email || "-"}</td>
                <td>{row.password || "-"}</td>
                <td>{row.last_password_set_at || "-"}</td>
              </tr>
            ))}
            {filtered.length === 0 && (
              <tr>
                <td colSpan="10" style={{ textAlign: "center", opacity: 0.7 }}>
                  No login details found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      )}
    </div>
  );
}
