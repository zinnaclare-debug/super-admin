import { useEffect, useState } from "react";
import api from "../../../services/api";

export default function UserProfilePanel({ userId, onClose, onChanged }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [resettingPassword, setResettingPassword] = useState(false);

  const loadProfile = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/school-admin/users/${userId}`);
      setUser(res.data.data);
    } catch (e) {
      alert("Failed to load user profile");
      onClose();
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadProfile();
  }, [userId]);

  const toggleStatus = async () => {
    try {
      await api.patch(`/api/school-admin/users/${userId}/toggle`);
      await loadProfile();
      onChanged?.();
    } catch (e) {
      alert("Failed to update user status");
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

  return (
    <div
      style={{
        marginTop: 20,
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
            {user?.is_active ? "Active" : "Inactive"}
          </p>

          <button onClick={toggleStatus}>
            {user?.is_active ? "Disable User" : "Enable User"}
          </button>
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
