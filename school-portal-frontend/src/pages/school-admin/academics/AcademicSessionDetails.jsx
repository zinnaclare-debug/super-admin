import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import { getStoredUser } from "../../../utils/authStorage";

const prettyLevel = (value) =>
  String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

export default function AcademicSessionDetails() {
  const { sessionId } = useParams();
  const navigate = useNavigate();

  const [session, setSession] = useState(null);
  const [levels, setLevels] = useState([]);
  const [terms, setTerms] = useState([]);
  const [currentTerm, setCurrentTerm] = useState(null);
  const [loading, setLoading] = useState(true);
  const [newDepartmentByLevel, setNewDepartmentByLevel] = useState({});
  const [creatingDepartmentLevel, setCreatingDepartmentLevel] = useState("");
  const [updatingDepartmentId, setUpdatingDepartmentId] = useState(0);
  const [deletingDepartmentId, setDeletingDepartmentId] = useState(0);

  const schoolName = useMemo(() => {
    const user = getStoredUser();
    return user?.school?.name || user?.school_name || "School";
  }, []);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/school-admin/academic-sessions/${sessionId}/details`);
      const data = res.data?.data || {};
      setSession(data.session || null);
      setLevels(data.levels || []);
      setTerms(data.terms || []);
      setCurrentTerm(data.current_term || null);
    } catch (err) {
      console.error("Failed to load session details:", err?.response?.data || err);
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

  const setCurrent = async (termId) => {
    try {
      await api.patch(`/api/school-admin/terms/${termId}/set-current`);
      await load();
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to set current term");
    }
  };

  const setDepartmentDraft = (levelKey, value) => {
    setNewDepartmentByLevel((prev) => ({ ...prev, [levelKey]: value }));
  };

  const createDepartment = async (levelKey) => {
    const name = String(newDepartmentByLevel[levelKey] || "").trim();
    if (!name) return alert("Enter a department name.");

    setCreatingDepartmentLevel(levelKey);
    try {
      await api.post(`/api/school-admin/academic-sessions/${sessionId}/level-departments`, {
        level: levelKey,
        name,
      });
      setDepartmentDraft(levelKey, "");
      await load();
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to create department.");
    } finally {
      setCreatingDepartmentLevel("");
    }
  };

  const editDepartment = async (department) => {
    const currentName = String(department?.name || "").trim();
    const nextName = window.prompt("Enter new department name", currentName);
    if (nextName === null) return;

    const trimmed = String(nextName || "").trim();
    if (!trimmed) return alert("Department name cannot be empty.");
    if (trimmed.toLowerCase() === currentName.toLowerCase()) return;

    setUpdatingDepartmentId(Number(department?.id || 0));
    try {
      await api.patch(
        `/api/school-admin/academic-sessions/${sessionId}/level-departments/${department.id}`,
        { name: trimmed }
      );
      await load();
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to update department.");
    } finally {
      setUpdatingDepartmentId(0);
    }
  };

  const deleteDepartment = async (department) => {
    if (!window.confirm(`Delete department "${department?.name}" for this level?`)) return;

    setDeletingDepartmentId(Number(department?.id || 0));
    try {
      const res = await api.delete(
        `/api/school-admin/academic-sessions/${sessionId}/level-departments/${department.id}`
      );
      const retained = Number(res?.data?.meta?.retained_class_departments || 0);
      if (retained > 0) {
        alert(`Department deleted. ${retained} class department(s) still have enrollments and were retained.`);
      }
      await load();
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to delete department.");
    } finally {
      setDeletingDepartmentId(0);
    }
  };

  return (
    <div>
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
        <p style={{ marginTop: 16, color: "red" }}>Session not found or no access.</p>
      )}

      {!loading && session && (
        <>
          <div style={{ marginTop: 10, border: "1px solid #ddd", borderRadius: 8, padding: 12 }}>
            <h3 style={{ marginTop: 0 }}>Terms</h3>
            <div style={{ display: "flex", flexWrap: "wrap", gap: 8 }}>
              {terms.map((term) => (
                <button
                  key={term.id}
                  onClick={() => setCurrent(term.id)}
                  style={{
                    padding: "8px 10px",
                    borderRadius: 6,
                    border: term.is_current ? "1px solid #2563eb" : "1px solid #ccc",
                    background: term.is_current ? "#eff6ff" : "#fff",
                  }}
                >
                  {term.name} {term.is_current ? "(Current)" : ""}
                </button>
              ))}
            </div>
          </div>

          {!currentTerm ? (
            <div
              style={{
                marginTop: 14,
                padding: 12,
                border: "1px solid #f59e0b",
                borderRadius: 8,
                background: "#fffbeb",
              }}
            >
              Set a current term first before managing levels and classes for this session.
            </div>
          ) : null}

          <div style={{ marginTop: 16, display: currentTerm ? "grid" : "none", gap: 12 }}>
            {levels.map((level) => (
              <div
                key={level.level}
                style={{
                  border: "1px solid #ddd",
                  padding: 14,
                  borderRadius: 8,
                  background: "#fff",
                }}
              >
                <div>
                  <strong style={{ fontSize: 16 }}>{prettyLevel(level.level)}</strong>
                  <div style={{ fontSize: 12, opacity: 0.7 }}>
                    Classes: {level.classes?.length || 0} | Departments: {level.departments?.length || 0}
                  </div>
                </div>

                <div style={{ marginTop: 12, display: "flex", flexWrap: "wrap", gap: 8 }}>
                  {(level.classes || []).map((cls) => (
                    <button
                      key={cls.id}
                      onClick={() => navigate(`/school/admin/classes/${cls.id}`)}
                      style={{
                        padding: "8px 10px",
                        border: "1px solid #ccc",
                        borderRadius: 6,
                        background: "#fff",
                        cursor: "pointer",
                      }}
                    >
                      {cls.name}
                    </button>
                  ))}

                  {(level.classes || []).length === 0 && (
                    <div style={{ fontSize: 13, opacity: 0.7 }}>No classes yet for this level.</div>
                  )}
                </div>

                <div style={{ marginTop: 12 }}>
                  <strong style={{ fontSize: 14 }}>Departments</strong>
                  <div style={{ marginTop: 8, display: "flex", flexWrap: "wrap", gap: 8 }}>
                    {(level.departments || []).map((department) => (
                      <span
                        key={department.id}
                        style={{
                          display: "inline-flex",
                          alignItems: "center",
                          gap: 6,
                          border: "1px solid #cbd5e1",
                          borderRadius: 999,
                          padding: "4px 10px",
                          background: "#f8fafc",
                          fontSize: 12,
                        }}
                      >
                        <span>{department.name}</span>
                        <button
                          type="button"
                          onClick={() => editDepartment(department)}
                          disabled={updatingDepartmentId === Number(department.id) || deletingDepartmentId === Number(department.id)}
                        >
                          {updatingDepartmentId === Number(department.id) ? "..." : "Edit"}
                        </button>
                        <button
                          type="button"
                          onClick={() => deleteDepartment(department)}
                          disabled={updatingDepartmentId === Number(department.id) || deletingDepartmentId === Number(department.id)}
                        >
                          {deletingDepartmentId === Number(department.id) ? "..." : "Delete"}
                        </button>
                      </span>
                    ))}
                    {(level.departments || []).length === 0 && (
                      <span style={{ fontSize: 13, opacity: 0.7 }}>No department added yet.</span>
                    )}
                  </div>
                  <div style={{ marginTop: 8, display: "flex", gap: 8, flexWrap: "wrap" }}>
                    <input
                      type="text"
                      value={newDepartmentByLevel[level.level] || ""}
                      onChange={(e) => setDepartmentDraft(level.level, e.target.value)}
                      placeholder={`Add ${prettyLevel(level.level)} department`}
                      style={{ minWidth: 220, padding: "8px 10px" }}
                    />
                    <button
                      type="button"
                      onClick={() => createDepartment(level.level)}
                      disabled={creatingDepartmentLevel === level.level}
                    >
                      {creatingDepartmentLevel === level.level ? "Adding..." : "Add Department"}
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>

          <div style={{ marginTop: 12, fontSize: 13, opacity: 0.85 }}>
            You can manage departments in both <strong>School Dashboard - Branding</strong> (global templates)
            and here per level for this specific session.
          </div>
        </>
      )}
    </div>
  );
}
