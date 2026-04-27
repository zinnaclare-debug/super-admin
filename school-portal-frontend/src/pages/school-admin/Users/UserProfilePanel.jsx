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

  useEffect(() => {
    if (typeof document === "undefined") return undefined;
    const originalOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = originalOverflow;
    };
  }, []);

  useEffect(() => {
    if (typeof window === "undefined") return undefined;
    const handleEscape = (event) => {
      if (event.key === "Escape") onClose?.();
    };
    window.addEventListener("keydown", handleEscape);
    return () => window.removeEventListener("keydown", handleEscape);
  }, [onClose]);

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
      onClick={onClose}
      style={{
        position: "fixed",
        inset: 0,
        zIndex: 1200,
        display: "grid",
        placeItems: "center",
        padding: 16,
        background: "rgba(15, 23, 42, 0.58)",
        backdropFilter: "blur(4px)",
        boxSizing: "border-box",
      }}
    >
      <div style={{ display: "flex", justifyContent: "space-between" }}>
        <div
          onClick={(event) => event.stopPropagation()}
          style={{
            width: "min(560px, 100%)",
            maxHeight: "min(88vh, 760px)",
            overflowY: "auto",
            border: "1px solid #dbeafe",
            padding: 18,
            borderRadius: 18,
            background: "#fff",
            boxShadow: "0 24px 48px rgba(15, 23, 42, 0.24)",
            boxSizing: "border-box",
          }}
        >
          <div style={{ display: "flex", justifyContent: "space-between", gap: 12, alignItems: "center", flexWrap: "wrap" }}>
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

              <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                <button onClick={toggleStatus}>
                  {user?.is_active ? "Disable User" : "Enable User"}
                </button>
                <button
                  onClick={resetPassword}
                  disabled={resettingPassword}
                >
                  {resettingPassword ? "Resetting..." : "Reset Password"}
                </button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

