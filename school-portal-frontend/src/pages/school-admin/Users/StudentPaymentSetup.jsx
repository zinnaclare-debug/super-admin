import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import peopleArt from "../../../assets/users/people.svg";
import usersPerMinuteArt from "../../../assets/users/users-per-minute.svg";
import profileCardArt from "../../../assets/profile/profile-card.svg";
import "../../shared/PaymentsShowcase.css";
import "./StudentPaymentSetup.css";

const EMPTY_ROWS = Array.from({ length: 10 }, () => ({ enabled: false, description: "", amount: "" }));

function normalizeRows(rows = []) {
  const next = EMPTY_ROWS.map(() => ({ enabled: false, description: "", amount: "" }));
  rows.slice(0, 10).forEach((row, idx) => {
    const hasValue =
      String(row?.description || "").trim() !== "" ||
      !(row?.amount === null || row?.amount === undefined || row?.amount === "");
    next[idx] = {
      enabled: typeof row?.enabled === "boolean" ? row.enabled : hasValue,
      description: String(row?.description || ""),
      amount: row?.amount === null || row?.amount === undefined || row?.amount === ""
        ? ""
        : String(row.amount),
    };
  });
  return next;
}

export default function StudentPaymentSetup() {
  const navigate = useNavigate();
  const { userId } = useParams();

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [details, setDetails] = useState(null);
  const [rows, setRows] = useState(EMPTY_ROWS);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/school-admin/payments/students/${userId}/plan`);
      const data = res.data?.data || null;
      setDetails(data);
      setRows(normalizeRows(data?.plan?.line_items || []));
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load student payment setup.");
      navigate(-1);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);

  const totalAmount = useMemo(() =>
    rows.reduce((sum, row) => sum + (row.enabled ? (Number(row.amount) || 0) : 0), 0), [rows]
  );

  const updateRow = (index, field, value) => {
    setRows((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], [field]: value };
      return next;
    });
  };

  const save = async () => {
    const payload = {
      line_items: rows.map((row) => ({
        enabled: !!row.enabled,
        description: row.description,
        amount: row.amount === "" ? null : Number(row.amount),
      })),
    };

    setSaving(true);
    try {
      await api.put(`/api/school-admin/payments/students/${userId}/plan`, payload);
      alert("Student payment setup saved.");
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to save student payment setup.");
    } finally {
      setSaving(false);
    }
  };

  const clearCustomPlan = async () => {
    if (!window.confirm("Clear this custom plan and use centralized level school fee instead?")) return;

    setSaving(true);
    try {
      await api.put(`/api/school-admin/payments/students/${userId}/plan`, { line_items: [] });
      setRows(EMPTY_ROWS);
      alert("Custom payment setup cleared. Student now uses centralized level fee.");
      await load();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to clear custom payment setup.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <p className="payx-state payx-state--loading">Loading student payment setup...</p>;

  return (
    <div className="payx-page payx-page--admin student-payment-setup">
      <section className="payx-hero student-payment-setup__hero">
        <div>
          <span className="payx-pill">Student Fee Setup</span>
          <h2 className="payx-title">Set a custom payment plan for this student.</h2>
          <p className="payx-subtitle">
            Use the centralized school fee as the fallback, or define student-specific fee items for the current session and term.
          </p>
          <div className="payx-meta">
            <span>{details?.student?.name || "Student"}</span>
            <span>{details?.current_session?.session_name || details?.current_session?.academic_year || "Session -"}</span>
            <span>{details?.current_term?.name || "Term -"}</span>
          </div>
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main student-payment-setup__art-main">
            <img src={peopleArt} alt="" />
          </div>
          <div className="payx-art payx-art--card student-payment-setup__art-card">
            <img src={usersPerMinuteArt} alt="" />
          </div>
          <div className="payx-art payx-art--online student-payment-setup__art-online">
            <img src={profileCardArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel student-payment-setup__panel">
        <div className="payx-card student-payment-setup__card">
          <div className="student-payment-setup__toolbar">
            <div>
              <h3>Student Details</h3>
              <p>Review the student and current billing context before saving a custom plan.</p>
            </div>
            <button className="payx-btn payx-btn--soft" type="button" onClick={() => navigate(-1)}>
              Back
            </button>
          </div>

          <div className="payx-kv student-payment-setup__kv">
            <div className="payx-row">
              <span className="payx-label">Name</span>
              <span className="payx-value">{details?.student?.name || "-"}</span>
            </div>
            <div className="payx-row">
              <span className="payx-label">Email</span>
              <span className="payx-value">{details?.student?.email || "-"}</span>
            </div>
            <div className="payx-row">
              <span className="payx-label">Username</span>
              <span className="payx-value">{details?.student?.username || "-"}</span>
            </div>
            <div className="payx-row">
              <span className="payx-label">Level / Class / Department</span>
              <span className="payx-value">
                {details?.student?.education_level || "-"} / {details?.student?.class_name || "-"} / {details?.student?.department_name || "-"}
              </span>
            </div>
            <div className="payx-row">
              <span className="payx-label">Session / Term</span>
              <span className="payx-value">
                {details?.current_session?.session_name || details?.current_session?.academic_year || "-"} / {details?.current_term?.name || "-"}
              </span>
            </div>
            <div className="payx-row">
              <span className="payx-label">Centralized Fee</span>
              <span className="payx-value">NGN {Number(details?.fallback?.amount_due || 0).toFixed(2)}</span>
            </div>
          </div>
        </div>

        <div className="payx-card student-payment-setup__card">
          <div className="student-payment-setup__toolbar">
            <div>
              <h3>Custom Fee Items</h3>
              <p>Enable only the rows you want included in this student payment plan.</p>
            </div>
            <strong className="student-payment-setup__total">NGN {totalAmount.toFixed(2)}</strong>
          </div>

          <div className="payx-table-wrap student-payment-setup__table-wrap">
            <table className="payx-table student-payment-setup__table">
              <thead>
                <tr>
                  <th style={{ width: 80 }}>No.</th>
                  <th style={{ width: 90 }}>Use</th>
                  <th>Fee Description</th>
                  <th style={{ width: 220 }}>Amount (NGN)</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row, idx) => (
                  <tr key={idx}>
                    <td>{idx + 1}</td>
                    <td style={{ textAlign: "center" }}>
                      <input
                        type="checkbox"
                        checked={!!row.enabled}
                        onChange={(e) => updateRow(idx, "enabled", e.target.checked)}
                      />
                    </td>
                    <td>
                      <input
                        className="payx-input student-payment-setup__table-input"
                        value={row.description}
                        onChange={(e) => updateRow(idx, "description", e.target.value)}
                        placeholder={`Fee item ${idx + 1}`}
                      />
                    </td>
                    <td>
                      <input
                        className="payx-input student-payment-setup__table-input"
                        type="number"
                        min="0"
                        step="0.01"
                        value={row.amount}
                        onChange={(e) => updateRow(idx, "amount", e.target.value)}
                        placeholder="0.00"
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="student-payment-setup__footer">
            <div>
              <span className="payx-label">Custom Total</span>
              <strong className="payx-value">NGN {totalAmount.toFixed(2)}</strong>
            </div>
            <div className="payx-actions">
              <button className="payx-btn payx-btn--soft student-payment-setup__warning-btn" onClick={clearCustomPlan} disabled={saving}>
                {saving ? "Please wait..." : "Use Centralized Fee"}
              </button>
              <button className="payx-btn" onClick={save} disabled={saving}>
                {saving ? "Saving..." : "Save Payment Plan"}
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
