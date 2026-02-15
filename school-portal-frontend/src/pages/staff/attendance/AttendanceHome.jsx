import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";

export default function AttendanceHome() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");

  const [classes, setClasses] = useState([]);
  const [terms, setTerms] = useState([]);
  const [classId, setClassId] = useState("");
  const [termId, setTermId] = useState("");
  const [students, setStudents] = useState([]);
  const [totalSchoolDays, setTotalSchoolDays] = useState(0);
  const [nextTermBeginDate, setNextTermBeginDate] = useState("");

  const load = async (nextClassId = "", nextTermId = "") => {
    setLoading(true);
    setMessage("");
    try {
      const res = await api.get("/api/staff/attendance", {
        params: {
          ...(nextClassId ? { class_id: nextClassId } : {}),
          ...(nextTermId ? { term_id: nextTermId } : {}),
        },
      });
      const data = res.data?.data;
      if (!data) {
        setMessage(res.data?.message || "No attendance data.");
        setClasses([]);
        setTerms([]);
        setStudents([]);
        return;
      }

      setClasses(data.classes || []);
      setTerms(data.terms || []);
      setClassId(String(data.selected_class_id || ""));
      setTermId(String(data.selected_term_id || ""));
      setTotalSchoolDays(Number(data.total_school_days || 0));
      setNextTermBeginDate(data.next_term_begin_date || "");
      setStudents(
        (data.students || []).map((s) => ({
          ...s,
          days_present: Number(s.days_present || 0),
          comment: s.comment || "",
        }))
      );
    } catch (err) {
      setMessage(err?.response?.data?.message || "Failed to load attendance");
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
      await api.post("/api/staff/attendance", {
        class_id: Number(classId),
        term_id: Number(termId),
        total_school_days: Number(totalSchoolDays || 0),
        next_term_begin_date: nextTermBeginDate || null,
        rows: students.map((s) => ({
          student_id: s.student_id,
          days_present: Number(s.days_present || 0),
          comment: (s.comment || "").trim(),
        })),
      });
      alert("Attendance saved");
      await load(classId, termId);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to save attendance");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h2>Attendance (Class Teacher)</h2>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      {message ? (
        <div style={{ marginTop: 12, padding: 10, border: "1px solid #f3c06b", borderRadius: 8 }}>
          {message}
        </div>
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
                <th style={{ width: 180 }}>Attendance (Days Present)</th>
                <th style={{ width: 280 }}>Comment</th>
              </tr>
            </thead>
            <tbody>
              {students.map((s, idx) => (
                <tr key={s.student_id}>
                  <td>{idx + 1}</td>
                  <td>{s.student_name}</td>
                  <td>
                    <input
                      type="number"
                      min="0"
                      value={s.days_present}
                      onChange={(e) => {
                        const v = Number(e.target.value || 0);
                        setStudents((prev) =>
                          prev.map((x) => (x.student_id === s.student_id ? { ...x, days_present: v } : x))
                        );
                      }}
                    />
                  </td>
                  <td>
                    <input
                      type="text"
                      value={s.comment || ""}
                      placeholder="Comment"
                      onChange={(e) => {
                        const v = e.target.value;
                        setStudents((prev) =>
                          prev.map((x) => (x.student_id === s.student_id ? { ...x, comment: v } : x))
                        );
                      }}
                      style={{ width: "100%" }}
                    />
                  </td>
                </tr>
              ))}
              {!students.length && (
                <tr>
                  <td colSpan="4">No enrolled students found for this class and term.</td>
                </tr>
              )}
            </tbody>
          </table>
        )}
      </div>

      <div style={{ marginTop: 14, display: "flex", alignItems: "center", gap: 8 }}>
        <label>Total school days in this term:</label>
        <input
          type="number"
          min="0"
          value={totalSchoolDays}
          onChange={(e) => setTotalSchoolDays(Number(e.target.value || 0))}
          style={{ width: 120 }}
        />
        <label style={{ marginLeft: 10 }}>Next term begins:</label>
        <input
          type="date"
          value={nextTermBeginDate}
          onChange={(e) => setNextTermBeginDate(e.target.value)}
        />
        <button onClick={save} disabled={saving || loading || !classId || !termId}>
          {saving ? "Saving..." : "Save"}
        </button>
      </div>
    </div>
  );
}
