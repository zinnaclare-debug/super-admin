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

  const toggleSchool = (schoolId) => {
    setSelectedSchoolId((prev) => (prev === schoolId ? null : schoolId));
  };

  return (
    <div>
      <h2>School Billing Payments</h2>
      <p style={{ marginTop: 6, opacity: 0.75 }}>
        Review each school&apos;s online subscription payments and bank-transfer receipt uploads in one place.
      </p>

      {loadingSchools ? (
        <p>Loading schools...</p>
      ) : schools.length === 0 ? (
        <p>No schools found.</p>
      ) : (
        <div style={{ marginTop: 18, display: "grid", gap: 14 }}>
          {schools.map((school, index) => {
            const isOpen = selectedSchoolId === school.id;

            return (
              <section
                key={school.id}
                style={{
                  border: isOpen ? "1px solid #2563eb" : "1px solid #dbe3f0",
                  borderRadius: 18,
                  background: "#fff",
                  overflow: "hidden",
                  boxShadow: isOpen ? "0 18px 40px rgba(37, 99, 235, 0.12)" : "0 10px 24px rgba(15, 23, 42, 0.06)",
                }}
              >
                <button
                  type="button"
                  onClick={() => toggleSchool(school.id)}
                  style={{
                    width: "100%",
                    border: "none",
                    background: isOpen ? "linear-gradient(135deg, #eff6ff, #dbeafe)" : "#ffffff",
                    padding: "18px 20px",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    gap: 16,
                    cursor: "pointer",
                    textAlign: "left",
                  }}
                >
                  <div style={{ minWidth: 0 }}>
                    <div style={{ fontSize: 12, fontWeight: 700, letterSpacing: "0.08em", color: "#64748b", textTransform: "uppercase" }}>
                      School {index + 1}
                    </div>
                    <div style={{ fontSize: 18, fontWeight: 700, color: "#0f172a", marginTop: 4, wordBreak: "break-word" }}>
                      {school.name}
                    </div>
                  </div>
                  <div
                    aria-hidden="true"
                    style={{
                      flexShrink: 0,
                      width: 38,
                      height: 38,
                      borderRadius: 999,
                      display: "grid",
                      placeItems: "center",
                      background: isOpen ? "#2563eb" : "#e2e8f0",
                      color: isOpen ? "#fff" : "#334155",
                      fontSize: 22,
                      fontWeight: 700,
                      lineHeight: 1,
                    }}
                  >
                    {isOpen ? "-" : "+"}
                  </div>
                </button>

                {isOpen ? (
                  <div style={{ padding: 20, borderTop: "1px solid #dbeafe", background: "#f8fbff" }}>
                    <SchoolSubscriptionSection schoolId={school.id} />
                  </div>
                ) : null}
              </section>
            );
          })}
        </div>
      )}
    </div>
  );
}
