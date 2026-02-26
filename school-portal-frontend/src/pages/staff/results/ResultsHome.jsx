import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import examPrepArt from "../../../assets/results/exam-prep.svg";
import onlineSurveyArt from "../../../assets/results/online-survey.svg";
import certificateArt from "../../../assets/results/certificate.svg";
import "../../shared/ResultsShowcase.css";

export default function ResultsHome() {
  const navigate = useNavigate();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/staff/results/subjects");
      setItems(res.data.data || []);
    } catch (e) {
      alert("Failed to load your assigned subjects");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  return (
    <StaffFeatureLayout title="Results">
      <div className="rs-page rs-page--staff">
        <section className="rs-hero">
          <div>
            <span className="rs-pill">Staff Results Desk</span>
            <h2 className="rs-title">Manage scores by subject and class</h2>
            <p className="rs-subtitle">
              Open any assigned subject to score students and track term-by-term performance with less friction.
            </p>
            <div className="rs-meta">
              <span>{loading ? "Loading..." : `${items.length} assigned subject${items.length === 1 ? "" : "s"}`}</span>
              <span>Score and publish workflow</span>
            </div>
          </div>

          <div className="rs-hero-art" aria-hidden="true">
            <div className="rs-art rs-art--main">
              <img src={examPrepArt} alt="" />
            </div>
            <div className="rs-art rs-art--survey">
              <img src={onlineSurveyArt} alt="" />
            </div>
            <div className="rs-art rs-art--cert">
              <img src={certificateArt} alt="" />
            </div>
          </div>
        </section>

        <section className="rs-panel">
          {loading ? <p className="rs-state rs-state--loading">Loading assigned subjects...</p> : null}
          {!loading && items.length === 0 ? (
            <p className="rs-state rs-state--empty">No subjects assigned to you yet.</p>
          ) : null}

          {!loading && items.length > 0 ? (
            <div className="rs-cards">
              {items.map((x) => (
                <button
                  key={x.term_subject_id}
                  className="rs-card-btn"
                  onClick={() => navigate(`/staff/results/${x.term_subject_id}`)}
                >
                  <h3 className="rs-card-title">{x.subject_name}</h3>
                  <p className="rs-card-meta">
                    {x.class_name} | {x.term_name}
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
