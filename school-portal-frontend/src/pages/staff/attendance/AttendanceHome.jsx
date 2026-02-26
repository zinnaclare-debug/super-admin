import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import personalNotebookArt from "../../../assets/attendance/personal-notebook.svg";
import bookshelvesArt from "../../../assets/attendance/bookshelves.svg";
import trueFriendsArt from "../../../assets/attendance/true-friends.svg";
import "./AttendanceHome.css";

export default function AttendanceHome() {
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
    <StaffFeatureLayout title="Attendance (Class Teacher)">
      <div className="sat-page">
        <section className="sat-hero">
          <div>
            <span className="sat-pill">Staff Attendance</span>
            <h2>Track attendance clearly and save class records fast</h2>
            <p className="sat-subtitle">
              Select class and term, update each student&apos;s attendance days, and keep term summary details in one organized page.
            </p>
            <div className="sat-metrics">
              <span>{loading ? "Loading..." : `${students.length} student${students.length === 1 ? "" : "s"}`}</span>
              <span>{loading ? "Syncing..." : `${totalSchoolDays} total school day${totalSchoolDays === 1 ? "" : "s"}`}</span>
            </div>
          </div>

          <div className="sat-hero-art" aria-hidden="true">
            <div className="sat-art sat-art--main">
              <img src={personalNotebookArt} alt="" />
            </div>
            <div className="sat-art sat-art--bookshelves">
              <img src={bookshelvesArt} alt="" />
            </div>
            <div className="sat-art sat-art--friends">
              <img src={trueFriendsArt} alt="" />
            </div>
          </div>
        </section>

        <section className="sat-panel">
          {message ? <p className="sat-state sat-state--warn">{message}</p> : null}

          <div className="sat-filter-row">
            <div className="sat-filter">
              <label htmlFor="sat-class">Class</label>
              <select
                id="sat-class"
                className="sat-field"
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
            </div>

            <div className="sat-filter">
              <label htmlFor="sat-term">Term</label>
              <select
                id="sat-term"
                className="sat-field"
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
          </div>
        </section>

        <section className="sat-panel">
          {loading ? (
            <p className="sat-state sat-state--loading">Loading attendance...</p>
          ) : (
            <div className="sat-table-wrap">
              <table className="sat-table">
                <thead>
                  <tr>
                    <th style={{ width: 70 }}>S/N</th>
                    <th>Student Name</th>
                    <th style={{ width: 220 }}>Attendance (Days Present)</th>
                    <th style={{ width: 320 }}>Comment</th>
                  </tr>
                </thead>
                <tbody>
                  {students.map((s, idx) => (
                    <tr key={s.student_id}>
                      <td>{idx + 1}</td>
                      <td>{s.student_name}</td>
                      <td>
                        <input
                          className="sat-field sat-field--small"
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
                          className="sat-field"
                          type="text"
                          value={s.comment || ""}
                          placeholder="Comment"
                          onChange={(e) => {
                            const v = e.target.value;
                            setStudents((prev) =>
                              prev.map((x) => (x.student_id === s.student_id ? { ...x, comment: v } : x))
                            );
                          }}
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
            </div>
          )}
        </section>

        <section className="sat-panel">
          <h3>Term Attendance Summary</h3>
          <div className="sat-summary-grid">
            <div className="sat-filter">
              <label htmlFor="sat-total-days">Total school days in this term</label>
              <input
                id="sat-total-days"
                className="sat-field"
                type="number"
                min="0"
                value={totalSchoolDays}
                onChange={(e) => setTotalSchoolDays(Number(e.target.value || 0))}
              />
            </div>

            <div className="sat-filter">
              <label htmlFor="sat-next-term">Next term begins</label>
              <input
                id="sat-next-term"
                className="sat-field"
                type="date"
                value={nextTermBeginDate}
                onChange={(e) => setNextTermBeginDate(e.target.value)}
              />
            </div>

            <div className="sat-action">
              <button className="sat-btn" onClick={save} disabled={saving || loading || !classId || !termId}>
                {saving ? "Saving..." : "Save Attendance"}
              </button>
            </div>
          </div>
        </section>
      </div>
    </StaffFeatureLayout>
  );
}
