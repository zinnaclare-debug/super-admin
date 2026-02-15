import { useEffect, useState } from "react";
import api from "../../services/api";

export default function SchoolAdminPayments() {
  const [configLoading, setConfigLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [tableLoading, setTableLoading] = useState(true);
  const [amountDue, setAmountDue] = useState("");
  const [paystackSubaccountCode, setPaystackSubaccountCode] = useState("");
  const [context, setContext] = useState(null);
  const [rows, setRows] = useState([]);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(null);

  const loadConfig = async () => {
    setConfigLoading(true);
    try {
      const res = await api.get("/api/school-admin/payments/config");
      const data = res.data?.data || null;
      setContext(data);
      setAmountDue(String(data?.amount_due ?? ""));
      setPaystackSubaccountCode(String(data?.paystack_subaccount_code ?? ""));
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load payment config.");
      setContext(null);
      setAmountDue("");
      setPaystackSubaccountCode("");
    } finally {
      setConfigLoading(false);
    }
  };

  const loadPayments = async () => {
    setTableLoading(true);
    try {
      const res = await api.get("/api/school-admin/payments", { params: { search, page } });
      setRows(res.data?.data || []);
      setMeta(res.data?.meta || null);
      if (res.data?.context) setContext((prev) => ({ ...(prev || {}), ...res.data.context }));
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load payments.");
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
    loadPayments();
  }, [search, page]);

  const saveConfig = async () => {
    const amount = Number(amountDue);
    if (!Number.isFinite(amount) || amount < 0) {
      return alert("Enter a valid school fee amount.");
    }

    setSaving(true);
    try {
      await api.put("/api/school-admin/payments/config", {
        amount_due: amount,
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

  return (
    <div>
      <h2>Payments</h2>

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
          <div style={{ marginTop: 12, display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            <label htmlFor="amount_due"><strong>School Fee Amount (NGN):</strong></label>
            <input
              id="amount_due"
              type="number"
              min="0"
              step="0.01"
              value={amountDue}
              onChange={(e) => setAmountDue(e.target.value)}
              style={{ width: 220, padding: 10 }}
            />
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
