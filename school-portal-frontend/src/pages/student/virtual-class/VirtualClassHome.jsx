import { useEffect, useState } from "react";
import api from "../../../services/api";
import aiResponseArt from "../../../assets/virtual-class/ai-response.svg";
import onlineMeetingsArt from "../../../assets/virtual-class/online-meetings.svg";
import vrChatArt from "../../../assets/virtual-class/vr-chat.svg";
import "../../shared/VirtualClassShowcase.css";

function formatDate(value) {
  if (!value) return "-";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export default function StudentVirtualClassHome() {
  const [subjects, setSubjects] = useState([]);
  const [items, setItems] = useState([]);
  const [termSubjectId, setTermSubjectId] = useState("");
  const [loading, setLoading] = useState(true);

  const loadSubjects = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/virtual-classes/subjects");
      const rows = res.data?.data || [];
      setSubjects(rows);
      if (rows.length > 0) {
        setTermSubjectId(String(rows[0].term_subject_id));
      } else {
        setTermSubjectId("");
        setItems([]);
      }
    } catch {
      alert("Failed to load virtual classes");
    } finally {
      setLoading(false);
    }
  };

  const loadItems = async (termId) => {
    if (!termId) {
      setItems([]);
      return;
    }
    try {
      const res = await api.get("/api/student/virtual-classes", {
        params: { term_subject_id: termId },
      });
      setItems(res.data?.data || []);
    } catch {
      setItems([]);
    }
  };

  useEffect(() => {
    loadSubjects();
  }, []);

  useEffect(() => {
    loadItems(termSubjectId);
  }, [termSubjectId]);

  return (
    <div className="vcx-page vcx-page--student">
      <section className="vcx-hero">
        <div>
          <span className="vcx-pill">Student Virtual Class</span>
          <h2 className="vcx-title">Join your online classes on time</h2>
          <p className="vcx-subtitle">
            Pick a subject and access active class meeting links shared by your teachers for this term.
          </p>
          <div className="vcx-meta">
            <span>{loading ? "Loading..." : `${subjects.length} subject${subjects.length === 1 ? "" : "s"}`}</span>
            <span>{`${items.length} virtual class${items.length === 1 ? "" : "es"}`}</span>
          </div>
        </div>

        <div className="vcx-hero-art" aria-hidden="true">
          <div className="vcx-art vcx-art--main">
            <img src={aiResponseArt} alt="" />
          </div>
          <div className="vcx-art vcx-art--meeting">
            <img src={onlineMeetingsArt} alt="" />
          </div>
          <div className="vcx-art vcx-art--chat">
            <img src={vrChatArt} alt="" />
          </div>
        </div>
      </section>

      <section className="vcx-panel">
        {loading ? <p className="vcx-state vcx-state--loading">Loading virtual classes...</p> : null}
        {!loading && subjects.length === 0 ? (
          <p className="vcx-state vcx-state--empty">No virtual class subjects available for your current class and current term.</p>
        ) : null}

        {!loading && subjects.length > 0 ? (
          <>
            <div className="vcx-filter">
              <label htmlFor="student-vc-subject">Subject</label>
              <select
                id="student-vc-subject"
                className="vcx-field"
                value={termSubjectId}
                onChange={(e) => setTermSubjectId(e.target.value)}
                style={{ minWidth: 260 }}
              >
                {subjects.map((s) => (
                  <option key={s.term_subject_id} value={s.term_subject_id}>
                    {s.subject_name}
                  </option>
                ))}
              </select>
            </div>

            <div className="vcx-table-wrap">
              <table className="vcx-table">
                <thead>
                  <tr>
                    <th style={{ width: 70 }}>S/N</th>
                    <th>Title</th>
                    <th>Start Time</th>
                    <th style={{ width: 160 }}>Join</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((x, idx) => (
                    <tr key={x.id}>
                      <td>{idx + 1}</td>
                      <td>{x.title}</td>
                      <td>{formatDate(x.starts_at)}</td>
                      <td>
                        <a className="vcx-link" href={x.meeting_link} target="_blank" rel="noreferrer">
                          Join Class
                        </a>
                      </td>
                    </tr>
                  ))}
                  {items.length === 0 ? (
                    <tr>
                      <td colSpan="4">No virtual class links posted yet.</td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </>
        ) : null}
      </section>
    </div>
  );
}
