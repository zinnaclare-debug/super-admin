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

const SCORE_TO_LETTER = ["-", "E", "D", "C", "B", "A"];
const LETTER_TO_SCORE = { E: 1, D: 2, C: 3, B: 4, A: 5 };

const DEFAULT_COMMENT_ONLY_CRITERIA = Array.from({ length: 10 }, (_, index) => ({
  key: `learning_area_${index + 1}`,
  label: `Learning Area ${index + 1}`,
}));
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
  const [ratingFormat, setRatingFormat] = useState("number");
  const [commentsOnly, setCommentsOnly] = useState(false);
  const [commentOnlyCriteria, setCommentOnlyCriteria] = useState(DEFAULT_COMMENT_ONLY_CRITERIA);

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
      setRatingFormat(data.behaviour_rating_format === "letter" ? "letter" : "number");
      setCommentsOnly(Boolean(data.comments_only));
      setCommentOnlyCriteria(Array.isArray(data.comment_only_schema?.criteria) ? data.comment_only_schema.criteria : DEFAULT_COMMENT_ONLY_CRITERIA);
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
          ...(commentsOnly ? {
            learning_observations: Object.fromEntries(commentOnlyCriteria.map((criterion) => [
              criterion.key,
              String(s.learning_observations?.[criterion.key] || '').trim(),
            ])),
          } : {}),
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
              Rate each behaviour criterion using the school-selected format. Existing ratings retain their original number or letter format. Comments-only classes use their Assessment Sheet on the Results page instead.
            </p>
            <div className="bvr-metrics">
              <span>{loading ? "Loading..." : `${students.length} student${students.length === 1 ? "" : "s"}`}</span>
              <span>{commentsOnly ? `${commentOnlyCriteria.length} learning areas` : `${criteria.length} behaviour criteria`}</span>
              {!commentsOnly ? <span>{ratingFormat === "letter" ? "Letter entry (E-A)" : "Number entry (1-5)"}</span> : null}
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

        {!commentsOnly ? (
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
                      {criteria.map((criterion) => {
                        const inputFormat = s.behaviour_rating_format || ratingFormat;
                        const score = Number(s.ratings?.[criterion.key] || 0);
                        return (
                          <td key={criterion.key}>
                            {inputFormat === "letter" ? (
                              <select
                                className="bvr-field bvr-field--score"
                                value={SCORE_TO_LETTER[score] || "0"}
                                onChange={(event) => {
                                  const selected = event.target.value;
                                  const nextScore = selected === "0" ? 0 : LETTER_TO_SCORE[selected];
                                  setStudents((previous) => previous.map((student) => (
                                    student.student_id === s.student_id
                                      ? { ...student, ratings: { ...student.ratings, [criterion.key]: nextScore } }
                                      : student
                                  )));
                                }}
                                aria-label={`${s.student_name} ${criterion.label} rating letter`}
                              >
                                <option value="0">-</option>
                                {Object.keys(LETTER_TO_SCORE).map((letter) => <option key={letter} value={letter}>{letter}</option>)}
                              </select>
                            ) : (
                              <input
                                className="bvr-field bvr-field--score"
                                type="number"
                                min="0"
                                max="5"
                                value={score}
                                onChange={(event) => {
                                  const value = Math.max(0, Math.min(5, Number(event.target.value || 0)));
                                  setStudents((previous) => previous.map((student) => (
                                    student.student_id === s.student_id
                                      ? { ...student, ratings: { ...student.ratings, [criterion.key]: value } }
                                      : student
                                  )));
                                }}
                                aria-label={`${s.student_name} ${criterion.label} numeric rating`}
                              />
                            )}
                          </td>
                        );
                      })}

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
        ) : null}

        {commentsOnly ? (
          <section className="bvr-panel bvr-observation-panel">
            <div className="bvr-observation-head">
              <div>
                <span className="bvr-observation-kicker">Comments-only assessment</span>
                <h3>Learning area observations</h3>
                <p>Write a clear observation for every learning area. These entries appear in the student result preview and assessment-sheet PDF.</p>
              </div>
            </div>
            <div className="bvr-table-wrap bvr-observation-wrap">
              <table className="bvr-table bvr-observation-table">
                <thead>
                  <tr>
                    <th style={{ width: 70 }}>S/N</th>
                    <th style={{ minWidth: 190 }}>Student Name</th>
                    {commentOnlyCriteria.map((criterion) => <th key={criterion.key}>{criterion.label}</th>)}
                  </tr>
                </thead>
                <tbody>
                  {students.map((student, index) => (
                    <tr key={student.student_id}>
                      <td>{index + 1}</td>
                      <td>{student.student_name}</td>
                      {commentOnlyCriteria.map((criterion) => (
                        <td key={criterion.key}>
                          <textarea
                            className="bvr-observation-input"
                            rows={3}
                            maxLength={1000}
                            value={student.learning_observations?.[criterion.key] || ""}
                            onChange={(event) => setStudents((previous) => previous.map((item) => (
                              item.student_id === student.student_id
                                ? { ...item, learning_observations: { ...item.learning_observations, [criterion.key]: event.target.value } }
                                : item
                            )))}
                            placeholder="Write observation"
                          />
                        </td>
                      ))}
                    </tr>
                  ))}
                  {!students.length ? <tr><td colSpan={2 + commentOnlyCriteria.length}>No enrolled students found for this class and term.</td></tr> : null}
                </tbody>
              </table>
            </div>
          </section>
        ) : null}

        <section className="bvr-panel">
          <button className="bvr-btn" onClick={save} disabled={saving || loading || !classId || !termId}>
            {saving ? "Saving..." : commentsOnly ? "Save Assessment Observations" : "Save Behaviour Ratings"}
          </button>
        </section>
      </div>
    </StaffFeatureLayout>
  );
}

