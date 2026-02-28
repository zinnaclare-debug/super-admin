import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

const BULK_SUBJECT_ROWS = 20;
const createEmptyBulkRows = () =>
  Array.from({ length: BULK_SUBJECT_ROWS }, () => ({ name: "", code: "" }));

export default function ClassSubjects() {
  const { classId } = useParams();
  const navigate = useNavigate();

  const [cls, setCls] = useState(null);
  const [terms, setTerms] = useState([]);
  const [selectedTermId, setSelectedTermId] = useState(null);
  const [subjects, setSubjects] = useState([]);
  const [loading, setLoading] = useState(true);

  // create modal-ish section
  const [bulkSubjects, setBulkSubjects] = useState(createEmptyBulkRows);
  const [creating, setCreating] = useState(false);
  const [editingSubject, setEditingSubject] = useState(null);
  const [editSubjectName, setEditSubjectName] = useState("");
  const [editSubjectCode, setEditSubjectCode] = useState("");
  const [savingEdit, setSavingEdit] = useState(false);
  const [deletingSubjectId, setDeletingSubjectId] = useState(null);

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

  useEffect(() => {
    if (selectedTermId) loadSubjects(selectedTermId);
  }, [selectedTermId]);

  const termName = useMemo(() => {
    return terms.find(t => t.id === selectedTermId)?.name || "Term";
  }, [terms, selectedTermId]);

  const updateBulkSubject = (index, field, value) => {
    setBulkSubjects((prev) =>
      prev.map((item, i) => (i === index ? { ...item, [field]: value } : item))
    );
  };

  const createSubject = async () => {
    const payloadSubjects = bulkSubjects
      .map((row) => ({
        name: String(row.name || "").trim(),
        code: String(row.code || "").trim(),
      }))
      .filter((row) => row.name !== "")
      .map((row) => ({
        name: row.name,
        code: row.code || null,
      }));

    if (payloadSubjects.length === 0) return alert("Enter at least one subject name");

    setCreating(true);
    try {
      const res = await api.post(`/api/school-admin/classes/${classId}/subjects`, {
        subjects: payloadSubjects,
      });

      setBulkSubjects(createEmptyBulkRows());
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

  const startEditSubject = (subject) => {
    setEditingSubject(subject);
    setEditSubjectName(subject?.name || "");
    setEditSubjectCode(subject?.code || "");
  };

  const cancelEditSubject = () => {
    setEditingSubject(null);
    setEditSubjectName("");
    setEditSubjectCode("");
  };

  const saveEditedSubject = async () => {
    if (!editingSubject?.id) return;
    if (!editSubjectName.trim()) return alert("Enter subject name");

    setSavingEdit(true);
    try {
      const res = await api.patch(`/api/school-admin/subjects/${editingSubject.id}`, {
        name: editSubjectName.trim(),
        code: editSubjectCode.trim() || null,
      });

      alert(res.data?.message || "Subject updated");
      await loadSubjects(selectedTermId);
      cancelEditSubject();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to update subject");
    } finally {
      setSavingEdit(false);
    }
  };

  const deleteSubject = async (subject) => {
    if (!subject?.id) return;
    const ok = window.confirm(
      `Delete "${subject.name}" for this class session?\nThis removes it from all terms in this session.`
    );
    if (!ok) return;

    setDeletingSubjectId(subject.id);
    try {
      const res = await api.delete(`/api/school-admin/classes/${classId}/subjects/${subject.id}`);
      alert(res.data?.message || "Subject deleted");
      await loadSubjects(selectedTermId);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to delete subject");
    } finally {
      setDeletingSubjectId(null);
    }
  };

  if (loading) return <p>Loading...</p>;

  return (
    <div>
      {/* navbar */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
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
            <th style={{ width: 220 }}>Action</th>
          </tr>
        </thead>
        <tbody>
          {subjects.map((s, idx) => (
            <tr key={s.id}>
              <td>{idx + 1}</td>
              <td>{s.name}</td>
              <td>{s.code || "-"}</td>
              <td style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                <button
                  onClick={() =>
                    navigate(`/school/admin/academics/classes/${classId}/terms/${selectedTermId}/subjects/${s.id}/cbt`)
                  }
                  disabled={!selectedTermId}
                >
                  CBT
                </button>
                <button onClick={() => startEditSubject(s)}>Edit</button>
                <button
                  onClick={() =>
                    navigate(
                      `/school/admin/academics/classes/${classId}/terms/${selectedTermId}/subjects/${s.id}/students`
                    )
                  }
                  disabled={!selectedTermId}
                >
                  Students
                </button>
                <button
                  onClick={() => deleteSubject(s)}
                  disabled={deletingSubjectId === s.id}
                  style={{ background: "#dc2626", border: "1px solid #b91c1c", color: "#fff" }}
                >
                  {deletingSubjectId === s.id ? "Deleting..." : "Delete"}
                </button>
              </td>
            </tr>
          ))}
          {subjects.length === 0 && (
            <tr>
              <td colSpan="4">No courses yet for this term.</td>
            </tr>
          )}
        </tbody>
      </table>

      {/* create subject */}
      <div style={{ marginTop: 16, border: "1px solid #ddd", padding: 14, borderRadius: 10 }}>
        <h4 style={{ marginTop: 0 }}>Create Subject (Applies to whole session)</h4>
        <p style={{ marginTop: 0, opacity: 0.75, fontSize: 13 }}>
          Fill up to 20 subjects at once. Leave unused rows blank.
        </p>

        <div style={{ display: "grid", gap: 8 }}>
          {bulkSubjects.map((row, idx) => (
            <div key={`bulk-subject-row-${idx}`} style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
              <input
                value={row.name}
                onChange={(e) => updateBulkSubject(idx, "name", e.target.value)}
                placeholder={`Subject ${idx + 1} name`}
                style={{ padding: 10, width: 320 }}
              />
              <input
                value={row.code}
                onChange={(e) => updateBulkSubject(idx, "code", e.target.value)}
                placeholder="Short code (optional)"
                style={{ padding: 10, width: 180 }}
              />
            </div>
          ))}
        </div>

        <div style={{ marginTop: 12, display: "flex", gap: 8 }}>
          <button onClick={createSubject} disabled={creating}>
            {creating ? "Saving..." : "Save Subjects"}
          </button>
          <button type="button" onClick={() => setBulkSubjects(createEmptyBulkRows())} disabled={creating}>
            Clear All
          </button>
        </div>
      </div>

      {editingSubject && (
        <div
          role="dialog"
          aria-modal="true"
          style={{
            position: "fixed",
            inset: 0,
            background: "rgba(0,0,0,0.4)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            zIndex: 1100,
            padding: 12,
          }}
        >
          <div style={{ width: "min(560px, 100%)", background: "#fff", borderRadius: 10, padding: 16 }}>
            <h4 style={{ marginTop: 0 }}>Edit Subject</h4>
            <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
              <input
                value={editSubjectName}
                onChange={(e) => setEditSubjectName(e.target.value)}
                placeholder="Subject name"
                style={{ padding: 10, width: 280 }}
              />
              <input
                value={editSubjectCode}
                onChange={(e) => setEditSubjectCode(e.target.value)}
                placeholder="Code (optional)"
                style={{ padding: 10, width: 180 }}
              />
            </div>

            <div style={{ marginTop: 12, display: "flex", gap: 8 }}>
              <button onClick={saveEditedSubject} disabled={savingEdit}>
                {savingEdit ? "Saving..." : "Save Changes"}
              </button>
              <button onClick={cancelEditSubject} disabled={savingEdit}>
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
