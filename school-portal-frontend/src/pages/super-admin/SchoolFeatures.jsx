import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import api from "../../services/api";
import FeatureTable from "../../components/FeatureTable";
import { FEATURE_DEFINITIONS } from "../../config/features";

function SchoolFeatures() {
  const { schoolId } = useParams();
  const [features, setFeatures] = useState([]);

  const labelMap = FEATURE_DEFINITIONS.reduce((acc, cur) => {
    acc[cur.key] = cur.label;
    return acc;
  }, {});

  const loadFeatures = async () => {
    const res = await api.get(
      `/api/super-admin/schools/${schoolId}/features`
    );
    setFeatures(res.data.data || []);
  };

  useEffect(() => {
    loadFeatures();
  }, [schoolId]);

  const toggleFeature = async (featureKey, enabled) => {
    await api.post(
      `/api/super-admin/schools/${schoolId}/features/toggle`,
      { feature: featureKey, enabled }
    );

    loadFeatures();
  };

  return (
    <div>
      <h2>School Features</h2>

      <div style={{ width: "60%" }}>
        <FeatureTable
          features={features}
          onToggle={toggleFeature}
          labelMap={labelMap}
        />
      </div>
    </div>
  );
}

export default SchoolFeatures;
