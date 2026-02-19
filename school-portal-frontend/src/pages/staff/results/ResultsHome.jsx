import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";

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

  useEffect(() => { load(); }, []);

  return (
    <StaffFeatureLayout title="Results">

      {loading ? (
        <p>Loading...</p>
      ) : items.length === 0 ? (
        <p>No subjects assigned to you yet.</p>
      ) : (
        <div style={{ display: "flex", flexWrap: "wrap", gap: 10, marginTop: 12 }}>
          {items.map((x) => (
            <button
              key={x.term_subject_id}
              onClick={() => navigate(`/staff/results/${x.term_subject_id}`)}
              style={{ padding: "10px 12px", borderRadius: 8, border: "1px solid #ddd" }}
            >
              <div style={{ fontWeight: 700 }}>{x.subject_name}</div>
              <div style={{ fontSize: 12, opacity: 0.8 }}>
                {x.class_name} • {x.term_name}
              </div>
            </button>
          ))}
        </div>
      )}
    </StaffFeatureLayout>
  );
}
