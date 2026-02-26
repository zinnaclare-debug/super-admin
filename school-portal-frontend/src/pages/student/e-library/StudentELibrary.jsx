import { useEffect, useState } from "react";
import api from "../../../services/api";
import mobileDevicesArt from "../../../assets/e-library/mobile-devices.svg";
import onlineReadingArt from "../../../assets/e-library/online-reading.svg";
import audiobookArt from "../../../assets/e-library/audiobook.svg";
import "./StudentELibrary.css";

function formatDate(value) {
  if (!value) return null;
  try {
    return new Date(value).toLocaleString();
  } catch {
    return null;
  }
}

export default function StudentELibrary() {
  const [books, setBooks] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/student/e-library");
      setBooks(res.data.data || []);
    } catch (e) {
      alert("Failed to load e-library");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  return (
    <div className="sel-page">
      <section className="sel-hero">
        <div>
          <span className="sel-pill">Student E-Library</span>
          <h2>Digital books, ready for every study session</h2>
          <p className="sel-subtitle">
            Explore textbooks and learning materials posted for your level. Download and revise anytime.
          </p>
          <div className="sel-metrics">
            <span>{loading ? "Loading..." : `${books.length} book${books.length === 1 ? "" : "s"} available`}</span>
            <span>PDF resources</span>
          </div>
        </div>

        <div className="sel-hero-art" aria-hidden="true">
          <div className="sel-art sel-art--main">
            <img src={mobileDevicesArt} alt="" />
          </div>
          <div className="sel-art sel-art--reading">
            <img src={onlineReadingArt} alt="" />
          </div>
          <div className="sel-art sel-art--audio">
            <img src={audiobookArt} alt="" />
          </div>
        </div>
      </section>

      <section className="sel-panel">
        {loading ? (
          <p className="sel-state sel-state--loading">Loading e-library...</p>
        ) : books.length === 0 ? (
          <p className="sel-state sel-state--empty">No textbooks available.</p>
        ) : (
          <div className="sel-grid">
            {books.map((b, idx) => {
              const postedAt = formatDate(b.created_at || b.updated_at || b.published_at);
              return (
                <article key={b.id} className="sel-card">
                  <div className="sel-card-head">
                    <span className="sel-index">{idx + 1}</span>
                    <span className="sel-level">{b.education_level || "All Levels"}</span>
                  </div>

                  <h3>{b.title}</h3>
                  <p className="sel-author">Author: {b.author || "Unknown"}</p>
                  {b.description ? <p className="sel-description">{b.description}</p> : null}

                  <div className="sel-meta">
                    <small>{postedAt ? `Posted: ${postedAt}` : "Resource material"}</small>
                  </div>

                  <a className="sel-download" href={b.file_url} target="_blank" rel="noreferrer">
                    Download Book
                  </a>
                </article>
              );
            })}
          </div>
        )}
      </section>
    </div>
  );
}
