import { useEffect, useState } from "react";
import { isMobileBuild } from "../services/api";

export default function PwaInstallPrompt() {
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (isMobileBuild || typeof window === "undefined") return undefined;
    const capturePrompt = (event) => { event.preventDefault(); setDeferredPrompt(event); };
    const requestPrompt = () => setOpen(true);
    window.addEventListener("beforeinstallprompt", capturePrompt);
    window.addEventListener("request-pwa-install", requestPrompt);
    return () => { window.removeEventListener("beforeinstallprompt", capturePrompt); window.removeEventListener("request-pwa-install", requestPrompt); };
  }, [deferredPrompt]);

  useEffect(() => {
    if (!open) return undefined;
    const timer = window.setTimeout(() => setOpen(false), 10000);
    return () => window.clearTimeout(timer);
  }, [open]);

  const install = async () => {
    if (!deferredPrompt) {
      window.alert("Chrome has not enabled the install dialog yet. Open the Chrome menu (three dots) and choose Install app.");
      return;
    }
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    setDeferredPrompt(null);
    setOpen(false);
  };

  if (!open) return null;
  return <div role="dialog" aria-live="polite" style={{ position: "fixed", left: 18, top: 18, zIndex: 9999, maxWidth: 330, padding: 16, borderRadius: 14, color: "#fff", background: "#082f49", boxShadow: "0 18px 42px rgba(8,47,73,.35)" }}>
    <strong>Install School Portal</strong>
    <p style={{ margin: "7px 0 12px", fontSize: 14 }}>Add this portal to your device for quick app-like access.</p>
    <button type="button" onClick={install} style={{ marginRight: 8, background: "#fbbf24", color: "#111827", border: 0, borderRadius: 8, padding: "8px 12px", fontWeight: 700 }}>Download app</button>
    <button type="button" onClick={() => setOpen(false)} style={{ background: "transparent", color: "#fff", border: "1px solid rgba(255,255,255,.55)", borderRadius: 8, padding: "7px 11px" }}>Not now</button>
  </div>;
}
