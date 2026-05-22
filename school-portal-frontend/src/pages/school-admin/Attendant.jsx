import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";
import professorArt from "../../assets/attendant/professor.svg";
import gradingArt from "../../assets/attendant/grading-papers.svg";
import educatorArt from "../../assets/attendant/educator.svg";
import "../shared/Attendant.css";

const DAYS = [
  { value: 1, label: "Mon" },
  { value: 2, label: "Tue" },
  { value: 3, label: "Wed" },
  { value: 4, label: "Thu" },
  { value: 5, label: "Fri" },
  { value: 6, label: "Sat" },
  { value: 7, label: "Sun" },
];

const today = new Date().toISOString().slice(0, 10);

function compactDateTime(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

function statusLabel(value) {
  return String(value || "present").replaceAll("_", " ");
}

function formatHolidayDate(value) {
  if (!value) return "-";
  const raw = String(value);
  const datePart = raw.includes("T") ? raw.split("T")[0] : raw;
  const [year, month, day] = datePart.split("-");
  if (!year || !month || !day) return raw;
  return new Date(Number(year), Number(month) - 1, Number(day)).toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function emptySetting() {
  return {
    latitude: "",
    longitude: "",
    radius_meters: 150,
    timezone: "Africa/Lagos",
    working_days: [1, 2, 3, 4, 5],
    sign_in_start_time: "",
    sign_in_end_time: "",
    late_after_time: "",
    allow_outside_location: false,
  };
}

export default function SchoolAdminAttendant() {
  const [setting, setSetting] = useState(emptySetting());
  const [staff, setStaff] = useState([]);
  const [records, setRecords] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [holidays, setHolidays] = useState([]);
  const [filters, setFilters] = useState({
    date_from: today,
    date_to: today,
    staff_user_id: "",
    status: "",
  });
  const [holidayForm, setHolidayForm] = useState({ holiday_date: today, title: "", description: "" });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [recordsLoading, setRecordsLoading] = useState(false);
  const [holidaySaving, setHolidaySaving] = useState(false);
  const [message, setMessage] = useState("");

  const locationConfigured = setting.latitude !== "" && setting.latitude !== null && setting.longitude !== "" && setting.longitude !== null;
  const signedCount = useMemo(() => records.length, [records.length]);

  const normalizeSetting = (nextSetting) => ({
    ...emptySetting(),
    ...(nextSetting || {}),
    latitude: nextSetting?.latitude ?? "",
    longitude: nextSetting?.longitude ?? "",
    sign_in_start_time: nextSetting?.sign_in_start_time?.slice(0, 5) || "",
    sign_in_end_time: nextSetting?.sign_in_end_time?.slice(0, 5) || "",
    late_after_time: nextSetting?.late_after_time?.slice(0, 5) || "",
    working_days: Array.isArray(nextSetting?.working_days) && nextSetting.working_days.length
      ? nextSetting.working_days.map(Number)
      : [1, 2, 3, 4, 5],
    allow_outside_location: Boolean(nextSetting?.allow_outside_location),
  });

  const loadContext = async () => {
    setLoading(true);
    setMessage("");
    try {
      const res = await api.get("/api/school-admin/attendant/context");
      const data = res.data?.data || {};
      setSetting(normalizeSetting(data.setting));
      setStaff(data.staff || []);
    } catch (e) {
      setMessage(e?.response?.data?.message || "Failed to load attendant settings.");
    } finally {
      setLoading(false);
    }
  };

  const loadRecords = async () => {
    setRecordsLoading(true);
    try {
      const res = await api.get("/api/school-admin/attendant/records", { params: filters });
      setRecords(res.data?.data?.records || []);
      setPagination(res.data?.data?.pagination || null);
    } catch (e) {
      setMessage(e?.response?.data?.message || "Failed to load attendant records.");
    } finally {
      setRecordsLoading(false);
    }
  };

  const loadHolidays = async () => {
    try {
      const res = await api.get("/api/school-admin/attendant/holidays");
      setHolidays(res.data?.data || []);
    } catch (e) {
      setMessage(e?.response?.data?.message || "Failed to load public holidays.");
    }
  };

  useEffect(() => {
    loadContext();
    loadHolidays();
  }, []);

  useEffect(() => {
    loadRecords();
  }, [filters.date_from, filters.date_to, filters.staff_user_id, filters.status]);

  const updateSetting = (key, value) => {
    setSetting((current) => ({ ...current, [key]: value }));
  };

  const toggleDay = (day) => {
    setSetting((current) => {
      const days = new Set((current.working_days || []).map(Number));
      if (days.has(day)) {
        days.delete(day);
      } else {
        days.add(day);
      }
      return { ...current, working_days: Array.from(days).sort((a, b) => a - b) };
    });
  };

  const useCurrentLocation = () => {
    if (!navigator.geolocation) {
      alert("Location is not supported by this device/browser.");
      return;
    }

    setMessage("Requesting this device location...");
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setSetting((current) => ({
          ...current,
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        }));
        setMessage("Location captured. Save settings to activate it.");
      },
      (error) => setMessage(error?.message || "Could not read your current location."),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
  };

  const saveSettings = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage("");
    try {
      const payload = {
        ...setting,
        latitude: setting.latitude === "" ? null : Number(setting.latitude),
        longitude: setting.longitude === "" ? null : Number(setting.longitude),
        radius_meters: Number(setting.radius_meters || 150),
        working_days: setting.working_days || [1, 2, 3, 4, 5],
        sign_in_start_time: setting.sign_in_start_time || null,
        sign_in_end_time: setting.sign_in_end_time || null,
        late_after_time: setting.late_after_time || null,
        timezone: setting.timezone || "Africa/Lagos",
        allow_outside_location: Boolean(setting.allow_outside_location),
      };
      const res = await api.put("/api/school-admin/attendant/settings", payload);
      setSetting(normalizeSetting(res.data?.data?.setting));
      setMessage(res.data?.message || "Staff attendance settings saved.");
    } catch (e) {
      setMessage(e?.response?.data?.message || "Failed to save attendant settings.");
    } finally {
      setSaving(false);
    }
  };

  const saveHoliday = async (e) => {
    e.preventDefault();
    setHolidaySaving(true);
    setMessage("");
    try {
      await api.post("/api/school-admin/attendant/holidays", holidayForm);
      setHolidayForm({ holiday_date: today, title: "", description: "" });
      await loadHolidays();
      setMessage("Public holiday saved.");
    } catch (e) {
      setMessage(e?.response?.data?.message || "Failed to save public holiday.");
    } finally {
      setHolidaySaving(false);
    }
  };

  const deleteHoliday = async (holiday) => {
    if (!window.confirm(`Delete public holiday "${holiday.title}"?`)) return;
    try {
      await api.delete(`/api/school-admin/attendant/holidays/${holiday.id}`);
      await loadHolidays();
      setMessage("Public holiday deleted.");
    } catch (e) {
      setMessage(e?.response?.data?.message || "Failed to delete public holiday.");
    }
  };

  return (
    <div className="att-page">
      <section className="att-hero">
        <div>
          <span className="att-pill">School Admin Staff Attendance</span>
          <h2 className="att-title">Track staff attendance with location proof</h2>
          <p className="att-subtitle">
            Configure the school location, choose working days, add public holidays, and review where each staff member signed in from.
          </p>
          <div className="att-meta">
            <span>{staff.length} active staff</span>
            <span>{locationConfigured ? "Location configured" : "Location not configured"}</span>
            <span>{signedCount} record{signedCount === 1 ? "" : "s"} in view</span>
          </div>
        </div>
        <div className="att-art" aria-hidden="true">
          <div className="att-art-card att-art-card--main"><img src={professorArt} alt="" /></div>
          <div className="att-art-card att-art-card--small-a"><img src={gradingArt} alt="" /></div>
          <div className="att-art-card att-art-card--small-b"><img src={educatorArt} alt="" /></div>
        </div>
      </section>

      {message ? <p className="att-state att-state--warn">{message}</p> : null}

      <section className="att-grid">
        <form className="att-panel" onSubmit={saveSettings}>
          <h3>Location & Rules</h3>
          {loading ? <p className="att-small">Loading settings...</p> : null}
          <div className="att-grid" style={{ marginTop: 10 }}>
            <label className="att-label">
              Latitude
              <input className="att-field" value={setting.latitude} onChange={(e) => updateSetting("latitude", e.target.value)} placeholder="9.0765" />
            </label>
            <label className="att-label">
              Longitude
              <input className="att-field" value={setting.longitude} onChange={(e) => updateSetting("longitude", e.target.value)} placeholder="7.3986" />
            </label>
          </div>
          <div className="att-grid" style={{ marginTop: 10 }}>
            <label className="att-label">
              Allowed Radius (meters)
              <input className="att-field" type="number" min="20" max="5000" value={setting.radius_meters} onChange={(e) => updateSetting("radius_meters", e.target.value)} />
            </label>
            <label className="att-label">
              Timezone
              <input className="att-field" value={setting.timezone} onChange={(e) => updateSetting("timezone", e.target.value)} />
            </label>
          </div>
          <div className="att-grid att-grid--three" style={{ marginTop: 10 }}>
            <label className="att-label">
              Sign-in Opens
              <input className="att-field" type="time" value={setting.sign_in_start_time} onChange={(e) => updateSetting("sign_in_start_time", e.target.value)} />
            </label>
            <label className="att-label">
              Late After
              <input className="att-field" type="time" value={setting.late_after_time} onChange={(e) => updateSetting("late_after_time", e.target.value)} />
            </label>
            <label className="att-label">
              Sign-in Closes
              <input className="att-field" type="time" value={setting.sign_in_end_time} onChange={(e) => updateSetting("sign_in_end_time", e.target.value)} />
            </label>
          </div>
          <div style={{ marginTop: 12 }}>
            <p className="att-small"><strong>Working days</strong></p>
            <div className="att-check-row">
              {DAYS.map((day) => (
                <label className="att-check" key={day.value}>
                  <input type="checkbox" checked={(setting.working_days || []).map(Number).includes(day.value)} onChange={() => toggleDay(day.value)} />
                  {day.label}
                </label>
              ))}
            </div>
          </div>
          <label className="att-check" style={{ marginTop: 12 }}>
            <input type="checkbox" checked={Boolean(setting.allow_outside_location)} onChange={(e) => updateSetting("allow_outside_location", e.target.checked)} />
            Allow sign-in outside school radius but mark it out of range
          </label>
          <div className="att-filter-row" style={{ marginTop: 14 }}>
            <button className="att-btn att-btn--soft" type="button" onClick={useCurrentLocation}>Use This Device Location</button>
            <button className="att-btn" type="submit" disabled={saving}>{saving ? "Saving..." : "Save Staff Attendance Settings"}</button>
          </div>
        </form>

        <section className="att-panel">
          <h3>Public Holidays</h3>
          <form onSubmit={saveHoliday} className="att-card">
            <label className="att-label">
              Holiday Date
              <input className="att-field" type="date" value={holidayForm.holiday_date} onChange={(e) => setHolidayForm((current) => ({ ...current, holiday_date: e.target.value }))} required />
            </label>
            <label className="att-label" style={{ marginTop: 10 }}>
              Title
              <input className="att-field" value={holidayForm.title} onChange={(e) => setHolidayForm((current) => ({ ...current, title: e.target.value }))} placeholder="Public holiday" required />
            </label>
            <label className="att-label" style={{ marginTop: 10 }}>
              Description
              <textarea className="att-field" rows="2" value={holidayForm.description} onChange={(e) => setHolidayForm((current) => ({ ...current, description: e.target.value }))} placeholder="Optional note" />
            </label>
            <button className="att-btn" type="submit" disabled={holidaySaving} style={{ marginTop: 10 }}>
              {holidaySaving ? "Saving..." : "Add Holiday"}
            </button>
          </form>
          <div style={{ marginTop: 12 }}>
            {holidays.map((holiday) => (
              <div className="att-card" key={holiday.id} style={{ marginBottom: 8 }}>
                <p className="att-small" style={{ marginBottom: 2 }}>
                  <strong>{formatHolidayDate(holiday.holiday_date)}</strong> - {holiday.title}
                </p>
                {holiday.description ? <p className="att-small" style={{ marginTop: 0 }}>{holiday.description}</p> : null}
                <button className="att-btn att-btn--danger att-btn--tiny" type="button" onClick={() => deleteHoliday(holiday)}>Delete</button>
              </div>
            ))}
            {holidays.length === 0 ? <p className="att-small">No public holiday added yet.</p> : null}
          </div>
        </section>
      </section>

      <section className="att-panel">
        <h3>Staff Attendance Records</h3>
        <div className="att-filter-row">
          <input className="att-field" style={{ maxWidth: 170 }} type="date" value={filters.date_from} onChange={(e) => setFilters((current) => ({ ...current, date_from: e.target.value }))} />
          <input className="att-field" style={{ maxWidth: 170 }} type="date" value={filters.date_to} onChange={(e) => setFilters((current) => ({ ...current, date_to: e.target.value }))} />
          <select className="att-select" style={{ maxWidth: 240 }} value={filters.staff_user_id} onChange={(e) => setFilters((current) => ({ ...current, staff_user_id: e.target.value }))}>
            <option value="">All Staff</option>
            {staff.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <select className="att-select" style={{ maxWidth: 190 }} value={filters.status} onChange={(e) => setFilters((current) => ({ ...current, status: e.target.value }))}>
            <option value="">All Status</option>
            <option value="present">Present</option>
            <option value="late">Late</option>
            <option value="out_of_range">Out of Range</option>
          </select>
          <button className="att-btn att-btn--soft" type="button" onClick={loadRecords}>Refresh</button>
        </div>

        <div className="att-table-wrap" style={{ marginTop: 14 }}>
          <table className="att-table">
            <thead>
              <tr>
                <th>Staff</th>
                <th>Date</th>
                <th>Signed In</th>
                <th>Status</th>
                <th>Location</th>
                <th>Device</th>
                <th>Map</th>
              </tr>
            </thead>
            <tbody>
              {records.map((record) => (
                <tr key={record.id}>
                  <td>
                    <strong>{record.staff_user?.name || "Staff"}</strong>
                    <p className="att-small">{record.staff_user?.email || record.staff_user?.username || "-"}</p>
                  </td>
                  <td>{record.attendance_date}</td>
                  <td>{compactDateTime(record.signed_in_at)}</td>
                  <td><span className="att-badge">{statusLabel(record.status)}</span></td>
                  <td>
                    <p className="att-small">{statusLabel(record.location_status)}</p>
                    <p className="att-small">{record.distance_from_school_meters ?? "-"}m from school</p>
                    <p className="att-small">Accuracy: {record.accuracy_meters ?? "-"}m</p>
                  </td>
                  <td>
                    <p className="att-small">{record.ip_address || "-"}</p>
                    <p className="att-small">{record.device_info?.platform || "-"}</p>
                  </td>
                  <td>
                    {record.latitude && record.longitude ? (
                      <a className="att-btn att-btn--soft" href={`https://www.google.com/maps?q=${record.latitude},${record.longitude}`} target="_blank" rel="noreferrer">Open Map</a>
                    ) : "-"}
                  </td>
                </tr>
              ))}
              {!recordsLoading && records.length === 0 ? (
                <tr>
                  <td colSpan="7">No attendant record found for this filter.</td>
                </tr>
              ) : null}
              {recordsLoading ? (
                <tr>
                  <td colSpan="7">Loading attendant records...</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        {pagination ? (
          <p className="att-small" style={{ marginTop: 10 }}>
            Showing {records.length} of {pagination.total} record{Number(pagination.total) === 1 ? "" : "s"}.
          </p>
        ) : null}
      </section>
    </div>
  );
}
