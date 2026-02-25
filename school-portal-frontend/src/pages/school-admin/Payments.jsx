import { useEffect, useState } from "react";
import api from "../../services/api";

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
    <div>
      {sessionConfigError ? (
        <p style={{ marginTop: 10, color: "#b45309" }}>{sessionConfigError}</p>
      ) : null}

      {configLoading ? (
        <p>Loading fee setup...</p>
      ) : (
        <div style={{ border: "1px solid #ddd", borderRadius: 10, padding: 14, marginTop: 12 }}>
          <p style={{ margin: "4px 0" }}>
            <strong>Current Session:</strong>{" "}
            {context?.current_session?.session_name || context?.current_session?.academic_year || "-"}
          </p>
          <p style={{ margin: "4px 0" }}>
            <strong>Current Term:</strong> {context?.current_term?.name || "-"}
          </p>
          <div style={{ marginTop: 12 }}>
            <strong>School Fee By Education Level (NGN):</strong>
            {!activeLevels.length ? (
              <p style={{ margin: "8px 0 0", opacity: 0.8 }}>
                No active levels found in this session.
              </p>
            ) : (
              <div style={{ marginTop: 10, display: "grid", gap: 8 }}>
                {activeLevels.map((level) => (
                  <div
                    key={level}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      gap: 8,
                      flexWrap: "wrap",
                      border: "1px solid #e5e7eb",
                      borderRadius: 8,
                      padding: "8px 10px",
                    }}
                  >
                    <label style={{ display: "flex", alignItems: "center", gap: 6, minWidth: 130 }}>
                      <input
                        type="checkbox"
                        checked={Boolean(levelFees[level]?.enabled)}
                        onChange={(e) => toggleLevel(level, e.target.checked)}
                      />
                      <span>{prettyLevel(level)}</span>
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      placeholder={`Enter ${prettyLevel(level)} fee`}
                      value={levelFees[level]?.amount ?? ""}
                      disabled={!levelFees[level]?.enabled}
                      onChange={(e) => changeLevelAmount(level, e.target.value)}
                      style={{ width: 220, padding: 8 }}
                    />
                  </div>
                ))}
              </div>
            )}
          </div>
          <div style={{ marginTop: 12, display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            <label htmlFor="paystack_subaccount_code"><strong>Paystack Subaccount Code:</strong></label>
            <input
              id="paystack_subaccount_code"
              type="text"
              value={paystackSubaccountCode}
              onChange={(e) => setPaystackSubaccountCode(e.target.value)}
              placeholder="e.g. ACCT_xxxxxxxxx"
              style={{ width: 280, padding: 10 }}
            />
            <button onClick={saveConfig} disabled={saving}>
              {saving ? "Saving..." : "Save Settings"}
            </button>
          </div>
          <small style={{ display: "block", marginTop: 8, opacity: 0.7 }}>
            Set this to route fees to your school subaccount. Leave blank to use main Paystack account.
          </small>
        </div>
      )}

      <div style={{ marginTop: 16 }}>
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

        {tableLoading ? (
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
                {rows.length === 0 && (
                  <tr>
                    <td colSpan="8">No payments found.</td>
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
    </div>
  );
}
