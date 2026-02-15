# CBT Security Protocol (Framework v1)

This project now includes a browser-side CBT security framework scaffold in:
`school-portal-frontend/src/utils/cbtSecurityFramework.js`

## Implemented Controls (Framework)

- Fullscreen enforcement with violation tracking.
- Copy/cut/paste/context-menu blocking hooks.
- Tab/background detection via `visibilitychange`.
- Webcam permission + face-presence checks (FaceDetector API when available).
- Warning counters with auto major-violation callbacks.
- Exam-level security policy persisted per CBT exam in `cbt_exams.security_policy`.

## Important Limits

Browser-only controls are best-effort. They cannot fully lock OS-level actions on unmanaged devices.
For high-stakes exams, combine this with kiosk/lockdown clients and human/AI proctoring.

## Recommended Hardening Next

- Add student exam runtime page that consumes `security_policy` and enforces framework start/stop.
- Server-side violation log table and auto-submit endpoint for major violations.
- Integrate a dedicated proctoring model/service for head pose and gaze.
- Require MFA for high-risk exam access.
- Offer on-site kiosk mode or Safe Exam Browser for high-stakes scenarios.

## References (Current Standards/Primary Docs)

- MDN Page Visibility API:
  https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API
- MDN `Document.visibilityState`:
  https://developer.mozilla.org/en-US/docs/Web/API/Document/visibilityState
- MDN Fullscreen `requestFullscreen()`:
  https://developer.mozilla.org/en-US/docs/Web/API/Element/requestFullscreen
- MDN Fullscreen Guide:
  https://developer.mozilla.org/en-US/docs/Web/API/Fullscreen_API/Guide
- MDN Clipboard events (`copy`, `cut`, `paste`):
  https://developer.mozilla.org/en-US/docs/Web/API/Element/copy_event
  https://developer.mozilla.org/en-US/docs/Web/API/Element/cut_event
  https://developer.mozilla.org/en-US/docs/Web/API/Element/paste_event
- W3C Screen Capture API (security/privacy considerations):
  https://www.w3.org/TR/screen-capture/
- NIST SP 800-63B (phishing resistance/MFA guidance):
  https://pages.nist.gov/800-63-4/sp800-63b.html
- Safe Exam Browser project overview:
  https://www.safeexambrowser.org/about_overview_en.html

