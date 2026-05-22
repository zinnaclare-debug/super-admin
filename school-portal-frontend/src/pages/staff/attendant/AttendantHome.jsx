import { useEffect, useMemo, useState } from "react";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import api from "../../../services/api";
import professorArt from "../../../assets/attendant/professor.svg";
import gradingArt from "../../../assets/attendant/grading-papers.svg";
import educatorArt from "../../../assets/attendant/educator.svg";
import "../../shared/Attendant.css";

function formatDateTime(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

function statusText(record) {
  if (!record) return "Not signed today";
  return String(record.status || "present").replaceAll("_", " ");
}

export default function StaffAttendantHome() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [signing, setSigning] = useState(false);
  const [message, setMessage] = useState("");

  const record = data?.record || null;
  const setting = data?.setting || {};
  const stateClass = record ? "att-state--good" : data?.is_blocked ? "att-state--warn" : "att-state--good";
  const canSign = useMemo(() => {
    return !loading && !data?.is_blocked && setting.location_configured && !record;
  }, [data?.is_blocked, loading, record, setting.location_configured]);

  const load = async () => {
    setLoading(true);
    setMessage("");
    try {
      const res = await api.get("/api/staff/attendant/today");
      setData(res.data?.data || null);
    } catch (e) {
      setMessage(e?.response?.data?.message || "Failed to load attendant.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const signIn = async () => {
    if (!navigator.geolocation) {
      alert("Location is not supported on this device/browser.");
      return;
    }

    setSigning(true);
    setMessage("Requesting location permission...");
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        try {
          const res = await api.post("/api/staff/attendant/sign-in", {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy_meters: Math.round(position.coords.accuracy || 0),
            device_info: {
              platform: navigator.platform,
              language: navigator.language,
              screen: `${window.screen?.width || 0}x${window.screen?.height || 0}`,
            },
          });
          setMessage(res.data?.message || "Staff attendance signed successfully.");
          await load();
        } catch (e) {
          setMessage(e?.response?.data?.message || "Sign-in failed.");
        } finally {
          setSigning(false);
        }
      },
      (error) => {
        setSigning(false);
        setMessage(error?.message || "Location permission was denied.");
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
  };

  return (
    <StaffFeatureLayout title="Staff Attendance" showHeader={false}>
      <div className="att-page">
        <section className="att-hero">
          <div>
            <span className="att-pill">Daily Staff Attendance</span>
            <h2 className="att-title">Sign staff attendance with verified location</h2>
            <p className="att-subtitle">
              Sign in once per working day. Your school can verify your attendance time and location from the admin dashboard.
            </p>
            <div className="att-meta">
              <span>{loading ? "Loading..." : data?.today || "Today"}</span>
              <span>{record ? "Signed" : "Not signed"}</span>
              <span>{setting.location_configured ? `${setting.radius_meters}m allowed radius` : "Location not configured"}</span>
            </div>
          </div>
          <div className="att-art" aria-hidden="true">
            <div className="att-art-card att-art-card--main"><img src={professorArt} alt="" /></div>
            <div className="att-art-card att-art-card--small-a"><img src={gradingArt} alt="" /></div>
            <div className="att-art-card att-art-card--small-b"><img src={educatorArt} alt="" /></div>
          </div>
        </section>

        <section className="att-grid">
          <article className="att-panel">
            <h3>Today&apos;s Status</h3>
            {loading ? <p className="att-state att-state--warn">Loading attendant status...</p> : null}
            {!loading ? (
              <>
                <p className={`att-state ${stateClass}`}>
                  {record ? `Signed: ${statusText(record)}` : data?.is_blocked ? data?.blocked_reason : "Ready to sign attendance"}
                </p>
                {message ? <p className="att-small">{message}</p> : null}
                {!setting.location_configured ? (
                  <p className="att-state att-state--bad" style={{ marginTop: 10 }}>
                    School admin must configure the school location before staff can sign.
                  </p>
                ) : null}
                <div style={{ marginTop: 14 }}>
                  <button className="att-btn" onClick={signIn} disabled={!canSign || signing}>
                    {signing ? "Signing..." : record ? "Already Signed Today" : "Sign Staff Attendance"}
                  </button>
                </div>
              </>
            ) : null}
          </article>

          <article className="att-panel">
            <h3>Sign-in Details</h3>
            <div className="att-card">
              <p className="att-small"><strong>Time:</strong> {formatDateTime(record?.signed_in_at)}</p>
              <p className="att-small"><strong>Status:</strong> {statusText(record)}</p>
            </div>
          </article>
        </section>
      </div>
    </StaffFeatureLayout>
  );
}
