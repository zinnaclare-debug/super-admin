/*
  CBT runtime security scaffold.
  Use this in the student CBT test-taking page.
  This is a best-effort browser layer, not a full secure browser.
*/

export function createCbtSecurityFramework(policy, callbacks = {}) {
  const state = {
    warnings: 0,
    noFaceSeconds: 0,
    headMovementWarnings: 0,
    running: false,
    timers: [],
    stream: null,
    lastFaceCenter: null,
    lastHeadMovementAt: 0,
  };

  const onWarning = callbacks.onWarning || (() => {});
  const onMajorViolation = callbacks.onMajorViolation || (() => {});
  const onStatus = callbacks.onStatus || (() => {});
  const onHeadMovement = callbacks.onHeadMovement || (() => {});

  const maxWarnings = policy?.max_warnings ?? 3;
  const noFaceTimeout = policy?.no_face_timeout_seconds ?? 30;
  const maxHeadMovements = policy?.max_head_movement_warnings ?? 2;
  const headMovementThresholdPx = policy?.head_movement_threshold_px ?? 60;

  const warn = (reason) => {
    state.warnings += 1;
    onWarning({ reason, warnings: state.warnings });
    if (state.warnings >= maxWarnings) {
      onMajorViolation({ reason, warnings: state.warnings });
    }
  };

  const blockEvent = (e) => {
    e.preventDefault();
    e.stopPropagation();
    return false;
  };

  const keydownHandler = (e) => {
    const k = (e.key || "").toLowerCase();
    const isCopy = (e.ctrlKey || e.metaKey) && ["c", "x", "v", "a", "p", "s"].includes(k);
    const isPrintScreen = k === "printscreen";
    const isDevTools = e.key === "F12" || ((e.ctrlKey || e.metaKey) && e.shiftKey && ["i", "j", "c"].includes(k));
    if (policy?.block_copy_paste && (isCopy || isPrintScreen || isDevTools)) {
      warn("blocked_key_action");
      return blockEvent(e);
    }
    return true;
  };

  const visibilityHandler = () => {
    if (policy?.block_tab_switch && document.hidden) {
      warn("tab_switch");
    }
  };

  const beforeUnloadHandler = (e) => {
    warn("navigation_attempt");
    e.preventDefault();
    e.returnValue = "";
    return "";
  };

  const popStateHandler = () => {
    warn("navigation_attempt");
  };

  const linkClickHandler = (e) => {
    const anchor = e.target?.closest?.("a[href]");
    if (!anchor) return;
    const href = anchor.getAttribute("href") || "";
    if (!href || href.startsWith("#")) return;
    warn("external_navigation_attempt");
    e.preventDefault();
    e.stopPropagation();
  };

  const fullscreenHandler = () => {
    if (policy?.fullscreen_required && !document.fullscreenElement) {
      warn("fullscreen_exit");
    }
  };

  async function enableWebcamChecks() {
    if (!policy?.ai_proctoring_enabled) return;
    if (!navigator.mediaDevices?.getUserMedia) return;

    try {
      state.stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
      const video = document.createElement("video");
      video.srcObject = state.stream;
      video.muted = true;
      await video.play();

      const faceDetector = "FaceDetector" in window ? new window.FaceDetector({ maxDetectedFaces: 2 }) : null;
      if (!faceDetector) {
        onStatus({ type: "proctoring", message: "FaceDetector API not available; AI checks disabled." });
        return;
      }

      const timer = setInterval(async () => {
        if (!state.running) return;
        try {
          const faces = await faceDetector.detect(video);
          if (!faces.length) {
            state.noFaceSeconds += 2;
            state.lastFaceCenter = null;
            if (state.noFaceSeconds >= noFaceTimeout) {
              warn("no_face_detected");
              state.noFaceSeconds = 0;
            }
          } else {
            state.noFaceSeconds = 0;
            if (faces.length > 1) {
              warn("multiple_faces_detected");
            }

            // Head movement guard (best effort): if face center jumps repeatedly, treat as violation.
            const box = faces[0]?.boundingBox;
            if (box) {
              const center = {
                x: box.x + box.width / 2,
                y: box.y + box.height / 2,
              };

              if (state.lastFaceCenter) {
                const dx = center.x - state.lastFaceCenter.x;
                const dy = center.y - state.lastFaceCenter.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const now = Date.now();

                if (distance >= headMovementThresholdPx && now - state.lastHeadMovementAt >= 1500) {
                  state.headMovementWarnings += 1;
                  state.lastHeadMovementAt = now;
                  onHeadMovement({ count: state.headMovementWarnings, distance });
                  warn("head_movement_detected");

                  if (state.headMovementWarnings >= maxHeadMovements) {
                    onMajorViolation({
                      reason: "head_movement_limit_exceeded",
                      warnings: state.warnings,
                      head_movements: state.headMovementWarnings,
                    });
                  }
                }
              }

              state.lastFaceCenter = center;
            }
          }
        } catch {
          // ignore detector frame errors
        }
      }, 2000);

      state.timers.push(timer);
    } catch {
      warn("webcam_access_denied");
    }
  }

  async function enterFullscreen() {
    if (!policy?.fullscreen_required) return;
    const el = document.documentElement;
    if (!document.fullscreenElement && el.requestFullscreen) {
      await el.requestFullscreen();
    }
  }

  async function start() {
    state.running = true;
    window.addEventListener("keydown", keydownHandler, true);
    document.addEventListener("copy", blockEvent, true);
    document.addEventListener("cut", blockEvent, true);
    document.addEventListener("paste", blockEvent, true);
    document.addEventListener("contextmenu", blockEvent, true);
    document.addEventListener("visibilitychange", visibilityHandler, true);
    document.addEventListener("fullscreenchange", fullscreenHandler, true);
    window.addEventListener("beforeunload", beforeUnloadHandler, true);
    window.addEventListener("popstate", popStateHandler, true);
    document.addEventListener("click", linkClickHandler, true);
    await enterFullscreen();
    await enableWebcamChecks();
    onStatus({ type: "security", message: "CBT security started." });
  }

  function stop() {
    state.running = false;
    window.removeEventListener("keydown", keydownHandler, true);
    document.removeEventListener("copy", blockEvent, true);
    document.removeEventListener("cut", blockEvent, true);
    document.removeEventListener("paste", blockEvent, true);
    document.removeEventListener("contextmenu", blockEvent, true);
    document.removeEventListener("visibilitychange", visibilityHandler, true);
    document.removeEventListener("fullscreenchange", fullscreenHandler, true);
    window.removeEventListener("beforeunload", beforeUnloadHandler, true);
    window.removeEventListener("popstate", popStateHandler, true);
    document.removeEventListener("click", linkClickHandler, true);
    state.timers.forEach(clearInterval);
    state.timers = [];
    state.lastFaceCenter = null;
    state.lastHeadMovementAt = 0;
    if (state.stream) {
      state.stream.getTracks().forEach((t) => t.stop());
      state.stream = null;
    }
  }

  return {
    start,
    stop,
  };
}
