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

const parseFileName = (headers, fallback = "fee_receipt.pdf") => {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback;
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
};

function stateClass(status) {
  if (status === "paid") return "payx-state payx-state--ok";
  if (status === "awaiting_invoice") return "payx-state payx-state--warn";
  return "payx-state payx-state--loading";
}

export default function StudentSchoolFees() {
  const [searchParams, setSearchParams] = useSearchParams();

  const [loading, setLoading] = useState(true);
  const [verifying, setVerifying] = useState(false);
  const [paying, setPaying] = useState(false);
  const [downloadingInvoice, setDownloadingInvoice] = useState(false);
  const [error, setError] = useState("");
  const [summary, setSummary] = useState(null);
  const [amount, setAmount] = useState("");

  const fee = summary?.fee || {};
  const outstanding = Number(fee?.outstanding || 0);
  const amountDue = Number(fee?.amount_due || 0);
  const totalPaid = Number(fee?.total_paid || 0);
  const hasInvoice = !!fee?.has_invoice;
  const isFullyPaid = !!fee?.is_fully_paid;
  const canPay = !!fee?.can_pay;
  const paymentStatus = fee?.payment_status || "awaiting_invoice";
  const paymentStatusLabel = fee?.payment_status_label || "Awaiting Invoice";
  const statusMessage =
    fee?.status_message ||
    "School fees online payment has not been inputed for this term. Please contact your school admin.";
  const referenceFromQuery = searchParams.get("reference");

  const invoiceItems = useMemo(() => {
    if (Array.isArray(fee?.line_items) && fee.line_items.length > 0) {
      return fee.line_items;
    }
    if (hasInvoice && amountDue > 0) {
      return [{ description: "School Fees", amount: amountDue }];
    }
    return [];
  }, [fee?.line_items, hasInvoice, amountDue]);

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
    if (canPay && outstanding > 0) {
      setAmount(String(outstanding));
    } else {
      setAmount("");
    }
  }, [summary, outstanding, canPay]);

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
    if (!canPay) {
      return alert("School fees online payment is not available until your invoice is ready.");
    }

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

  const downloadReceipt = async (paymentId, reference) => {
    try {
      const res = await api.get(`/api/student/school-fees/payments/${paymentId}/receipt`, {
        responseType: "blob",
      });
      const blob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = parseFileName(res.headers, `fee_receipt_${reference || paymentId}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to download receipt.");
    }
  };

  const downloadInvoice = async () => {
    if (!hasInvoice) {
      return alert("Invoice is not available yet. Please contact your school admin.");
    }

    setDownloadingInvoice(true);
    try {
      const res = await api.get(fee?.invoice_download_url || "/api/student/school-fees/invoice", {
        responseType: "blob",
      });
      const blob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = parseFileName(res.headers, "school_fee_invoice.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to download invoice.");
    } finally {
      setDownloadingInvoice(false);
    }
  };

  return (
    <div className="payx-page payx-page--student">
      <section className="payx-hero">
        <div>
          <span className="payx-pill">Student School Fees</span>
          <h2 className="payx-title">Review your invoice and track fee payments</h2>
          <p className="payx-subtitle">
            See the invoice your school has prepared for this term, confirm whether payment has been made, and pay online only when the invoice is available.
          </p>
          <div className="payx-meta">
            <span>{summary?.current_session?.session_name || summary?.current_session?.academic_year || "Session -"}</span>
            <span>{summary?.current_term?.name || "Term -"}</span>
            <span>{paymentStatusLabel}</span>
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
                    <span className="payx-label">Invoice Total</span>
                    <span className="payx-value">
                      {hasInvoice ? `NGN ${formatMoney(amountDue)}` : "Awaiting invoice"}
                    </span>
                  </div>
                  <div className="payx-row">
                    <span className="payx-label">Total Paid</span>
                    <span className="payx-value">NGN {formatMoney(totalPaid)}</span>
                  </div>
                  <div className="payx-row">
                    <span className="payx-label">Outstanding</span>
                    <span className="payx-value">
                      {hasInvoice ? `NGN ${formatMoney(outstanding)}` : "Awaiting invoice"}
                    </span>
                  </div>
                  <div className="payx-row">
                    <span className="payx-label">Status</span>
                    <span className="payx-value">{paymentStatusLabel}</span>
                  </div>
                </div>
              </article>

              <article className="payx-card">
                <h3>Invoice</h3>
                <p className={stateClass(paymentStatus)}>{statusMessage}</p>

                {hasInvoice ? (
                  <>
                    <div className="payx-kv" style={{ marginTop: 12 }}>
                      {invoiceItems.map((item, index) => (
                        <div className="payx-fee-row" key={`${item.description}-${index}`}>
                          <span className="payx-label">{item.description}</span>
                          <span className="payx-value">NGN {formatMoney(item.amount)}</span>
                        </div>
                      ))}
                    </div>

                    <div className="payx-kv" style={{ marginTop: 12 }}>
                      <div className="payx-row">
                        <span className="payx-label">Total Invoice</span>
                        <span className="payx-value">NGN {formatMoney(amountDue)}</span>
                      </div>
                      <div className="payx-row">
                        <span className="payx-label">Payment Made</span>
                        <span className="payx-value">NGN {formatMoney(totalPaid)}</span>
                      </div>
                      <div className="payx-row">
                        <span className="payx-label">Outstanding Balance</span>
                        <span className="payx-value">NGN {formatMoney(outstanding)}</span>
                      </div>
                    </div>

                    <div className="payx-actions">
                      <button
                        className="payx-btn payx-btn--soft"
                        onClick={downloadInvoice}
                        disabled={downloadingInvoice || paying || verifying}
                      >
                        {downloadingInvoice ? "Preparing Invoice..." : "Download School Fee Invoice"}
                      </button>
                    </div>

                    {canPay ? (
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
                          You can pay partially. Remaining balance will continue to show until the invoice is fully settled.
                        </small>
                      </>
                    ) : null}
                  </>
                ) : (
                  <small className="payx-small">
                    Once your school admin inputs online school fees for the current term, your invoice will appear here and the payment button will become available.
                  </small>
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
                      <th>Reason</th>
                      <th>Date</th>
                      <th>Receipt</th>
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
                        <td>{p.failure_reason || "-"}</td>
                        <td>{p.paid_at || p.created_at || "-"}</td>
                        <td>
                          {p.status === "success" ? (
                            <button onClick={() => downloadReceipt(p.id, p.reference)}>
                              Download
                            </button>
                          ) : (
                            "-"
                          )}
                        </td>
                      </tr>
                    ))}
                    {(summary?.payments || []).length === 0 ? (
                      <tr>
                        <td colSpan="7">No payments yet.</td>
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
