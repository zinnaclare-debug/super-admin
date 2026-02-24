import { useEffect, useState, useMemo } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import { getStoredUser } from "../../../utils/authStorage";

export default function AcademicSessionDetails() {
  const { sessionId } = useParams();
  const navigate = useNavigate();

  const [session, setSession] = useState(null);
  const [levels, setLevels] = useState([]);
  const [terms, setTerms] = useState([]);
  const [currentTerm, setCurrentTerm] = useState(null);
  const [loading, setLoading] = useState(true);

  const [deptName, setDeptName] = useState("");
  const [deptLevel, setDeptLevel] = useState(null);

  const schoolName = useMemo(() => {
    const u = getStoredUser();
    return u?.school?.name || u?.school_name || "School";
  }, []);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(
        `/api/school-admin/academic-sessions/${sessionId}/details`
      );

      const data = res.data.data;
      setSession(data.session || null);
      setLevels(data.levels || []);
      setTerms(data.terms || []);
      setCurrentTerm(data.current_term || null);
    } catch (e) {
      console.error("Failed to load session details:", e?.response?.data || e);
      setSession(null);
      setLevels([]);
      setTerms([]);
      setCurrentTerm(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sessionId]);

  const openCreateDept = (level) => {
    setDeptLevel(level);
    setDeptName("");
  };

  const createDept = async () => {
    if (!deptName || !deptLevel) return;

    try {
      await api.post(
        `/api/school-admin/academic-sessions/${sessionId}/level-departments`,
        {
          level: deptLevel,
          name: deptName,
        }
      );

      alert("Department created for this level and applied to all classes in the session.");
      setDeptLevel(null);
      setDeptName("");
      load();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to create department");
    }
  };

  return (
    <div>
      {/* ✅ page navbar */}
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          padding: "10px 12px",
          border: "1px solid #ddd",
          borderRadius: 10,
          background: "#fff",
        }}
      >
        <strong>{schoolName}</strong>

      </div>

      <div style={{ marginTop: 14 }}>
        <p style={{ margin: "8px 0", fontWeight: 700 }}>
          {session?.session_name || session?.academic_year || "Academic Session"}
        </p>
        <p style={{ marginTop: 0, opacity: 0.75 }}>
          Status: <strong>{session?.status || "N/A"}</strong>
        </p>
        <p style={{ marginTop: 0, opacity: 0.85 }}>
          Current Term: <strong>{currentTerm?.name || "Not set"}</strong>
        </p>
      </div>

      {loading && <p>Loading session details...</p>}

      {!loading && !session && (
        <p style={{ marginTop: 16, color: "red" }}>
          Session not found / no access.
        </p>
      )}

      {!loading && session && (
        <>
          <div style={{ marginTop: 10, border: "1px solid #ddd", borderRadius: 8, padding: 12 }}>
            <h3 style={{ marginTop: 0 }}>Terms</h3>
            <div style={{ display: "flex", flexWrap: "wrap", gap: 8 }}>
              {terms.map((t) => (
                <button
                  key={t.id}
                  onClick={async () => {
                    try {
                      await api.patch(`/api/school-admin/terms/${t.id}/set-current`);
                      await load();
                    } catch (e) {
                      alert(e?.response?.data?.message || "Failed to set current term");
                    }
                  }}
                  style={{
                    padding: "8px 10px",
                    borderRadius: 6,
                    border: t.is_current ? "1px solid #2563eb" : "1px solid #ccc",
                    background: t.is_current ? "#eff6ff" : "#fff",
                  }}
                >
                  {t.name} {t.is_current ? "(Current)" : ""}
                </button>
              ))}
            </div>
          </div>

          {!currentTerm ? (
            <div style={{ marginTop: 14, padding: 12, border: "1px solid #f59e0b", borderRadius: 8, background: "#fffbeb" }}>
              Set a current term first before managing levels and classes for this session.
            </div>
          ) : null}

          {/* LEVEL CARDS */}
          <div style={{ marginTop: 16, display: currentTerm ? "grid" : "none", gap: 12 }}>
            {levels.map((lvl) => (
              <div
                key={lvl.level}
                style={{
                  border: "1px solid #ddd",
                  padding: 14,
                  borderRadius: 8,
                  background: "#fff",
                }}
              >
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    gap: 10,
                  }}
                >
                  <div>
                    <strong style={{ fontSize: 16 }}>
                      {String(lvl.level).toUpperCase()}
                    </strong>
                    <div style={{ fontSize: 12, opacity: 0.7 }}>
                      Classes: {lvl.classes?.length || 0} | Departments:{" "}
                      {lvl.departments?.length || 0}
                    </div>
                  </div>

                  <button onClick={() => openCreateDept(lvl.level)}>
                    Create Department
                  </button>
                </div>

                {/* Classes buttons */}
                <div
                  style={{
                    marginTop: 12,
                    display: "flex",
                    flexWrap: "wrap",
                    gap: 8,
                  }}
                >
                  {(lvl.classes || []).map((c) => (
                    <button
                      key={c.id}
                      onClick={() => navigate(`/school/admin/classes/${c.id}`)}
                      style={{
                        padding: "8px 10px",
                        border: "1px solid #ccc",
                        borderRadius: 6,
                        background: "#fff",
                        cursor: "pointer",
                      }}
                    >
                      {c.name}
                    </button>
                  ))}

                  {(lvl.classes || []).length === 0 && (
                    <div style={{ fontSize: 13, opacity: 0.7 }}>
                      No classes yet for this level.
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        </>
      )}

      {/* CREATE DEPARTMENT INLINE MODAL */}
      {deptLevel && (
        <div
          style={{
            marginTop: 16,
            padding: 14,
            border: "1px solid #ccc",
            borderRadius: 8,
            background: "#fff",
          }}
        >
          <h4 style={{ marginTop: 0 }}>
            Create Department — {String(deptLevel).toUpperCase()}
          </h4>

          <input
            placeholder="Department name (e.g. Science, Arts, A, B)"
            value={deptName}
            onChange={(e) => setDeptName(e.target.value)}
            style={{ width: "100%", padding: 10 }}
          />

          <div style={{ marginTop: 10 }}>
            <button onClick={createDept}>Save</button>
            <button onClick={() => setDeptLevel(null)} style={{ marginLeft: 10 }}>
              Cancel
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
