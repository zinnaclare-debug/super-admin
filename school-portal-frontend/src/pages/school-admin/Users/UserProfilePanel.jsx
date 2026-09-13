import { useEffect, useState } from "react";
import api from "../../../services/api";

export default function UserProfilePanel({ userId, onClose, onChanged }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [resettingPassword, setResettingPassword] = useState(false);
  const [showDisableOptions, setShowDisableOptions] = useState(false);
  const [disableReason, setDisableReason] = useState("left_school");
  const [updatingAccess, setUpdatingAccess] = useState(false);

  const loadProfile = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/school-admin/users/${userId}`);
      setUser(res.data.data);
    } catch (e) {
      alert(e?.response?.status === 401
        ? "Your login session has expired. Please sign in again."
        : (e?.response?.data?.message || "Failed to load user profile"));
      onClose();
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadProfile();
  }, [userId]);

  const toggleStaffStatus = async () => {
    try {
      await api.patch(`/api/school-admin/users/${userId}/toggle`);
      await loadProfile();
      onChanged?.();
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to update user status");
    }
  };

  const updateStudentAccess = async (action, reason = null) => {
    setUpdatingAccess(true);
    try {
      const res = await api.post("/api/school-admin/users/students/access", {
        ids: [userId],
        action,
        reason,
      });
      setShowDisableOptions(false);
      await loadProfile();
      onChanged?.();
      alert(res.data?.message || "Student access updated.");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to update student access.");
    } finally {
      setUpdatingAccess(false);
    }
  };

  const resetPassword = async () => {
    const password = window.prompt(`Enter new password for ${user?.name || "this user"}:`);
    if (!password) return;
    if (password.length < 6) {
      alert("Password must be at least 6 characters.");
      return;
    }

    const confirmPassword = window.prompt("Confirm new password:");
    if (confirmPassword !== password) {
      alert("Passwords do not match.");
      return;
    }

    setResettingPassword(true);
    try {
      await api.post(`/api/school-admin/users/${userId}/reset-password`, { password });
      alert("Password reset successful.");
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to reset password.");
    } finally {
      setResettingPassword(false);
    }
  };

  const reasonLabel = disableReason === "left_school" ? "Left School" : "Fees Unpaid";
  const isStudent = user?.role === "student";
  const isGraduated = user?.status === "graduated";
  const isFeesUnpaid = user?.exit_reason === "fees_unpaid";
  const isLeftSchool = user?.exit_reason === "left_school";

  return (
    <div
      style={{
        marginTop: 0,
        border: "1px solid #ddd",
        padding: 16,
        borderRadius: 8,
        background: "#fff",
      }}
    >
      <div style={{ display: "flex", justifyContent: "space-between" }}>
        <strong>User Profile</strong>
        <button onClick={onClose}>Close</button>
      </div>

      {loading ? (
        <p>Loading profile...</p>
      ) : (
        <>
          <p><strong>Name:</strong> {user?.name}</p>
          <p><strong>Email:</strong> {user?.email || "-"}</p>
          <p><strong>Role:</strong> {user?.role}</p>
          <p>
            <strong>Status:</strong>{" "}
            {isGraduated ? "Graduated" : isFeesUnpaid ? "Fees Unpaid" : (user?.is_active ? "Active" : "Inactive")}
          </p>

          {isGraduated ? (
            <p style={{ margin: "8px 0 0", color: "#92400e", fontWeight: 700 }}>
              Graduated account is locked. Use transcripts or printed results for this student.
            </p>
          ) : isStudent && user?.is_active ? (
            <>
              <button
                onClick={() => setShowDisableOptions((open) => !open)}
                style={{ background: "#dc2626", border: "1px solid #b91c1c", color: "#fff" }}
              >
                Disable User
              </button>
              {showDisableOptions ? (
                <div style={{ marginTop: 10, maxWidth: 440, padding: 12, border: "1px solid #fecaca", borderRadius: 8, background: "#fff7ed" }}>
                  <strong>Why is this student being disabled?</strong>
                  <label style={{ display: "block", marginTop: 10 }}>
                    <input
                      type="radio"
                      name={`disable-reason-${userId}`}
                      value="left_school"
                      checked={disableReason === "left_school"}
                      onChange={(event) => setDisableReason(event.target.value)}
                    />{" "}
                    Left School - re-enabling will require Super Admin approval.
                  </label>
                  <label style={{ display: "block", marginTop: 8 }}>
                    <input
                      type="radio"
                      name={`disable-reason-${userId}`}
                      value="fees_unpaid"
                      checked={disableReason === "fees_unpaid"}
                      onChange={(event) => setDisableReason(event.target.value)}
                    />{" "}
                    Fees Unpaid - School Admin can enable the student again later.
                  </label>
                  <div style={{ display: "flex", gap: 8, marginTop: 12, flexWrap: "wrap" }}>
                    <button
                      onClick={() => updateStudentAccess("disable", disableReason)}
                      disabled={updatingAccess}
                      style={{ background: "#dc2626", border: "1px solid #b91c1c", color: "#fff" }}
                    >
                      {updatingAccess ? "Disabling..." : `Confirm: ${reasonLabel}`}
                    </button>
                    <button onClick={() => setShowDisableOptions(false)} disabled={updatingAccess}>Cancel</button>
                  </div>
                </div>
              ) : null}
            </>
          ) : isStudent && isFeesUnpaid ? (
            <button onClick={() => updateStudentAccess("enable")} disabled={updatingAccess}>
              {updatingAccess ? "Enabling..." : "Enable User"}
            </button>
          ) : isStudent && isLeftSchool ? (
            <button
              onClick={() => updateStudentAccess("request_reactivation")}
              disabled={updatingAccess || user?.reactivation_requested}
            >
              {user?.reactivation_requested ? "Approval Requested" : (updatingAccess ? "Sending..." : "Request Enable Approval")}
            </button>
          ) : (
            <button onClick={toggleStaffStatus}>
              {user?.is_active ? "Disable User" : "Enable User"}
            </button>
          )}
          <button
            onClick={resetPassword}
            style={{ marginLeft: 8 }}
            disabled={resettingPassword}
          >
            {resettingPassword ? "Resetting..." : "Reset Password"}
          </button>
        </>
      )}
    </div>
  );
}