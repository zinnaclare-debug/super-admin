/*
  CBT runtime security scaffold.
  Use this in the student CBT test-taking page.
  This is a best-effort browser layer, not a full secure browser.
*/

import { FaceDetection } from "@mediapipe/face_detection";

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
    majorViolationSent: false,
    faceDetector: null,
    faceDetectionRunning: false,
    faceDetectionFailures: 0,
    darkFrameSeconds: 0,
    lastFaceResultAt: 0,
    videoEl: null,
  };

  const onWarning = callbacks.onWarning || (() => {});
  const onMajorViolation = callbacks.onMajorViolation || (() => {});
  const onStatus = callbacks.onStatus || (() => {});
  const onHeadMovement = callbacks.onHeadMovement || (() => {});

  const maxWarnings = Math.min(Number(policy?.max_warnings ?? 2), 2);
  const configuredNoFaceTimeout = Number(policy?.no_face_timeout_seconds ?? 3);
  const noFaceTimeout =
    policy?.auto_submit_on_violation || policy?.auto_submit_on_no_face
      ? Math.min(Math.max(configuredNoFaceTimeout, 1), 3)
      : Math.max(configuredNoFaceTimeout, 1);
  const maxHeadMovements = policy?.max_head_movement_warnings ?? 2;
  const headMovementThresholdPx = policy?.head_movement_threshold_px ?? 60;
  const soundThreshold = Number(policy?.sound_threshold ?? 0.12);
  const soundDuration = Number(policy?.sound_duration_seconds ?? 2);

  const majorViolation = (reason, extra = {}) => {
    if (state.majorViolationSent) return;
    state.majorViolationSent = true;
    onMajorViolation({ reason, warnings: state.warnings, ...extra });
  };

  const warn = (reason) => {
    state.warnings += 1;
    onWarning({ reason, warnings: state.warnings });
    if (state.warnings >= maxWarnings) {
      majorViolation(reason);
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
        majorViolation("fullscreen_exit");
      } else {
        warn("fullscreen_exit");
      }
    }
  };

  const handleCameraClosed = (reason) => {
    onStatus({ type: "camera", message: "Camera is not active during CBT." });
    if (policy?.auto_submit_on_violation || policy?.auto_submit_on_camera_blocked) {
      majorViolation(reason);
    } else {
      warn(reason);
    }
  };

  const isVideoFrameDark = (video) => {
    if (!video?.videoWidth || !video?.videoHeight) return false;

    try {
      const canvas = document.createElement("canvas");
      canvas.width = 24;
      canvas.height = 16;
      const ctx = canvas.getContext("2d", { willReadFrequently: true });
      if (!ctx) return false;

      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
      let totalBrightness = 0;
      for (let i = 0; i < pixels.length; i += 4) {
        totalBrightness += (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
      }

      const averageBrightness = totalBrightness / (pixels.length / 4);
      return averageBrightness < 8;
    } catch {
      return false;
    }
  };

  const detectionCenter = (detection, video) => {
    const box = detection?.boundingBox || detection?.locationData?.relativeBoundingBox || detection;
    if (!box) return null;

    if (box.xCenter != null && box.yCenter != null) {
      const x = Number(box.xCenter);
      const y = Number(box.yCenter);
      return {
        x: x <= 1 ? x * (video?.videoWidth || 1) : x,
        y: y <= 1 ? y * (video?.videoHeight || 1) : y,
      };
    }

    const x = Number(box.x ?? box.left ?? 0);
    const y = Number(box.y ?? box.top ?? 0);
    const width = Number(box.width ?? 0);
    const height = Number(box.height ?? 0);
    return {
      x: x + width / 2,
      y: y + height / 2,
    };
  };

  const handleFaceDetections = (detections, video) => {
    const faces = Array.isArray(detections) ? detections : [];
    if (!faces.length) {
      state.noFaceSeconds += 1;
      state.lastFaceCenter = null;
      if (state.noFaceSeconds >= noFaceTimeout) {
        if (policy?.auto_submit_on_violation || policy?.auto_submit_on_no_face) {
          majorViolation("no_face_detected");
        } else {
          warn("no_face_detected");
        }
        state.noFaceSeconds = 0;
      }
      return;
    }

    state.noFaceSeconds = 0;
    if (faces.length > 1) {
      warn("multiple_faces_detected");
      if (policy?.auto_submit_on_violation || policy?.auto_submit_on_multiple_faces) {
        majorViolation("multiple_faces_detected");
      }
    }

    const center = detectionCenter(faces[0], video);
    if (!center) return;

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
          majorViolation("head_movement_limit_exceeded", {
            head_movements: state.headMovementWarnings,
          });
        }
      }
    }

    state.lastFaceCenter = center;
  };

  async function enableMediaPipeFaceChecks(video) {
    const detector = new FaceDetection({
      locateFile: (file) => `/mediapipe/face_detection/${file}`,
    });

    detector.setOptions({
      model: "short",
      minDetectionConfidence: Number(policy?.min_face_detection_confidence ?? 0.6),
    });

    detector.onResults((results) => {
      state.lastFaceResultAt = Date.now();
      handleFaceDetections(results?.detections || [], video);
      state.faceDetectionRunning = false;
    });

    state.faceDetector = detector;
    onStatus({ type: "proctoring", message: "MediaPipe AI face detection started." });

    const timer = setInterval(async () => {
      if (!state.running || state.faceDetectionRunning) return;

      const tracks = state.videoStream?.getVideoTracks?.() || [];
      const hasLiveTrack = tracks.some((track) => track.readyState === "live" && track.enabled && !track.muted);
      if (!hasLiveTrack || video.paused || video.ended || video.readyState < 2) {
        handleCameraClosed("camera_not_live");
        return;
      }

      if (isVideoFrameDark(video)) {
        state.darkFrameSeconds += 1;
        onStatus({ type: "camera", message: "Camera is returning a blank video frame." });
        if (state.darkFrameSeconds >= noFaceTimeout) {
          handleCameraClosed("camera_blank_frame");
        }
        return;
      }
      state.darkFrameSeconds = 0;

      state.faceDetectionRunning = true;
      try {
        await Promise.race([
          detector.send({ image: video }),
          new Promise((_, reject) => {
            setTimeout(() => reject(new Error("face_detection_timeout")), 2500);
          }),
        ]);
        state.faceDetectionFailures = 0;
      } catch {
        state.faceDetectionRunning = false;
        state.faceDetectionFailures += 1;
        onStatus({ type: "proctoring", message: "MediaPipe face check failed on this frame." });
        if (state.faceDetectionFailures >= 3) {
          handleCameraClosed("face_detection_failed");
        }
      }
    }, 1000);

    state.timers.push(timer);

    const watchdogTimer = setInterval(() => {
      if (!state.running) return;
      const now = Date.now();
      if (state.lastFaceResultAt && now - state.lastFaceResultAt > 5000) {
        handleCameraClosed("face_detection_stalled");
      }
    }, 1000);
    state.timers.push(watchdogTimer);
  }

  async function enableWebcamChecks() {
    if (!policy?.ai_proctoring_enabled) return;
    if (!navigator.mediaDevices?.getUserMedia) return;

    try {
      state.videoStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
      const videoTracks = state.videoStream.getVideoTracks();
      videoTracks.forEach((track) => {
        track.onended = () => handleCameraClosed("camera_ended");
        track.onmute = () => handleCameraClosed("camera_muted");
      });

      const video = document.createElement("video");
      video.srcObject = state.videoStream;
      video.muted = true;
      video.playsInline = true;
      video.setAttribute("playsinline", "true");
      video.style.cssText = "position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;";
      document.body.appendChild(video);
      state.videoEl = video;
      await video.play();

      const cameraLiveTimer = setInterval(() => {
        if (!state.running) return;
        const tracks = state.videoStream?.getVideoTracks?.() || [];
        const hasLiveTrack = tracks.some((track) => track.readyState === "live" && track.enabled && !track.muted);
        if (!hasLiveTrack) {
          handleCameraClosed("camera_not_live");
        }
      }, 1000);
      state.timers.push(cameraLiveTimer);

      try {
        await enableMediaPipeFaceChecks(video);
        return;
      } catch {
        onStatus({ type: "proctoring", message: "MediaPipe AI face detector failed; using browser fallback." });
      }

      const faceDetector = "FaceDetector" in window ? new window.FaceDetector({ maxDetectedFaces: 2 }) : null;
      if (!faceDetector) {
        onStatus({ type: "proctoring", message: "AI face detector is not available in this browser; camera-live checks remain active." });
        return;
      }

      const timer = setInterval(async () => {
        if (!state.running) return;
        try {
          const faces = await faceDetector.detect(video);
          handleFaceDetections(faces, video);
        } catch {
          // ignore detector frame errors
        }
      }, 2000);

      state.timers.push(timer);
    } catch {
      if (policy?.auto_submit_on_violation || policy?.auto_submit_on_camera_blocked) {
        majorViolation("webcam_access_denied");
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
              majorViolation("sound_detected");
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
    state.darkFrameSeconds = 0;
    state.lastFaceResultAt = 0;
    if (state.videoStream) {
      state.videoStream.getTracks().forEach((t) => t.stop());
      state.videoStream = null;
    }
    if (state.videoEl) {
      state.videoEl.remove();
      state.videoEl = null;
    }
    if (state.audioStream) {
      state.audioStream.getTracks().forEach((t) => t.stop());
      state.audioStream = null;
    }
    if (state.audioContext) {
      state.audioContext.close?.();
      state.audioContext = null;
    }
    if (state.faceDetector) {
      state.faceDetector.close?.();
      state.faceDetector = null;
    }
    state.faceDetectionRunning = false;
  }

  return {
    start,
    stop,
  };
}

