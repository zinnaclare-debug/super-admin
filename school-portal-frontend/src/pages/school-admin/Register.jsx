import { useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import api from "../../services/api";

const prettyLevel = (value) =>
  String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

const toAbsoluteUrl = (url) => {
  if (!url) return "";
  if (/^(https?:\/\/|blob:|data:)/i.test(url)) return url;
  const base = (api.defaults.baseURL || "").replace(/\/$/, "");
  return `${base}${url.startsWith("/") ? "" : "/"}${url}`;
};

export default function Register() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const editUserId = searchParams.get("editUserId");
  const editRole = searchParams.get("role");
  const returnTo = searchParams.get("returnTo") || "/school/admin/users/staff/active";
  const isEditMode = Boolean(editUserId);

  const [step, setStep] = useState(1);
  const [username, setUsername] = useState("");

  const [photo, setPhoto] = useState(null);
  const [photoPreview, setPhotoPreview] = useState(null);
  const [removePhoto, setRemovePhoto] = useState(false);

  const [submitting, setSubmitting] = useState(false);
  const [educationLevels, setEducationLevels] = useState([]);

  const [form, setForm] = useState({
    role: "",
    education_level: "",

    name: "",
    email: "",
    password: "",
    sex: "",
    religion: "",
    dob: "",
    address: "",

    guardian_name: "",
    guardian_email: "",
    guardian_mobile: "",
    guardian_location: "",
    guardian_state_of_origin: "",
    guardian_occupation: "",
    guardian_relationship: "",

    staff_position: "",
  });

  const isStudent = form.role === "student";
  const isStaff = form.role === "staff";

  useEffect(() => {
    let mounted = true;
    const loadEducationLevels = async () => {
      try {
        const res = await api.get("/api/school-admin/class-templates");
        const templates = Array.isArray(res.data?.data) ? res.data.data : [];
        const levels = templates
          .filter((section) => Boolean(section?.enabled))
          .map((section) => String(section?.key || "").trim().toLowerCase())
          .filter(Boolean);
        if (mounted) {
          setEducationLevels(Array.from(new Set(levels)));
        }
      } catch {
        if (mounted) {
          setEducationLevels([]);
        }
      }
    };

    loadEducationLevels();
    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (!isEditMode || !editUserId) return;

    const loadEditData = async () => {
      setSubmitting(true);
      try {
        const res = await api.get(`/api/school-admin/users/${editUserId}/edit-data`);
        const data = res.data?.data || {};

        setUsername(data.username || "");
        setForm((prev) => ({
          ...prev,
          role: data.role || editRole || "",
          education_level: data.education_level || "",
          name: data.name || "",
          email: data.email || "",
          password: "",
          sex: data.sex || "",
          religion: data.religion || "",
          dob: data.dob || "",
          address: data.address || "",
          guardian_name: data.guardian_name || "",
          guardian_email: data.guardian_email || "",
          guardian_mobile: data.guardian_mobile || "",
          guardian_location: data.guardian_location || "",
          guardian_state_of_origin: data.guardian_state_of_origin || "",
          guardian_occupation: data.guardian_occupation || "",
          guardian_relationship: data.guardian_relationship || "",
          staff_position: data.staff_position || "",
        }));

        if (data.photo_url) {
          setPhotoPreview(data.photo_url);
        }
        setPhoto(null);
        setRemovePhoto(false);
      } catch (err) {
        alert(err?.response?.data?.message || "Failed to load user for editing");
      } finally {
        setSubmitting(false);
      }
    };

    loadEditData();
  }, [editRole, editUserId, isEditMode]);

  const handleChange = (e) => {
    setForm((p) => ({ ...p, [e.target.name]: e.target.value }));
  };

  const onPickPhoto = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setPhoto(file);
    setPhotoPreview(URL.createObjectURL(file));
    setRemovePhoto(false);
  };

  const buildFormData = (extra = {}) => {
    const fd = new FormData();

    Object.entries({ ...form, ...extra }).forEach(([k, v]) => {
      if (v !== null && v !== undefined && v !== "") fd.append(k, v);
    });

    if (isEditMode && removePhoto) {
      fd.append("remove_photo", "1");
    }

    if (photo) fd.append("photo", photo);

    return fd;
  };

  const preview = async (e) => {
    e.preventDefault();

    if (isEditMode) {
      return updateUser();
    }

    if (!form.role) return alert("Select user role");
    if (!form.name) return alert("Enter full name");
    if (!form.password || form.password.length < 6) return alert("Password must be at least 6 characters");

    // education_level required for staff (because you use it for class assignment)
    if (isStaff && !form.education_level) return alert("Select staff education level");
    if (isStaff && !form.sex) return alert("Select staff sex");
    if (isStaff && !form.dob) return alert("Select staff date of birth");

    // for students you can keep it optional, but if you want it required uncomment below:
    // if (isStudent && !form.education_level) return alert("Select student education level");

    setSubmitting(true);
    try {
      // IMPORTANT: preview should also accept FormData (so photo can be validated early)
      const fd = buildFormData();

      const res = await api.post("/api/school-admin/register/preview", fd);

      setUsername(res.data.username || "");
      setStep(2);
    } catch (err) {
      alert(err?.response?.data?.error || err?.response?.data?.message || err.message || "Preview failed");
    } finally {
      setSubmitting(false);
    }
  };

  const updateUser = async () => {
    if (!isEditMode || !editUserId) return;
    if (!form.name) return alert("Enter full name");
    if (form.password && form.password.length < 6) return alert("Password must be at least 6 characters");

    setSubmitting(true);
    try {
      const fd = buildFormData();
      await api.post(`/api/school-admin/users/${editUserId}/update`, fd);

      alert("User Updated");
      navigate(returnTo);
    } catch (err) {
      alert(err?.response?.data?.error || err?.response?.data?.message || err.message || "Update failed");
    } finally {
      setSubmitting(false);
    }
  };

  const confirm = async () => {
    if (!username) return alert("Username is empty");

    setSubmitting(true);
    try {
      // IMPORTANT: confirm MUST be FormData (so photo gets uploaded)
      const fd = buildFormData({ username });

      await api.post("/api/school-admin/register/confirm", fd);

      alert("User Created");

      // reset
      setStep(1);
      setUsername("");
      setPhoto(null);
      setPhotoPreview(null);

      setForm({
        role: "",
        education_level: "",
        name: "",
        email: "",
        password: "",
        sex: "",
        religion: "",
        dob: "",
        address: "",
        guardian_name: "",
        guardian_email: "",
        guardian_mobile: "",
        guardian_location: "",
        guardian_state_of_origin: "",
        guardian_occupation: "",
        guardian_relationship: "",
        staff_position: "",
      });
    } catch (err) {
      alert(err?.response?.data?.error || err?.response?.data?.message || err.message || "Confirm failed");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div style={{ maxWidth: 720 }}>
      {isEditMode && username && (
        <p style={{ marginTop: 4, opacity: 0.75 }}>
          Username: <strong>{username}</strong>
        </p>
      )}

      {step === 1 && (
        <form onSubmit={preview}>
          <h3>User Type</h3>
          <select name="role" value={form.role} onChange={handleChange} disabled={isEditMode}>
            <option value="">Select Role</option>
            <option value="student">Student</option>
            <option value="staff">Staff</option>
          </select>

          {/* Education Level (one place only) */}
          <h3 style={{ marginTop: 14 }}>
            Education Level {isStaff ? "(required for staff assignment)" : ""}
          </h3>
          <select name="education_level" value={form.education_level} onChange={handleChange}>
            <option value="">Select Level</option>
            {educationLevels.map((level) => (
              <option key={level} value={level}>
                {prettyLevel(level)}
              </option>
            ))}
            {form.education_level && !educationLevels.includes(form.education_level) && (
              <option value={form.education_level}>{prettyLevel(form.education_level)}</option>
            )}
          </select>

          <h3 style={{ marginTop: 14 }}>Basic Info</h3>
          <input name="name" placeholder="Full Name" value={form.name} onChange={handleChange} />
          <input name="email" placeholder="Email" value={form.email} onChange={handleChange} />
          <input
            type="password"
            name="password"
            placeholder={isEditMode ? "New Password (optional)" : "Password"}
            value={form.password}
            onChange={handleChange}
          />
          <input name="address" placeholder="Address" value={form.address} onChange={handleChange} />

          {/* Photo Widget */}
          <h3 style={{ marginTop: 14 }}>Photo (optional)</h3>
          <input type="file" accept="image/*" onChange={onPickPhoto} />
          {photoPreview && (
            <div style={{ marginTop: 10 }}>
              <img
                src={toAbsoluteUrl(photoPreview)}
                alt="preview"
                style={{
                  width: 110,
                  height: 110,
                  borderRadius: 12,
                  objectFit: "cover",
                  border: "1px solid #ddd",
                }}
              />
              <div>
                <button
                  type="button"
                  onClick={() => {
                    setPhoto(null);
                    setPhotoPreview(null);
                    if (isEditMode) setRemovePhoto(true);
                  }}
                  style={{ marginTop: 8 }}
                >
                  Remove Photo
                </button>
              </div>
            </div>
          )}

          {isStudent && (
            <>
              <h3 style={{ marginTop: 14 }}>Student Details</h3>

              <select name="sex" value={form.sex} onChange={handleChange}>
                <option value="">Sex</option>
                <option value="M">Male</option>
                <option value="F">Female</option>
              </select>

              <input name="religion" placeholder="Religion" value={form.religion} onChange={handleChange} />
              <input type="date" name="dob" value={form.dob} onChange={handleChange} />

              <h3 style={{ marginTop: 14 }}>Guardian</h3>
              <input name="guardian_name" placeholder="Name" value={form.guardian_name} onChange={handleChange} />
              <input name="guardian_email" placeholder="Email" value={form.guardian_email} onChange={handleChange} />
              <input name="guardian_mobile" placeholder="Mobile" value={form.guardian_mobile} onChange={handleChange} />
              <input name="guardian_location" placeholder="Location" value={form.guardian_location} onChange={handleChange} />
              <input
                name="guardian_state_of_origin"
                placeholder="State of Origin"
                value={form.guardian_state_of_origin}
                onChange={handleChange}
              />
              <input
                name="guardian_occupation"
                placeholder="Occupation"
                value={form.guardian_occupation}
                onChange={handleChange}
              />

              <select name="guardian_relationship" value={form.guardian_relationship} onChange={handleChange}>
                <option value="">Relationship</option>
                <option value="father">Father</option>
                <option value="mother">Mother</option>
                <option value="guardian">Guardian</option>
              </select>
            </>
          )}

          {isStaff && (
            <>
              <h3 style={{ marginTop: 14 }}>Staff</h3>
              <select name="sex" value={form.sex} onChange={handleChange}>
                <option value="">Sex</option>
                <option value="M">Male</option>
                <option value="F">Female</option>
              </select>
              <input type="date" name="dob" value={form.dob} onChange={handleChange} />
              <input name="staff_position" placeholder="Position" value={form.staff_position} onChange={handleChange} />
            </>
          )}

          <div style={{ marginTop: 14 }}>
            <button type="submit" disabled={submitting}>
              {isEditMode ? (submitting ? "Saving..." : "Save Changes") : (submitting ? "Generating..." : "Generate Username")}
            </button>
            {isEditMode && (
              <button
                type="button"
                style={{ marginLeft: 8 }}
                onClick={() => navigate(returnTo)}
                disabled={submitting}
              >
                Cancel
              </button>
            )}
          </div>
        </form>
      )}

      {step === 2 && !isEditMode && (
        <div style={{ marginTop: 16 }}>
          <h3>Generated Username</h3>

          <input value={username} onChange={(e) => setUsername(e.target.value)} />

          <div style={{ marginTop: 12, display: "flex", gap: 8 }}>
            <button onClick={confirm} disabled={submitting}>
              {submitting ? "Saving..." : "Confirm"}
            </button>
            <button onClick={() => setStep(1)} disabled={submitting}>
              Back
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
