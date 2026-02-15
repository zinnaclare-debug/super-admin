import { useEffect, useState } from "react";
import api from "../../api/axios";

export default function Schools() {
  const [schools, setSchools] = useState([]);
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [subdomain, setSubdomain] = useState("");

  const loadSchools = async () => {
    const res = await api.get("/api/schools");
    setSchools(res.data);
  };

  useEffect(() => {
    loadSchools();
  }, []);

  const createSchool = async (e) => {
    e.preventDefault();

    await api.post("/api/schools", {
      name,
      slug,
      subdomain,
    });

    loadSchools();
  };

  return (
    <div>
      <h1>Schools</h1>

      <form onSubmit={createSchool}>
        <input placeholder="Name" onChange={(e) => setName(e.target.value)} />
        <input placeholder="Slug" onChange={(e) => setSlug(e.target.value)} />
        <input placeholder="Subdomain" onChange={(e) => setSubdomain(e.target.value)} />
        <button>Create</button>
      </form>

      <ul>
        {schools.map((school) => (
          <li key={school.id}>
            {school.name} ({school.subdomain})
          </li>
        ))}
      </ul>
    </div>
  );
}
