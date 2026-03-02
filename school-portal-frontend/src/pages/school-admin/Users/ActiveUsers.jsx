import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import UserProfilePanel from "./UserProfilePanel";

const parseFileName = (headers, fallback = "users_active.pdf") => {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback;
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
};

export default function ActiveUsers() {
  const { role } = useParams(); // staff | student
  const navigate = useNavigate();
  const [rows, setRows] = useState([]);
  const [q, setQ] = useState("");
  const [levelFilter, setLevelFilter] = useState("");
  const [classFilter, setClassFilter] = useState("");
  const [departmentFilter, setDepartmentFilter] = useState("");
  const [loading, setLoading] = useState(true);
  const [selectedUserId, setSelectedUserId] = useState(null);
  const [deletingId, setDeletingId] = useState(null);
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [bulkDeleting, setBulkDeleting] = useState(false);
  const [downloadingPdf, setDownloadingPdf] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      // ✅ Adjust endpoint if yours is different.
      // Recommended backend: GET /api/school-admin/users?role=staff&status=active
      const res = await api.get("/api/school-admin/users", {
        params: { role, status: "active" },
      });
      setRows(res.data.data || []);
    } catch (e) {
      alert("Failed to load users");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    setSelectedIds(new Set());
    setLevelFilter("");
    setClassFilter("");
    setDepartmentFilter("");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [role]);

  const levelOptions = useMemo(() => {
    return Array.from(
      new Set(
        rows
          .flatMap((u) => (Array.isArray(u.levels) ? u.levels : [u.education_level]))
          .map((v) => String(v || "").trim())
          .filter(Boolean)
      )
    ).sort((a, b) => a.localeCompare(b));
  }, [rows]);

  const classOptions = useMemo(() => {
    return Array.from(
      new Set(
        rows
          .flatMap((u) => (Array.isArray(u.classes) ? u.classes : [u.class_name]))
          .map((v) => String(v || "").trim())
          .filter(Boolean)
      )
    ).sort((a, b) => a.localeCompare(b));
  }, [rows]);

  const departmentOptions = useMemo(() => {
    return Array.from(
      new Set(
        rows
          .flatMap((u) => (Array.isArray(u.departments) ? u.departments : [u.department_name]))
          .map((v) => String(v || "").trim())
          .filter(Boolean)
      )
    ).sort((a, b) => a.localeCompare(b));
  }, [rows]);

  const removeUser = async (u) => {
    if (!u?.id) return;
    if (!window.confirm(`Delete ${u.name || "this user"} permanently? This cannot be undone.`)) return;

    setDeletingId(u.id);
    try {
      await api.delete(`/api/school-admin/users/${u.id}`);
      if (selectedUserId === u.id) {
        setSelectedUserId(null);
      }
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
      const deletedIds = Array.isArray(res?.data?.data?.deleted_ids)
        ? res.data.data.deleted_ids
        : [];

      if (selectedUserId && deletedIds.includes(selectedUserId)) {
        setSelectedUserId(null);
      }

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
      const params = {
        status: "active",
        role,
      };
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
      link.download = parseFileName(res.headers, `users_${role || "all"}_active.pdf`);
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

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase();
    const level = levelFilter.trim().toLowerCase();
    const className = classFilter.trim().toLowerCase();
    const department = departmentFilter.trim().toLowerCase();

    const textFiltered = rows.filter((u) => {
      if (!s) return true;
      const name = (u.name || "").toLowerCase();
      const email = (u.email || "").toLowerCase();
      const username = (u.username || "").toLowerCase();
      return name.includes(s) || email.includes(s) || username.includes(s);
    });

    return textFiltered.filter((u) => {
      const rowLevels = (Array.isArray(u.levels) ? u.levels : [u.education_level])
        .map((v) => String(v || "").trim().toLowerCase())
        .filter(Boolean);
      const rowClasses = (Array.isArray(u.classes) ? u.classes : [u.class_name])
        .map((v) => String(v || "").trim().toLowerCase())
        .filter(Boolean);
      const rowDepartments = (Array.isArray(u.departments) ? u.departments : [u.department_name])
        .map((v) => String(v || "").trim().toLowerCase())
        .filter(Boolean);

      if (level && !rowLevels.includes(level)) return false;
      if (className && !rowClasses.includes(className)) return false;
      if (department && !rowDepartments.includes(department)) return false;
      return true;
    });
  }, [rows, q, levelFilter, classFilter, departmentFilter]);

  const visibleIds = useMemo(
    () => filtered.map((u) => u.id),
    [filtered]
  );

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

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", gap: 12 }}>
        <h4 style={{ margin: 0 }}>Active {role === "staff" ? "Staff" : "Students"}</h4>

        <div style={{ display: "flex", gap: 8, flexWrap: "wrap", justifyContent: "flex-end" }}>
          <select value={levelFilter} onChange={(e) => setLevelFilter(e.target.value)} style={{ padding: 8, minWidth: 170 }}>
            <option value="">All Levels</option>
            {levelOptions.map((v) => (
              <option key={v} value={v}>{v}</option>
            ))}
          </select>
          <select value={classFilter} onChange={(e) => setClassFilter(e.target.value)} style={{ padding: 8, minWidth: 170 }}>
            <option value="">All Classes</option>
            {classOptions.map((v) => (
              <option key={v} value={v}>{v}</option>
            ))}
          </select>
          <select value={departmentFilter} onChange={(e) => setDepartmentFilter(e.target.value)} style={{ padding: 8, minWidth: 170 }}>
            <option value="">All Departments</option>
            {departmentOptions.map((v) => (
              <option key={v} value={v}>{v}</option>
            ))}
          </select>
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Search name, email, username..."
            style={{ padding: 8, width: 320 }}
          />
        </div>
      </div>

      <div style={{ marginTop: 10, display: "flex", alignItems: "center", gap: 10 }}>
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
                    <input
                      type="checkbox"
                      checked={allVisibleSelected}
                      onChange={toggleAllVisible}
                    />
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
              {filtered.map((u, idx) => {
                const levels = Array.isArray(u.levels) && u.levels.length > 0
                  ? u.levels
                  : (u.education_level ? [u.education_level] : []);
                const classes = Array.isArray(u.classes) && u.classes.length > 0
                  ? u.classes
                  : (u.class_name ? [u.class_name] : []);
                const departments = Array.isArray(u.departments) && u.departments.length > 0
                  ? u.departments
                  : (u.department_name ? [u.department_name] : []);

                return (
                <tr key={u.id}>
                  <td>
                    <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                      <input
                        type="checkbox"
                        checked={selectedIds.has(u.id)}
                        onChange={() => toggleRow(u.id)}
                      />
                      <span>{idx + 1}</span>
                    </div>
                  </td>
                  <td>{u.name}</td>
                  <td>{levels.length ? levels.join(", ") : "-"}</td>
                  <td>{classes.length ? classes.join(", ") : "-"}</td>
                  <td>{departments.length ? departments.join(", ") : "-"}</td>
                  <td>
                    <strong>{u.status || "active"}</strong>
                  </td>
                  <td>
                    {/* Next step: "More" dropdown + profile fetch */}
                    <button onClick={() => setSelectedUserId(u.id)}>
                      More
                    </button>
                    <button
                      style={{ marginLeft: 8 }}
                      onClick={() =>
                        navigate(
                          `/school/admin/register?editUserId=${u.id}&role=${u.role}&returnTo=${encodeURIComponent(
                            `/school/admin/users/${role}/active`
                          )}`
                        )
                      }
                    >
                      Edit
                    </button>
                    <button
                      style={{
                        marginLeft: 8,
                        background: "#dc2626",
                        border: "1px solid #b91c1c",
                        color: "#fff",
                      }}
                      onClick={() => removeUser(u)}
                      disabled={deletingId === u.id}
                    >
                      {deletingId === u.id ? "Deleting..." : "Delete"}
                    </button>
                  </td>
                </tr>
                );
              })}

              {filtered.length === 0 && (
                <tr>
                  <td colSpan="7" style={{ textAlign: "center", opacity: 0.7 }}>
                    No users found
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        )}
              {/* Profile panel */}
      {selectedUserId && (
        <UserProfilePanel
          userId={selectedUserId}
          onClose={() => setSelectedUserId(null)}
          onChanged={load}
        />
      )}
      </div>
    </div>
  );
}
