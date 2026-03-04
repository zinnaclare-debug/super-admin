import { useEffect, useMemo, useState } from "react";
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

const parseFileName = (headers, fallback = "payments_summary.pdf") => {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback;
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
};

export default function SchoolAdminPayments() {
  const [configLoading, setConfigLoading] = useState(true);
  const [configLoaded, setConfigLoaded] = useState(false);
  const [saving, setSaving] = useState(false);
  const [tableLoading, setTableLoading] = useState(true);
  const [downloadingPdf, setDownloadingPdf] = useState(false);
  const [activeLevels, setActiveLevels] = useState([]);
  const [levelFees, setLevelFees] = useState({});
  const [paystackSubaccountCode, setPaystackSubaccountCode] = useState("");
  const [context, setContext] = useState(null);
  const [sessionConfigError, setSessionConfigError] = useState("");
  const [rows, setRows] = useState([]);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(null);
  const [viewMode, setViewMode] = useState("payments");
  const [statusFilter, setStatusFilter] = useState("all");
  const [levelFilter, setLevelFilter] = useState("");
  const [classFilter, setClassFilter] = useState("");
  const [departmentFilter, setDepartmentFilter] = useState("");

  const filterOptions = context?.filter_options || {};
  const levelOptions = Array.isArray(filterOptions.levels) ? filterOptions.levels : [];
  const classOptions = Array.isArray(filterOptions.classes) ? filterOptions.classes : [];
  const departmentOptions = Array.isArray(filterOptions.departments) ? filterOptions.departments : [];

  const totals = context?.totals || { paid: 0, outstanding: 0 };

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
      const params = {
        search,
        page,
        view: viewMode,
        status: statusFilter,
      };
      if (levelFilter) params.level = levelFilter;
      if (classFilter) params.class = classFilter;
      if (departmentFilter) params.department = departmentFilter;

      const res = await api.get("/api/school-admin/payments", { params });
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
  }, [
    configLoaded,
    search,
    page,
    sessionConfigError,
    viewMode,
    statusFilter,
    levelFilter,
    classFilter,
    departmentFilter,
  ]);

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

  const downloadSummaryPdf = async () => {
    setDownloadingPdf(true);
    try {
      const params = {
        view: viewMode,
        status: statusFilter,
        search,
      };
      if (levelFilter) params.level = levelFilter;
      if (classFilter) params.class = classFilter;
      if (departmentFilter) params.department = departmentFilter;

      const res = await api.get("/api/school-admin/payments/download/pdf", {
        params,
        responseType: "blob",
      });

      const blob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = parseFileName(res.headers, "payments_summary.pdf");
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to download payments PDF.");
    } finally {
      setDownloadingPdf(false);
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

  const modeTitle = useMemo(() => {
    if (viewMode === "outstanding") return "Outstanding Payment Table";
    return "Payment Records";
  }, [viewMode]);

  return (
    <div className="payx-page payx-page--admin">
      <section className="payx-hero">
        <div>
          <span className="payx-pill">School Admin Payments</span>
          <h2 className="payx-title">Configure fees and monitor school payments</h2>
          <p className="payx-subtitle">
            Filter by payment status, level, class, department, and view outstanding students with downloadable summary.
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
          <h3>{modeTitle}</h3>
          <div className="payx-actions" style={{ flexWrap: "wrap" }}>
            <select
              className="payx-input"
              value={viewMode}
              onChange={(e) => {
                setViewMode(e.target.value);
                setPage(1);
              }}
              style={{ width: 190 }}
            >
              <option value="payments">All Payment Records</option>
              <option value="outstanding">Outstanding Payments</option>
            </select>

            <select
              className="payx-input"
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              style={{ width: 190 }}
              disabled={viewMode === "outstanding"}
            >
              <option value="all">All Payments</option>
              <option value="successful">Successful Payments</option>
              <option value="unsuccessful">Unsuccessful Payments</option>
            </select>

            <select className="payx-input" value={levelFilter} onChange={(e) => { setLevelFilter(e.target.value); setPage(1); }} style={{ width: 170 }}>
              <option value="">All Levels</option>
              {levelOptions.map((v) => <option key={v} value={v}>{prettyLevel(v)}</option>)}
            </select>

            <select className="payx-input" value={classFilter} onChange={(e) => { setClassFilter(e.target.value); setPage(1); }} style={{ width: 170 }}>
              <option value="">All Classes</option>
              {classOptions.map((v) => <option key={v} value={v}>{v}</option>)}
            </select>

            <select className="payx-input" value={departmentFilter} onChange={(e) => { setDepartmentFilter(e.target.value); setPage(1); }} style={{ width: 170 }}>
              <option value="">All Departments</option>
              {departmentOptions.map((v) => <option key={v} value={v}>{v}</option>)}
            </select>

            <input
              className="payx-input"
              placeholder="Search name/email/username/reference"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              style={{ width: 320 }}
            />

            <button className="payx-btn payx-btn--soft" onClick={() => {
              setStatusFilter("all");
              setLevelFilter("");
              setClassFilter("");
              setDepartmentFilter("");
              setSearch("");
              setPage(1);
            }}>
              Clear Filters
            </button>

            <button className="payx-btn" onClick={downloadSummaryPdf} disabled={downloadingPdf}>
              {downloadingPdf ? "Downloading..." : "Download PDF"}
            </button>
          </div>

          <div className="payx-kv" style={{ marginTop: 10 }}>
            <div className="payx-row">
              <span className="payx-label">Total Paid</span>
              <span className="payx-value">NGN {Number(totals.paid || 0).toFixed(2)}</span>
            </div>
            <div className="payx-row">
              <span className="payx-label">Total Outstanding</span>
              <span className="payx-value">NGN {Number(totals.outstanding || 0).toFixed(2)}</span>
            </div>
          </div>

          {tableLoading ? (
            <p className="payx-state payx-state--loading" style={{ marginTop: 10 }}>Loading payments...</p>
          ) : (
            <>
              <div className="payx-table-wrap">
                {viewMode === "outstanding" ? (
                  <table className="payx-table">
                    <thead>
                      <tr>
                        <th>S/N</th>
                        <th>Name</th>
                        <th>Level</th>
                        <th>Class</th>
                        <th>Department</th>
                        <th>Amount Paid</th>
                        <th>Amount Remaining</th>
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((r, idx) => (
                        <tr key={`${r.student?.id || idx}`}>
                          <td>{((meta?.current_page || 1) - 1) * (meta?.per_page || rows.length || 0) + idx + 1}</td>
                          <td>{r.student?.name || "-"}</td>
                          <td>{prettyLevel(r.level || "-")}</td>
                          <td>{r.class_name || "-"}</td>
                          <td>{r.department_name || "-"}</td>
                          <td>NGN {Number(r.amount_paid || 0).toFixed(2)}</td>
                          <td>NGN {Number(r.amount_outstanding || 0).toFixed(2)}</td>
                        </tr>
                      ))}
                      {rows.length === 0 ? (
                        <tr>
                          <td colSpan="7">No outstanding students found.</td>
                        </tr>
                      ) : null}
                    </tbody>
                  </table>
                ) : (
                  <table className="payx-table">
                    <thead>
                      <tr>
                        <th>S/N</th>
                        <th>Name</th>
                        <th>Level</th>
                        <th>Class</th>
                        <th>Department</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Reference</th>
                        <th>Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((r, idx) => (
                        <tr key={r.id}>
                          <td>{((meta?.current_page || 1) - 1) * (meta?.per_page || rows.length || 0) + idx + 1}</td>
                          <td>{r.student?.name || "-"}</td>
                          <td>{prettyLevel(r.level || "-")}</td>
                          <td>{r.class_name || "-"}</td>
                          <td>{r.department_name || "-"}</td>
                          <td>{r.currency} {Number(r.amount_paid || 0).toFixed(2)}</td>
                          <td>{r.status}</td>
                          <td>{r.failure_reason || "-"}</td>
                          <td>{r.reference}</td>
                          <td>{r.paid_at || r.created_at || "-"}</td>
                        </tr>
                      ))}
                      {rows.length === 0 ? (
                        <tr>
                          <td colSpan="10">No payments found.</td>
                        </tr>
                      ) : null}
                    </tbody>
                  </table>
                )}
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

