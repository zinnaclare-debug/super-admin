import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import UserProfilePanel from "./UserProfilePanel";

export default function ActiveUsers() {
  const { role } = useParams(); // staff | student
  const navigate = useNavigate();
  const [rows, setRows] = useState([]);
  const [q, setQ] = useState("");
  const [loading, setLoading] = useState(true);
  const [selectedUserId, setSelectedUserId] = useState(null);
  const [deletingId, setDeletingId] = useState(null);
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [bulkDeleting, setBulkDeleting] = useState(false);

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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [role]);

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

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase();
    if (!s) return rows;
    return rows.filter((u) => {
      const name = (u.name || "").toLowerCase();
      const email = (u.email || "").toLowerCase();
      const username = (u.username || "").toLowerCase();
      return name.includes(s) || email.includes(s) || username.includes(s);
    });
  }, [rows, q]);

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

        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search name, email, username..."
          style={{ padding: 8, width: 320 }}
        />
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
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>

            <tbody>
              {filtered.map((u, idx) => (
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
              ))}

              {filtered.length === 0 && (
                <tr>
                  <td colSpan="4" style={{ textAlign: "center", opacity: 0.7 }}>
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
