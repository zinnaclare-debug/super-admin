import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "react-router-dom";
import api from "../../services/api";
import portfolioUpdateArt from "../../assets/website-admin/portfolio-update.svg";
import heatmapArt from "../../assets/website-admin/heatmap.svg";
import landingPageArt from "../../assets/website-admin/landing-page.svg";
import "./WebsiteAdmin.css";

const emptyWebsiteContent = {
  hero_title: "",
  hero_subtitle: "",
  about_title: "",
  about_text: "",
  core_values_text: "",
  vision_text: "",
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
  created_at: "",
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

function WebsiteField({ label, children, wide = false }) {
  return (
    <div className={wide ? "website-admin-field website-admin-field--wide" : "website-admin-field"}>
      <label>{label}</label>
      {children}
    </div>
  );
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
  const todayLabel = useMemo(
    () => formatDate(contentForm.created_at || new Date().toISOString()),
    [contentForm.created_at]
  );

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
    setContentForm({ ...emptyContentForm, created_at: new Date().toISOString() });
    setShowCreateContent(true);
  };

  const startEditContent = (item) => {
    setContentForm({
      id: item.id,
      heading: item.heading || "",
      content: item.content || "",
      existingImages: mapExistingImages(item),
      photos: [],
      created_at: item.created_at || "",
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
    <div className="website-admin-page">
      <section className="website-admin-hero">
        <div className="website-admin-hero-copy">
          <span className="website-admin-pill">School Website</span>
          <h1>Shape how your school looks before visitors even click login.</h1>
          <p>
            Update the homepage message, About Us, Vision, Mission, public contact details,
            theme colors, and school content from one place.
          </p>
          <div className="website-admin-meta">
            <span>Homepage copy</span>
            <span>Public contacts</span>
            <span>Content publishing</span>
          </div>
          <div className="website-admin-hero-actions">
            <button type="button" onClick={saveWebsite} disabled={saving}>
              {saving ? "Saving..." : "Save Website"}
            </button>
            <button type="button" className="website-admin-button website-admin-button--ghost" onClick={startCreateContent}>
              Create Content
            </button>
          </div>
        </div>

        <div className="website-admin-hero-art">
          <div className="website-admin-art website-admin-art--main">
            <img src={landingPageArt} alt="School website layout illustration" />
          </div>
          <div className="website-admin-art website-admin-art--left">
            <img src={portfolioUpdateArt} alt="Content planning illustration" />
          </div>
          <div className="website-admin-art website-admin-art--right">
            <img src={heatmapArt} alt="Content insights illustration" />
          </div>
        </div>
      </section>

      <section className="website-admin-panel">
        <div className="website-admin-panel-head">
          <div>
            <h2>Website Details</h2>
            <p>Control the homepage message, admissions intro, contact details, colors, and public actions.</p>
          </div>
        </div>

        <div className="website-admin-form-grid">
          <WebsiteField label="Hero Title">
            <input value={websiteContent.hero_title} onChange={(e) => updateWebsiteContent("hero_title", e.target.value)} />
          </WebsiteField>

          <WebsiteField label="About Title">
            <input value={websiteContent.about_title} onChange={(e) => updateWebsiteContent("about_title", e.target.value)} />
          </WebsiteField>

          <WebsiteField label="Address">
            <input value={websiteContent.address} onChange={(e) => updateWebsiteContent("address", e.target.value)} />
          </WebsiteField>

          <WebsiteField label="Public Email">
            <input type="email" value={websiteContent.contact_email} onChange={(e) => updateWebsiteContent("contact_email", e.target.value)} />
          </WebsiteField>

          <WebsiteField label="Public Phone">
            <input value={websiteContent.contact_phone} onChange={(e) => updateWebsiteContent("contact_phone", e.target.value)} />
          </WebsiteField>

          <WebsiteField label="Primary Color">
            <input type="color" className="website-admin-color" value={websiteContent.primary_color} onChange={(e) => updateWebsiteContent("primary_color", e.target.value)} />
          </WebsiteField>

          <WebsiteField label="Accent Color">
            <input type="color" className="website-admin-color" value={websiteContent.accent_color} onChange={(e) => updateWebsiteContent("accent_color", e.target.value)} />
          </WebsiteField>

          <WebsiteField label="Hero Subtitle" wide>
            <textarea rows="4" value={websiteContent.hero_subtitle} onChange={(e) => updateWebsiteContent("hero_subtitle", e.target.value)} />
          </WebsiteField>

          <WebsiteField label="Apply Now Intro" wide>
            <textarea rows="4" value={websiteContent.admissions_intro} onChange={(e) => updateWebsiteContent("admissions_intro", e.target.value)} />
          </WebsiteField>

          <WebsiteField label="About Us" wide>
            <textarea rows="5" value={websiteContent.about_text} onChange={(e) => updateWebsiteContent("about_text", e.target.value)} placeholder="Tell visitors about your school." />
          </WebsiteField>

          <WebsiteField label="Vision" wide>
            <textarea rows="5" value={websiteContent.vision_text} onChange={(e) => updateWebsiteContent("vision_text", e.target.value)} placeholder="What vision leads the school?" />
          </WebsiteField>

          <WebsiteField label="Mission" wide>
            <textarea rows="5" value={websiteContent.mission_text} onChange={(e) => updateWebsiteContent("mission_text", e.target.value)} placeholder="What mission drives the school?" />
          </WebsiteField>
        </div>

        <div className="website-admin-toggle-row">
          <label><input type="checkbox" checked={websiteContent.show_apply_now} onChange={(e) => updateWebsiteContent("show_apply_now", e.target.checked)} /> Show Apply Now</label>
          <label><input type="checkbox" checked={websiteContent.show_entrance_exam} onChange={(e) => updateWebsiteContent("show_entrance_exam", e.target.checked)} /> Show Entrance Exam</label>
          <label><input type="checkbox" checked={websiteContent.show_verify_score} onChange={(e) => updateWebsiteContent("show_verify_score", e.target.checked)} /> Show Verify Score</label>
        </div>
      </section>

      <section className="website-admin-panel">
        <div className="website-admin-panel-head">
          <div>
            <h2>School Contents</h2>
            <p>Create school updates with heading, automatic date, written content, and up to five photos.</p>
          </div>
          <button type="button" onClick={startCreateContent}>Create Content</button>
        </div>

        {showCreateContent ? (
          <div className="website-admin-editor">
            <div className="website-admin-editor-head">
              <div>
                <strong>{isEditingContent ? "Edit Content" : "Create Content"}</strong>
                <p>{todayLabel}</p>
              </div>
              <span className="website-admin-editor-count">{totalSelectedImages}/5 images</span>
            </div>

            <div className="website-admin-form-grid">
              <WebsiteField label="Heading" wide>
                <input
                  value={contentForm.heading}
                  onChange={(e) => setContentForm((prev) => ({ ...prev, heading: e.target.value }))}
                  placeholder="Enter content heading"
                />
              </WebsiteField>

              <WebsiteField label="Date">
                <input value={todayLabel} readOnly className="website-admin-input-readonly" />
              </WebsiteField>

              <WebsiteField label="Photos (Maximum 5)" wide>
                <input type="file" accept="image/*" multiple onChange={handlePhotoChange} />
                {contentForm.photos.length > 0 ? (
                  <div className="website-admin-file-chips">
                    {contentForm.photos.map((file) => (
                      <span key={file.name}>{file.name}</span>
                    ))}
                  </div>
                ) : null}
              </WebsiteField>

              {contentForm.existingImages.length > 0 ? (
                <WebsiteField label="Existing Photos" wide>
                  <div className="website-admin-existing-images">
                    {contentForm.existingImages.map((image) => (
                      <div key={image.path} className="website-admin-existing-card">
                        {image.url ? <img src={image.url} alt={contentForm.heading || "School content"} /> : <div className="website-admin-image-fallback">Image</div>}
                        <button type="button" className="website-admin-text-button" onClick={() => removeExistingImage(image.path)}>
                          Remove
                        </button>
                      </div>
                    ))}
                  </div>
                </WebsiteField>
              ) : null}

              <WebsiteField label="Content" wide>
                <textarea
                  rows="7"
                  value={contentForm.content}
                  onChange={(e) => setContentForm((prev) => ({ ...prev, content: e.target.value }))}
                  placeholder="Write the school content here"
                />
              </WebsiteField>
            </div>

            <div className="website-admin-editor-actions">
              <button type="button" onClick={saveContent} disabled={savingContent}>
                {savingContent ? "Saving..." : isEditingContent ? "Update Content" : "Save Content"}
              </button>
              <button type="button" className="website-admin-button website-admin-button--ghost" onClick={resetContentEditor} disabled={savingContent}>
                Cancel
              </button>
            </div>
          </div>
        ) : null}

        <div className="website-admin-content-list">
          {contents.map((item) => (
            <article key={item.id} className="website-admin-content-card">
              <div className="website-admin-content-card-head">
                <div>
                  <h3>{item.heading}</h3>
                  <span>{item.display_date || formatDate(item.created_at)}</span>
                </div>
                <div className="website-admin-card-actions">
                  <button type="button" className="website-admin-button website-admin-button--ghost" onClick={() => startEditContent(item)}>
                    Edit
                  </button>
                  <button type="button" className="website-admin-button website-admin-button--danger" onClick={() => deleteContent(item.id)}>
                    Delete
                  </button>
                </div>
              </div>

              <p>{item.content}</p>

              {item.image_urls?.length ? (
                <div className="website-admin-content-gallery">
                  {item.image_urls.map((url) => (
                    <img key={url} src={url} alt={item.heading} />
                  ))}
                </div>
              ) : null}
            </article>
          ))}

          {contents.length === 0 ? (
            <div className="website-admin-empty-state">
              No school content created yet.
            </div>
          ) : null}
        </div>
      </section>
    </div>
  );
}


