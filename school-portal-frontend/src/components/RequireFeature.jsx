import { Navigate } from "react-router-dom";
import { useEffect, useState } from "react";
import api from "../services/api";

function RequireFeature({ feature, children }) {
  const [allowed, setAllowed] = useState(null);

  useEffect(() => {
    api.get("/api/schools/features").then((res) => {
      const enabled = res.data.data.some(
        f => f.feature === feature && f.enabled
      );
      setAllowed(enabled);
    });
  }, [feature]);

  if (allowed === null) return null;

  if (!allowed) {
    return <Navigate to="/school/dashboard" replace />;
  }

  return children;
}

export default RequireFeature;
