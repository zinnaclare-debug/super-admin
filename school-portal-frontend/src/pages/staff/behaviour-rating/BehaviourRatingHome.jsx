import { useEffect, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import standOutArt from "../../../assets/behaviour-rating/stand-out.svg";
import splitTestingArt from "../../../assets/behaviour-rating/split-testing.svg";
import customerSurveyArt from "../../../assets/behaviour-rating/customer-survey.svg";
import "./BehaviourRatingHome.css";

const DEFAULT_CRITERIA = [
  "Handwriting", "Speech", "Attitude", "Reading", "Punctuality",
  "Teamwork", "Self Control", "Neatness", "Honesty", "Obedience",
].map((label, index) => ({ key: `criterion_${index + 1}`, label }));

export default function BehaviourRatingHome() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");
  const [classes, setClasses] = useState([]);
  const [terms, setTerms] = useState([]);
  const [classId, setClassId] = useState("");
  const [termId, setTermId] = useState("");
  const [students, setStudents] = useState([]);
  const [criteria, setCriteria] = useState(DEFAULT_CRITERIA);

  const load = async (nextClassId = "") => {
    setLoading(true);
    setMessage("");
    try {
      const res = await api.get("/api/staff/behaviour-rating", {
        params: {
          ...(nextClassId ? { class_id: nextClassId } : {}),
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
      setCriteria(Array.isArray(data.behaviour_schema?.criteria) ? data.behaviour_schema.criteria : DEFAULT_CRITERIA);
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
          ratings: Object.fromEntries(criteria.map((criterion) => [
            criterion.key,
            Number(s.ratings?.[criterion.key] || 0),
          ])),
        })),
      });
      alert("Behaviour ratings saved");
      await load(classId);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to save behaviour ratings");
    } finally {
      setSaving(false);
    }
  };

  return (
    <StaffFeatureLayout title="Behaviour Rating (Class Teacher)" showHeader={false}>
      <div className="bvr-page">
        <section className="bvr-hero">
          <div>
            <span className="bvr-pill">Staff Behaviour Rating</span>
            <h2>Rate classroom behaviour with structure and speed</h2>
            <p className="bvr-subtitle">
              Select your assigned class, score behaviour criteria on a 0-5 scale, and save current-term ratings for each student.
            </p>
            <div className="bvr-metrics">
              <span>{loading ? "Loading..." : `${students.length} student${students.length === 1 ? "" : "s"}`}</span>
              <span>{criteria.length} behaviour criteria</span>
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
                  await load(v);
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
              <label htmlFor="bvr-term">Current Term</label>
              <input id="bvr-term" className="bvr-field" value={terms[0]?.name || "No current term"} readOnly />
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
                    {criteria.map((criterion) => (
                      <th key={criterion.key}>{criterion.label}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {students.map((s, idx) => (
                    <tr key={s.student_id}>
                      <td>{idx + 1}</td>
                      <td>{s.student_name}</td>
                      {criteria.map((criterion) => (
                        <td key={criterion.key}>
                          <input
                            className="bvr-field bvr-field--score"
                            type="number"
                            min="0"
                            max="5"
                            value={s.ratings?.[criterion.key] ?? 0}
                            onChange={(e) => {
                              const v = Number(e.target.value || 0);
                              setStudents((prev) =>
                                prev.map((x) => (x.student_id === s.student_id
                                  ? { ...x, ratings: { ...x.ratings, [criterion.key]: v } }
                                  : x))
                              );
                            }}
                          />
                        </td>
                      ))}

                    </tr>
                  ))}
                  {!students.length && (
                    <tr>
                      <td colSpan={2 + criteria.length}>No enrolled students found for this class and term.</td>
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

