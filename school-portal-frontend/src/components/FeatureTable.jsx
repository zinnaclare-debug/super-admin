import React, { useMemo, useState } from "react";

/**
 * FeatureTable
 * Props:
 * - features: [{ feature, enabled, category?, description? }]
 * - onToggle: (featureKey, enabled) => Promise
 * - labelMap: { key -> label }
 * - showDescription: boolean
 */
export default function FeatureTable({
  features = [],
  onToggle = () => {},
  labelMap = {},
  showDescription = true,
}) {
  const [sortAsc, setSortAsc] = useState(true);

  const hasCategory = useMemo(
    () => features.some((f) => f.category),
    [features]
  );

  const sorted = useMemo(() => {
    return [...features].sort((a, b) => {
      const aLabel = (labelMap[a.feature] || a.feature).toLowerCase();
      const bLabel = (labelMap[b.feature] || b.feature).toLowerCase();
      if (aLabel < bLabel) return sortAsc ? -1 : 1;
      if (aLabel > bLabel) return sortAsc ? 1 : -1;
      return 0;
    });
  }, [features, sortAsc, labelMap]);

  return (
    <div>
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          marginBottom: 12,
        }}
      >
        <strong>Features</strong>
        <button onClick={() => setSortAsc((s) => !s)}>
          Sort: {sortAsc ? "A→Z" : "Z→A"}
        </button>
      </div>

      <table border="1" cellPadding="10" width="100%">
        <thead>
          <tr>
            <th>Feature</th>
            {showDescription && <th>Description</th>}
            {hasCategory && <th>Category</th>}
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>

        <tbody>
          {sorted.map((f) => (
            <tr key={f.feature}>
              <td>{labelMap[f.feature] || f.feature}</td>
              {showDescription && <td>{f.description || ""}</td>}
              {hasCategory && (
                <td style={{ textTransform: "capitalize" }}>
                  {f.category || ""}
                </td>
              )}
              <td>
                <strong>{f.enabled ? "Enabled" : "Disabled"}</strong>
              </td>
              <td>
                <button onClick={() => onToggle(f.feature, !f.enabled)}>
                  {f.enabled ? "Disable" : "Enable"}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
