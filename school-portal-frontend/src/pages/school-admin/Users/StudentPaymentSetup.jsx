import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

const EMPTY_ROWS = Array.from({ length: 10 }, () => ({ description: "", amount: "" }));

function normalizeRows(rows = []) {
  const next = EMPTY_ROWS.map(() => ({ description: "", amount: "" }));
  rows.slice(0, 10).forEach((row, idx) => {
    next[idx] = {
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

  const totalAmount = useMemo(
    () => rows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0),
    [rows]
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

  if (loading) return <p>Loading student payment setup...</p>;

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", gap: 12, flexWrap: "wrap" }}>
        <h3 style={{ margin: 0 }}>Set Student Payment</h3>
      </div>

      <div
        style={{
          border: "1px solid #e5e7eb",
          borderRadius: 10,
          padding: 14,
          marginTop: 12,
          background: "#f8fafc",
          display: "grid",
          gap: 8,
        }}
      >
        <div><strong>Name:</strong> {details?.student?.name || "-"}</div>
        <div><strong>Email:</strong> {details?.student?.email || "-"}</div>
        <div><strong>Username:</strong> {details?.student?.username || "-"}</div>
        <div>
          <strong>Level/Class/Department:</strong>{" "}
          {details?.student?.education_level || "-"} / {details?.student?.class_name || "-"} / {details?.student?.department_name || "-"}
        </div>
        <div>
          <strong>Current Session/Term:</strong>{" "}
          {details?.current_session?.session_name || details?.current_session?.academic_year || "-"} / {details?.current_term?.name || "-"}
        </div>
        <div>
          <strong>Centralized Fee (fallback):</strong> NGN {Number(details?.fallback?.amount_due || 0).toFixed(2)}
        </div>
      </div>

      <div style={{ marginTop: 14 }}>
        <table border="1" cellPadding="8" cellSpacing="0" width="100%">
          <thead>
            <tr>
              <th style={{ width: 80 }}>No.</th>
              <th>Fee Description</th>
              <th style={{ width: 220 }}>Amount (NGN)</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, idx) => (
              <tr key={idx}>
                <td>{idx + 1}</td>
                <td>
                  <input
                    value={row.description}
                    onChange={(e) => updateRow(idx, "description", e.target.value)}
                    placeholder={`Fee item ${idx + 1}`}
                    style={{ width: "100%", padding: 8 }}
                  />
                </td>
                <td>
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    value={row.amount}
                    onChange={(e) => updateRow(idx, "amount", e.target.value)}
                    placeholder="0.00"
                    style={{ width: "100%", padding: 8 }}
                  />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div style={{ marginTop: 12, display: "flex", justifyContent: "space-between", gap: 10, flexWrap: "wrap" }}>
        <div><strong>Custom Total:</strong> NGN {totalAmount.toFixed(2)}</div>
        <div style={{ display: "flex", gap: 8 }}>
          <button
            onClick={clearCustomPlan}
            disabled={saving}
            style={{ background: "#f59e0b", border: "1px solid #d97706", color: "#fff" }}
          >
            {saving ? "Please wait..." : "Use Centralized Fee"}
          </button>
          <button onClick={save} disabled={saving}>
            {saving ? "Saving..." : "Save Payment Plan"}
          </button>
        </div>
      </div>
    </div>
  );
}
