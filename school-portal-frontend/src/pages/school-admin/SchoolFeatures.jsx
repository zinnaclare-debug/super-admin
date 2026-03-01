import { useEffect, useState, useMemo } from "react";
import api from "../../services/api";
import FeatureTable from "../../components/FeatureTable";
import { FEATURE_DEFINITIONS } from "../../config/features";

function SchoolFeatures() {
  const [features, setFeatures] = useState([]);
  const [loading, setLoading] = useState(true);

  // map feature key → label
  const labelMap = useMemo(() => {
    return FEATURE_DEFINITIONS.reduce((acc, cur) => {
      acc[cur.key] = cur.label;
      return acc;
    }, {});
  }, []);

  // load enabled features from backend
  useEffect(() => {
    api
      .get("/api/schools/features")
      .then((res) => {
        setFeatures(res.data.data || []);
      })
      .catch(() => alert("Failed to load features"))
      .finally(() => setLoading(false));
  }, []);

  // 🔑 ONLY GENERAL FEATURES
  const generalFeatures = useMemo(() => {
    const generalDefs = FEATURE_DEFINITIONS.filter(
      (f) => f.category === "general"
    );

    return generalDefs.map((def) => {
      const dbFeature = features.find((f) => f.feature === def.key);

      return {
        feature: def.key,
        enabled: dbFeature ? dbFeature.enabled : false,
        category: "general",
      };
    });
  }, [features]);

  if (loading) return <p>Loading features...</p>;

  return (
    <div>
      <h1>General Features</h1>
      <p>Features enabled by the Super Admin for your school.</p>

      <FeatureTable
        features={generalFeatures}
        labelMap={labelMap}
        showDescription={false}
        readOnly
      />
    </div>
  );
}

export default SchoolFeatures;
