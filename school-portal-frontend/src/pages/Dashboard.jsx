import { useEffect, useState } from "react";
import api from "../services/api";

function Overview() {
  const [stats, setStats] = useState({
    schools: 0,
    active_users: 0,
    admins: 0,
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get("/api/super-admin/stats");
        setStats({
          schools: res.data?.schools ?? 0,
          active_users: res.data?.active_users ?? 0,
          admins: res.data?.admins ?? 0,
        });
      } catch {
        setStats({ schools: 0, active_users: 0, admins: 0 });
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  return (
    <div>
      <h1>Platform Overview</h1>
      <p>System-wide statistics</p>

      <div style={{ display: "flex", gap: 20, marginTop: 20 }}>
        <Stat title="Total Schools" value={loading ? "..." : String(stats.schools)} />
        <Stat title="Active Users" value={loading ? "..." : String(stats.active_users)} />
        <Stat title="Admins" value={loading ? "..." : String(stats.admins)} />
      </div>
    </div>
  );
}

function Stat({ title, value }) {
  return (
    <div style={styles.card}>
      <h3>{title}</h3>
      <p style={{ fontSize: 24, fontWeight: "bold" }}>{value}</p>
    </div>
  );
}

const styles = {
  card: {
    background: "#fff",
    padding: 20,
    borderRadius: 8,
    width: 200,
    boxShadow: "0 4px 12px rgba(0,0,0,0.1)",
  },
};

export default Overview;
