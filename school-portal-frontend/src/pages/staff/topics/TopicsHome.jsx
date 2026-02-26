import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import onlineTestArt from "../../../assets/topics/online-test.svg";
import bloggingArt from "../../../assets/topics/blogging.svg";
import "../../shared/TopicsShowcase.css";

export default function TopicsHome() {
  const navigate = useNavigate();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/staff/topics/subjects");
      setItems(res.data.data || []);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load assigned subjects");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  return (
    <StaffFeatureLayout title="Topics">
      <div className="tps-page tps-page--staff">
        <section className="tps-hero">
          <div>
            <span className="tps-pill">Staff Topics Desk</span>
            <h2 className="tps-title">Organize topic materials by subject</h2>
            <p className="tps-subtitle">
              Open any assigned subject to upload and manage topic resources for your students.
            </p>
            <div className="tps-meta">
              <span>{loading ? "Loading..." : `${items.length} assigned subject${items.length === 1 ? "" : "s"}`}</span>
              <span>Topic resource management</span>
            </div>
          </div>

          <div className="tps-hero-art" aria-hidden="true">
            <div className="tps-art tps-art--main">
              <img src={onlineTestArt} alt="" />
            </div>
            <div className="tps-art tps-art--alt">
              <img src={bloggingArt} alt="" />
            </div>
          </div>
        </section>

        <section className="tps-panel">
          {loading ? <p className="tps-state tps-state--loading">Loading assigned subjects...</p> : null}
          {!loading && items.length === 0 ? (
            <p className="tps-state tps-state--empty">No assigned subjects yet.</p>
          ) : null}

          {!loading && items.length > 0 ? (
            <div className="tps-subject-grid">
              {items.map((x) => (
                <button
                  key={x.term_subject_id}
                  className="tps-subject-btn"
                  onClick={() => navigate(`/staff/topics/${x.term_subject_id}`, { state: x })}
                >
                  <h3 className="tps-subject-title">
                    {x.subject_name} {x.subject_code ? `(${x.subject_code})` : ""}
                  </h3>
                  <p className="tps-subject-meta">
                    {x.class_level?.toUpperCase()} | {x.class_name} | {x.term_name}
                  </p>
                </button>
              ))}
            </div>
          ) : null}
        </section>
      </div>
    </StaffFeatureLayout>
  );
}
