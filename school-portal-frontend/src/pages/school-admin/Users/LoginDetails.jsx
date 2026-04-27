import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";

const PAGE_SIZE = 100;

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
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, per_page: PAGE_SIZE, total: 0 });

  const load = async () => {
    setLoading(true);
    try {
      const params = { page, per_page: PAGE_SIZE };
      if (role) params.role = role;
      if (level) params.level = level;
      if (classId) params.class_id = classId;
      if (department) params.department = department;
      if (q) params.q = q;

      const res = await api.get("/api/school-admin/users/login-details", { params });
      const nextMeta = res.data?.meta || {};

      setRows(Array.isArray(res.data?.data) ? res.data.data : []);
      setLevels(Array.isArray(nextMeta.levels) ? nextMeta.levels : []);
      setClasses(Array.isArray(nextMeta.classes) ? nextMeta.classes : []);
      setDepartments(Array.isArray(nextMeta.departments) ? nextMeta.departments : []);
      setMeta({
        current_page: Number(nextMeta.current_page) || 1,
        last_page: Number(nextMeta.last_page) || 1,
        per_page: Number(nextMeta.per_page) || PAGE_SIZE,
        total: Number(nextMeta.total) || 0,
      });

      const currentPage = Number(nextMeta.current_page) || 1;
      if (currentPage !== page) setPage(currentPage);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load login details.");
      setRows([]);
      setLevels([]);
      setClasses([]);
      setDepartments([]);
      setMeta({ current_page: 1, last_page: 1, per_page: PAGE_SIZE, total: 0 });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [role, level, classId, department, q, page]);

  useEffect(() => {
    if (role === "staff") {
      if (classId) setClassId("");
      if (department) setDepartment("");
    }
  }, [classId, department, role]);

  useEffect(() => {
    if (!classId) return;
    const exists = classes.some((item) => String(item?.id) === String(classId));
    if (!exists) setClassId("");
  }, [classId, classes]);

  const download = async () => {
    setDownloading(true);
    try {
      const params = {};
      if (role) params.role = role;
      if (level) params.level = level;
      if (classId) params.class_id = classId;
      if (department) params.department = department;
      if (q) params.q = q;

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
      if (q) params.q = q;

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

  const pageStart = meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1;
  const pageEnd = meta.total === 0 ? 0 : pageStart + rows.length - 1;
  const classOptions = useMemo(() => classes || [], [classes]);

  return (
    <div style={{ marginTop: 12 }}>
      <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
        <h4 style={{ margin: 0, marginRight: 8 }}>Staff & Student Login Details</h4>
        <select value={role} onChange={(e) => { setRole(e.target.value); setPage(1); }} style={{ padding: 8 }}>
          <option value="">All</option>
          <option value="staff">Staff only</option>
          <option value="student">Students only</option>
        </select>
        <select value={level} onChange={(e) => { setLevel(e.target.value); setPage(1); }} style={{ padding: 8 }}>
          <option value="">All levels</option>
          {levels.map((value) => (
            <option key={value} value={value}>
              {prettyLevel(value)}
            </option>
          ))}
        </select>
        <select value={classId} onChange={(e) => { setClassId(e.target.value); setPage(1); }} style={{ padding: 8 }} disabled={role === "staff"}>
          <option value="">All classes</option>
          {classOptions.map((item) => (
            <option key={item.id} value={item.id}>
              {item.name}
            </option>
          ))}
        </select>
        <select value={department} onChange={(e) => { setDepartment(e.target.value); setPage(1); }} style={{ padding: 8 }} disabled={role === "staff"}>
          <option value="">All departments</option>
          {departments.map((value) => (
            <option key={value} value={value}>
              {value}
            </option>
          ))}
        </select>
        <input value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Search name, email, username..." style={{ padding: 8, width: 320 }} />
        <button onClick={download} disabled={downloading}>{downloading ? "Downloading..." : "Download Excel (CSV)"}</button>
        <button onClick={downloadPdf} disabled={downloadingPdf}>{downloadingPdf ? "Downloading..." : "Download PDF"}</button>
      </div>

      <p style={{ marginTop: 10, opacity: 0.8 }}>
        Password is only available after create/reset/update done by school admin.
      </p>

      <div style={{ marginBottom: 12, opacity: 0.75 }}>Showing {pageStart}-{pageEnd} of {meta.total}</div>

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
            {rows.map((row) => (
              <tr key={row.user_id}>
                <td>{row.sn ?? "-"}</td>
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
            {rows.length === 0 && (
              <tr>
                <td colSpan="10" style={{ textAlign: "center", opacity: 0.7 }}>
                  No login details found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      )}

      <div style={{ marginTop: 12, display: "flex", justifyContent: "flex-end", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
        <button onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={loading || meta.current_page <= 1}>Previous</button>
        <span>Page {meta.current_page} of {meta.last_page}</span>
        <button onClick={() => setPage((current) => Math.min(meta.last_page, current + 1))} disabled={loading || meta.current_page >= meta.last_page}>Next</button>
      </div>
    </div>
  );
}
