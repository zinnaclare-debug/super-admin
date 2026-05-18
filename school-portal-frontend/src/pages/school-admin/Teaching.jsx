import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";
import newsfeedArt from "../../assets/teaching/newsfeed.svg";
import mobileArt from "../../assets/teaching/mobile-devices.svg";
import readingArt from "../../assets/teaching/reading-book.svg";
import "../shared/Teaching.css";

const CATEGORY_ORDER = ["topic", "exam_question", "lesson_note"];

function bytes(value) {
  const size = Number(value || 0);
  if (!size) return "-";
  if (size < 1024) return `${size} B`;
  return `${(size / 1024).toFixed(1)} KB`;
}

function fileNameFromHeaders(headers, fallback) {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "teaching-material";
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
}

export default function SchoolAdminTeaching() {
  const [context, setContext] = useState(null);
  const [staff, setStaff] = useState([]);
  const [selectedStaff, setSelectedStaff] = useState(null);
  const [materials, setMaterials] = useState([]);
  const [loadingStaff, setLoadingStaff] = useState(true);
  const [loadingMaterials, setLoadingMaterials] = useState(false);
  const [selectedSessionId, setSelectedSessionId] = useState("");
  const [selectedTermId, setSelectedTermId] = useState("");
  const [downloadingId, setDownloadingId] = useState(null);

  const periods = Array.isArray(context?.periods) ? context.periods : [];
  const selectedSession = periods.find((period) => String(period.id) === String(selectedSessionId)) || null;
  const termOptions = Array.isArray(selectedSession?.terms) ? selectedSession.terms : [];
  const categories = context?.categories || {};
  const grouped = useMemo(() => {
    return CATEGORY_ORDER.reduce((acc, key) => {
      acc[key] = materials.filter((item) => item.category === key);
      return acc;
    }, {});
  }, [materials]);

  const loadContext = async () => {
    const res = await api.get("/api/school-admin/teaching/context");
    const data = res.data?.data || null;
    setContext(data);
    setSelectedSessionId(String(data?.current_session?.id || ""));
    setSelectedTermId(String(data?.current_term?.id || ""));
  };

  const loadStaff = async () => {
    if (!selectedSessionId || !selectedTermId) return;
    setLoadingStaff(true);
    try {
      const res = await api.get("/api/school-admin/teaching/staff", {
        params: { academic_session_id: selectedSessionId, term_id: selectedTermId },
      });
      const nextStaff = res.data?.data?.staff || [];
      setStaff(nextStaff);
      setSelectedStaff((current) => {
        if (current && nextStaff.some((item) => item.id === current.id)) return current;
        return nextStaff[0] || null;
      });
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load staff.");
      setStaff([]);
      setSelectedStaff(null);
    } finally {
      setLoadingStaff(false);
    }
  };

  const loadMaterials = async () => {
    if (!selectedStaff || !selectedSessionId || !selectedTermId) {
      setMaterials([]);
      return;
    }
    setLoadingMaterials(true);
    try {
      const res = await api.get("/api/school-admin/teaching/materials", {
        params: {
          staff_user_id: selectedStaff.id,
          academic_session_id: selectedSessionId,
          term_id: selectedTermId,
        },
      });
      setMaterials(res.data?.data?.materials || []);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load staff teaching files.");
      setMaterials([]);
    } finally {
      setLoadingMaterials(false);
    }
  };

  useEffect(() => {
    loadContext().catch((e) => alert(e?.response?.data?.message || "Failed to load teaching context."));
  }, []);

  useEffect(() => {
    loadStaff();
  }, [selectedSessionId, selectedTermId]);

  useEffect(() => {
    loadMaterials();
  }, [selectedStaff?.id, selectedSessionId, selectedTermId]);

  const changeSession = (sessionId) => {
    const nextSession = periods.find((period) => String(period.id) === String(sessionId));
    const currentTerm = nextSession?.terms?.find((term) => term.is_current) || nextSession?.terms?.[0];
    setSelectedSessionId(String(sessionId || ""));
    setSelectedTermId(currentTerm ? String(currentTerm.id) : "");
    setSelectedStaff(null);
    setMaterials([]);
  };

  const download = async (item) => {
    setDownloadingId(item.id);
    try {
      const res = await api.get(`/api/school-admin/teaching/materials/${item.id}/download`, {
        responseType: "blob",
      });
      const blobUrl = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, item.original_name);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
      alert(e?.response?.data?.message || "Download failed. File may still be processing.");
    } finally {
      setDownloadingId(null);
    }
  };

  return (
    <div className="teach-page">
      <section className="teach-hero">
        <div>
          <span className="teach-pill">School Admin Teaching</span>
          <h2 className="teach-title">Review staff teaching files by session and term</h2>
          <p className="teach-subtitle">
            Select an active staff member, inspect uploaded topics, exam questions, and lesson notes, then download what you need.
          </p>
          <div className="teach-meta">
            <span>{selectedSession?.label || context?.current_session?.session_name || "Session -"}</span>
            <span>{termOptions.find((term) => String(term.id) === String(selectedTermId))?.name || "Term -"}</span>
            <span>{staff.length} active staff</span>
          </div>
        </div>
        <div className="teach-art" aria-hidden="true">
          <div className="teach-art-card teach-art-card--main"><img src={newsfeedArt} alt="" /></div>
          <div className="teach-art-card teach-art-card--phone"><img src={mobileArt} alt="" /></div>
          <div className="teach-art-card teach-art-card--book"><img src={readingArt} alt="" /></div>
        </div>
      </section>

      <section className="teach-panel">
        <div className="teach-filter-row">
          <select className="teach-field" value={selectedSessionId} onChange={(e) => changeSession(e.target.value)} style={{ maxWidth: 240 }}>
            <option value="">Select Session</option>
            {periods.map((period) => (
              <option key={period.id} value={period.id}>{period.label}</option>
            ))}
          </select>
          <select className="teach-field" value={selectedTermId} onChange={(e) => { setSelectedTermId(e.target.value); setSelectedStaff(null); }} style={{ maxWidth: 220 }}>
            <option value="">Select Term</option>
            {termOptions.map((term) => (
              <option key={term.id} value={term.id}>{term.name}</option>
            ))}
          </select>
          <button className="teach-btn teach-btn--soft" type="button" onClick={loadStaff}>
            Refresh
          </button>
        </div>

        <div className="teach-grid" style={{ marginTop: 14 }}>
          <article className="teach-card">
            <h3>Active Staff</h3>
            {loadingStaff ? <p className="teach-state">Loading staff...</p> : null}
            {!loadingStaff ? (
              <div className="teach-staff-list">
                {staff.map((item) => (
                  <button
                    className={`teach-staff-btn${selectedStaff?.id === item.id ? " teach-staff-btn--active" : ""}`}
                    type="button"
                    key={item.id}
                    onClick={() => setSelectedStaff(item)}
                  >
                    <span>
                      <strong>{item.name}</strong>
                      <span className="teach-small">{item.email || item.username || "-"}</span>
                    </span>
                    <strong>{item.materials_count}</strong>
                  </button>
                ))}
                {staff.length === 0 ? <p className="teach-small">No active staff found.</p> : null}
              </div>
            ) : null}
          </article>

          <article className="teach-card">
            <h3>{selectedStaff ? `${selectedStaff.name}'s Files` : "Select Staff"}</h3>
            {loadingMaterials ? <p className="teach-state">Loading files...</p> : null}
            {!loadingMaterials && selectedStaff ? (
              CATEGORY_ORDER.map((key) => (
                <div className="teach-category" key={key}>
                  <h4>{categories[key] || key}</h4>
                  <div className="teach-file-list">
                    {(grouped[key] || []).map((item) => (
                      <div className="teach-file-row" key={item.id}>
                        <div className="teach-file-main">
                          <p className="teach-file-name">{item.title || item.original_name}</p>
                          <p className="teach-file-meta">
                            {item.original_name} | {item.status} | {bytes(item.compressed_size || item.file_size)}
                          </p>
                          {item.processing_note ? <p className="teach-file-meta">{item.processing_note}</p> : null}
                        </div>
                        <button className="teach-btn" onClick={() => download(item)} disabled={downloadingId === item.id || item.status !== "ready"}>
                          {downloadingId === item.id ? "Downloading..." : "Download"}
                        </button>
                      </div>
                    ))}
                    {(grouped[key] || []).length === 0 ? <p className="teach-small">No file uploaded.</p> : null}
                  </div>
                </div>
              ))
            ) : null}
            {!selectedStaff ? <p className="teach-small">Choose a staff name from the vertical list to view teaching files.</p> : null}
          </article>
        </div>
      </section>
    </div>
  );
}
