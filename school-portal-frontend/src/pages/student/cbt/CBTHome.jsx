import { useEffect, useRef, useState } from "react";
import api from "../../../services/api";
import cbtMainArt from "../../../assets/cbt-dashboard/online-meetings.svg";
import cbtResumeArt from "../../../assets/cbt-dashboard/online-resume.svg";
import cbtProfilesArt from "../../../assets/cbt-dashboard/swipe-profiles.svg";
import { createCbtSecurityFramework } from "../../../utils/cbtSecurityFramework";
import "../../shared/CbtShowcase.css";

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export default function StudentCBTHome() {
  const strictSecurityDefaults = {
    fullscreen_required: true,
    block_copy_paste: true,
    block_tab_switch: true,
    auto_submit_on_violation: true,
    auto_submit_on_fullscreen_exit: true,
    auto_submit_on_multiple_faces: true,
    ai_proctoring_enabled: true,
    max_warnings: 3,
    no_face_timeout_seconds: 30,
    max_head_movement_warnings: 2,
    head_movement_threshold_px: 60,
  };

  const [exams, setExams] = useState([]);
  const [selectedExam, setSelectedExam] = useState(null);
  const [reviewExam, setReviewExam] = useState(null);
  const [reviewData, setReviewData] = useState(null);
  const [loadingReview, setLoadingReview] = useState(false);
  const [questions, setQuestions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingQuestions, setLoadingQuestions] = useState(false);
  const [answers, setAnswers] = useState({});
  const [activeQuestionIndex, setActiveQuestionIndex] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [submitResult, setSubmitResult] = useState(null);
  const [securityWarnings, setSecurityWarnings] = useState(0);
  const [headMovementWarnings, setHeadMovementWarnings] = useState(0);
  const [securityStatus, setSecurityStatus] = useState("");
  const [attemptEndsAtMs, setAttemptEndsAtMs] = useState(null);
  const [timeLeftSeconds, setTimeLeftSeconds] = useState(null);

  const securityRef = useRef(null);
  const submittingRef = useRef(false);

  const stopSecurityRuntime = () => {
    if (securityRef.current) {
      securityRef.current.stop();
      securityRef.current = null;
    }
  };

  const exitFullscreenSafely = async () => {
    try {
      if (document.fullscreenElement && document.exitFullscreen) {
        await document.exitFullscreen();
      }
    } catch {
      // Ignore fullscreen exit failures.
    }
  };

  const closeExam = async () => {
    stopSecurityRuntime();
    await exitFullscreenSafely();
    setSelectedExam(null);
    setQuestions([]);
    setAnswers({});
    setActiveQuestionIndex(0);
    setSubmitResult(null);
    setSecurityWarnings(0);
    setHeadMovementWarnings(0);
    setSecurityStatus("");
    setAttemptEndsAtMs(null);
    setTimeLeftSeconds(null);
  };

  const closeReview = () => {
    setReviewExam(null);
    setReviewData(null);
    setLoadingReview(false);
  };

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/cbt/exams");
      setExams(res.data?.data || []);
    } catch {
      alert("Failed to load CBT");
    } finally {
      setLoading(false);
    }
  };

  const submitExam = async (submitMode = "manual", violationReason = null) => {
    if (!selectedExam || submittingRef.current) return false;

    setSubmitting(true);
    submittingRef.current = true;
    try {
      const res = await api.post(`/api/student/cbt/exams/${selectedExam.id}/submit`, {
        answers,
        submit_mode: submitMode,
        violation_reason: violationReason || undefined,
        security_warnings: securityWarnings,
        head_movement_warnings: headMovementWarnings,
      });
      setSubmitResult(res.data?.data || null);
      stopSecurityRuntime();
      await exitFullscreenSafely();
      await load();
      setSecurityStatus(
        submitMode === "auto"
          ? "Exam was auto-submitted by policy/timer."
          : "Exam submitted successfully."
      );
      await closeExam();
      return true;
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to submit CBT");
      return false;
    } finally {
      setSubmitting(false);
      submittingRef.current = false;
    }
  };

  const startSecurityRuntime = async (exam) => {
    stopSecurityRuntime();

    const policy = { ...(exam?.security_policy || {}), ...strictSecurityDefaults };
    const runtime = createCbtSecurityFramework(policy, {
      onStatus: ({ message }) => setSecurityStatus(message || ""),
      onWarning: ({ reason, warnings }) => {
        setSecurityWarnings(warnings || 0);
        setSecurityStatus(`Security warning: ${reason}`);
      },
      onHeadMovement: ({ count }) => {
        setHeadMovementWarnings(count || 0);
      },
      onMajorViolation: ({ reason }) => {
        setSecurityStatus(`Major violation detected: ${reason}`);
        submitExam("auto", reason || "major_violation");
      },
    });

    securityRef.current = runtime;
    await runtime.start();
  };

  const openExam = async (exam) => {
    if (exam?.has_taken) {
      alert("You already ended this CBT. Re-entry is not allowed.");
      return;
    }
    if (!exam?.is_open) {
      alert("This CBT is not open yet. Check the exam start time.");
      return;
    }

    setSelectedExam(exam);
    setLoadingQuestions(true);
    try {
      const res = await api.get(`/api/student/cbt/exams/${exam.id}/questions`);
      const list = res.data?.data || [];
      setQuestions(list);
      setAnswers({});
      setActiveQuestionIndex(0);
      setSubmitResult(null);
      setSecurityWarnings(0);
      setHeadMovementWarnings(0);
      setSecurityStatus("");

      const now = Date.now();
      const allowedEndAt = res.data?.attempt?.allowed_end_at ? new Date(res.data.attempt.allowed_end_at).getTime() : null;
      const durationMs = Math.max(1, Number(exam.duration_minutes || 60)) * 60 * 1000;
      const byDuration = now + durationMs;
      const byWindow = exam.ends_at ? new Date(exam.ends_at).getTime() : byDuration;
      const fallbackEndsAt = Number.isFinite(byWindow) ? Math.min(byDuration, byWindow) : byDuration;
      const finalEndsAt = Number.isFinite(allowedEndAt) ? allowedEndAt : fallbackEndsAt;
      setAttemptEndsAtMs(finalEndsAt);
      setTimeLeftSeconds(Math.max(0, Math.floor((finalEndsAt - now) / 1000)));

      try {
        await startSecurityRuntime(exam);
      } catch {
        setSecurityStatus("Security runtime could not fully start. Continue carefully.");
      }
    } catch (err) {
      setQuestions([]);
      alert(err?.response?.data?.message || "Failed to load exam questions");
    } finally {
      setLoadingQuestions(false);
    }
  };

  const openReview = async (exam) => {
    setReviewExam(exam);
    setLoadingReview(true);
    setReviewData(null);
    try {
      const res = await api.get(`/api/student/cbt/exams/${exam.id}/review`);
      setReviewData(res.data?.data || null);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load exam review");
      setReviewExam(null);
    } finally {
      setLoadingReview(false);
    }
  };

  useEffect(() => {
    if (!selectedExam || !attemptEndsAtMs) return undefined;

    const tick = () => {
      const left = Math.max(0, Math.floor((attemptEndsAtMs - Date.now()) / 1000));
      setTimeLeftSeconds(left);
      if (left <= 0 && !submittingRef.current) {
        submitExam("auto");
      }
    };

    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [selectedExam, attemptEndsAtMs]);

  useEffect(() => {
    load();
    return () => {
      stopSecurityRuntime();
    };
  }, []);

  const currentQuestion = questions[activeQuestionIndex] || null;
  const attemptedCount = Object.values(answers).filter((v) => ["A", "B", "C", "D"].includes(String(v))).length;
  const totalCount = questions.length;

  const formatCountdown = (seconds) => {
    if (seconds == null) return "-";
    const s = Math.max(0, Number(seconds));
    const hh = String(Math.floor(s / 3600)).padStart(2, "0");
    const mm = String(Math.floor((s % 3600) / 60)).padStart(2, "0");
    const ss = String(s % 60).padStart(2, "0");
    return `${hh}:${mm}:${ss}`;
  };

  const handleExitExam = async () => {
    if (!selectedExam) return;
    await submitExam("exit");
  };

  return (
    <div className="cbx-page cbx-page--student">
      {!selectedExam && !reviewExam ? (
        <>
          <section className="cbx-hero">
            <div>
              <span className="cbx-pill">Student CBT</span>
              <h2 className="cbx-title">View active computer-based exams</h2>
              <p className="cbx-subtitle">Click Start CBT to begin your exam.</p>
              <div className="cbx-meta">
                <span>{loading ? "Loading..." : `${exams.length} exam${exams.length === 1 ? "" : "s"}`}</span>
              </div>
            </div>

            <div className="cbx-hero-art" aria-hidden="true">
              <div className="cbx-art cbx-art--main">
                <img src={cbtMainArt} alt="" />
              </div>
              <div className="cbx-art cbx-art--resume">
                <img src={cbtResumeArt} alt="" />
              </div>
              <div className="cbx-art cbx-art--profiles">
                <img src={cbtProfilesArt} alt="" />
              </div>
            </div>
          </section>

          <section className="cbx-panel">
            {loading ? <p className="cbx-state cbx-state--loading">Loading CBT exams...</p> : null}
            {!loading && exams.length === 0 ? (
              <p className="cbx-state cbx-state--empty">No published CBT exam for your current class and current term.</p>
            ) : null}

            {!loading && exams.length > 0 ? (
              <div className="cbx-table-wrap">
                <table className="cbx-table">
                  <thead>
                    <tr>
                      <th style={{ width: 70 }}>S/N</th>
                      <th>Title</th>
                      <th>Subject</th>
                      <th>Window</th>
                      <th>Status</th>
                      <th style={{ width: 180 }}>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {exams.map((x, idx) => (
                      <tr key={x.id}>
                        <td>{idx + 1}</td>
                        <td>{x.title}</td>
                        <td>{x.subject_name || "-"}</td>
                        <td>
                          {formatDate(x.starts_at)} - {formatDate(x.ends_at)}
                        </td>
                        <td>{x.has_taken ? `Ended (${x.attempt_status || "submitted"})` : x.is_open ? "Open" : "Closed/Upcoming"}</td>
                        <td>
                          <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                            <button className="cbx-btn cbx-btn--soft" onClick={() => openExam(x)} disabled={!x.can_start}>
                              {x.has_taken ? "Ended" : x.is_open ? "Start CBT" : "Not Open"}
                            </button>
                            {x.has_taken ? (
                              <button className="cbx-btn cbx-btn--soft" onClick={() => openReview(x)}>
                                View Exam
                              </button>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : null}
          </section>
        </>
      ) : selectedExam ? (
        <section className="cbx-panel" style={{ maxWidth: 900, margin: "0 auto" }}>
          <h3 style={{ marginTop: 0 }}>{selectedExam.title}</h3>
          <div style={{ marginBottom: 12, fontWeight: 700, color: "#0f172a" }}>
            Answered {attemptedCount} / {totalCount}
          </div>

          {loadingQuestions ? (
            <p className="cbx-state cbx-state--loading">Loading questions...</p>
          ) : currentQuestion ? (
            <>
              <div className="cbx-table-wrap">
                <table className="cbx-table">
                  <tbody>
                    <tr>
                      <th style={{ width: 130 }}>Question</th>
                      <td>{activeQuestionIndex + 1} of {totalCount}: {currentQuestion.question_text}</td>
                    </tr>
                    {[
                      ["A", currentQuestion.option_a],
                      ["B", currentQuestion.option_b],
                      ["C", currentQuestion.option_c],
                      ["D", currentQuestion.option_d],
                    ].map(([key, text]) => (
                      <tr key={key}>
                        <th>Option {key}</th>
                        <td>
                          <label style={{ display: "flex", alignItems: "center", gap: 8, cursor: "pointer" }}>
                            <input
                              type="radio"
                              name={`question-${currentQuestion.id}`}
                              value={key}
                              checked={answers[currentQuestion.id] === key}
                              onChange={(e) =>
                                setAnswers((prev) => ({ ...prev, [currentQuestion.id]: e.target.value }))
                              }
                            />
                            <span>{text || "-"}</span>
                          </label>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div style={{ marginTop: 12, display: "flex", gap: 8, flexWrap: "wrap" }}>
                <button
                  className="cbx-btn cbx-btn--soft"
                  onClick={() => setActiveQuestionIndex((x) => Math.max(0, x - 1))}
                  disabled={activeQuestionIndex <= 0}
                >
                  Previous
                </button>
                <button
                  className="cbx-btn cbx-btn--soft"
                  onClick={() => setActiveQuestionIndex((x) => Math.min(questions.length - 1, x + 1))}
                  disabled={activeQuestionIndex >= questions.length - 1}
                >
                  Next
                </button>
                <button className="cbx-btn cbx-btn--danger" onClick={handleExitExam} disabled={submitting}>
                  {submitting ? "Ending..." : "Exit"}
                </button>
                <button
                  className="cbx-btn"
                  style={{ marginLeft: "auto" }}
                  onClick={() => submitExam("manual")}
                  disabled={submitting || !questions.length}
                >
                  {submitting ? "Submitting..." : "Submit"}
                </button>
              </div>
            </>
          ) : (
            <p className="cbx-state cbx-state--empty">No questions in this exam yet.</p>
          )}
        </section>
      ) : (
        <section className="cbx-panel" style={{ maxWidth: 980, margin: "0 auto" }}>
          <h3 style={{ marginTop: 0 }}>{reviewExam?.title || "Exam Review"}</h3>
          {loadingReview ? (
            <p className="cbx-state cbx-state--loading">Loading review...</p>
          ) : reviewData ? (
            <>
              <div className="cbx-table-wrap">
                <table className="cbx-table">
                  <thead>
                    <tr>
                      <th>S/N</th>
                      <th>Question</th>
                      <th>Options</th>
                      <th>Your Answer</th>
                      <th>Correct</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(reviewData.questions || []).map((q) => (
                      <tr key={q.id}>
                        <td>{q.sn}</td>
                        <td>{q.question_text}</td>
                        <td>
                          {[
                            ["A", q.option_a],
                            ["B", q.option_b],
                            ["C", q.option_c],
                            ["D", q.option_d],
                          ].map(([key, text]) => {
                            const isCorrect = q.correct_option === key;
                            const isWrongStudent = q.selected_option === key && q.selected_option !== q.correct_option;
                            return (
                              <div
                                key={key}
                                style={{
                                  color: isCorrect ? "#166534" : isWrongStudent ? "#b91c1c" : "inherit",
                                  fontWeight: isCorrect || isWrongStudent ? 700 : 400,
                                }}
                              >
                                {key}. {text || "-"}
                              </div>
                            );
                          })}
                        </td>
                        <td style={{ color: q.selected_option && !q.is_correct ? "#b91c1c" : "inherit", fontWeight: q.selected_option && !q.is_correct ? 700 : 400 }}>
                          {q.selected_option || "-"}
                        </td>
                        <td style={{ color: "#166534", fontWeight: 700 }}>{q.correct_option || "-"}</td>
                      </tr>
                    ))}
                    {!reviewData.questions?.length ? (
                      <tr>
                        <td colSpan="5">No questions to review.</td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
              <div style={{ marginTop: 12, fontWeight: 700 }}>
                Total Score: {reviewData.attempt?.score_percent ?? 0}% ({reviewData.attempt?.correct ?? 0}/{reviewData.attempt?.total_questions ?? 0})
              </div>
              <div style={{ marginTop: 12 }}>
                <button className="cbx-btn cbx-btn--soft" onClick={closeReview}>Back</button>
              </div>
            </>
          ) : (
            <p className="cbx-state cbx-state--empty">No review data found.</p>
          )}
        </section>
      )}
    </div>
  );
}


