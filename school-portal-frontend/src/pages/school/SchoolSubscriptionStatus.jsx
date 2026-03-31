import { useEffect, useRef, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import api from "../../services/api";

const emptySummary = {
  status: "free",
  status_label: "FREE VERSION",
  status_tone: "yellow",
  status_reason: "",
  current_session: null,
  current_term: null,
  student_count: 0,
  settings: {
    amount_per_student_per_term: 0,
    currency: "NGN",
    tax_percent: 1.6,
    bank_name: "ECOBANK",
    bank_account_number: "3680106500",
    bank_account_name: "LYTEBRIDGE PROFESSIONAL SERVICE LTD",
  },
  quotes: {
    termly: null,
    yearly: null,
  },
  active_invoice: null,
  latest_pending_invoice: null,
};

const formatMoney = (amount, currency = "NGN") => {
  const value = Number(amount || 0);
  return `${currency} ${value.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
};

export default function SchoolSubscriptionStatus() {
  const [summary, setSummary] = useState(emptySummary);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [busyCycle, setBusyCycle] = useState("");
  const [bankCycle, setBankCycle] = useState("");
  const [bankForms, setBankForms] = useState({});

  const location = useLocation();
  const navigate = useNavigate();
  const hasVerifiedRef = useRef(false);

  const loadSummary = async () => {
    setLoading(true);
    try {
      const res = await api.get("/api/school-admin/subscription");
      setSummary(res.data?.data || emptySummary);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load subscription status.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSummary();
  }, []);

  useEffect(() => {
    const search = new URLSearchParams(location.search);
    const reference = search.get("reference") || search.get("trxref");
    if (!reference || hasVerifiedRef.current) {
      return;
    }

    hasVerifiedRef.current = true;
    (async () => {
      try {
        const res = await api.get("/api/school-admin/subscription/verify", {
          params: { reference },
        });
        setSummary(res.data?.data?.summary || emptySummary);
        alert(res.data?.message || "Subscription payment verified successfully.");
      } catch (err) {
        alert(err?.response?.data?.message || "Failed to verify subscription payment.");
      } finally {
        navigate(location.pathname, { replace: true });
      }
    })();
  }, [location.pathname, location.search, navigate]);

  const triggerPaystack = async (cycle) => {
    setBusyCycle(cycle);
    try {
      const res = await api.post("/api/school-admin/subscription/initialize", {
        billing_cycle: cycle,
      });
      const authUrl = res.data?.data?.authorization_url;
      if (!authUrl) {
        throw new Error("Missing Paystack authorization URL.");
      }
      window.location.href = authUrl;
    } catch (err) {
      alert(err?.response?.data?.message || err?.message || "Failed to initialize subscription payment.");
      setBusyCycle("");
    }
  };

  const submitBankTransfer = async (cycle) => {
    setBusyCycle(cycle);
    try {
      const res = await api.post("/api/school-admin/subscription/bank-transfer", {
        billing_cycle: cycle,
        transfer_reference: bankForms[cycle]?.transfer_reference || "",
        note: bankForms[cycle]?.note || "",
      });
      setSummary(res.data?.data?.summary || emptySummary);
      setBankCycle(cycle);
      alert(res.data?.message || "Bank transfer submitted for review.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to submit bank transfer.");
    } finally {
      setBusyCycle("");
    }
  };

  const updateBankForm = (cycle, field, value) => {
    setBankForms((prev) => ({
      ...prev,
      [cycle]: {
        ...(prev[cycle] || {}),
        [field]: value,
      },
    }));
  };

  const quoteCards = [summary?.quotes?.termly, summary?.quotes?.yearly].filter(Boolean);
  const buttonLabel = loading ? "LOADING..." : (summary.status_label || "FREE VERSION");

  return (
    <>
      <button
        type="button"
        className={`sd-status-button sd-status-button--${summary.status_tone || "yellow"}`}
        onClick={() => setOpen(true)}
      >
        {buttonLabel}
      </button>

      {open ? (
        <div className="sd-modal-backdrop" onClick={() => setOpen(false)}>
          <div className="sd-modal-card sd-subscription-modal" onClick={(e) => e.stopPropagation()}>
            <div className="sd-modal-head">
              <div>
                <h3>School Subscription</h3>
                <p className="sd-modal-help">{summary.status_reason || "Manage your current school subscription."}</p>
              </div>
              <button type="button" className="sd-modal-close" onClick={() => setOpen(false)}>
                Close
              </button>
            </div>

            <div className="sd-subscription-overview">
              <div className="sd-subscription-stat">
                <span>Status</span>
                <strong>{summary.status_label || "FREE VERSION"}</strong>
              </div>
              <div className="sd-subscription-stat">
                <span>Current Session</span>
                <strong>{summary.current_session?.session_name || "Not set"}</strong>
              </div>
              <div className="sd-subscription-stat">
                <span>Current Term</span>
                <strong>{summary.current_term?.name || "Not set"}</strong>
              </div>
              <div className="sd-subscription-stat">
                <span>Billable Students</span>
                <strong>{Number(summary.student_count || 0).toLocaleString()}</strong>
              </div>
            </div>

            {summary.active_invoice ? (
              <div className="sd-subscription-highlight sd-subscription-highlight--active">
                <h4>Active Coverage</h4>
                <p>
                  {summary.active_invoice.billing_cycle_label} invoice <strong>{summary.active_invoice.reference}</strong> is active.
                </p>
                <p>Total Paid: {formatMoney(summary.active_invoice.total_amount, summary.active_invoice.currency)}</p>
              </div>
            ) : null}

            {summary.latest_pending_invoice ? (
              <div className="sd-subscription-highlight sd-subscription-highlight--pending">
                <h4>Latest Pending Invoice</h4>
                <p>
                  {summary.latest_pending_invoice.reference} is currently {summary.latest_pending_invoice.status_label.toLowerCase()}.
                </p>
              </div>
            ) : null}

            {summary.status === "free" ? (
              <div className="sd-subscription-free">
                <p>This school is currently on the free version.</p>
              </div>
            ) : null}

            <div className="sd-subscription-cards">
              {quoteCards.length === 0 ? (
                <p className="sd-note">Subscription billing has not been configured yet.</p>
              ) : quoteCards.map((quote) => {
                const cycle = quote.billing_cycle;
                const bankForm = bankForms[cycle] || { transfer_reference: "", note: "" };
                const showBankDetails = bankCycle === cycle;

                return (
                  <article key={cycle} className="sd-subscription-card-item">
                    <h4>{quote.label}</h4>
                    <div className="sd-subscription-line">
                      <span>Student Count</span>
                      <strong>{quote.student_count}</strong>
                    </div>
                    <div className="sd-subscription-line">
                      <span>Bill</span>
                      <strong>{formatMoney(quote.subtotal, quote.currency)}</strong>
                    </div>
                    <div className="sd-subscription-line">
                      <span>Tax ({quote.tax_percent}%)</span>
                      <strong>{formatMoney(quote.tax_amount, quote.currency)}</strong>
                    </div>
                    <div className="sd-subscription-line sd-subscription-line--total">
                      <span>Total Amount Due</span>
                      <strong>{formatMoney(quote.total_amount, quote.currency)}</strong>
                    </div>

                    <div className="sd-subscription-actions">
                      <button type="button" onClick={() => triggerPaystack(cycle)} disabled={busyCycle === cycle || summary.status === "free"}>
                        {busyCycle === cycle ? "Working..." : "Pay Online"}
                      </button>
                      <button
                        type="button"
                        className="sd-subscription-alt"
                        onClick={() => setBankCycle((prev) => (prev === cycle ? "" : cycle))}
                        disabled={summary.status === "free"}
                      >
                        Pay With Bank
                      </button>
                    </div>

                    {showBankDetails ? (
                      <div className="sd-bank-box">
                        <h5>Bank Transfer Details</h5>
                        <p><strong>{summary.settings?.bank_name || "ECOBANK"}</strong></p>
                        <p>{summary.settings?.bank_account_number || "3680106500"}</p>
                        <p>{summary.settings?.bank_account_name || "LYTEBRIDGE PROFESSIONAL SERVICE LTD"}</p>

                        <label>
                          Transfer Reference
                          <input
                            type="text"
                            value={bankForm.transfer_reference || ""}
                            onChange={(e) => updateBankForm(cycle, "transfer_reference", e.target.value)}
                            placeholder="Bank transfer reference"
                          />
                        </label>

                        <label>
                          Note
                          <textarea
                            rows="3"
                            value={bankForm.note || ""}
                            onChange={(e) => updateBankForm(cycle, "note", e.target.value)}
                            placeholder="Optional note for Super Admin"
                          />
                        </label>

                        <button type="button" onClick={() => submitBankTransfer(cycle)} disabled={busyCycle === cycle}>
                          {busyCycle === cycle ? "Submitting..." : "Submit Bank Payment Notice"}
                        </button>
                      </div>
                    ) : null}
                  </article>
                );
              })}
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}

