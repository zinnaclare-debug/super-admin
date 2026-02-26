import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import standOutArt from "../../../assets/behaviour-rating/stand-out.svg";
import splitTestingArt from "../../../assets/behaviour-rating/split-testing.svg";
import customerSurveyArt from "../../../assets/behaviour-rating/customer-survey.svg";
import "./BehaviourRatingHome.css";

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
      <div className="bvr-page">
        <section className="bvr-hero">
          <div>
            <span className="bvr-pill">Staff Behaviour Rating</span>
            <h2>Rate classroom behaviour with structure and speed</h2>
            <p className="bvr-subtitle">
              Select class and term, score behaviour criteria on a 0-5 scale, and save comments for each student in one page.
            </p>
            <div className="bvr-metrics">
              <span>{loading ? "Loading..." : `${students.length} student${students.length === 1 ? "" : "s"}`}</span>
              <span>{cols.length} behaviour criteria</span>
            </div>
          </div>

          <div className="bvr-hero-art" aria-hidden="true">
            <div className="bvr-art bvr-art--main">
              <img src={standOutArt} alt="" />
            </div>
            <div className="bvr-art bvr-art--split">
              <img src={splitTestingArt} alt="" />
            </div>
            <div className="bvr-art bvr-art--survey">
              <img src={customerSurveyArt} alt="" />
            </div>
          </div>
        </section>

        <section className="bvr-panel">
          {message ? <p className="bvr-state bvr-state--warn">{message}</p> : null}

          <div className="bvr-filter-row">
            <div className="bvr-filter">
              <label htmlFor="bvr-class">Class</label>
              <select
                id="bvr-class"
                className="bvr-field"
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

            <div className="bvr-filter">
              <label htmlFor="bvr-term">Term</label>
              <select
                id="bvr-term"
                className="bvr-field"
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

        <section className="bvr-panel">
          {loading ? (
            <p className="bvr-state bvr-state--loading">Loading behaviour rating...</p>
          ) : (
            <div className="bvr-table-wrap">
              <table className="bvr-table">
                <thead>
                  <tr>
                    <th style={{ width: 70 }}>S/N</th>
                    <th style={{ minWidth: 190 }}>Student Name</th>
                    {cols.map(([, label]) => (
                      <th key={label}>{label}</th>
                    ))}
                    <th style={{ minWidth: 260 }}>Teacher Comment</th>
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
                            className="bvr-field bvr-field--score"
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
                          />
                        </td>
                      ))}
                      <td>
                        <input
                          className="bvr-field"
                          type="text"
                          value={s.teacher_comment || ""}
                          placeholder="Teacher comment"
                          onChange={(e) => {
                            const v = e.target.value;
                            setStudents((prev) =>
                              prev.map((x) => (x.student_id === s.student_id ? { ...x, teacher_comment: v } : x))
                            );
                          }}
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
            </div>
          )}
        </section>

        <section className="bvr-panel">
          <button className="bvr-btn" onClick={save} disabled={saving || loading || !classId || !termId}>
            {saving ? "Saving..." : "Save Behaviour Ratings"}
          </button>
        </section>
      </div>
    </StaffFeatureLayout>
  );
}
