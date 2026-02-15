import { useEffect, useState } from "react";
import axios from "../../services/api";

export default function SuperAdmin() {
  const [schools, setSchools] = useState([]);

  useEffect(() => {
    axios.get("/super-admin/schools").then(res => {
      setSchools(res.data.data);
    });
  }, []);

  return (
    <div style={{ padding: 30 }}>
      <h1>Super Admin Dashboard</h1>

      <table border="1" cellPadding="10">
        <thead>
          <tr>
            <th>School Name</th>
            <th>Email</th>
            <th>Status</th>
          </tr>
        </thead>

        <tbody>
          {schools.map(school => (
            <tr key={school.id}>
              <td>{school.name}</td>
              <td>{school.email}</td>
              <td>{school.is_active ? "Active" : "Disabled"}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
