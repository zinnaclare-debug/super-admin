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
    videoStream: null,
    audioStream: null,
    audioContext: null,
    lastFaceCenter: null,
    lastHeadMovementAt: 0,
    soundSeconds: 0,
  };

  const onWarning = callbacks.onWarning || (() => {});
  const onMajorViolation = callbacks.onMajorViolation || (() => {});
  const onStatus = callbacks.onStatus || (() => {});
  const onHeadMovement = callbacks.onHeadMovement || (() => {});

  const maxWarnings = Math.min(Number(policy?.max_warnings ?? 2), 2);
  const noFaceTimeout = policy?.no_face_timeout_seconds ?? 30;
  const maxHeadMovements = policy?.max_head_movement_warnings ?? 2;
  const headMovementThresholdPx = policy?.head_movement_threshold_px ?? 60;
  const soundThreshold = Number(policy?.sound_threshold ?? 0.12);
  const soundDuration = Number(policy?.sound_duration_seconds ?? 2);

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

  const copyPasteHandler = (e) => {
    if (!policy?.block_copy_paste) return true;
    warn("blocked_copy_paste_action");
    return blockEvent(e);
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
      if (policy?.auto_submit_on_violation || policy?.auto_submit_on_fullscreen_exit) {
        onMajorViolation({ reason: "fullscreen_exit", warnings: state.warnings });
      } else {
        warn("fullscreen_exit");
      }
    }
  };

  async function enableWebcamChecks() {
    if (!policy?.ai_proctoring_enabled) return;
    if (!navigator.mediaDevices?.getUserMedia) return;

    try {
      state.videoStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
      const video = document.createElement("video");
      video.srcObject = state.videoStream;
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
              if (policy?.auto_submit_on_violation || policy?.auto_submit_on_multiple_faces) {
                onMajorViolation({ reason: "multiple_faces_detected", warnings: state.warnings });
              }
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
      if (policy?.auto_submit_on_violation || policy?.auto_submit_on_camera_blocked) {
        onMajorViolation({ reason: "webcam_access_denied", warnings: state.warnings });
      } else {
        warn("webcam_access_denied");
      }
    }
  }

  async function enableSoundChecks() {
    if (!policy?.sound_detection_enabled) return;
    if (!navigator.mediaDevices?.getUserMedia) return;
    if (!window.AudioContext && !window.webkitAudioContext) return;

    try {
      state.audioStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      state.audioContext = new AudioCtx();
      const source = state.audioContext.createMediaStreamSource(state.audioStream);
      const analyser = state.audioContext.createAnalyser();
      analyser.fftSize = 512;
      source.connect(analyser);

      const data = new Uint8Array(analyser.fftSize);
      const timer = setInterval(() => {
        if (!state.running) return;
        analyser.getByteTimeDomainData(data);

        let sum = 0;
        for (let i = 0; i < data.length; i += 1) {
          const normalized = (data[i] - 128) / 128;
          sum += normalized * normalized;
        }
        const rms = Math.sqrt(sum / data.length);

        if (rms >= soundThreshold) {
          state.soundSeconds += 1;
          onStatus({ type: "sound", message: "Sound detected during CBT." });
          if (state.soundSeconds >= soundDuration) {
            warn("sound_detected");
            state.soundSeconds = 0;
            if (policy?.auto_submit_on_violation || policy?.auto_submit_on_sound_detected) {
              onMajorViolation({ reason: "sound_detected", warnings: state.warnings });
            }
          }
        } else {
          state.soundSeconds = 0;
        }
      }, 1000);

      state.timers.push(timer);
    } catch {
      onStatus({ type: "sound", message: "Microphone check could not start; sound detection disabled." });
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
    document.addEventListener("copy", copyPasteHandler, true);
    document.addEventListener("cut", copyPasteHandler, true);
    document.addEventListener("paste", copyPasteHandler, true);
    document.addEventListener("contextmenu", copyPasteHandler, true);
    document.addEventListener("visibilitychange", visibilityHandler, true);
    document.addEventListener("fullscreenchange", fullscreenHandler, true);
    window.addEventListener("beforeunload", beforeUnloadHandler, true);
    window.addEventListener("popstate", popStateHandler, true);
    document.addEventListener("click", linkClickHandler, true);
    await enterFullscreen();
    await enableWebcamChecks();
    await enableSoundChecks();
    onStatus({ type: "security", message: "CBT security started." });
  }

  function stop() {
    state.running = false;
    window.removeEventListener("keydown", keydownHandler, true);
    document.removeEventListener("copy", copyPasteHandler, true);
    document.removeEventListener("cut", copyPasteHandler, true);
    document.removeEventListener("paste", copyPasteHandler, true);
    document.removeEventListener("contextmenu", copyPasteHandler, true);
    document.removeEventListener("visibilitychange", visibilityHandler, true);
    document.removeEventListener("fullscreenchange", fullscreenHandler, true);
    window.removeEventListener("beforeunload", beforeUnloadHandler, true);
    window.removeEventListener("popstate", popStateHandler, true);
    document.removeEventListener("click", linkClickHandler, true);
    state.timers.forEach(clearInterval);
    state.timers = [];
    state.lastFaceCenter = null;
    state.lastHeadMovementAt = 0;
    state.soundSeconds = 0;
    if (state.videoStream) {
      state.videoStream.getTracks().forEach((t) => t.stop());
      state.videoStream = null;
    }
    if (state.audioStream) {
      state.audioStream.getTracks().forEach((t) => t.stop());
      state.audioStream = null;
    }
    if (state.audioContext) {
      state.audioContext.close?.();
      state.audioContext = null;
    }
  }

  return {
    start,
    stop,
  };
}

