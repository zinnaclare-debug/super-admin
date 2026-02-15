import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
 import UserProfilePanel from "./UserProfilePanel";

export default function InactiveUsers() {
  const { role } = useParams();
  const navigate = useNavigate();
  const [rows, setRows] = useState([]);
  const [q, setQ] = useState("");
  const [loading, setLoading] = useState(true);
  const [selectedUserId, setSelectedUserId] = useState(null);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/school-admin/users", {
        params: { role, status: "inactive" },
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [role]);

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

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", gap: 12 }}>
        <h4 style={{ margin: 0 }}>
          Inactive {role === "staff" ? "Staff" : "Students"}
        </h4>

        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search name, email, username..."
          style={{ padding: 8, width: 320 }}
        />
      </div>

      <div style={{ marginTop: 14 }}>
        {loading ? (
          <p>Loading...</p>
        ) : (
          <table border="1" cellPadding="10" cellSpacing="0" width="100%">
            <thead>
              <tr>
                <th>S/N</th>
                <th>Name</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>

            <tbody>
              {filtered.map((u, idx) => (
                <tr key={u.id}>
                  <td>{idx + 1}</td>
                  <td>{u.name}</td>
                  <td>
                    <strong>{u.status || "inactive"}</strong>
                  </td>
                  <td>
                    <button onClick={() => setSelectedUserId(u.id)}>
                      More
                    </button>
                    <button
                      style={{ marginLeft: 8 }}
                      onClick={() =>
                        navigate(
                          `/school/admin/register?editUserId=${u.id}&role=${u.role}&returnTo=${encodeURIComponent(
                            `/school/admin/users/${role}/inactive`
                          )}`
                        )
                      }
                    >
                      Edit
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
 
