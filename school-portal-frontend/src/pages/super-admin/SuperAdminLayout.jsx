import { Outlet, Link } from "react-router-dom";

function SuperAdminLayout() {
  return (
    <div style={{ display: "flex", height: "100vh" }}>
      {/* Sidebar */}
      <aside style={styles.sidebar}>
        <h2>Super Admin</h2>
        <nav style={styles.nav}>
          <Link to="/super-admin">Overview</Link>
          <Link to="/super-admin/schools">Schools</Link>
          <Link to="/super-admin/users">Users</Link>
          <Link to="/super-admin/dashboard">Back to Dashboard</Link>
        </nav>
      </aside>

      {/* Content */}
      <main style={styles.main}>
        <Outlet />
      </main>
    </div>
  );
}

const styles = {
  sidebar: {
    width: 240,
    background: "#111827",
    color: "#fff",
    padding: 20,
  },
  nav: {
    display: "flex",
    flexDirection: "column",
    gap: 12,
    marginTop: 20,
  },
  main: {
    flex: 1,
    padding: 30,
    background: "#f9fafb",
  },
};

export default SuperAdminLayout;
