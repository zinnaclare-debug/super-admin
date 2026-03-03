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
    ai_proctoring_enabled: true,
    max_warnings: 3,
    no_face_timeout_seconds: 30,
    max_head_movement_warnings: 2,
    head_movement_threshold_px: 60,
  };

  const [exams, setExams] = useState([]);
  const [selectedExam, setSelectedExam] = useState(null);
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
    if (!selectedExam || submittingRef.current || submitResult) return false;

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
        if (
          ["tab_switch", "fullscreen_exit", "external_navigation_attempt", "navigation_attempt"].includes(
            String(reason)
          )
        ) {
          submitExam("auto", String(reason));
        }
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

  useEffect(() => {
    if (!selectedExam || !attemptEndsAtMs || submitResult) return undefined;

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
  }, [selectedExam, attemptEndsAtMs, submitResult]);

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
    const ok = await submitExam("exit");
    if (ok) {
      await closeExam();
    }
  };

  return (
    <div className="cbx-page cbx-page--student">
      <section className="cbx-hero">
        <div>
          <span className="cbx-pill">Student CBT</span>
          <h2 className="cbx-title">View active computer-based exams</h2>
          <p className="cbx-subtitle">
            Enter full-screen exam mode, answer questions, and submit your CBT in one flow.
          </p>
          <div className="cbx-meta">
            <span>{loading ? "Loading..." : `${exams.length} exam${exams.length === 1 ? "" : "s"}`}</span>
            <span>{`${totalCount} question${totalCount === 1 ? "" : "s"} loaded`}</span>
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
                    <td style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                      <button className="cbx-btn cbx-btn--soft" onClick={() => openExam(x)} disabled={!x.can_start}>
                        {x.has_taken ? "Ended" : x.is_open ? "Start CBT" : "Not Open"}
                      </button>
                      {selectedExam && selectedExam.id === x.id ? (
                        <button className="cbx-btn cbx-btn--danger" onClick={handleExitExam} disabled={submitting}>
                          {submitting ? "Ending..." : "Exit"}
                        </button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>

      {selectedExam ? (
        <section className="cbx-panel">
          <h3 style={{ marginTop: 0 }}>{selectedExam.title} - Live CBT</h3>
          <div className="cbx-table-wrap" style={{ marginBottom: 14 }}>
            <table className="cbx-table">
              <tbody>
                <tr>
                  <th style={{ width: 160 }}>Subject</th>
                  <td>{selectedExam.subject_name || "-"}</td>
                  <th style={{ width: 160 }}>Time Left</th>
                  <td>{formatCountdown(timeLeftSeconds)}</td>
                </tr>
                <tr>
                  <th>Answered</th>
                  <td>{attemptedCount} / {totalCount}</td>
                  <th>Security Warnings</th>
                  <td>{securityWarnings}</td>
                </tr>
                <tr>
                  <th>Head Movement Warnings</th>
                  <td>{headMovementWarnings}</td>
                  <th>Policy</th>
                  <td>Exam ends after 2 excessive head movements</td>
                </tr>
                <tr>
                  <th>Security Status</th>
                  <td colSpan="3">{securityStatus || "Active"}</td>
                </tr>
              </tbody>
            </table>
          </div>

          {loadingQuestions ? (
            <p className="cbx-state cbx-state--loading">Loading questions...</p>
          ) : submitResult ? (
            <div className="cbx-table-wrap">
              <table className="cbx-table">
                <tbody>
                  <tr>
                    <th style={{ width: 180 }}>Total Questions</th>
                    <td>{submitResult.total_questions}</td>
                    <th style={{ width: 180 }}>Attempted</th>
                    <td>{submitResult.attempted}</td>
                  </tr>
                  <tr>
                    <th>Correct</th>
                    <td>{submitResult.correct}</td>
                    <th>Wrong</th>
                    <td>{submitResult.wrong}</td>
                  </tr>
                  <tr>
                    <th>Unanswered</th>
                    <td>{submitResult.unanswered}</td>
                    <th>Score (%)</th>
                    <td>{submitResult.score_percent}</td>
                  </tr>
                </tbody>
              </table>
              <div style={{ marginTop: 12 }}>
                <button className="cbx-btn" onClick={closeExam}>Back To Exams</button>
              </div>
            </div>
          ) : (
            <>
              <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: 14 }}>
                {questions.map((q, idx) => {
                  const answered = ["A", "B", "C", "D"].includes(String(answers[q.id] || ""));
                  return (
                    <button
                      key={q.id}
                      className="cbx-btn cbx-btn--soft"
                      style={{
                        background: idx === activeQuestionIndex ? "#1d4ed8" : answered ? "#166534" : undefined,
                        color: idx === activeQuestionIndex || answered ? "#fff" : undefined,
                      }}
                      onClick={() => setActiveQuestionIndex(idx)}
                    >
                      Q{idx + 1}
                    </button>
                  );
                })}
              </div>

              {currentQuestion ? (
                <div className="cbx-table-wrap">
                  <table className="cbx-table">
                    <tbody>
                      <tr>
                        <th style={{ width: 120 }}>Question</th>
                        <td>{activeQuestionIndex + 1}. {currentQuestion.question_text}</td>
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
              ) : (
                <p className="cbx-state cbx-state--empty">No questions in this exam yet.</p>
              )}

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
                <button className="cbx-btn" onClick={() => submitExam("manual")} disabled={submitting || !questions.length}>
                  {submitting ? "Submitting..." : "Submit CBT"}
                </button>
              </div>
            </>
          )}
        </section>
      ) : null}
    </div>
  );
}
