import { useEffect, useState } from "react";
import api from "../../services/api";
import SchoolSubscriptionSection from "./SchoolSubscriptionSection";

export default function SuperAdminPayments() {
  const [schools, setSchools] = useState([]);
  const [selectedSchoolId, setSelectedSchoolId] = useState(null);
  const [loadingSchools, setLoadingSchools] = useState(true);

  const loadSchools = async () => {
    setLoadingSchools(true);
    try {
      const res = await api.get("/api/super-admin/payments/schools");
      const list = res.data?.data || [];
      setSchools(list);
      if (list.length > 0) {
        setSelectedSchoolId((prev) => prev || list[0].id);
      }
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load schools.");
      setSchools([]);
      setSelectedSchoolId(null);
    } finally {
      setLoadingSchools(false);
    }
  };

  useEffect(() => {
    loadSchools();
  }, []);

  return (
    <div>
      <h2>School Billing Payments</h2>
      <p style={{ marginTop: 6, opacity: 0.75 }}>
        Review each school&apos;s online subscription payments and bank-transfer receipt uploads in one place.
      </p>

      {loadingSchools ? (
        <p>Loading schools...</p>
      ) : (
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginTop: 14 }}>
          {schools.map((school) => (
            <button
              key={school.id}
              onClick={() => setSelectedSchoolId(school.id)}
              style={{
                padding: "8px 12px",
                borderRadius: 8,
                border: selectedSchoolId === school.id ? "1px solid #2563eb" : "1px solid #ccc",
                background: selectedSchoolId === school.id ? "#eff6ff" : "#fff",
              }}
            >
              {school.name}
            </button>
          ))}
        </div>
      )}

      {selectedSchoolId ? (
        <div style={{ marginTop: 18 }}>
          <SchoolSubscriptionSection schoolId={selectedSchoolId} />
        </div>
      ) : null}
    </div>
  );
}
