import { useEffect, useMemo, useState } from "react";
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
    allow_termly: true,
    allow_yearly: true,
    is_free_version: true,
    manual_status_override: null,
    bank_name: "ECOBANK",
    bank_account_number: "3680106500",
    bank_account_name: "LYTEBRIDGE PROFESSIONAL SERVICE LTD",
    notes: "",
  },
  quotes: {
    termly: null,
    yearly: null,
  },
  active_invoice: null,
  latest_pending_invoice: null,
  recent_invoices: [],
};

const toNumberString = (value) => {
  const parsed = Number(value ?? 0);
  return Number.isFinite(parsed) ? String(parsed) : "0";
};

const buildForm = (summary) => ({
  amount_per_student_per_term: toNumberString(summary?.settings?.amount_per_student_per_term),
  currency: String(summary?.settings?.currency || "NGN"),
  tax_percent: toNumberString(summary?.settings?.tax_percent ?? 1.6),
  allow_termly: Boolean(summary?.settings?.allow_termly),
  allow_yearly: Boolean(summary?.settings?.allow_yearly),
  is_free_version: Boolean(summary?.settings?.is_free_version),
  manual_status_override: String(summary?.settings?.manual_status_override || ""),
  bank_name: String(summary?.settings?.bank_name || "ECOBANK"),
  bank_account_number: String(summary?.settings?.bank_account_number || "3680106500"),
  bank_account_name: String(summary?.settings?.bank_account_name || "LYTEBRIDGE PROFESSIONAL SERVICE LTD"),
  notes: String(summary?.settings?.notes || ""),
});

const formatMoney = (amount, currency = "NGN") => {
  const value = Number(amount || 0);
  return `${currency} ${value.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
};

export default function SchoolSubscriptionSection({ schoolId }) {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [actingInvoiceId, setActingInvoiceId] = useState(null);
  const [summary, setSummary] = useState(emptySummary);
  const [form, setForm] = useState(buildForm(emptySummary));

  const loadBilling = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/super-admin/schools/${schoolId}/information/billing`);
      const nextSummary = res.data?.data || emptySummary;
      setSummary(nextSummary);
      setForm(buildForm(nextSummary));
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load subscription billing.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadBilling();
  }, [schoolId]);

  const invoiceRows = useMemo(() => Array.isArray(summary.recent_invoices) ? summary.recent_invoices : [], [summary]);

  const saveSettings = async () => {
    setSaving(true);
    try {
      const payload = {
        amount_per_student_per_term: Number(form.amount_per_student_per_term || 0),
        currency: (form.currency || "NGN").trim().toUpperCase() || "NGN",
        tax_percent: Number(form.tax_percent || 0),
        allow_termly: Boolean(form.allow_termly),
        allow_yearly: Boolean(form.allow_yearly),
        is_free_version: Boolean(form.is_free_version),
        manual_status_override: form.manual_status_override || null,
        bank_name: (form.bank_name || "").trim(),
        bank_account_number: (form.bank_account_number || "").trim(),
        bank_account_name: (form.bank_account_name || "").trim(),
        notes: (form.notes || "").trim() || null,
      };

      const res = await api.put(`/api/super-admin/schools/${schoolId}/information/billing`, payload);
      const nextSummary = res.data?.data || emptySummary;
      setSummary(nextSummary);
      setForm(buildForm(nextSummary));
      alert(res.data?.message || "Subscription billing saved.");
    } catch (err) {
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || err?.response?.data?.message || "Failed to save subscription billing.");
    } finally {
      setSaving(false);
    }
  };

  const updateInvoiceStatus = async (invoiceId, statusTarget) => {
    const confirmText = statusTarget === "active"
      ? "Mark this invoice active for the school?"
      : "Move this invoice back to pending payment?";

    if (!window.confirm(confirmText)) {
      return;
    }

    setActingInvoiceId(invoiceId);
    try {
      const res = await api.post(`/api/super-admin/schools/${schoolId}/subscription/invoices/${invoiceId}/status`, {
        status_target: statusTarget,
      });
      const nextSummary = res.data?.data?.summary || emptySummary;
      setSummary(nextSummary);
      setForm(buildForm(nextSummary));
      alert(res.data?.message || "Invoice status updated.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to update invoice status.");
    } finally {
      setActingInvoiceId(null);
    }
  };

  const deleteInvoice = async (invoiceId) => {
    if (!window.confirm("Delete this subscription invoice permanently?")) {
      return;
    }

    setActingInvoiceId(invoiceId);
    try {
      const res = await api.delete(`/api/super-admin/schools/${schoolId}/subscription/invoices/${invoiceId}`);
      const nextSummary = res.data?.data?.summary || emptySummary;
      setSummary(nextSummary);
      setForm(buildForm(nextSummary));
      alert(res.data?.message || "Invoice deleted successfully.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to delete invoice.");
    } finally {
      setActingInvoiceId(null);
    }
  };

  return (
    <section className="sai-card sai-subscription-card">
      <div className="sai-subscription-head">
        <div>
          <h3>Subscription Billing</h3>
          <p className="sai-note">Configure the school subscription plan, payment channels, tax, and manual override.</p>
        </div>
        <span className={`sai-status-pill sai-status-pill--${summary.status_tone || "yellow"}`}>
          {summary.status_label || "FREE VERSION"}
        </span>
      </div>

      {loading ? <p className="sai-note">Loading billing settings...</p> : null}

      {!loading ? (
        <>
          <div className="sai-subscription-summary">
            <div className="sai-subscription-tile">
              <span>Current Session</span>
              <strong>{summary.current_session?.session_name || "Not set"}</strong>
            </div>
            <div className="sai-subscription-tile">
              <span>Current Term</span>
              <strong>{summary.current_term?.name || "Not set"}</strong>
            </div>
            <div className="sai-subscription-tile">
              <span>Billable Students</span>
              <strong>{Number(summary.student_count || 0).toLocaleString()}</strong>
            </div>
            <div className="sai-subscription-tile">
              <span>Status Reason</span>
              <strong>{summary.status_reason || ""}</strong>
            </div>
          </div>

          <div className="sai-grid sai-subscription-grid">
            <div className="sai-field">
              <label>Amount Per Student / Term</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={form.amount_per_student_per_term}
                onChange={(e) => setForm((prev) => ({ ...prev, amount_per_student_per_term: e.target.value }))}
              />
            </div>

            <div className="sai-field">
              <label>Currency</label>
              <input
                type="text"
                maxLength={3}
                value={form.currency}
                onChange={(e) => setForm((prev) => ({ ...prev, currency: e.target.value.toUpperCase() }))}
              />
            </div>

            <div className="sai-field">
              <label>Tax Percent</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={form.tax_percent}
                onChange={(e) => setForm((prev) => ({ ...prev, tax_percent: e.target.value }))}
              />
            </div>

            <div className="sai-field">
              <label>Manual Status Override</label>
              <select
                value={form.manual_status_override}
                onChange={(e) => setForm((prev) => ({ ...prev, manual_status_override: e.target.value }))}
              >
                <option value="">System Derived</option>
                <option value="free">FREE VERSION</option>
                <option value="pending">PENDING PAYMENT</option>
                <option value="active">ACTIVE</option>
              </select>
            </div>

            <div className="sai-field sai-check-stack">
              <label>Payment Modes</label>
              <label className="sai-check">
                <input
                  type="checkbox"
                  checked={form.is_free_version}
                  onChange={(e) => setForm((prev) => ({ ...prev, is_free_version: e.target.checked }))}
                />
                <span>Free Version</span>
              </label>
              <label className="sai-check">
                <input
                  type="checkbox"
                  checked={form.allow_termly}
                  onChange={(e) => setForm((prev) => ({ ...prev, allow_termly: e.target.checked }))}
                />
                <span>Allow Termly Payment</span>
              </label>
              <label className="sai-check">
                <input
                  type="checkbox"
                  checked={form.allow_yearly}
                  onChange={(e) => setForm((prev) => ({ ...prev, allow_yearly: e.target.checked }))}
                />
                <span>Allow Yearly Payment</span>
              </label>
            </div>

            <div className="sai-field">
              <label>Bank Name</label>
              <input
                type="text"
                value={form.bank_name}
                onChange={(e) => setForm((prev) => ({ ...prev, bank_name: e.target.value }))}
              />
            </div>

            <div className="sai-field">
              <label>Bank Account Number</label>
              <input
                type="text"
                value={form.bank_account_number}
                onChange={(e) => setForm((prev) => ({ ...prev, bank_account_number: e.target.value }))}
              />
            </div>

            <div className="sai-field">
              <label>Bank Account Name</label>
              <input
                type="text"
                value={form.bank_account_name}
                onChange={(e) => setForm((prev) => ({ ...prev, bank_account_name: e.target.value }))}
              />
            </div>

            <div className="sai-field sai-field--wide">
              <label>Internal Notes</label>
              <textarea
                rows="3"
                value={form.notes}
                onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
                placeholder="Optional billing note for this school"
              />
            </div>
          </div>

          <div className="sai-subscription-quotes">
            {[summary.quotes?.termly, summary.quotes?.yearly].filter(Boolean).map((quote) => (
              <article key={quote.billing_cycle} className="sai-quote-card">
                <h4>{quote.label}</h4>
                <p>{quote.student_count} billable student(s)</p>
                <div className="sai-quote-list">
                  <span>Subtotal</span>
                  <strong>{formatMoney(quote.subtotal, quote.currency)}</strong>
                  <span>Tax ({quote.tax_percent}%)</span>
                  <strong>{formatMoney(quote.tax_amount, quote.currency)}</strong>
                  <span>Total Due</span>
                  <strong>{formatMoney(quote.total_amount, quote.currency)}</strong>
                </div>
              </article>
            ))}
          </div>

          <div className="sai-actions">
            <button type="button" onClick={saveSettings} disabled={saving}>
              {saving ? "Saving..." : "Save Subscription Billing"}
            </button>
          </div>

          <div className="sai-invoice-block">
            <div className="sai-subscription-head">
              <div>
                <h4>Recent Subscription Invoices</h4>
                <p className="sai-note">Use the action buttons below for bank-transfer reviews and manual active/pending control.</p>
              </div>
            </div>

            <div className="sai-grade-table-wrap">
              <table className="sai-grade-table sai-invoice-table">
                <thead>
                  <tr>
                    <th>Reference</th>
                    <th>Cycle</th>
                    <th>Status</th>
                    <th>Channel</th>
                    <th>Total</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {invoiceRows.length === 0 ? (
                    <tr>
                      <td colSpan="6">No subscription invoice yet.</td>
                    </tr>
                  ) : invoiceRows.map((invoice) => (
                    <tr key={invoice.id}>
                      <td>
                        <div className="sai-invoice-ref">
                          <strong>{invoice.reference}</strong>
                          <small>{invoice.created_at || ""}</small>
                        </div>
                      </td>
                      <td>{invoice.billing_cycle_label}</td>
                      <td>{invoice.status_label}</td>
                      <td>{invoice.payment_channel || "-"}</td>
                      <td>{formatMoney(invoice.total_amount, invoice.currency)}</td>
                      <td>
                        <div className="sai-inline-actions">
                          <button
                            type="button"
                            onClick={() => updateInvoiceStatus(invoice.id, "active")}
                            disabled={actingInvoiceId === invoice.id}
                          >
                            {actingInvoiceId === invoice.id ? "Working..." : "Mark Active"}
                          </button>
                          <button
                            type="button"
                            className="sai-action-secondary"
                            onClick={() => updateInvoiceStatus(invoice.id, "pending")}
                            disabled={actingInvoiceId === invoice.id}
                          >
                            Move To Pending
                          </button>
                          <button
                            type="button"
                            className="sai-action-secondary"
                            onClick={() => deleteInvoice(invoice.id)}
                            disabled={actingInvoiceId === invoice.id}
                          >
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      ) : null}
    </section>
  );
}


