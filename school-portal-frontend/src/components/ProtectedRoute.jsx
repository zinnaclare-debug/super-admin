import { Navigate } from "react-router-dom";
import { getStoredToken, getStoredUser } from "../utils/authStorage";

export default function ProtectedRoute({ children, roles }) {
  const token = getStoredToken();
  const user = getStoredUser();

  // Not logged in
  if (!token || !user) {
    return <Navigate to="/login" replace />;
  }

  // Role-based protection
  if (roles && !roles.includes(user.role)) {
    // Redirect users to their own dashboard instead of the shared /dashboard fallback
    if (user.role === "school_admin") {
      return <Navigate to="/school/dashboard" replace />;
    }
    // default fallback
    return <Navigate to="/login" replace />;
  }

  return children;
}
