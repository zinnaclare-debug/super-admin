import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

export default function ClassSubjects() {
  const { classId } = useParams();
  const navigate = useNavigate();

  const [cls, setCls] = useState(null);
  const [terms, setTerms] = useState([]);
  const [selectedTermId, setSelectedTermId] = useState(null);
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);

  // create modal-ish section
  const [newSubjectName, setNewSubjectName] = useState("");
  const [newSubjectCode, setNewSubjectCode] = useState("");
  const [termIdsToApply, setTermIdsToApply] = useState([]);
  const [creating, setCreating] = useState(false);

  const loadTerms = async () => {
    const res = await api.get(`/api/school-admin/classes/${classId}/terms`);
    setCls(res.data.data.class);
    const t = res.data.data.terms || [];
    setTerms(t);
    if (!selectedTermId && t.length) setSelectedTermId(t[0].id);
  };

  const loadSubjects = async (termId) => {
    if (!termId) return;
    const res = await api.get(`/api/school-admin/classes/${classId}/terms/${termId}/subjects`);
    setSubjects(res.data.data || []);
  };

  const loadAll = async () => {
    setLoading(true);
    try {
      await loadTerms();
    } catch (e) {
      alert("Failed to load class terms");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadAll(); }, [classId]);

  // default apply to selected term if user hasn't toggled any
  useEffect(() => {
    if (selectedTermId && termIdsToApply.length === 0) {
      setTermIdsToApply([selectedTermId]);
    }
  }, [selectedTermId]);

  useEffect(() => {
    if (selectedTermId) loadSubjects(selectedTermId);
  }, [selectedTermId]);

  const termName = useMemo(() => {
    return terms.find(t => t.id === selectedTermId)?.name || "Term";
  }, [terms, selectedTermId]);
  const isSecondaryClass = String(cls?.level || "").toLowerCase() === "secondary";

  const toggleApplyTerm = (id) => {
    setTermIdsToApply((prev) =>
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );
  };

  const createSubject = async () => {
    if (!newSubjectName.trim()) return alert("Enter subject name");
    if (termIdsToApply.length === 0) return alert("Select term(s) to apply");

    setCreating(true);
    try {
      const res = await api.post(`/api/school-admin/classes/${classId}/subjects`, {
        subjects: [{ name: newSubjectName, code: newSubjectCode || null }],
        term_ids: termIdsToApply,
      });

      setNewSubjectName("");
      setNewSubjectCode("");
      // keep term selection so user can add multiple subjects quickly
      await loadSubjects(selectedTermId);
      alert(res.data?.message || "Subject saved!");
    } catch (e) {
      console.error('createSubject error', e);
      const msg = e?.response?.data?.message || e.message || 'Failed to save subject';
      alert(msg);
    } finally {
      setCreating(false);
    }
  };

  if (loading) return <p>Loading...</p>;

  return (
    <div>
      {/* navbar */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h2 style={{ margin: 0 }}>{cls?.name || "Class Subjects"}</h2>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      {/* term selector */}
      <div style={{ display: "flex", gap: 10, marginTop: 12, flexWrap: "wrap" }}>
        {terms.map((t) => (
          <button
            key={t.id}
            onClick={() => setSelectedTermId(t.id)}
            style={{
              padding: "6px 10px",
              borderRadius: 8,
              border: "1px solid #ddd",
              background: selectedTermId === t.id ? "#2563eb" : "#fff",
              color: selectedTermId === t.id ? "#fff" : "#111",
            }}
          >
            {t.name}
          </button>
        ))}
      </div>

      {/* subjects table */}
      <h3 style={{ marginTop: 16 }}>{termName} Courses</h3>
      <table border="1" cellPadding="10" width="100%">
        <thead>
          <tr>
            <th style={{ width: 70 }}>S/N</th>
            <th>Course</th>
            <th style={{ width: 160 }}>Code</th>
            {isSecondaryClass && <th style={{ width: 160 }}>Action</th>}
          </tr>
        </thead>
        <tbody>
          {subjects.map((s, idx) => (
            <tr key={s.id}>
              <td>{idx + 1}</td>
              <td>{s.name}</td>
              <td>{s.code || "-"}</td>
              {isSecondaryClass && (
                <td>
                  <button
                    onClick={() =>
                      navigate(`/school/admin/academics/classes/${classId}/terms/${selectedTermId}/subjects/${s.id}/cbt`)
                    }
                    disabled={!selectedTermId}
                  >
                    CBT
                  </button>
                </td>
              )}
            </tr>
          ))}
          {subjects.length === 0 && (
            <tr>
              <td colSpan={isSecondaryClass ? 4 : 3}>No courses yet for this term.</td>
            </tr>
          )}
        </tbody>
      </table>

      {/* create subject */}
      <div style={{ marginTop: 16, border: "1px solid #ddd", padding: 14, borderRadius: 10 }}>
        <h4 style={{ marginTop: 0 }}>Create Subject (Apply to selected term(s))</h4>

        <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
          <input
            value={newSubjectName}
            onChange={(e) => setNewSubjectName(e.target.value)}
            placeholder="Subject name e.g. Mathematics"
            style={{ padding: 10, width: 280 }}
          />
          <input
            value={newSubjectCode}
            onChange={(e) => setNewSubjectCode(e.target.value)}
            placeholder="Code (optional)"
            style={{ padding: 10, width: 180 }}
          />
        </div>

        <div style={{ marginTop: 10 }}>
          <strong>Apply to:</strong>
          <div style={{ display: "flex", gap: 12, flexWrap: "wrap", marginTop: 8 }}>
            {terms.map((t) => (
              <label key={t.id} style={{ display: "flex", alignItems: "center", gap: 6 }}>
                <input
                  type="checkbox"
                  checked={termIdsToApply.includes(t.id)}
                  onChange={() => toggleApplyTerm(t.id)}
                />
                {t.name}
              </label>
            ))}
          </div>
        </div>

        <div style={{ marginTop: 12 }}>
          <button onClick={createSubject} disabled={creating || termIdsToApply.length === 0}>
            {creating ? 'Saving...' : 'Save Subject'}
          </button>
        </div>
      </div>
    </div>
  );
}
