import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

const cols = [
  ["handwriting", "Handwriting"],
  ["speech", "Speech"],
  ["attitude", "Attitude"],
  ["reading", "Reading"],
  ["punctuality", "Punctuality"],
  ["teamwork", "Teamwork"],
  ["self_control", "Self Control"],
];

export default function BehaviourRatingHome() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");
  const [classes, setClasses] = useState([]);
  const [terms, setTerms] = useState([]);
  const [classId, setClassId] = useState("");
  const [termId, setTermId] = useState("");
  const [students, setStudents] = useState([]);

  const load = async (nextClassId = "", nextTermId = "") => {
    setLoading(true);
    setMessage("");
    try {
      const res = await api.get("/api/staff/behaviour-rating", {
        params: {
          ...(nextClassId ? { class_id: nextClassId } : {}),
          ...(nextTermId ? { term_id: nextTermId } : {}),
        },
      });
      const data = res.data?.data;
      if (!data) {
        setMessage(res.data?.message || "No data found.");
        setClasses([]);
        setTerms([]);
        setStudents([]);
        return;
      }

      setClasses(data.classes || []);
      setTerms(data.terms || []);
      setClassId(String(data.selected_class_id || ""));
      setTermId(String(data.selected_term_id || ""));
      setStudents(data.students || []);
    } catch (err) {
      setMessage(err?.response?.data?.message || "Failed to load behaviour rating");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const save = async () => {
    if (!classId || !termId) return alert("Select class and term");
    setSaving(true);
    try {
      await api.post("/api/staff/behaviour-rating", {
        class_id: Number(classId),
        term_id: Number(termId),
        rows: students.map((s) => ({
          student_id: s.student_id,
          handwriting: Number(s.handwriting || 0),
          speech: Number(s.speech || 0),
          attitude: Number(s.attitude || 0),
          reading: Number(s.reading || 0),
          punctuality: Number(s.punctuality || 0),
          teamwork: Number(s.teamwork || 0),
          self_control: Number(s.self_control || 0),
          teacher_comment: (s.teacher_comment || "").trim(),
        })),
      });
      alert("Behaviour ratings saved");
      await load(classId, termId);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to save behaviour ratings");
    } finally {
      setSaving(false);
    }
  };

  return (
    <StaffFeatureLayout title="Behaviour Rating (Class Teacher)">

      {message ? (
        <div style={{ marginTop: 12, padding: 10, border: "1px solid #f3c06b", borderRadius: 8 }}>{message}</div>
      ) : null}

      <div style={{ marginTop: 12, display: "flex", gap: 10 }}>
        <select
          value={classId}
          onChange={async (e) => {
            const v = e.target.value;
            setClassId(v);
            await load(v, termId);
          }}
          disabled={loading || !classes.length}
        >
          <option value="">Select class</option>
          {classes.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name} ({c.level})
            </option>
          ))}
        </select>

        <select
          value={termId}
          onChange={async (e) => {
            const v = e.target.value;
            setTermId(v);
            await load(classId, v);
          }}
          disabled={loading || !terms.length}
        >
          <option value="">Select term</option>
          {terms.map((t) => (
            <option key={t.id} value={t.id}>
              {t.name}
            </option>
          ))}
        </select>
      </div>

      <div style={{ marginTop: 14 }}>
        {loading ? (
          <p>Loading...</p>
        ) : (
          <table border="1" cellPadding="8" width="100%">
            <thead>
              <tr>
                <th style={{ width: 70 }}>S/N</th>
                <th>Student Name</th>
                {cols.map(([, label]) => (
                  <th key={label}>{label}</th>
                ))}
                <th style={{ width: 260 }}>Teacher Comment</th>
              </tr>
            </thead>
            <tbody>
              {students.map((s, idx) => (
                <tr key={s.student_id}>
                  <td>{idx + 1}</td>
                  <td>{s.student_name}</td>
                  {cols.map(([key]) => (
                    <td key={key}>
                      <input
                        type="number"
                        min="0"
                        max="5"
                        value={s[key] ?? 0}
                        onChange={(e) => {
                          const v = Number(e.target.value || 0);
                          setStudents((prev) =>
                            prev.map((x) => (x.student_id === s.student_id ? { ...x, [key]: v } : x))
                          );
                        }}
                        style={{ width: 65 }}
                      />
                    </td>
                  ))}
                  <td>
                    <input
                      type="text"
                      value={s.teacher_comment || ""}
                      placeholder="Teacher comment"
                      onChange={(e) => {
                        const v = e.target.value;
                        setStudents((prev) =>
                          prev.map((x) => (x.student_id === s.student_id ? { ...x, teacher_comment: v } : x))
                        );
                      }}
                      style={{ width: "100%" }}
                    />
                  </td>
                </tr>
              ))}
              {!students.length && (
                <tr>
                  <td colSpan={3 + cols.length}>No enrolled students found for this class and term.</td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>

      <div style={{ marginTop: 14 }}>
        <button onClick={save} disabled={saving || loading || !classId || !termId}>
          {saving ? "Saving..." : "Save"}
        </button>
      </div>
    </StaffFeatureLayout>
  );
}
