import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import api from "../../services/api";

const emptyWebsiteContent = {
  hero_title: "",
  hero_subtitle: "",
  about_title: "",
  about_text: "",
  core_values_text: "",
  mission_text: "",
  admissions_intro: "",
  address: "",
  contact_email: "",
  contact_phone: "",
  primary_color: "#0f172a",
  accent_color: "#0f766e",
  show_apply_now: true,
  show_entrance_exam: true,
  show_verify_score: true,
};

const emptyContentForm = {
  id: null,
  heading: "",
  content: "",
  existingImages: [],
  photos: [],
};

function normalizeData(payload = {}) {
  return {
    websiteContent: { ...emptyWebsiteContent, ...(payload.website_content || {}) },
  };
}

function formatDate(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return date.toLocaleDateString(undefined, {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function mapExistingImages(item = {}) {
  const paths = Array.isArray(item.image_paths) ? item.image_paths : [];
  const urls = Array.isArray(item.image_urls) ? item.image_urls : [];

  return paths.map((path, index) => ({
    path,
    url: urls[index] || "",
  }));
}

export default function WebsiteAdmin() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [savingContent, setSavingContent] = useState(false);
  const [websiteContent, setWebsiteContent] = useState(emptyWebsiteContent);
  const [contents, setContents] = useState([]);
  const [showCreateContent, setShowCreateContent] = useState(false);
  const [contentForm, setContentForm] = useState(emptyContentForm);

  const isEditingContent = Boolean(contentForm.id);
  const totalSelectedImages = contentForm.existingImages.length + contentForm.photos.length;

  const loadWebsite = async () => {
    const websiteRes = await api.get("/api/school-admin/website");
    const normalized = normalizeData(websiteRes.data || {});
    setWebsiteContent(normalized.websiteContent);
  };

  const loadContents = async () => {
    const contentsRes = await api.get("/api/school-admin/website/contents");
    setContents(Array.isArray(contentsRes.data?.data) ? contentsRes.data.data : []);
  };

  const load = async () => {
    setLoading(true);
    try {
      await Promise.all([loadWebsite(), loadContents()]);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load school website settings.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  useEffect(() => {
    if (searchParams.get("createContent") === "1") {
      startCreateContent();
    }
  }, [searchParams]);

  const updateWebsiteContent = (field, value) => {
    setWebsiteContent((prev) => ({ ...prev, [field]: value }));
  };

  const saveWebsite = async () => {
    setSaving(true);
    try {
      await api.put("/api/school-admin/website", {
        website_content: websiteContent,
      });
      alert("School website content saved.");
      await loadWebsite();
    } catch (err) {
      const validationError = Object.values(err?.response?.data?.errors || {}).flat()[0];
      alert(validationError || err?.response?.data?.message || "Failed to save school website content.");
    } finally {
      setSaving(false);
    }
  };

  const clearCreateContentFlag = () => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      next.delete("createContent");
      return next;
    });
  };

  const resetContentEditor = () => {
    setContentForm(emptyContentForm);
    setShowCreateContent(false);
    clearCreateContentFlag();
  };

  const startCreateContent = () => {
    setContentForm(emptyContentForm);
    setShowCreateContent(true);
  };

  const startEditContent = (item) => {
    setContentForm({
      id: item.id,
      heading: item.heading || "",
      content: item.content || "",
      existingImages: mapExistingImages(item),
      photos: [],
    });
    setShowCreateContent(true);
    clearCreateContentFlag();
  };

  const handlePhotoChange = (event) => {
    const files = Array.from(event.target.files || []);
    const allowedCount = Math.max(0, 5 - contentForm.existingImages.length);
    const nextFiles = files.slice(0, allowedCount);

    setContentForm((prev) => ({
      ...prev,
      photos: nextFiles,
    }));
  };

  const removeExistingImage = (imagePath) => {
    setContentForm((prev) => ({
      ...prev,
      existingImages: prev.existingImages.filter((image) => image.path !== imagePath),
    }));
  };

  const saveContent = async () => {
    if (!contentForm.heading.trim() || !contentForm.content.trim()) {
      return alert("Heading and content are required.");
    }

    if (totalSelectedImages > 5) {
      return alert("You can upload a maximum of 5 photos.");
    }

    setSavingContent(true);
    try {
      const formData = new FormData();
      formData.append("heading", contentForm.heading.trim());
      formData.append("content", contentForm.content.trim());
      contentForm.existingImages.forEach((image) => formData.append("keep_image_paths[]", image.path));
      contentForm.photos.forEach((file) => formData.append("photos[]", file));

      if (isEditingContent) {
        formData.append("_method", "PATCH");
        await api.post(`/api/school-admin/website/contents/${contentForm.id}`, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        alert("School content updated successfully.");
      } else {
        await api.post("/api/school-admin/website/contents", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        alert("School content created successfully.");
      }

      resetContentEditor();
      await loadContents();
    } catch (err) {
      const validationError = Object.values(err?.response?.data?.errors || {}).flat()[0];
      alert(validationError || err?.response?.data?.message || "Failed to save school content.");
    } finally {
      setSavingContent(false);
    }
  };

  const deleteContent = async (contentId) => {
    if (!window.confirm("Delete this school content?")) return;

    try {
      await api.delete(`/api/school-admin/website/contents/${contentId}`);
      alert("School content deleted successfully.");
      if (contentForm.id === contentId) {
        resetContentEditor();
      }
      await loadContents();
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to delete school content.");
    }
  };

  if (loading) return <p>Loading school website settings...</p>;

  return (
    <div style={{ display: "grid", gap: 18 }}>
      <section style={{ background: "#fff", border: "1px solid #dbeafe", borderRadius: 14, padding: 18 }}>
        <div style={{ display: "flex", justifyContent: "space-between", gap: 16, flexWrap: "wrap" }}>
          <div>
            <h2 style={{ margin: 0 }}>Website</h2>
            <p style={{ marginTop: 8, color: "#475569" }}>
              Manage your school subdomain homepage, public content, contact details, and admissions links.
            </p>
          </div>
          <button onClick={saveWebsite} disabled={saving}>{saving ? "Saving..." : "Save Website"}</button>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))", gap: 14, marginTop: 18 }}>
          <div>
            <label>Hero Title</label>
            <input style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.hero_title} onChange={(e) => updateWebsiteContent("hero_title", e.target.value)} />
          </div>
          <div>
            <label>About Title</label>
            <input style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.about_title} onChange={(e) => updateWebsiteContent("about_title", e.target.value)} />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <label>Hero Subtitle</label>
            <textarea rows="3" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.hero_subtitle} onChange={(e) => updateWebsiteContent("hero_subtitle", e.target.value)} />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <label>Apply Now Intro</label>
            <textarea rows="3" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.admissions_intro} onChange={(e) => updateWebsiteContent("admissions_intro", e.target.value)} />
          </div>
          <div>
            <label>Address</label>
            <input style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.address} onChange={(e) => updateWebsiteContent("address", e.target.value)} />
          </div>
          <div>
            <label>Public Email</label>
            <input type="email" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.contact_email} onChange={(e) => updateWebsiteContent("contact_email", e.target.value)} />
          </div>
          <div>
            <label>Public Phone</label>
            <input style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.contact_phone} onChange={(e) => updateWebsiteContent("contact_phone", e.target.value)} />
          </div>
          <div>
            <label>Primary Color</label>
            <input type="color" style={{ width: "100%", height: 44, marginTop: 6 }} value={websiteContent.primary_color} onChange={(e) => updateWebsiteContent("primary_color", e.target.value)} />
          </div>
          <div>
            <label>Accent Color</label>
            <input type="color" style={{ width: "100%", height: 44, marginTop: 6 }} value={websiteContent.accent_color} onChange={(e) => updateWebsiteContent("accent_color", e.target.value)} />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <label>About Us</label>
            <textarea rows="4" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.about_text} onChange={(e) => updateWebsiteContent("about_text", e.target.value)} placeholder="Tell visitors about your school" />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <label>Core Values</label>
            <textarea rows="4" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.core_values_text} onChange={(e) => updateWebsiteContent("core_values_text", e.target.value)} placeholder="Enter the school's core values" />
          </div>
          <div style={{ gridColumn: "1 / -1" }}>
            <label>Mission</label>
            <textarea rows="4" style={{ width: "100%", padding: 10, marginTop: 6 }} value={websiteContent.mission_text} onChange={(e) => updateWebsiteContent("mission_text", e.target.value)} placeholder="Enter the school's mission" />
          </div>
        </div>

        <div style={{ display: "flex", gap: 12, flexWrap: "wrap", marginTop: 16 }}>
          <label><input type="checkbox" checked={websiteContent.show_apply_now} onChange={(e) => updateWebsiteContent("show_apply_now", e.target.checked)} /> Show Apply Now</label>
          <label><input type="checkbox" checked={websiteContent.show_entrance_exam} onChange={(e) => updateWebsiteContent("show_entrance_exam", e.target.checked)} /> Show Entrance Exam</label>
          <label><input type="checkbox" checked={websiteContent.show_verify_score} onChange={(e) => updateWebsiteContent("show_verify_score", e.target.checked)} /> Show Verify Score</label>
        </div>
      </section>

      <section style={{ background: "#fff", border: "1px solid #dbeafe", borderRadius: 14, padding: 18 }}>
        <div style={{ display: "flex", justifyContent: "space-between", gap: 16, flexWrap: "wrap", alignItems: "center" }}>
          <div>
            <h2 style={{ margin: 0 }}>School Contents</h2>
            <p style={{ marginTop: 8, color: "#475569" }}>
              Create school content blocks with heading, automatic date, written content, and up to 5 photos.
            </p>
          </div>
          <button type="button" onClick={startCreateContent}>Create Content</button>
        </div>

        {showCreateContent ? (
          <div style={{ marginTop: 16, border: "1px solid #dbeafe", borderRadius: 12, padding: 16, background: "#f8fbff", display: "grid", gap: 12 }}>
            <div style={{ display: "flex", justifyContent: "space-between", gap: 12, flexWrap: "wrap", alignItems: "center" }}>
              <strong>{isEditingContent ? "Edit Content" : "Create Content"}</strong>
              <span style={{ color: "#64748b", fontSize: 13 }}>
                {totalSelectedImages}/5 images selected
              </span>
            </div>
            <div>
              <label>Heading</label>
              <input style={{ width: "100%", padding: 10, marginTop: 6 }} value={contentForm.heading} onChange={(e) => setContentForm((prev) => ({ ...prev, heading: e.target.value }))} placeholder="Enter content heading" />
            </div>
            <div>
              <label>Date</label>
              <input style={{ width: "100%", padding: 10, marginTop: 6, background: "#e2e8f0" }} value={formatDate(new Date().toISOString())} readOnly />
            </div>
            {contentForm.existingImages.length > 0 ? (
              <div>
                <label>Existing Photos</label>
                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(120px, 1fr))", gap: 10, marginTop: 8 }}>
                  {contentForm.existingImages.map((image) => (
                    <div key={image.path} style={{ border: "1px solid #dbe3ef", borderRadius: 10, overflow: "hidden", background: "#fff" }}>
                      {image.url ? (
                        <img src={image.url} alt={contentForm.heading || "School content"} style={{ width: "100%", height: 100, objectFit: "cover", display: "block" }} />
                      ) : (
                        <div style={{ height: 100, display: "grid", placeItems: "center", color: "#64748b", fontSize: 12 }}>Image</div>
                      )}
                      <button
                        type="button"
                        onClick={() => removeExistingImage(image.path)}
                        style={{ width: "100%", border: 0, borderTop: "1px solid #dbe3ef", padding: "8px 10px", background: "#fff5f5", color: "#b91c1c", fontWeight: 700, cursor: "pointer" }}
                      >
                        Remove
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            ) : null}
            <div>
              <label>Photos (Maximum 5)</label>
              <input type="file" accept="image/*" multiple style={{ width: "100%", marginTop: 6 }} onChange={handlePhotoChange} />
              {contentForm.photos.length > 0 ? (
                <div style={{ marginTop: 8, display: "flex", flexWrap: "wrap", gap: 8 }}>
                  {contentForm.photos.map((file) => (
                    <span key={file.name} style={{ padding: "6px 10px", borderRadius: 999, background: "#eff6ff", color: "#1d4ed8", fontSize: 12, fontWeight: 600 }}>{file.name}</span>
                  ))}
                </div>
              ) : null}
            </div>
            <div>
              <label>Content</label>
              <textarea rows="6" style={{ width: "100%", padding: 10, marginTop: 6 }} value={contentForm.content} onChange={(e) => setContentForm((prev) => ({ ...prev, content: e.target.value }))} placeholder="Write the school content here" />
            </div>
            <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
              <button type="button" onClick={saveContent} disabled={savingContent}>{savingContent ? "Saving..." : isEditingContent ? "Update" : "Save"}</button>
              <button type="button" onClick={resetContentEditor} disabled={savingContent}>Cancel</button>
            </div>
          </div>
        ) : null}

        <div style={{ display: "grid", gap: 14, marginTop: 18 }}>
          {contents.map((item) => (
            <article key={item.id} style={{ border: "1px solid #dbe3ef", borderRadius: 12, padding: 14, background: "#fff" }}>
              <div style={{ display: "flex", justifyContent: "space-between", gap: 12, flexWrap: "wrap", alignItems: "baseline" }}>
                <div>
                  <h3 style={{ margin: 0 }}>{item.heading}</h3>
                  <span style={{ color: "#64748b", fontSize: 13 }}>{item.display_date || formatDate(item.created_at)}</span>
                </div>
                <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                  <button type="button" onClick={() => startEditContent(item)}>Edit</button>
                  <button type="button" onClick={() => deleteContent(item.id)} style={{ background: "#fff5f5", color: "#b91c1c", border: "1px solid #fecaca" }}>Delete</button>
                </div>
              </div>
              <p style={{ color: "#334155", marginTop: 10, whiteSpace: "pre-wrap" }}>{item.content}</p>
              {item.image_urls?.length ? (
                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(120px, 1fr))", gap: 10, marginTop: 12 }}>
                  {item.image_urls.map((url) => (
                    <img key={url} src={url} alt={item.heading} style={{ width: "100%", height: 120, objectFit: "cover", borderRadius: 10, border: "1px solid #dbe3ef" }} />
                  ))}
                </div>
              ) : null}
            </article>
          ))}
          {contents.length === 0 ? (
            <div style={{ border: "1px dashed #cbd5e1", borderRadius: 12, padding: 18, textAlign: "center", color: "#64748b" }}>
              No school content created yet.
            </div>
          ) : null}
        </div>
      </section>
    </div>
  );
}

