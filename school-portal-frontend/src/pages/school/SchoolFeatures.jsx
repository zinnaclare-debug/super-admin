import { FEATURE_DEFINITIONS } from "../../config/features";
import { useEffect, useState } from "react";
import api from "../../services/api";

function SchoolFeatures() {
  const [features, setFeatures] = useState([]);

  useEffect(() => {
    api.get("/api/schools/features").then((res) => {
      setFeatures(res.data.data || []);
    });
  }, []);

  const generalFeatures = FEATURE_DEFINITIONS.filter(
    (f) => f.category === "general"
  );

  const isEnabled = (key) =>
    features.find((f) => f.feature === key)?.enabled;

  return (
    <div>
      <h2>General Features</h2>

      <table border="1" cellPadding="10" width="100%">
        <thead>
          <tr>
            <th>S/N</th>
            <th>Feature</th>
            <th>Status</th>
          </tr>
        </thead>

        <tbody>
          {generalFeatures.map((f, i) => (
            <tr key={f.key}>
              <td>{i + 1}</td>
              <td>{f.label}</td>
              <td>
                <strong>
                  {isEnabled(f.key) ? "Enabled" : "Disabled"}
                </strong>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default SchoolFeatures;
