import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "react-router-dom";
import api from "../../services/api";
import payCardArt from "../../assets/payments/pay-with-credit-card.svg";
import creditCardArt from "../../assets/payments/credit-card-payments.svg";
import onlinePayArt from "../../assets/payments/online-payments.svg";
import "../shared/PaymentsShowcase.css";

function formatMoney(value) {
  return Number(value || 0).toFixed(2);
}

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
    <div className="payx-page payx-page--student">
      <section className="payx-hero">
        <div>
          <span className="payx-pill">Student School Fees</span>
          <h2 className="payx-title">Pay securely and track your fee status</h2>
          <p className="payx-subtitle">
            Check outstanding balance, make partial or full payments, and monitor each transaction in your history.
          </p>
          <div className="payx-meta">
            <span>{summary?.current_session?.session_name || summary?.current_session?.academic_year || "Session - "}</span>
            <span>{summary?.current_term?.name || "Term -"}</span>
            <span>{isFullyPaid ? "Fully Paid" : `Outstanding: NGN ${formatMoney(outstanding)}`}</span>
          </div>
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main">
            <img src={payCardArt} alt="" />
          </div>
          <div className="payx-art payx-art--card">
            <img src={creditCardArt} alt="" />
          </div>
          <div className="payx-art payx-art--online">
            <img src={onlinePayArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel">
        {loading ? <p className="payx-state payx-state--loading">Loading school fees...</p> : null}
        {!loading && error ? <p className="payx-state payx-state--error">{error}</p> : null}

        {!loading && !error ? (
          <>
            <div className="payx-grid-2">
              <article className="payx-card">
                <h3>Current Session / Term</h3>
                <div className="payx-kv">
                  <div className="payx-row">
                    <span className="payx-label">Session</span>
                    <span className="payx-value">
                      {summary?.current_session?.session_name || summary?.current_session?.academic_year || "-"}
                    </span>
                  </div>
                  <div className="payx-row">
                    <span className="payx-label">Term</span>
                    <span className="payx-value">{summary?.current_term?.name || "-"}</span>
                  </div>
                  <div className="payx-row">
                    <span className="payx-label">Amount Payable</span>
                    <span className="payx-value">NGN {formatMoney(fee?.amount_due)}</span>
                  </div>
                  <div className="payx-row">
                    <span className="payx-label">Total Paid</span>
                    <span className="payx-value">NGN {formatMoney(fee?.total_paid)}</span>
                  </div>
                  <div className="payx-row">
                    <span className="payx-label">Status</span>
                    <span className="payx-value">{statusLabel}</span>
                  </div>
                </div>
              </article>

              <article className="payx-card">
                <h3>Pay School Fees</h3>
                {isFullyPaid ? (
                  <p className="payx-state payx-state--ok">No outstanding fee for this term.</p>
                ) : (
                  <>
                    <div className="payx-actions">
                      <input
                        className="payx-input"
                        type="number"
                        min="100"
                        step="0.01"
                        value={amount}
                        onChange={(e) => setAmount(e.target.value)}
                        placeholder="Enter amount"
                        style={{ width: 220 }}
                        disabled={paying || verifying}
                      />
                      <button className="payx-btn" onClick={payNow} disabled={paying || verifying}>
                        {paying ? "Redirecting..." : "Pay with Paystack"}
                      </button>
                      {verifying ? <span className="payx-label">Verifying payment...</span> : null}
                    </div>
                    <small className="payx-small">
                      You can pay partially. Remaining balance will keep showing as outstanding.
                    </small>
                  </>
                )}
              </article>
            </div>

            <div className="payx-card" style={{ marginTop: 12 }}>
              <h3>Payment History</h3>
              <div className="payx-table-wrap">
                <table className="payx-table">
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
                          {p.currency} {formatMoney(p.amount_paid)}
                        </td>
                        <td>{p.status}</td>
                        <td>{p.paid_at || p.created_at || "-"}</td>
                      </tr>
                    ))}
                    {(summary?.payments || []).length === 0 ? (
                      <tr>
                        <td colSpan="5">No payments yet.</td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>
          </>
        ) : null}
      </section>
    </div>
  );
}
