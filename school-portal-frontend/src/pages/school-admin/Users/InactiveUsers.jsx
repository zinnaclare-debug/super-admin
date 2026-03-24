import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import UserProfilePanel from "./UserProfilePanel";

const PAGE_SIZE = 100;

const parseFileName = (headers, fallback = "users_inactive.pdf") => {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback;
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
};

const filterStorageKey = (role, status) => `school-admin-users-filters:${status}:${role || "all"}`;

const emptyFilters = { q: "", levelFilter: "", classFilter: "", departmentFilter: "", page: 1 };

const readStoredFilters = (role, status) => {
  if (typeof window === "undefined") return emptyFilters;

  try {
    const raw = window.localStorage.getItem(filterStorageKey(role, status));
    if (!raw) return emptyFilters;

    const parsed = JSON.parse(raw);
    return {
      q: String(parsed?.q || ""),
      levelFilter: String(parsed?.levelFilter || ""),
      classFilter: String(parsed?.classFilter || ""),
      departmentFilter: String(parsed?.departmentFilter || ""),
      page: Math.max(1, Number(parsed?.page) || 1),
    };
  } catch {
    return emptyFilters;
  }
};

export default function InactiveUsers() {
  const { role } = useParams();
  const navigate = useNavigate();
  const storageKey = filterStorageKey(role, "inactive");
  const storedFilters = readStoredFilters(role, "inactive");
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState({
    levels: [],
    classes: [],
    departments: [],
    current_page: 1,
    last_page: 1,
    per_page: PAGE_SIZE,
    total: 0,
  });
  const [q, setQ] = useState(storedFilters.q);
  const [levelFilter, setLevelFilter] = useState(storedFilters.levelFilter);
  const [classFilter, setClassFilter] = useState(storedFilters.classFilter);
  const [departmentFilter, setDepartmentFilter] = useState(storedFilters.departmentFilter);
  const [page, setPage] = useState(storedFilters.page);
  const [loading, setLoading] = useState(true);
  const [selectedUserId, setSelectedUserId] = useState(null);
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [deletingId, setDeletingId] = useState(null);
  const [bulkDeleting, setBulkDeleting] = useState(false);
  const [downloadingPdf, setDownloadingPdf] = useState(false);

  useEffect(() => {
    const nextFilters = readStoredFilters(role, "inactive");
    setQ(nextFilters.q);
    setLevelFilter(nextFilters.levelFilter);
    setClassFilter(nextFilters.classFilter);
    setDepartmentFilter(nextFilters.departmentFilter);
    setPage(nextFilters.page);
    setSelectedIds(new Set());
    setSelectedUserId(null);
  }, [role]);

  useEffect(() => {
    if (typeof window === "undefined") return;
    window.localStorage.setItem(
      storageKey,
      JSON.stringify({ q, levelFilter, classFilter, departmentFilter, page })
    );
  }, [storageKey, q, levelFilter, classFilter, departmentFilter, page]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const params = { role, status: "inactive", page, per_page: PAGE_SIZE };
      if (q) params.q = q;
      if (levelFilter) params.level = levelFilter;
      if (classFilter) params.class = classFilter;
      if (departmentFilter) params.department = departmentFilter;

      const res = await api.get("/api/school-admin/users", { params });
      const nextRows = Array.isArray(res.data?.data) ? res.data.data : [];
      const nextMeta = res.data?.meta || {};

      setRows(nextRows);
      setMeta({
        levels: Array.isArray(nextMeta.levels) ? nextMeta.levels : [],
        classes: Array.isArray(nextMeta.classes) ? nextMeta.classes : [],
        departments: Array.isArray(nextMeta.departments) ? nextMeta.departments : [],
        current_page: Number(nextMeta.current_page) || 1,
        last_page: Number(nextMeta.last_page) || 1,
        per_page: Number(nextMeta.per_page) || PAGE_SIZE,
        total: Number(nextMeta.total) || 0,
      });

      const currentPage = Number(nextMeta.current_page) || 1;
      if (currentPage !== page) setPage(currentPage);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load users");
      setRows([]);
      setMeta({ levels: [], classes: [], departments: [], current_page: 1, last_page: 1, per_page: PAGE_SIZE, total: 0 });
    } finally {
      setLoading(false);
    }
  }, [classFilter, departmentFilter, levelFilter, page, q, role]);

  useEffect(() => {
    load();
  }, [load]);

  const levelOptions = useMemo(() => meta.levels || [], [meta.levels]);
  const classOptions = useMemo(() => meta.classes || [], [meta.classes]);
  const departmentOptions = useMemo(() => meta.departments || [], [meta.departments]);

  const removeUser = async (u) => {
    if (!u?.id) return;
    if (!window.confirm(`Delete ${u.name || "this user"} permanently? This cannot be undone.`)) return;

    setDeletingId(u.id);
    try {
      await api.delete(`/api/school-admin/users/${u.id}`);
      if (selectedUserId === u.id) setSelectedUserId(null);
      setSelectedIds((prev) => {
        const next = new Set(prev);
        next.delete(u.id);
        return next;
      });
      await load();
      alert("User deleted successfully");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to delete user");
    } finally {
      setDeletingId(null);
    }
  };

  const bulkDeleteUsers = async () => {
    if (selectedIds.size === 0) return;
    if (!window.confirm(`Delete ${selectedIds.size} selected user(s) permanently? This cannot be undone.`)) return;

    setBulkDeleting(true);
    try {
      const res = await api.delete("/api/school-admin/users/bulk-delete", {
        data: { ids: Array.from(selectedIds) },
      });
      const deletedIds = Array.isArray(res?.data?.data?.deleted_ids) ? res.data.data.deleted_ids : [];

      if (selectedUserId && deletedIds.includes(selectedUserId)) setSelectedUserId(null);

      await load();
      setSelectedIds(new Set());
      alert(res?.data?.message || "Bulk delete completed.");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to bulk delete users");
    } finally {
      setBulkDeleting(false);
    }
  };

  const downloadUsersPdf = async () => {
    setDownloadingPdf(true);
    try {
      const params = { status: "inactive", role };
      if (levelFilter) params.level = levelFilter;
      if (classFilter) params.class = classFilter;
      if (departmentFilter) params.department = departmentFilter;
      if (q) params.q = q;

      const res = await api.get("/api/school-admin/users/download/pdf", {
        params,
        responseType: "blob",
      });

      const blob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = parseFileName(res.headers, `users_${role || "all"}_inactive.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to download users PDF.");
    } finally {
      setDownloadingPdf(false);
    }
  };

  const visibleIds = useMemo(() => rows.map((u) => u.id), [rows]);
  const allVisibleSelected = visibleIds.length > 0 && visibleIds.every((id) => selectedIds.has(id));

  const toggleRow = (id) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const toggleAllVisible = () => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (allVisibleSelected) {
        visibleIds.forEach((id) => next.delete(id));
      } else {
        visibleIds.forEach((id) => next.add(id));
      }
      return next;
    });
  };

  const pageStart = meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1;
  const pageEnd = meta.total === 0 ? 0 : pageStart + rows.length - 1;

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", gap: 12 }}>
        <h4 style={{ margin: 0 }}>Inactive {role === "staff" ? "Staff" : "Students"}</h4>

        <div style={{ display: "flex", gap: 8, flexWrap: "wrap", justifyContent: "flex-end" }}>
          <select value={levelFilter} onChange={(e) => { setLevelFilter(e.target.value); setPage(1); }} style={{ padding: 8, minWidth: 170 }}>
            <option value="">All Levels</option>
            {levelOptions.map((v) => <option key={v} value={v}>{v}</option>)}
          </select>
          <select value={classFilter} onChange={(e) => { setClassFilter(e.target.value); setPage(1); }} style={{ padding: 8, minWidth: 170 }}>
            <option value="">All Classes</option>
            {classOptions.map((v) => <option key={v} value={v}>{v}</option>)}
          </select>
          <select value={departmentFilter} onChange={(e) => { setDepartmentFilter(e.target.value); setPage(1); }} style={{ padding: 8, minWidth: 170 }}>
            <option value="">All Departments</option>
            {departmentOptions.map((v) => <option key={v} value={v}>{v}</option>)}
          </select>
          <input value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Search name, email, username..." style={{ padding: 8, width: 320 }} />
        </div>
      </div>

      <div style={{ marginTop: 10, display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
        <button
          onClick={bulkDeleteUsers}
          disabled={selectedIds.size === 0 || bulkDeleting}
          style={{
            background: "#dc2626",
            border: "1px solid #b91c1c",
            color: "#fff",
            opacity: selectedIds.size === 0 || bulkDeleting ? 0.6 : 1,
          }}
        >
          {bulkDeleting ? "Deleting..." : `Bulk Delete (${selectedIds.size})`}
        </button>
        <button onClick={downloadUsersPdf} disabled={downloadingPdf}>
          {downloadingPdf ? "Downloading..." : "Download PDF"}
        </button>
        <span style={{ opacity: 0.75 }}>Showing {pageStart}-{pageEnd} of {meta.total}</span>
      </div>

      <div style={{ marginTop: 14 }}>
        {loading ? (
          <p>Loading...</p>
        ) : (
          <table border="1" cellPadding="10" cellSpacing="0" width="100%">
            <thead>
              <tr>
                <th>
                  <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                    <input type="checkbox" checked={allVisibleSelected} onChange={toggleAllVisible} />
                    <span>S/N</span>
                  </div>
                </th>
                <th>Name</th>
                <th>Level</th>
                <th>Class</th>
                <th>Department</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>

            <tbody>
              {rows.map((u) => {
                const levels = Array.isArray(u.levels) && u.levels.length > 0 ? u.levels : (u.education_level ? [u.education_level] : []);
                const classes = Array.isArray(u.classes) && u.classes.length > 0 ? u.classes : (u.class_name ? [u.class_name] : []);
                const departments = Array.isArray(u.departments) && u.departments.length > 0 ? u.departments : (u.department_name ? [u.department_name] : []);

                return (
                  <tr key={u.id}>
                    <td>
                      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        <input type="checkbox" checked={selectedIds.has(u.id)} onChange={() => toggleRow(u.id)} />
                        <span>{u.sn ?? "-"}</span>
                      </div>
                    </td>
                    <td>{u.name}</td>
                    <td>{levels.length ? levels.join(", ") : "-"}</td>
                    <td>{classes.length ? classes.join(", ") : "-"}</td>
                    <td>{departments.length ? departments.join(", ") : "-"}</td>
                    <td><strong>{u.status || "inactive"}</strong></td>
                    <td>
                      <button onClick={() => setSelectedUserId(u.id)}>More</button>
                      <button
                        style={{ marginLeft: 8 }}
                        onClick={() => navigate(`/school/admin/register?editUserId=${u.id}&role=${u.role}&returnTo=${encodeURIComponent(`/school/admin/users/${role}/inactive`)}`)}
                      >
                        Edit
                      </button>
                      <button
                        style={{ marginLeft: 8, background: "#dc2626", border: "1px solid #b91c1c", color: "#fff" }}
                        onClick={() => removeUser(u)}
                        disabled={deletingId === u.id}
                      >
                        {deletingId === u.id ? "Deleting..." : "Delete"}
                      </button>
                      {role === "student" ? (
                        <button style={{ marginLeft: 8 }} onClick={() => navigate(`/school/admin/students/${u.id}/set-payment`)}>
                          Set Payment
                        </button>
                      ) : null}
                    </td>
                  </tr>
                );
              })}

              {rows.length === 0 && (
                <tr>
                  <td colSpan="7" style={{ textAlign: "center", opacity: 0.7 }}>
                    No users found
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        )}

        <div style={{ marginTop: 12, display: "flex", justifyContent: "flex-end", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
          <button onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={loading || meta.current_page <= 1}>
            Previous
          </button>
          <span>Page {meta.current_page} of {meta.last_page}</span>
          <button onClick={() => setPage((current) => Math.min(meta.last_page, current + 1))} disabled={loading || meta.current_page >= meta.last_page}>
            Next
          </button>
        </div>

        {selectedUserId && <UserProfilePanel userId={selectedUserId} onClose={() => setSelectedUserId(null)} onChanged={load} />}
      </div>
    </div>
  );
}
