import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import AcademicPageShell from "./AcademicPageShell";

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
    <AcademicPageShell
      pill="Class Subjects"
      title={`${cls?.name || "Class"} subjects`}
      subtitle="Create subjects once for the session, then manage students, CBT publishing, and term subject records from this page."
      meta={[termName, `${subjects.length} subjects`, `${terms.length} terms`]}
    >
      <div className="academic-inner__toolbar">
        <div>
          <h3>{termName} Courses</h3>
          <p>Select a term to view and manage the subjects available in that term.</p>
        </div>
        <button className="academic-inner__back-btn" type="button" onClick={() => navigate(-1)}>
          Back
        </button>
      </div>

      <div className="academic-inner__chip-row">
        {terms.map((t) => (
          <button
            key={t.id}
            onClick={() => setSelectedTermId(t.id)}
            className={`academic-inner__chip${selectedTermId === t.id ? " academic-inner__chip--active" : ""}`}
          >
            {t.name}
          </button>
        ))}
      </div>

      <div className="payx-card academic-inner__card">
        <div className="academic-inner__table-wrap">
          <table className="payx-table academic-inner__table">
            <thead>
              <tr>
                <th style={{ width: 70 }}>S/N</th>
                <th>Course</th>
                <th style={{ width: 160 }}>Code</th>
                <th style={{ width: 280 }}>Action</th>
              </tr>
            </thead>
            <tbody>
              {subjects.map((s, idx) => (
                <tr key={s.id}>
                  <td>{idx + 1}</td>
                  <td>{s.name}</td>
                  <td>{s.code || "-"}</td>
                  <td>
                    <div className="academic-inner__action-cell">
                      <button
                        className="payx-btn payx-btn--soft"
                        onClick={() =>
                          navigate(`/school/admin/academics/classes/${classId}/terms/${selectedTermId}/subjects/${s.id}/cbt`)
                        }
                        disabled={!selectedTermId}
                      >
                        CBT
                      </button>
                      <button className="payx-btn payx-btn--soft" onClick={() => startEditSubject(s)}>Edit</button>
                      <button
                        className="payx-btn"
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
                        className="payx-btn academic-inner__danger"
                        onClick={() => deleteSubject(s)}
                        disabled={deletingSubjectId === s.id}
                      >
                        {deletingSubjectId === s.id ? "Deleting..." : "Delete"}
                      </button>
                    </div>
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
        </div>
      </div>

      {/* create subject */}
      <div className="payx-card academic-inner__card">
        <h3 className="academic-inner__card-title">Create Subject (Applies to whole session)</h3>
        <p className="academic-inner__muted">
          Fill up to 20 subjects at once. Leave unused rows blank.
        </p>

        <div className="academic-inner__form-grid" style={{ marginTop: 12 }}>
          {bulkSubjects.map((row, idx) => (
            <div key={`bulk-subject-row-${idx}`} className="academic-inner__bulk-row">
              <input
                className="academic-inner__field"
                value={row.name}
                onChange={(e) => updateBulkSubject(idx, "name", e.target.value)}
                placeholder={`Subject ${idx + 1} name`}
              />
              <input
                className="academic-inner__field"
                value={row.code}
                onChange={(e) => updateBulkSubject(idx, "code", e.target.value)}
                placeholder="Short code (optional)"
              />
            </div>
          ))}
        </div>

        <div className="academic-inner__actions" style={{ marginTop: 12 }}>
          <button className="payx-btn" onClick={createSubject} disabled={creating}>
            {creating ? "Saving..." : "Save Subjects"}
          </button>
          <button className="payx-btn payx-btn--soft" type="button" onClick={() => setBulkSubjects(createEmptyBulkRows())} disabled={creating}>
            Clear All
          </button>
        </div>
      </div>

      {editingSubject && (
        <div
          role="dialog"
          aria-modal="true"
          className="academic-inner__modal"
        >
          <div className="academic-inner__modal-card">
            <h3 className="academic-inner__card-title">Edit Subject</h3>
            <div className="academic-inner__actions" style={{ marginTop: 12 }}>
              <input
                className="academic-inner__field"
                value={editSubjectName}
                onChange={(e) => setEditSubjectName(e.target.value)}
                placeholder="Subject name"
              />
              <input
                className="academic-inner__field"
                value={editSubjectCode}
                onChange={(e) => setEditSubjectCode(e.target.value)}
                placeholder="Code (optional)"
              />
            </div>

            <div className="academic-inner__actions" style={{ marginTop: 12 }}>
              <button className="payx-btn" onClick={saveEditedSubject} disabled={savingEdit}>
                {savingEdit ? "Saving..." : "Save Changes"}
              </button>
              <button className="payx-btn payx-btn--soft" onClick={cancelEditSubject} disabled={savingEdit}>
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </AcademicPageShell>
  );
}
