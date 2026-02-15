import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../../services/api";

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

  useEffect(() => { load(); }, []);

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <h2>Topics</h2>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      {loading ? (
        <p>Loading...</p>
      ) : items.length === 0 ? (
        <p>No assigned subjects yet.</p>
      ) : (
        <div style={{ marginTop: 12, display: "grid", gap: 10 }}>
          {items.map((x) => (
            <button
              key={x.term_subject_id}
              onClick={() => navigate(`/staff/topics/${x.term_subject_id}`, { state: x })}
              style={{ padding: 12, textAlign: "left", border: "1px solid #ddd", borderRadius: 10 }}
            >
              <div style={{ fontWeight: 700 }}>
                {x.subject_name} {x.subject_code ? `(${x.subject_code})` : ""}
              </div>
              <div style={{ opacity: 0.8, fontSize: 13 }}>
                {x.class_level?.toUpperCase()} • {x.class_name} • {x.term_name}
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
