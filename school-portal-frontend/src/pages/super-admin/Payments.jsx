import { useEffect, useState } from "react";
import api from "../../services/api";

export default function SuperAdminPayments() {
  const [schools, setSchools] = useState([]);
  const [selectedSchoolId, setSelectedSchoolId] = useState(null);
  const [rows, setRows] = useState([]);
  const [loadingSchools, setLoadingSchools] = useState(true);
  const [loadingRows, setLoadingRows] = useState(false);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(null);

  const loadSchools = async () => {
    setLoadingSchools(true);
    try {
      const res = await api.get("/api/super-admin/payments/schools");
      const list = res.data?.data || [];
      setSchools(list);
      if (list.length > 0) setSelectedSchoolId((prev) => prev || list[0].id);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load schools.");
      setSchools([]);
      setSelectedSchoolId(null);
    } finally {
      setLoadingSchools(false);
    }
  };

  const loadPayments = async () => {
    if (!selectedSchoolId) return;
    setLoadingRows(true);
    try {
      const res = await api.get("/api/super-admin/payments", {
        params: { school_id: selectedSchoolId, search, page },
      });
      setRows(res.data?.data || []);
      setMeta(res.data?.meta || null);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load school payments.");
      setRows([]);
      setMeta(null);
    } finally {
      setLoadingRows(false);
    }
  };

  useEffect(() => {
    loadSchools();
  }, []);

  useEffect(() => {
    loadPayments();
  }, [selectedSchoolId, search, page]);

  return (
    <div>
      <h2>Payments</h2>

      {loadingSchools ? (
        <p>Loading schools...</p>
      ) : (
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginTop: 10 }}>
          {schools.map((s) => (
            <button
              key={s.id}
              onClick={() => {
                setSelectedSchoolId(s.id);
                setPage(1);
              }}
              style={{
                padding: "8px 12px",
                borderRadius: 8,
                border: selectedSchoolId === s.id ? "1px solid #2563eb" : "1px solid #ccc",
                background: selectedSchoolId === s.id ? "#eff6ff" : "#fff",
              }}
            >
              {s.name}
            </button>
          ))}
        </div>
      )}

      {selectedSchoolId && (
        <div style={{ marginTop: 14 }}>
          <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
            <input
              placeholder="Search by name/email/username/reference"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              style={{ width: 360, padding: 10 }}
            />
            <button
              onClick={() => {
                setSearch("");
                setPage(1);
              }}
            >
              Clear
            </button>
          </div>

          {loadingRows ? (
            <p>Loading payments...</p>
          ) : (
            <>
              <table border="1" cellPadding="8" width="100%" style={{ marginTop: 12 }}>
                <thead>
                  <tr>
                    <th>S/N</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Session</th>
                    <th>Term</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r, idx) => (
                    <tr key={r.id}>
                      <td>{((meta?.current_page || 1) - 1) * (meta?.per_page || rows.length || 0) + idx + 1}</td>
                      <td>{r.student?.name || "-"}</td>
                      <td>{r.student?.email || "-"}</td>
                      <td>{r.student?.username || "-"}</td>
                      <td>
                        {r.currency} {Number(r.amount_paid || 0).toFixed(2)}
                      </td>
                      <td>{r.status}</td>
                      <td>{r.reference}</td>
                      <td>{r.session_name || r.academic_year || "-"}</td>
                      <td>{r.term_name || "-"}</td>
                      <td>{r.paid_at || r.created_at || "-"}</td>
                    </tr>
                  ))}
                  {rows.length === 0 && (
                    <tr>
                      <td colSpan="10">No payments found for this school.</td>
                    </tr>
                  )}
                </tbody>
              </table>

              {meta && (
                <div style={{ marginTop: 12, display: "flex", gap: 8, alignItems: "center" }}>
                  <button disabled={page <= 1} onClick={() => setPage(page - 1)}>
                    Prev
                  </button>
                  <div>
                    Page {meta.current_page} / {meta.last_page}
                  </div>
                  <button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>
                    Next
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
}
