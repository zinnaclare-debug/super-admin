import { useEffect, useState } from "react";
import api from "../services/api";
import FeatureTable from "./FeatureTable";
import { FEATURE_DEFINITIONS } from "../config/features";

function FeatureModal({ school, onClose }) {
  const [features, setFeatures] = useState([]);
  const [loading, setLoading] = useState(true);

  const labelMap = FEATURE_DEFINITIONS.reduce((acc, cur) => {
    acc[cur.key] = cur.label;
    return acc;
  }, {});

  const loadFeatures = async () => {
    try {
      const res = await api.get(
        `/api/super-admin/schools/${school.id}/features`
      );
      setFeatures(res.data.data || []);
    } catch (err) {
      console.error("FEATURE LOAD ERROR", err.response?.data);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadFeatures();
  }, [school?.id]);

  const toggleFeature = async (featureKey, enabled) => {
    await api.post(
      `/api/super-admin/schools/${school.id}/features/toggle`,
      {
        feature: featureKey,
        enabled,
      }
    );
    loadFeatures();
  };

  return (
    <div style={{ border: "1px solid #333", padding: 20, marginTop: 20 }}>
      <h3>Manage Features — {school.name}</h3>

      {loading && <p>Loading features...</p>}

      {!loading && (
        <FeatureTable
          features={features}
          onToggle={toggleFeature}
          labelMap={labelMap}
        />
      )}

      <button onClick={onClose} style={{ marginTop: 15 }}>
        Close
      </button>
    </div>
  );
}

export default FeatureModal;
