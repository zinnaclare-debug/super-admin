import { useEffect, useState } from "react";
import api from "../../services/api";
import payCardArt from "../../assets/payments/pay-with-credit-card.svg";
import creditCardArt from "../../assets/payments/credit-card-payments.svg";
import onlinePayArt from "../../assets/payments/online-payments.svg";
import "../shared/PaymentsShowcase.css";

const isMissingCurrentSessionTerm = (message = "") =>
  String(message).toLowerCase().includes("no current academic session/term configured");
const prettyLevel = (level) =>
  String(level || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

const buildInitialLevelFees = (levels = [], feesByLevel = {}) => {
  const next = {};
  levels.forEach((level) => {
    const raw = feesByLevel?.[level];
    next[level] = {
      enabled: raw !== null && raw !== undefined && raw !== "",
      amount: raw !== null && raw !== undefined && raw !== "" ? String(raw) : "",
    };
  });
  return next;
};

export default function SchoolAdminPayments() {
  const [configLoading, setConfigLoading] = useState(true);
  const [configLoaded, setConfigLoaded] = useState(false);
  const [saving, setSaving] = useState(false);
  const [tableLoading, setTableLoading] = useState(true);
  const [activeLevels, setActiveLevels] = useState([]);
  const [levelFees, setLevelFees] = useState({});
  const [paystackSubaccountCode, setPaystackSubaccountCode] = useState("");
  const [context, setContext] = useState(null);
  const [sessionConfigError, setSessionConfigError] = useState("");
  const [rows, setRows] = useState([]);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(null);

  const loadConfig = async () => {
    setConfigLoading(true);
    try {
      const res = await api.get("/api/school-admin/payments/config");
      const data = res.data?.data || null;
      setSessionConfigError("");
      setContext(data);
      const levels = Array.isArray(data?.active_levels) ? data.active_levels : [];
      setActiveLevels(levels);
      setLevelFees(buildInitialLevelFees(levels, data?.fees_by_level || {}));
      setPaystackSubaccountCode(String(data?.paystack_subaccount_code ?? ""));
    } catch (e) {
      const message = e?.response?.data?.message || "Failed to load payment config.";
      setSessionConfigError(message);
      setContext(null);
      setActiveLevels([]);
      setLevelFees({});
      setPaystackSubaccountCode("");
    } finally {
      setConfigLoading(false);
      setConfigLoaded(true);
    }
  };

  const loadPayments = async () => {
    if (sessionConfigError) {
      setRows([]);
      setMeta(null);
      setTableLoading(false);
      return;
    }

    setTableLoading(true);
    try {
      const res = await api.get("/api/school-admin/payments", { params: { search, page } });
      setRows(res.data?.data || []);
      setMeta(res.data?.meta || null);
      if (res.data?.context) setContext((prev) => ({ ...(prev || {}), ...res.data.context }));
    } catch (e) {
      const message = e?.response?.data?.message || "Failed to load payments.";
      if (isMissingCurrentSessionTerm(message)) setSessionConfigError(message);
      setRows([]);
      setMeta(null);
    } finally {
      setTableLoading(false);
    }
  };

  useEffect(() => {
    loadConfig();
  }, []);

  useEffect(() => {
    if (!configLoaded || sessionConfigError) return;
    loadPayments();
  }, [configLoaded, search, page, sessionConfigError]);

  const saveConfig = async () => {
    if (!activeLevels.length) {
      return alert("No active education levels found for this session.");
    }

    const payloadFees = {};
    for (const level of activeLevels) {
      const row = levelFees[level];
      if (!row?.enabled) continue;

      const amount = Number(row.amount);
      if (!Number.isFinite(amount) || amount < 0) {
        return alert(`Enter a valid fee amount for ${prettyLevel(level)}.`);
      }

      payloadFees[level] = amount;
    }

    setSaving(true);
    try {
      await api.put("/api/school-admin/payments/config", {
        fees_by_level: payloadFees,
        paystack_subaccount_code: paystackSubaccountCode.trim() || null,
      });
      alert("Payment settings saved.");
      await loadConfig();
      await loadPayments();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to save payment settings.");
    } finally {
      setSaving(false);
    }
  };

  const toggleLevel = (level, enabled) => {
    setLevelFees((prev) => ({
      ...prev,
      [level]: {
        enabled,
        amount: enabled ? prev[level]?.amount || "" : "",
      },
    }));
  };

  const changeLevelAmount = (level, amount) => {
    setLevelFees((prev) => ({
      ...prev,
      [level]: {
        enabled: true,
        amount,
      },
    }));
  };

  return (
    <div className="payx-page payx-page--admin">
      <section className="payx-hero">
        <div>
          <span className="payx-pill">School Admin Payments</span>
          <h2 className="payx-title">Configure fees and manage payment flow</h2>
          <p className="payx-subtitle">
            Set fee amount per education level, configure subaccount routing, and monitor student fee payments.
          </p>
          <div className="payx-meta">
            <span>{context?.current_session?.session_name || context?.current_session?.academic_year || "Session -"}</span>
            <span>{context?.current_term?.name || "Term -"}</span>
            <span>{activeLevels.length} active level{activeLevels.length === 1 ? "" : "s"}</span>
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
        {sessionConfigError ? <p className="payx-state payx-state--warn">{sessionConfigError}</p> : null}

        {configLoading ? (
          <p className="payx-state payx-state--loading">Loading fee setup...</p>
        ) : (
          <div className="payx-card">
            <h3>Fee Configuration</h3>
            <div className="payx-kv">
              <div className="payx-row">
                <span className="payx-label">Current Session</span>
                <span className="payx-value">
                  {context?.current_session?.session_name || context?.current_session?.academic_year || "-"}
                </span>
              </div>
              <div className="payx-row">
                <span className="payx-label">Current Term</span>
                <span className="payx-value">{context?.current_term?.name || "-"}</span>
              </div>
            </div>

            <div style={{ marginTop: 12 }}>
              <strong>School Fee By Education Level (NGN)</strong>
              {!activeLevels.length ? (
                <p className="payx-small">No active levels found in this session.</p>
              ) : (
                <div style={{ marginTop: 10, display: "grid", gap: 8 }}>
                  {activeLevels.map((level) => (
                    <div key={level} className="payx-fee-row">
                      <label style={{ display: "flex", alignItems: "center", gap: 6, minWidth: 130 }}>
                        <input
                          type="checkbox"
                          checked={Boolean(levelFees[level]?.enabled)}
                          onChange={(e) => toggleLevel(level, e.target.checked)}
                        />
                        <span>{prettyLevel(level)}</span>
                      </label>
                      <input
                        className="payx-input"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder={`Enter ${prettyLevel(level)} fee`}
                        value={levelFees[level]?.amount ?? ""}
                        disabled={!levelFees[level]?.enabled}
                        onChange={(e) => changeLevelAmount(level, e.target.value)}
                        style={{ width: 220 }}
                      />
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="payx-actions">
              <label htmlFor="paystack_subaccount_code"><strong>Paystack Subaccount Code:</strong></label>
              <input
                className="payx-input"
                id="paystack_subaccount_code"
                type="text"
                value={paystackSubaccountCode}
                onChange={(e) => setPaystackSubaccountCode(e.target.value)}
                placeholder="e.g. ACCT_xxxxxxxxx"
                style={{ width: 280 }}
              />
              <button className="payx-btn" onClick={saveConfig} disabled={saving}>
                {saving ? "Saving..." : "Save Settings"}
              </button>
            </div>
            <small className="payx-small">
              Set this to route fees to your school subaccount. Leave blank to use main Paystack account.
            </small>
          </div>
        )}

        <div className="payx-card" style={{ marginTop: 12 }}>
          <h3>Payment Records</h3>
          <div className="payx-actions">
            <input
              className="payx-input"
              placeholder="Search by name/email/username/reference"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              style={{ width: 360 }}
            />
            <button
              className="payx-btn payx-btn--soft"
              onClick={() => {
                setSearch("");
                setPage(1);
              }}
            >
              Clear
            </button>
          </div>

          {tableLoading ? (
            <p className="payx-state payx-state--loading" style={{ marginTop: 10 }}>Loading payments...</p>
          ) : (
            <>
              <div className="payx-table-wrap">
                <table className="payx-table">
                  <thead>
                    <tr>
                      <th>S/N</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Username</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Reference</th>
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
                        <td>{r.paid_at || r.created_at || "-"}</td>
                      </tr>
                    ))}
                    {rows.length === 0 ? (
                      <tr>
                        <td colSpan="8">No payments found.</td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>

              {meta ? (
                <div className="payx-actions" style={{ marginTop: 12 }}>
                  <button className="payx-btn payx-btn--soft" disabled={page <= 1} onClick={() => setPage(page - 1)}>
                    Prev
                  </button>
                  <div className="payx-label">
                    Page {meta.current_page} / {meta.last_page}
                  </div>
                  <button className="payx-btn payx-btn--soft" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>
                    Next
                  </button>
                </div>
              ) : null}
            </>
          )}
        </div>
      </section>
    </div>
  );
}
