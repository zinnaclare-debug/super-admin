import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "react-router-dom";
import api from "../../services/api";

export default function StudentSchoolFees() {
  const [searchParams, setSearchParams] = useSearchParams();

  const [loading, setLoading] = useState(true);
  const [verifying, setVerifying] = useState(false);
  const [paying, setPaying] = useState(false);
  const [error, setError] = useState("");
  const [summary, setSummary] = useState(null);
  const [amount, setAmount] = useState("");

  const fee = summary?.fee || {};
  const outstanding = Number(fee?.outstanding || 0);
  const isFullyPaid = !!fee?.is_fully_paid;
  const referenceFromQuery = searchParams.get("reference");

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      const res = await api.get("/api/student/school-fees");
      const data = res.data?.data || null;
      setSummary(data);
    } catch (e) {
      setError(e?.response?.data?.message || "Failed to load school fees.");
      setSummary(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  useEffect(() => {
    if (!summary) return;
    if (outstanding > 0) {
      setAmount(String(outstanding));
    } else {
      setAmount("");
    }
  }, [summary, outstanding]);

  const verifyPayment = async (reference) => {
    if (!reference) return;
    setVerifying(true);
    try {
      await api.get("/api/student/school-fees/verify", { params: { reference } });
      await load();
      searchParams.delete("reference");
      setSearchParams(searchParams, { replace: true });
      alert("Payment verified successfully.");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to verify payment.");
    } finally {
      setVerifying(false);
    }
  };

  useEffect(() => {
    if (!referenceFromQuery) return;
    verifyPayment(referenceFromQuery);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [referenceFromQuery]);

  const payNow = async () => {
    const value = Number(amount);
    if (!Number.isFinite(value) || value <= 0) {
      return alert("Enter a valid amount.");
    }

    setPaying(true);
    try {
      const res = await api.post("/api/student/school-fees/initialize", {
        amount: value,
      });
      const url = res.data?.data?.authorization_url;
      if (!url) throw new Error("No authorization URL returned");
      window.location.href = url;
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to initialize payment.");
      setPaying(false);
    }
  };

  const statusLabel = useMemo(() => {
    if (isFullyPaid) return "School Fees Paid";
    return `Outstanding: NGN ${outstanding.toFixed(2)}`;
  }, [isFullyPaid, outstanding]);

  return (
    <div>
      {loading ? (
        <p>Loading school fees...</p>
      ) : error ? (
        <p style={{ color: "red" }}>{error}</p>
      ) : (
        <>
          <div style={{ border: "1px solid #ddd", borderRadius: 10, padding: 14, marginTop: 12 }}>
            <h3 style={{ marginTop: 0 }}>Current Session / Term</h3>
            <p style={{ margin: "4px 0" }}>
              <strong>Session:</strong>{" "}
              {summary?.current_session?.session_name || summary?.current_session?.academic_year || "-"}
            </p>
            <p style={{ margin: "4px 0" }}>
              <strong>Term:</strong> {summary?.current_term?.name || "-"}
            </p>
            <p style={{ margin: "4px 0" }}>
              <strong>Amount Payable:</strong> NGN {Number(fee?.amount_due || 0).toFixed(2)}
            </p>
            <p style={{ margin: "4px 0" }}>
              <strong>Total Paid:</strong> NGN {Number(fee?.total_paid || 0).toFixed(2)}
            </p>
            <p style={{ margin: "4px 0", color: isFullyPaid ? "green" : "#b45309" }}>
              <strong>Status:</strong> {statusLabel}
            </p>
          </div>

          {!isFullyPaid && (
            <div style={{ border: "1px solid #ddd", borderRadius: 10, padding: 14, marginTop: 12 }}>
              <h3 style={{ marginTop: 0 }}>Pay School Fees</h3>
              <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
                <input
                  type="number"
                  min="100"
                  step="0.01"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  placeholder="Enter amount"
                  style={{ width: 220, padding: 10 }}
                  disabled={paying || verifying}
                />
                <button onClick={payNow} disabled={paying || verifying}>
                  {paying ? "Redirecting..." : "Pay with Paystack"}
                </button>
                {verifying && <span>Verifying payment...</span>}
              </div>
              <small style={{ opacity: 0.7 }}>
                You can pay partially. Remaining balance will keep showing as outstanding.
              </small>
            </div>
          )}

          <div style={{ marginTop: 14 }}>
            <h3>Payment History</h3>
            <table border="1" cellPadding="8" width="100%">
              <thead>
                <tr>
                  <th>S/N</th>
                  <th>Reference</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                {(summary?.payments || []).map((p, idx) => (
                  <tr key={p.id}>
                    <td>{idx + 1}</td>
                    <td>{p.reference}</td>
                    <td>
                      {p.currency} {Number(p.amount_paid || 0).toFixed(2)}
                    </td>
                    <td>{p.status}</td>
                    <td>{p.paid_at || p.created_at || "-"}</td>
                  </tr>
                ))}
                {(summary?.payments || []).length === 0 && (
                  <tr>
                    <td colSpan="5">No payments yet.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}
