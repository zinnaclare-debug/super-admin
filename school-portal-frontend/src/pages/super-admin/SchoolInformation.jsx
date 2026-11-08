import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../services/api";
import "./SchoolInformation.css";
import { BRANDING_IMAGE_GUIDE, compressBrandingImage } from "../../utils/profileImage";
import schoolInformationArt from "../../assets/dashboard/branding.svg";

const ALLOWED_TYPES = ["image/jpeg", "image/jpg", "image/png", "image/webp"];
const defaultScoreProfile = (layout = "compact") => ({
  ca_maxes: layout === "classic" ? [4, 4, 4, 4, 4] : [6, 6, 6, 6, 6],
  ca_labels: ["CA1", "CA2", "CA3", "CA4", "CA5"],
  psychomotor_maxes: layout === "classic" ? [2, 2, 2, 2, 2] : [0, 0, 0, 0, 0],
  psychomotor_labels: ["P1", "P2", "P3", "P4", "P5"],
  quiz_max: layout === "classic" ? 5 : 0,
  quiz_label: "Quiz",
  note_copying_max: layout === "classic" ? 5 : 0,
  note_copying_label: "Note Copying",
  exam_max: layout === "classic" ? 60 : 70,
});

const normalizeScoreProfile = (raw, layout) => {
  const defaults = defaultScoreProfile(layout);
  const source = raw && typeof raw === "object" ? raw : {};
  const number = (value, fallback) => {
    const parsed = Number(value ?? fallback);
    return Number.isFinite(parsed) ? Math.max(0, Math.min(100, Math.round(parsed))) : fallback;
  };
  const values = (field, fallback) => Array.from({ length: 5 }, (_, index) => number(source[field]?.[index], fallback[index]));
  const labels = (field, fallback) => Array.from({ length: 5 }, (_, index) => String(source[field]?.[index] || fallback[index]).trim().slice(0, 30) || fallback[index]);

  return {
    ca_maxes: values("ca_maxes", defaults.ca_maxes),
    ca_labels: labels("ca_labels", defaults.ca_labels),
    psychomotor_maxes: values("psychomotor_maxes", defaults.psychomotor_maxes),
    psychomotor_labels: labels("psychomotor_labels", defaults.psychomotor_labels),
    quiz_max: layout === "classic" ? number(source.quiz_max, defaults.quiz_max) : 0,
    quiz_label: String(source.quiz_label || defaults.quiz_label).trim().slice(0, 30) || defaults.quiz_label,
    note_copying_max: layout === "classic" ? number(source.note_copying_max, defaults.note_copying_max) : 0,
    note_copying_label: String(source.note_copying_label || defaults.note_copying_label).trim().slice(0, 30) || defaults.note_copying_label,
    exam_max: number(source.exam_max, defaults.exam_max),
  };
};

const scoreProfileTotal = (profile, layout) => {
  const ca = (profile?.ca_maxes || []).reduce((sum, value) => sum + Number(value || 0), 0);
  if (layout !== "classic") return ca + Number(profile?.exam_max || 0);
  return ca
    + Number(profile?.exam_max || 0)
    + (profile?.psychomotor_maxes || []).reduce((sum, value) => sum + Number(value || 0), 0)
    + Number(profile?.quiz_max || 0)
    + Number(profile?.note_copying_max || 0);
};
const DEFAULT_EXAM_RECORD = {
  ca_maxes: [30, 0, 0, 0, 0],
  ca_labels: ["CA1", "CA2", "CA3", "CA4", "CA5"],
  exam_max: 70,
  total_max: 100,
};

const DEFAULT_RESULT_TEMPLATE = {
  layout: "compact",
  score_profile: defaultScoreProfile("compact"),
  primary_color: "#111827",
  accent_color: "#1D4ED8",
  watermark_opacity: 0.07,
  show_student_photo: true,
  show_school_logo: true,
  show_watermark: true,
  show_attendance: true,
  show_behaviour: true,
  show_signature: true,
  show_result_position: true,
  third_term: {
    show_previous_term_totals: true,
  },
  cumulative: {
    show_term_totals: true,
    show_average: true,
  },
};

const MAX_GRADING_ROWS = 10;
const EMPTY_GRADING_ROW = { from: "", to: "", grade: "", remark: "" };

const sanitizeScore = (value) => {
  if (value === "" || value === null || value === undefined) return "";
  const parsed = Number(value);
  return Number.isFinite(parsed) ? Math.max(0, Math.min(100, Math.round(parsed))) : "";
};

const recomputeGradingRows = (rows) => {
  const nextRows = Array.from({ length: MAX_GRADING_ROWS }, (_, index) => {
    const row = rows[index] || EMPTY_GRADING_ROW;
    return {
      from: index === 0 ? 0 : "",
      to: sanitizeScore(row.to),
      grade: String(row.grade || ""),
      remark: String(row.remark || ""),
    };
  });

  for (let index = 1; index < nextRows.length; index += 1) {
    const prev = nextRows[index - 1];
    const prevTo = sanitizeScore(prev.to);
    const prevReady = prev.grade.trim() !== "" && prevTo !== "" && prevTo >= prev.from && prevTo < 100;
    nextRows[index].from = prevReady ? prevTo + 1 : "";
  }

  return nextRows;
};

const normalizeGradingRows = (rows) =>
  recomputeGradingRows(
    Array.from({ length: MAX_GRADING_ROWS }, (_, index) => {
      const row = Array.isArray(rows) ? rows[index] || {} : {};
      return {
        from: index === 0 ? 0 : sanitizeScore(row.from),
        to: sanitizeScore(row.to),
        grade: String(row.grade || ""),
        remark: String(row.remark || ""),
      };
    })
  );

const toAbsoluteUrl = (u) => {
  if (!u) return "";
  if (/^(https?:\/\/|blob:|data:)/i.test(u)) return u;
  const base = (api.defaults.baseURL || "").replace(/\/$/, "");
  return `${base}${u.startsWith("/") ? "" : "/"}${u}`;
};

const normalizeExamRecord = (record) => {
  const raw = record || {};
  const schema = raw.default && typeof raw.default === "object" ? raw.default : raw;
  const caMaxes = Array.isArray(raw.ca_maxes) ? raw.ca_maxes : DEFAULT_EXAM_RECORD.ca_maxes;
  const schemaCaMaxes = Array.isArray(schema.ca_maxes) ? schema.ca_maxes : caMaxes;
  const caLabels = Array.isArray(schema.ca_labels)
    ? schema.ca_labels
    : Array.isArray(raw.ca_labels)
      ? raw.ca_labels
      : DEFAULT_EXAM_RECORD.ca_labels;
  const normalizedCa = Array.from({ length: 5 }, (_, idx) => {
    const value = Number(schemaCaMaxes[idx] || 0);
    return Number.isFinite(value) ? Math.max(0, Math.min(100, Math.round(value))) : 0;
  });
  const normalizedLabels = Array.from({ length: 5 }, (_, idx) => {
    const label = String(caLabels[idx] || "").trim();
    return label ? label.slice(0, 30) : `CA${idx + 1}`;
  });
  const caTotal = normalizedCa.reduce((sum, v) => sum + v, 0);
  const requestedExam = Number(schema.exam_max ?? 100 - caTotal);
  const examMax = Number.isFinite(requestedExam) ? Math.max(0, Math.min(100, Math.round(requestedExam))) : 0;

  return {
    ca_maxes: normalizedCa,
    ca_labels: normalizedLabels,
    exam_max: caTotal + examMax === 100 ? examMax : Math.max(0, 100 - caTotal),
    total_max: 100,
    comments_only: Boolean(schema.comments_only),
  };
};

const normalizeExamRecordsByLevel = (raw, levels = []) => {
  const base = normalizeExamRecord(raw?.default || raw || DEFAULT_EXAM_RECORD);
  const sourceByLevel = raw?.by_level && typeof raw.by_level === "object" ? raw.by_level : {};
  const byLevel = {};

  levels.forEach((level) => {
    const key = String(level?.key || "").trim();
    if (!key) return;
    byLevel[key] = normalizeExamRecord(sourceByLevel[key] || base);
  });

  return {
    default: base,
    by_level: byLevel,
  };
};

const normalizeClassRow = (row) => {
  if (row && typeof row === "object" && !Array.isArray(row)) {
    const classEnabled = row.enabled !== false;
    const departmentInput = Object.prototype.hasOwnProperty.call(row, "department_input")
      ? String(row.department_input || "")
      : toDepartmentCsv(row.department_names || []);
    return {
      name: String(row.name || "").trim(),
      enabled: classEnabled,
      department_enabled: classEnabled && row.department_enabled !== false,
      department_input: departmentInput,
    };
  }

  return {
    name: String(row || "").trim(),
    enabled: true,
    department_enabled: true,
    department_input: "",
  };
};

const normalizeTemplates = (items) =>
  (Array.isArray(items) ? items : []).map((section) => ({
    key: section?.key || "",
    label: section?.label || "",
    enabled: Boolean(section?.enabled),
    classes: (Array.isArray(section?.classes) ? section.classes : []).map(normalizeClassRow),
  }));

const toDepartmentCsv = (names) =>
  (Array.isArray(names) ? names : [])
    .map((name) => String(name || "").trim())
    .filter(Boolean)
    .join(", ");

const parseDepartmentCsv = (value) =>
  String(value || "")
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean)
    .filter((name, index, arr) => arr.findIndex((item) => item.toLowerCase() === name.toLowerCase()) === index);

const formatLoginDate = (value) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "-";
  return date.toLocaleString();
};

const normalizeResultTemplate = (raw) => {
  const source = raw && typeof raw === "object" && !Array.isArray(raw) ? raw : {};
  const thirdTerm = source.third_term && typeof source.third_term === "object" ? source.third_term : {};
  const cumulative = source.cumulative && typeof source.cumulative === "object" ? source.cumulative : {};
  const opacity = Number(source.watermark_opacity ?? DEFAULT_RESULT_TEMPLATE.watermark_opacity);

  const layout = ["classic", "compact"].includes(source.layout) ? source.layout : DEFAULT_RESULT_TEMPLATE.layout;

  return {
    ...DEFAULT_RESULT_TEMPLATE,
    ...source,
    layout,
    score_profile: normalizeScoreProfile(source.score_profile, layout),
    primary_color: /^#[0-9a-fA-F]{6}$/.test(String(source.primary_color || ""))
      ? source.primary_color
      : DEFAULT_RESULT_TEMPLATE.primary_color,
    accent_color: /^#[0-9a-fA-F]{6}$/.test(String(source.accent_color || ""))
      ? source.accent_color
      : DEFAULT_RESULT_TEMPLATE.accent_color,
    watermark_opacity: Number.isFinite(opacity)
      ? Math.max(0, Math.min(0.2, opacity))
      : DEFAULT_RESULT_TEMPLATE.watermark_opacity,
    third_term: {
      ...DEFAULT_RESULT_TEMPLATE.third_term,
      ...thirdTerm,
      show_previous_term_totals:
        thirdTerm.show_previous_term_totals ?? DEFAULT_RESULT_TEMPLATE.third_term.show_previous_term_totals,
    },
    cumulative: {
      ...DEFAULT_RESULT_TEMPLATE.cumulative,
      ...cumulative,
      show_term_totals: cumulative.show_term_totals ?? DEFAULT_RESULT_TEMPLATE.cumulative.show_term_totals,
      show_average: cumulative.show_average ?? DEFAULT_RESULT_TEMPLATE.cumulative.show_average,
    },
  };
};

export default function SchoolInformation() {
  const navigate = useNavigate();
  const { schoolId } = useParams();
  const [loading, setLoading] = useState(true);
  const [savingBranding, setSavingBranding] = useState(false);
  const [savingExamRecord, setSavingExamRecord] = useState(false);
  const [savingGradingSchema, setSavingGradingSchema] = useState(false);
  const [savingResultTemplate, setSavingResultTemplate] = useState(false);
  const [savingClassTemplates, setSavingClassTemplates] = useState(false);
  const [importingHistory, setImportingHistory] = useState(false);
  const [historyFile, setHistoryFile] = useState(null);
  const [makeLatestSessionCurrent, setMakeLatestSessionCurrent] = useState(false);
  const [historyImportResult, setHistoryImportResult] = useState(null);
  const [schoolAdminLoginLimit, setSchoolAdminLoginLimit] = useState(2);
  const [schoolAdminActiveDevices, setSchoolAdminActiveDevices] = useState([]);
  const [schoolAdminLogins, setSchoolAdminLogins] = useState([]);
  const [savingLoginLimit, setSavingLoginLimit] = useState(false);
  const [loggingOutTokenId, setLoggingOutTokenId] = useState(null);

  const [school, setSchool] = useState(null);
  const [branding, setBranding] = useState({
    school_location: "",
    contact_email: "",
    contact_phone: "",
    paystack_subaccount_code: "",
    school_logo_url: null,
    head_of_school_name: "",
    head_signature_url: null,
  });

  const [headName, setHeadName] = useState("");
  const [schoolLocation, setSchoolLocation] = useState("");
  const [contactEmail, setContactEmail] = useState("");
  const [contactPhone, setContactPhone] = useState("");
  const [paystackSubaccountCode, setPaystackSubaccountCode] = useState("");

  const [logoFile, setLogoFile] = useState(null);
  const [logoPreview, setLogoPreview] = useState(null);
  const [signatureFile, setSignatureFile] = useState(null);
  const [signaturePreview, setSignaturePreview] = useState(null);
  const [processingBrandingImage, setProcessingBrandingImage] = useState(false);

  const logoInputRef = useRef(null);
  const signatureInputRef = useRef(null);
  const historyInputRef = useRef(null);

  const [examRecord, setExamRecord] = useState(() => normalizeExamRecordsByLevel(DEFAULT_EXAM_RECORD, []));
  const [gradingRows, setGradingRows] = useState(() => normalizeGradingRows([]));
  const [resultTemplate, setResultTemplate] = useState(() => normalizeResultTemplate(DEFAULT_RESULT_TEMPLATE));
  const [classTemplates, setClassTemplates] = useState([]);
  const activeEducationLevels = useMemo(
    () => classTemplates.filter((section) => section.enabled && section.key),
    [classTemplates]
  );

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get(`/api/super-admin/schools/${schoolId}/information`);
        const payload = res.data || {};
        const brandingData = payload.branding || {};
        setSchool(payload.school || null);
        setSchoolAdminLoginLimit(Number(payload.school?.school_admin_login_limit || 2));
        setBranding({
          school_location: brandingData.school_location ?? "",
          contact_email: brandingData.contact_email ?? "",
          contact_phone: brandingData.contact_phone ?? "",
          paystack_subaccount_code: brandingData.paystack_subaccount_code ?? "",
          school_logo_url: brandingData.school_logo_url ?? null,
          head_of_school_name: brandingData.head_of_school_name ?? "",
          head_signature_url: brandingData.head_signature_url ?? null,
        });
        setHeadName(brandingData.head_of_school_name ?? "");
        setSchoolLocation(brandingData.school_location ?? "");
        setContactEmail(brandingData.contact_email ?? "");
        setContactPhone(brandingData.contact_phone ?? "");
        setPaystackSubaccountCode(brandingData.paystack_subaccount_code ?? "");
        setGradingRows(normalizeGradingRows(payload.grading_schema));
        setResultTemplate(normalizeResultTemplate(payload.result_template_config));
        const normalizedClassTemplates = normalizeTemplates(payload.class_templates);
        setClassTemplates(normalizedClassTemplates);
        setSchoolAdminActiveDevices(
          Array.isArray(payload.school_admin_active_devices) ? payload.school_admin_active_devices : []
        );
        setSchoolAdminLogins(Array.isArray(payload.school_admin_logins) ? payload.school_admin_logins : []);
        setExamRecord(
          normalizeExamRecordsByLevel(
            payload.exam_record,
            normalizedClassTemplates.filter((section) => section.enabled && section.key)
          )
        );
      } catch (err) {
        alert(err?.response?.data?.message || "Failed to load school information.");
      } finally {
        setLoading(false);
      }
    };

    load();
  }, [schoolId]);

  const examDraftSummaries = useMemo(() => {
    const summaries = {};
    activeEducationLevels.forEach((level) => {
      const record = normalizeExamRecord(examRecord.by_level?.[level.key] || examRecord.default);
      const caTotal = record.ca_maxes.reduce((sum, val) => sum + Number(val || 0), 0);
      summaries[level.key] = {
        caTotal,
        expectedExam: Math.max(0, 100 - caTotal),
        total: caTotal + Number(record.exam_max || 0),
      };
    });
    return summaries;
  }, [activeEducationLevels, examRecord]);

  const pickFile = async (event, kind) => {
    const file = event.target.files?.[0] || null;
    const resetInput = () => {
      event.target.value = "";
    };

    if (!file) {
      resetInput();
      return;
    }

    if (file && !ALLOWED_TYPES.includes(file.type)) {
      alert("Image must be JPG, PNG, or WEBP.");
      resetInput();
      return;
    }

    setProcessingBrandingImage(true);
    try {
      const compressed = await compressBrandingImage(
        file,
        kind === "signature"
          ? { maxWidth: 1200, maxHeight: 400, maxBytes: 150 * 1024 }
          : { maxWidth: 1200, maxHeight: 1200, maxBytes: BRANDING_IMAGE_GUIDE.maxBytes }
      );

      if (kind === "logo") {
        setLogoFile(compressed);
        setLogoPreview(URL.createObjectURL(compressed));
      } else {
        setSignatureFile(compressed);
        setSignaturePreview(URL.createObjectURL(compressed));
      }
    } catch (error) {
      alert(error?.message || "Failed to process image.");
      if (kind === "logo") {
        setLogoFile(null);
        setLogoPreview(null);
      } else {
        setSignatureFile(null);
        setSignaturePreview(null);
      }
    } finally {
      setProcessingBrandingImage(false);
      resetInput();
    }
  };

  const saveBranding = async () => {
    const normalizedName = (headName || "").trim();
    const normalizedLocation = (schoolLocation || "").trim();
    const normalizedContactEmail = (contactEmail || "").trim();
    const normalizedContactPhone = (contactPhone || "").trim();
    const normalizedSubaccountCode = (paystackSubaccountCode || "").trim();
    const hasNameChange = normalizedName !== (branding.head_of_school_name || "").trim();
    const hasLocationChange = normalizedLocation !== (branding.school_location || "").trim();
    const hasContactEmailChange = normalizedContactEmail !== (branding.contact_email || "").trim();
    const hasContactPhoneChange = normalizedContactPhone !== (branding.contact_phone || "").trim();
    const hasSubaccountCodeChange =
      normalizedSubaccountCode !== (branding.paystack_subaccount_code || "").trim();
    const hasLogoChange = Boolean(logoFile);
    const hasSignatureChange = Boolean(signatureFile);

    if (
      !hasNameChange &&
      !hasLocationChange &&
      !hasContactEmailChange &&
      !hasContactPhoneChange &&
      !hasSubaccountCodeChange &&
      !hasLogoChange &&
      !hasSignatureChange
    ) {
      return alert("No information changes to save.");
    }

    setSavingBranding(true);
    try {
      const fd = new FormData();
      fd.append("head_of_school_name", normalizedName);
      fd.append("school_location", normalizedLocation);
      fd.append("contact_email", normalizedContactEmail);
      fd.append("contact_phone", normalizedContactPhone);
      fd.append("paystack_subaccount_code", normalizedSubaccountCode);
      if (logoFile) fd.append("logo", logoFile);
      if (signatureFile) fd.append("head_signature", signatureFile);

      const res = await api.post(`/api/super-admin/schools/${schoolId}/information/branding`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      const data = res.data?.data || {};
      setBranding({
        school_location: data.school_location ?? normalizedLocation,
        contact_email: Object.prototype.hasOwnProperty.call(data, "contact_email")
          ? (data.contact_email ?? "")
          : normalizedContactEmail,
        contact_phone: Object.prototype.hasOwnProperty.call(data, "contact_phone")
          ? (data.contact_phone ?? "")
          : normalizedContactPhone,
        paystack_subaccount_code: Object.prototype.hasOwnProperty.call(data, "paystack_subaccount_code")
          ? (data.paystack_subaccount_code ?? "")
          : normalizedSubaccountCode,
        school_logo_url: data.school_logo_url ?? branding.school_logo_url,
        head_of_school_name: data.head_of_school_name ?? normalizedName,
        head_signature_url: data.head_signature_url ?? branding.head_signature_url,
      });
      setHeadName(data.head_of_school_name ?? normalizedName);
      setSchoolLocation(data.school_location ?? normalizedLocation);
      setContactEmail(
        Object.prototype.hasOwnProperty.call(data, "contact_email")
          ? (data.contact_email ?? "")
          : normalizedContactEmail
      );
      setContactPhone(
        Object.prototype.hasOwnProperty.call(data, "contact_phone")
          ? (data.contact_phone ?? "")
          : normalizedContactPhone
      );
      setPaystackSubaccountCode(
        Object.prototype.hasOwnProperty.call(data, "paystack_subaccount_code")
          ? (data.paystack_subaccount_code ?? "")
          : normalizedSubaccountCode
      );
      setLogoFile(null);
      setLogoPreview(null);
      setSignatureFile(null);
      setSignaturePreview(null);
      alert("School information updated.");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to save school information.");
    } finally {
      setSavingBranding(false);
    }
  };

  const updateExamDraftCa = (levelKey, index, value) => {
    const parsed = Number(value);
    const sanitized = Number.isFinite(parsed) ? Math.max(0, Math.min(100, Math.round(parsed))) : 0;
    setExamRecord((prev) => {
      const current = normalizeExamRecord(prev.by_level?.[levelKey] || prev.default);
      const caMaxes = [...current.ca_maxes];
      caMaxes[index] = sanitized;
      return {
        ...prev,
        by_level: {
          ...(prev.by_level || {}),
          [levelKey]: { ...current, ca_maxes: caMaxes },
        },
      };
    });
  };

  const updateExamDraftCaLabel = (levelKey, index, value) => {
    setExamRecord((prev) => {
      const current = normalizeExamRecord(prev.by_level?.[levelKey] || prev.default);
      const caLabels = [...current.ca_labels];
      caLabels[index] = String(value || "").slice(0, 30);
      return {
        ...prev,
        by_level: {
          ...(prev.by_level || {}),
          [levelKey]: { ...current, ca_labels: caLabels },
        },
      };
    });
  };

  const updateExamDraftExam = (levelKey, value) => {
    const parsed = Number(value);
    const sanitized = Number.isFinite(parsed) ? Math.max(0, Math.min(100, Math.round(parsed))) : 0;
    setExamRecord((prev) => {
      const current = normalizeExamRecord(prev.by_level?.[levelKey] || prev.default);
      return {
        ...prev,
        by_level: {
          ...(prev.by_level || {}),
          [levelKey]: { ...current, exam_max: sanitized },
        },
      };
    });
  };

  const updateExamDraftCommentsOnly = (levelKey, checked) => {
    setExamRecord((prev) => {
      const current = normalizeExamRecord(prev.by_level?.[levelKey] || prev.default);
      return {
        ...prev,
        by_level: {
          ...(prev.by_level || {}),
          [levelKey]: { ...current, comments_only: Boolean(checked) },
        },
      };
    });
  };
  const saveExamRecord = async () => {
    if (!activeEducationLevels.length) {
      alert("Enable at least one education level before saving exam records.");
      return;
    }

    const byLevel = {};
    for (const level of activeEducationLevels) {
      const record = normalizeExamRecord(examRecord.by_level?.[level.key] || examRecord.default);
      const caMaxes = Array.from({ length: 5 }, (_, idx) => {
        const n = Number(record.ca_maxes?.[idx] || 0);
        return Number.isFinite(n) ? Math.max(0, Math.min(100, Math.round(n))) : 0;
      });
      const caLabels = Array.from({ length: 5 }, (_, idx) => {
        const label = String(record.ca_labels?.[idx] || "").trim();
        return label ? label.slice(0, 30) : `CA${idx + 1}`;
      });
      const examMaxRaw = Number(record.exam_max || 0);
      const examMax = Number.isFinite(examMaxRaw) ? Math.max(0, Math.min(100, Math.round(examMaxRaw))) : 0;
      const caTotal = caMaxes.reduce((sum, val) => sum + val, 0);

      if (caTotal <= 0) {
        alert(`${level.label || level.key}: At least one CA score must be greater than zero.`);
        return;
      }

      if (caTotal + examMax !== 100) {
        alert(`${level.label || level.key}: Total of CA maxima and exam maximum must be exactly 100.`);
        return;
      }

      byLevel[level.key] = { ca_maxes: caMaxes, ca_labels: caLabels, exam_max: examMax, comments_only: Boolean(record.comments_only) };
    }

    setSavingExamRecord(true);
    try {
      const payload = { by_level: byLevel };
      const res = await api.put(`/api/super-admin/schools/${schoolId}/information/exam-record`, payload);
      setExamRecord(normalizeExamRecordsByLevel(res.data?.data || payload, activeEducationLevels));
      alert("Exam records updated.");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to save exam records.");
    } finally {
      setSavingExamRecord(false);
    }
  };

  const updateGradingRow = (index, field, value) => {
    setGradingRows((prev) => {
      const next = prev.map((row) => ({ ...row }));
      next[index] = {
        ...next[index],
        [field]: field === "to" ? sanitizeScore(value) : value,
      };
      return recomputeGradingRows(next);
    });
  };

  const saveGradingSchema = async () => {
    const usedRows = [];
    let blankEncountered = false;

    for (let index = 0; index < gradingRows.length; index += 1) {
      const row = gradingRows[index] || EMPTY_GRADING_ROW;
      const grade = String(row.grade || "").trim();
      const remark = String(row.remark || "").trim();
      const toValue = sanitizeScore(row.to);
      const isBlank = grade === "" && remark === "" && toValue === "";

      if (isBlank) {
        blankEncountered = true;
        continue;
      }

      if (blankEncountered) {
        alert(`Remove empty rows before row ${index + 1}.`);
        return;
      }

      if (row.from === "" || row.from === null || row.from === undefined) {
        alert(`Row ${index + 1} is waiting for the previous row to be completed.`);
        return;
      }

      if (toValue === "") {
        alert(`Enter a To score for row ${index + 1}.`);
        return;
      }

      if (grade === "") {
        alert(`Enter a grade for row ${index + 1}.`);
        return;
      }

      if (toValue < Number(row.from)) {
        alert(`Row ${index + 1} To score cannot be less than From.`);
        return;
      }

      usedRows.push({
        from: Number(row.from),
        to: toValue,
        grade,
        remark,
      });
    }

    if (usedRows.length === 0) {
      alert("Add at least one grading row.");
      return;
    }

    if (usedRows[usedRows.length - 1].to !== 100) {
      alert("The final grading row must end at 100.");
      return;
    }

    setSavingGradingSchema(true);
    try {
      const res = await api.put(`/api/super-admin/schools/${schoolId}/information/grading-schema`, {
        grading_schema: usedRows,
      });
      setGradingRows(normalizeGradingRows(res.data?.data || usedRows));
      alert("Grading system updated.");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to save grading system.");
    } finally {
      setSavingGradingSchema(false);
    }
  };

  const updateResultTemplate = (field, value) => {
    setResultTemplate((prev) => normalizeResultTemplate({
      ...prev,
      [field]: value,
      ...(field === "layout" ? { score_profile: defaultScoreProfile(value) } : {}),
    }));
  };

  const updateScoreProfile = (field, value, index = null) => {
    setResultTemplate((prev) => {
      const profile = normalizeScoreProfile(prev.score_profile, prev.layout);
      const next = Array.isArray(profile[field])
        ? { ...profile, [field]: profile[field].map((item, itemIndex) => itemIndex === index ? value : item) }
        : { ...profile, [field]: value };
      return normalizeResultTemplate({ ...prev, score_profile: next });
    });
  };

  const updateNestedResultTemplate = (group, field, value) => {
    setResultTemplate((prev) =>
      normalizeResultTemplate({
        ...prev,
        [group]: {
          ...(prev[group] || {}),
          [field]: value,
        },
      })
    );
  };

  const saveResultTemplate = async () => {
    const payload = normalizeResultTemplate(resultTemplate);
    payload.comments_only_levels = Object.fromEntries(activeEducationLevels.map((level) => [level.key, Boolean(examRecord.by_level?.[level.key]?.comments_only)]));
    setSavingResultTemplate(true);
    try {
      const res = await api.put(`/api/super-admin/schools/${schoolId}/information/result-template`, payload);
      setResultTemplate(normalizeResultTemplate(res.data?.data || payload));
      alert("Result PDF template updated.");
    } catch (err) {
      const apiMessage = err?.response?.data?.message;
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || apiMessage || "Failed to save result PDF template.");
    } finally {
      setSavingResultTemplate(false);
    }
  };

  const updateSection = (index, next) => {
    setClassTemplates((prev) => prev.map((item, idx) => (idx === index ? { ...item, ...next } : item)));
  };

  const updateClassName = (sectionIndex, classIndex, value) => {
    setClassTemplates((prev) =>
      prev.map((section, idx) => {
        if (idx !== sectionIndex) return section;
        const classes = Array.isArray(section.classes) ? [...section.classes] : [];
        const current = normalizeClassRow(classes[classIndex]);
        classes[classIndex] = { ...current, name: value };
        return { ...section, classes };
      })
    );
  };

  const updateClassEnabled = (sectionIndex, classIndex, enabled) => {
    setClassTemplates((prev) =>
      prev.map((section, idx) => {
        if (idx !== sectionIndex) return section;
        const classes = Array.isArray(section.classes) ? [...section.classes] : [];
        const current = normalizeClassRow(classes[classIndex]);
        classes[classIndex] = {
          ...current,
          enabled: Boolean(enabled),
          department_enabled: Boolean(enabled),
          department_input: Boolean(enabled) ? current.department_input : "",
        };
        return { ...section, classes };
      })
    );
  };

  const updateClassDepartmentEnabled = (sectionIndex, classIndex, enabled) => {
    setClassTemplates((prev) =>
      prev.map((section, idx) => {
        if (idx !== sectionIndex) return section;
        const classes = Array.isArray(section.classes) ? [...section.classes] : [];
        const current = normalizeClassRow(classes[classIndex]);
        classes[classIndex] = {
          ...current,
          department_enabled: Boolean(enabled),
          department_input: Boolean(enabled) ? current.department_input : "",
        };
        return { ...section, classes };
      })
    );
  };

  const updateClassDepartmentInput = (sectionIndex, classIndex, value) => {
    setClassTemplates((prev) =>
      prev.map((section, idx) => {
        if (idx !== sectionIndex) return section;
        const classes = Array.isArray(section.classes) ? [...section.classes] : [];
        const current = normalizeClassRow(classes[classIndex]);
        classes[classIndex] = {
          ...current,
          department_input: value,
          department_enabled: String(value || "").trim() !== "" ? true : current.department_enabled,
        };
        return { ...section, classes };
      })
    );
  };

  const saveClassTemplates = async () => {
    const enabledSectionWithoutClass = classTemplates.find((section) => {
      if (!section.enabled) return false;
      const checked = (section.classes || []).filter((item) => item?.enabled && String(item?.name || "").trim() !== "");
      return checked.length === 0;
    });
    if (enabledSectionWithoutClass) {
      return alert(`Select at least one checked class in ${enabledSectionWithoutClass.label || enabledSectionWithoutClass.key}.`);
    }

    const classWithMissingDepartment = classTemplates
      .flatMap((section) =>
        (section.classes || []).map((row) => ({
          section,
          row: normalizeClassRow(row),
        }))
      )
      .find(({ section, row }) => {
        if (!section.enabled || !row.enabled || !row.department_enabled) return false;
        return parseDepartmentCsv(row.department_input).length === 0;
      });
    if (classWithMissingDepartment) {
      return alert(
        `Enter at least one department for ${classWithMissingDepartment.row.name} or disable its department checkbox.`
      );
    }

    const payload = classTemplates.map((section) => ({
      key: section.key,
      label: section.label,
      enabled: Boolean(section.enabled),
      classes: (Array.isArray(section.classes) ? section.classes : []).map((row) => ({
        name: String(row?.name || "").trim(),
        enabled: Boolean(row?.enabled),
        department_enabled: Boolean(section.enabled) && Boolean(row?.enabled) && Boolean(row?.department_enabled),
        department_names:
          Boolean(section.enabled) && Boolean(row?.enabled) && Boolean(row?.department_enabled)
            ? parseDepartmentCsv(row?.department_input)
            : [],
      })),
    }));

    setSavingClassTemplates(true);
    try {
      const res = await api.put(`/api/super-admin/schools/${schoolId}/information/class-templates`, {
        class_templates: payload,
      });
      const normalized = normalizeTemplates(Array.isArray(res.data?.data) ? res.data.data : payload);
      setClassTemplates(normalized);
      alert("Class templates saved.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to save class templates.");
    } finally {
      setSavingClassTemplates(false);
    }
  };

  const downloadHistoryTemplate = async () => {
    try {
      const res = await api.get(`/api/super-admin/schools/${schoolId}/information/history-import/template`, {
        responseType: "blob",
      });
      const blob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "text/csv" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `${school?.name || "school"}-history-import-template.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to download history import template.");
    }
  };

  const pickHistoryFile = (event) => {
    const file = event.target.files?.[0] || null;
    event.target.value = "";

    if (!file) return;

    const lowerName = file.name.toLowerCase();
    if (!lowerName.endsWith(".csv") && !lowerName.endsWith(".txt")) {
      alert("Upload a CSV file. You can save Excel broadsheets as CSV before importing.");
      return;
    }

    setHistoryFile(file);
    setHistoryImportResult(null);
  };

  const importSchoolHistory = async () => {
    if (!historyFile) {
      alert("Select the school history CSV file first.");
      return;
    }

    if (
      !window.confirm(
        "Import this school history now? This will create/update sessions, classes, students, subjects, scores, and student graduation status."
      )
    ) {
      return;
    }

    setImportingHistory(true);
    try {
      const fd = new FormData();
      fd.append("file", historyFile);
      fd.append("make_latest_session_current", makeLatestSessionCurrent ? "1" : "0");

      const res = await api.post(`/api/super-admin/schools/${schoolId}/information/history-import`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      setHistoryImportResult(res.data?.data || null);
      setHistoryFile(null);
      alert(res.data?.message || "School history imported successfully.");
    } catch (err) {
      const firstValidationError = Object.values(err?.response?.data?.errors || {})
        .flat()
        .find(Boolean);
      alert(firstValidationError || err?.response?.data?.message || "Failed to import school history.");
    } finally {
      setImportingHistory(false);
    }
  };

  const saveSchoolAdminLoginLimit = async () => {
    const allowedLogins = Number(schoolAdminLoginLimit);
    if (!Number.isInteger(allowedLogins) || allowedLogins < 1 || allowedLogins > 500) {
      alert("Allowed active logins must be a number from 1 to 500.");
      return;
    }

    setSavingLoginLimit(true);
    try {
      const res = await api.put(`/api/super-admin/schools/${schoolId}/information/school-admin-login-limit`, {
        allowed_logins: allowedLogins,
      });
      setSchoolAdminLoginLimit(Number(res.data?.allowed_logins || allowedLogins));
      setSchoolAdminActiveDevices(Array.isArray(res.data?.active_devices) ? res.data.active_devices : []);
      alert(res.data?.message || "School admin login limit updated.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to save school admin login limit.");
    } finally {
      setSavingLoginLimit(false);
    }
  };

  const logoutSchoolAdminDevice = async (device) => {
    if (!device?.token_id) return;

    const label = device.admin_email || device.admin_name || "this school admin";
    if (!window.confirm(`Log out ${label} from this active device?`)) {
      return;
    }

    setLoggingOutTokenId(device.token_id);
    try {
      const res = await api.delete(
        `/api/super-admin/schools/${schoolId}/information/school-admin-devices/${device.token_id}`
      );
      setSchoolAdminActiveDevices(Array.isArray(res.data?.active_devices) ? res.data.active_devices : []);
      alert(res.data?.message || "Active device logged out.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to log out active device.");
    } finally {
      setLoggingOutTokenId(null);
    }
  };

  if (loading) {
    return <p>Loading school information...</p>;
  }

  return (
    <div className="sa-page sai-page sa-page--information">
      <section className="sa-page-hero sai-hero">
        <div>
          <span className="sa-page-eyebrow">School configuration</span>
          <h1>School Information</h1>
          <p>{school?.name ? `Manage ${school.name}` : "Manage school information"}</p>
        </div>
        <div className="sai-hero-actions">
          <img className="sa-page-art" src={schoolInformationArt} alt="" aria-hidden="true" />
          <button type="button" className="sai-back-button" onClick={() => navigate(-1)}>Back</button>
        </div>
      </section>

      <section className="sai-card" style={{ display: "none" }}>
        <h3>Branding</h3>
        <div className="sai-grid">
          <div className="sai-field">
            <label>School Logo</label>
            <div className="sai-upload">
              {(logoPreview || branding.school_logo_url) ? (
                <img
                  src={toAbsoluteUrl(logoPreview || branding.school_logo_url)}
                  alt="School logo preview"
                  className="sai-preview"
                />
              ) : (
                <span className="sai-empty">No logo uploaded</span>
              )}
              <input
                ref={logoInputRef}
                type="file"
                accept="image/*"
                onChange={(e) => pickFile(e, "logo")}
                style={{ display: "none" }}
              />
              <button type="button" onClick={() => logoInputRef.current?.click()}>
                {processingBrandingImage ? "Processing..." : "Select Logo"}
              </button>
              <small className="sai-note">
                Auto-compressed before upload. Recommended max {BRANDING_IMAGE_GUIDE.maxWidth}x{BRANDING_IMAGE_GUIDE.maxHeight}px.
              </small>
            </div>
          </div>

          <div className="sai-field">
            <label>Head of School Name</label>
            <input
              type="text"
              value={headName}
              onChange={(e) => setHeadName(e.target.value)}
              placeholder="Enter head of school name"
            />
          </div>

          <div className="sai-field">
            <label>School Location</label>
            <input
              type="text"
              value={schoolLocation}
              onChange={(e) => setSchoolLocation(e.target.value)}
              placeholder="Enter school location"
            />
          </div>

          <div className="sai-field">
            <label>Information Email</label>
            <input
              type="email"
              value={contactEmail}
              onChange={(e) => setContactEmail(e.target.value)}
              placeholder="contact@school.com"
            />
          </div>

          <div className="sai-field">
            <label>Mobile Number</label>
            <input
              type="tel"
              value={contactPhone}
              onChange={(e) => setContactPhone(e.target.value)}
              placeholder="+234 800 000 0000"
            />
          </div>

          <div className="sai-field">
            <label>Paystack Subaccount Code</label>
            <input
              type="text"
              value={paystackSubaccountCode}
              onChange={(e) => setPaystackSubaccountCode(e.target.value)}
              placeholder="ACCT_xxxxxxxxx"
            />
          </div>

          <div className="sai-field">
            <label>Head Signature</label>
            <div className="sai-upload">
              {(signaturePreview || branding.head_signature_url) ? (
                <img
                  src={toAbsoluteUrl(signaturePreview || branding.head_signature_url)}
                  alt="Head signature preview"
                  className="sai-preview sai-preview--signature"
                />
              ) : (
                <span className="sai-empty">No signature uploaded</span>
              )}
              <input
                ref={signatureInputRef}
                type="file"
                accept="image/*"
                onChange={(e) => pickFile(e, "signature")}
                style={{ display: "none" }}
              />
              <button type="button" onClick={() => signatureInputRef.current?.click()}>
                {processingBrandingImage ? "Processing..." : "Select Signature"}
              </button>
              <small className="sai-note">Signature is auto-compressed before upload.</small>
            </div>
          </div>
        </div>

        <div className="sai-actions">
          <button type="button" onClick={saveBranding} disabled={savingBranding || processingBrandingImage}>
            {savingBranding ? "Saving..." : "Save Branding"}
          </button>
        </div>
      </section>

<section className="sai-card sai-result-template-card">
        <h3>Result PDF Template</h3>
        <p className="sai-note">
          Configure how this school&apos;s normal result PDF and cumulative result PDF should appear. The CA columns
          still come from the school&apos;s exam records above.
        </p>

        <div className="sai-grid">
          <div className="sai-field">
            <label>Layout Style</label>
            <select
              value={resultTemplate.layout}
              onChange={(e) => updateResultTemplate("layout", e.target.value)}
            >
              <option value="compact">Compact result</option>
              <option value="classic">Classic full result</option>
            </select>
          </div>

          <div className="sai-field">
            <label>Primary Colour</label>
            <input
              type="color"
              value={resultTemplate.primary_color}
              onChange={(e) => updateResultTemplate("primary_color", e.target.value)}
            />
          </div>

          <div className="sai-field">
            <label>Accent Colour</label>
            <input
              type="color"
              value={resultTemplate.accent_color}
              onChange={(e) => updateResultTemplate("accent_color", e.target.value)}
            />
          </div>

          <div className="sai-field">
            <label>Watermark Opacity</label>
            <input
              type="number"
              min="0"
              max="0.2"
              step="0.01"
              value={resultTemplate.watermark_opacity}
              onChange={(e) => updateResultTemplate("watermark_opacity", e.target.value)}
            />
          </div>
        </div>


        <div className="sai-template-option-card">
          <h4>Score Allocation: Total 100</h4>
          <p className="sai-note">
            {resultTemplate.layout === "classic"
              ? "Choose the maximum for every field. CA, Exam, Psychomotor / Affective, Quiz and Note Copying must add up to exactly 100."
              : "Choose the five CA and Exam maximums. They must add up to exactly 100."}
          </p>
          <div className="sai-grid">
            {Array.from({ length: 5 }, (_, index) => (
              <div className="sai-field" key={`profile-ca-${index}`}>
                <label>CA {index + 1} name and maximum</label>
                <div className="sai-inline-fields">
                  <input value={resultTemplate.score_profile.ca_labels[index]} maxLength={30} onChange={(e) => updateScoreProfile("ca_labels", e.target.value, index)} />
                  <input type="number" min="0" max="100" value={resultTemplate.score_profile.ca_maxes[index]} onChange={(e) => updateScoreProfile("ca_maxes", e.target.value, index)} />
                </div>
              </div>
            ))}
            <div className="sai-field">
              <label>Examination maximum</label>
              <input type="number" min="0" max="100" value={resultTemplate.score_profile.exam_max} onChange={(e) => updateScoreProfile("exam_max", e.target.value)} />
            </div>
          </div>
          {resultTemplate.layout === "classic" ? (
            <>
              <h4>Psychomotor / Affective</h4>
              <div className="sai-grid">
                {Array.from({ length: 5 }, (_, index) => (
                  <div className="sai-field" key={`profile-psy-${index}`}>
                    <label>P{index + 1} name and maximum</label>
                    <div className="sai-inline-fields">
                      <input value={resultTemplate.score_profile.psychomotor_labels[index]} maxLength={30} onChange={(e) => updateScoreProfile("psychomotor_labels", e.target.value, index)} />
                      <input type="number" min="0" max="100" value={resultTemplate.score_profile.psychomotor_maxes[index]} onChange={(e) => updateScoreProfile("psychomotor_maxes", e.target.value, index)} />
                    </div>
                  </div>
                ))}
                <div className="sai-field">
                  <label>Quiz / Reading field name and maximum</label>
                  <div className="sai-inline-fields">
                    <input value={resultTemplate.score_profile.quiz_label} maxLength={30} onChange={(e) => updateScoreProfile("quiz_label", e.target.value)} />
                    <input type="number" min="0" max="100" value={resultTemplate.score_profile.quiz_max} onChange={(e) => updateScoreProfile("quiz_max", e.target.value)} />
                  </div>
                </div>
                <div className="sai-field">
                  <label>Note taking / copying field name and maximum</label>
                  <div className="sai-inline-fields">
                    <input value={resultTemplate.score_profile.note_copying_label} maxLength={30} onChange={(e) => updateScoreProfile("note_copying_label", e.target.value)} />
                    <input type="number" min="0" max="100" value={resultTemplate.score_profile.note_copying_max} onChange={(e) => updateScoreProfile("note_copying_max", e.target.value)} />
                  </div>
                </div>
              </div>
            </>
          ) : null}
          <p className={`sai-note ${scoreProfileTotal(resultTemplate.score_profile, resultTemplate.layout) === 100 ? "" : "sai-note--error"}`}>
            Configured allocation: {scoreProfileTotal(resultTemplate.score_profile, resultTemplate.layout)} / 100. Saving is allowed only at 100.
          </p>
        </div>
        <div className="sai-template-option-card">
          <h4>Result Mode By Education Level</h4>
          <p className="sai-note">Check Comments only only for the education levels that should use observation sheets instead of numeric marks.</p>
          <div className="sai-check-stack">
            {activeEducationLevels.map((level) => (
              <label className="sai-check sai-check-card sai-check-card--compact" key={level.key}>
                <input type="checkbox" checked={Boolean(examRecord.by_level?.[level.key]?.comments_only)} onChange={(event) => updateExamDraftCommentsOnly(level.key, event.target.checked)} />
                <span>{level.label}: Comments only</span>
              </label>
            ))}
          </div>
        </div>
        <div className="sai-result-template-grid">
          <div className="sai-template-option-card">
            <h4>Visible Sections</h4>
            <div className="sai-check-stack">
              {[
                ["show_student_photo", "Show student photo"],
                ["show_school_logo", "Show school logo"],
                ["show_watermark", "Use school logo as watermark"],
                ["show_attendance", "Show attendance"],
                ["show_behaviour", "Show behaviour rating"],
                ["show_signature", "Show head signature"],
                ["show_result_position", "Show class position"],
              ].map(([key, label]) => (
                <label className="sai-check sai-check-card sai-check-card--compact" key={key}>
                  <input
                    type="checkbox"
                    checked={Boolean(resultTemplate[key])}
                    onChange={(e) => updateResultTemplate(key, e.target.checked)}
                  />
                  <span>{label}</span>
                </label>
              ))}
            </div>
          </div>

          <div className="sai-template-option-card">
            <h4>Third Term Normal Result</h4>
            <p className="sai-note">
              When enabled, the normal third-term PDF shows CA + exam + third-term total, then first and second
              term totals beside it. Cumulative PDF remains separate.
            </p>
            <label className="sai-check sai-check-card sai-check-card--compact">
              <input
                type="checkbox"
                checked={Boolean(resultTemplate.third_term?.show_previous_term_totals)}
                onChange={(e) =>
                  updateNestedResultTemplate("third_term", "show_previous_term_totals", e.target.checked)
                }
              />
              <span>Show first/second/third term totals on third-term normal PDF</span>
            </label>
          </div>

          <div className="sai-template-option-card">
            <h4>Cumulative Result</h4>
            <div className="sai-check-stack">
              <label className="sai-check sai-check-card sai-check-card--compact">
                <input
                  type="checkbox"
                  checked={Boolean(resultTemplate.cumulative?.show_term_totals)}
                  onChange={(e) => updateNestedResultTemplate("cumulative", "show_term_totals", e.target.checked)}
                />
                <span>Show first, second, and third term totals</span>
              </label>
              <label className="sai-check sai-check-card sai-check-card--compact">
                <input
                  type="checkbox"
                  checked={Boolean(resultTemplate.cumulative?.show_average)}
                  onChange={(e) => updateNestedResultTemplate("cumulative", "show_average", e.target.checked)}
                />
                <span>Show cumulative average</span>
              </label>
            </div>
          </div>
        </div>

        <div className="sai-result-template-preview">
          <div
            className="sai-result-template-mini"
            style={{
              "--template-primary": resultTemplate.primary_color,
              "--template-accent": resultTemplate.accent_color,
            }}
          >
            <div className="sai-result-mini-head">
              <span>Photo</span>
              <strong>{school?.name || "School Name"}</strong>
              <span>Logo</span>
            </div>
            <div className="sai-result-mini-title">REPORT SHEET</div>
            <div className="sai-result-mini-row">
              <span>Subject</span>
              <span>CA</span>
              <span>Exam</span>
              <span>Total</span>
              {resultTemplate.third_term?.show_previous_term_totals ? <span>1st/2nd/3rd</span> : null}
            </div>
          </div>
        </div>

        <div className="sai-actions">
          <button type="button" onClick={saveResultTemplate} disabled={savingResultTemplate}>
            {savingResultTemplate ? "Saving..." : "Save Result PDF Template"}
          </button>
        </div>
      </section>

      <section className="sai-card">
        <h3>Class Templates</h3>
        <p className="sai-note">
          Configure levels, classes, and departments per class row. Use comma (,) for bulk department input.
        </p>

        <div className="sai-template-table">
          <div className="sai-template-header">
            <div>Level Setup</div>
            <div>Class Setup</div>
            <div>Department Setup</div>
          </div>

          {classTemplates.map((section, sectionIndex) => (
              <div className="sai-template-line" key={section.key || sectionIndex}>
                <div className="sai-template-level">
                  <label className="sai-check">
                    <input
                      type="checkbox"
                      checked={Boolean(section.enabled)}
                      onChange={(e) => updateSection(sectionIndex, { enabled: e.target.checked })}
                    />
                    <span>Enable Level</span>
                  </label>
                  <input
                    type="text"
                    value={section.label || ""}
                    onChange={(e) => updateSection(sectionIndex, { label: e.target.value })}
                    placeholder={section.key}
                  />
                </div>

                <div className="sai-template-classes">
                  {(section.classes || []).map((row, classIndex) => {
                    const classRow = normalizeClassRow(row);
                    return (
                      <div key={`${section.key}-${classIndex}`} className="sai-template-row">
                        <label className="sai-check">
                          <input
                            type="checkbox"
                            checked={Boolean(classRow.enabled)}
                            onChange={(e) => updateClassEnabled(sectionIndex, classIndex, e.target.checked)}
                            disabled={!section.enabled}
                          />
                          <span>Show</span>
                        </label>
                        <input
                          type="text"
                          value={classRow.name || ""}
                          onChange={(e) => updateClassName(sectionIndex, classIndex, e.target.value)}
                          placeholder={`${section.label || section.key} ${classIndex + 1}`}
                          disabled={!section.enabled}
                        />
                      </div>
                    );
                  })}
                </div>

                <div className="sai-template-departments">
                  {(section.classes || []).map((row, classIndex) => {
                    const classRow = normalizeClassRow(row);
                    return (
                      <div key={`${section.key}-department-${classIndex}`} className="sai-template-row">
                        <label className="sai-check">
                          <input
                            type="checkbox"
                            checked={Boolean(classRow.department_enabled)}
                            onChange={(e) =>
                              updateClassDepartmentEnabled(sectionIndex, classIndex, e.target.checked)
                            }
                            disabled={!section.enabled || !classRow.enabled}
                          />
                          <span>Enable</span>
                        </label>
                        <input
                          type="text"
                          value={classRow.department_input}
                          onChange={(e) => updateClassDepartmentInput(sectionIndex, classIndex, e.target.value)}
                          placeholder="Gold, Diamond, Silver"
                          disabled={!section.enabled || !classRow.enabled}
                        />
                      </div>
                    );
                  })}
                  <small>Each class has its own department list.</small>
                </div>
              </div>
            ))}
        </div>

        <div className="sai-actions">
          <button type="button" onClick={saveClassTemplates} disabled={savingClassTemplates}>
            {savingClassTemplates ? "Saving..." : "Save Class Templates"}
          </button>
        </div>
      </section>

      <section className="sai-card sai-history-card">
        <div className="sai-section-head">
          <div>
            <h3>Past Student History Import</h3>
            <p className="sai-note">
              Upload an Excel-compatible CSV broadsheet to migrate old sessions, classes, students, subjects,
              results, and graduation status into this school.
            </p>
          </div>
          <button type="button" onClick={downloadHistoryTemplate}>
            Download CSV Template
          </button>
        </div>

        <div className="sai-history-guide">
          <span>Required: session, term, class, student_name, username.</span>
          <span>Template format: use the downloaded CSV so each school gets the right CA columns for its assessment schema.</span>
          <span>Legacy wide format with subject total scores is still accepted and auto-distributed into the school CA/exam structure.</span>
          <span>Status can be active, inactive, or graduated. Blank status is auto-detected.</span>
        </div>

        <div className="sai-upload sai-history-upload">
          <input
            ref={historyInputRef}
            type="file"
            accept=".csv,text/csv,text/plain"
            onChange={pickHistoryFile}
            style={{ display: "none" }}
          />
          <div>
            <strong>{historyFile ? historyFile.name : "No history CSV selected"}</strong>
            <small className="sai-note">Save Excel/XLSX broadsheets as CSV before uploading.</small>
          </div>
          <button type="button" onClick={() => historyInputRef.current?.click()} disabled={importingHistory}>
            Select CSV
          </button>
        </div>

        <label className="sai-check sai-history-check">
          <input
            type="checkbox"
            checked={makeLatestSessionCurrent}
            onChange={(e) => setMakeLatestSessionCurrent(e.target.checked)}
          />
          <span>Make the latest imported session the current session</span>
        </label>

        <div className="sai-actions">
          <button type="button" onClick={importSchoolHistory} disabled={importingHistory || !historyFile}>
            {importingHistory ? "Importing..." : "Import School History"}
          </button>
        </div>

        <div className="sai-login-audit">
          <div className="sai-section-head">
            <div>
              <h3>School Admin Login</h3>
              <p className="sai-note">
                Control how many active devices school admins can use, then log out any active device when needed.
              </p>
            </div>
            <span className="sai-login-count">
              {schoolAdminActiveDevices.length} active / {schoolAdminLoginLimit || 2} allowed
            </span>
          </div>

          <div className="sai-login-limit-panel">
            <div className="sai-field">
              <label>Allowed Active School Admin Logins</label>
              <input
                type="number"
                min="1"
                max="500"
                value={schoolAdminLoginLimit}
                onChange={(e) => setSchoolAdminLoginLimit(e.target.value)}
              />
            </div>
            <button type="button" onClick={saveSchoolAdminLoginLimit} disabled={savingLoginLimit}>
              {savingLoginLimit ? "Saving..." : "Save Login Limit"}
            </button>
          </div>

          <h4 className="sai-login-subtitle">Active Devices</h4>
          <div className="sai-table-wrap">
            <table className="sai-login-table" data-sticky-identity="two">
              <thead>
                <tr>
                  <th>S/N</th>
                  <th>Admin</th>
                  <th>Device</th>
                  <th>IP Address</th>
                  <th>Browser</th>
                  <th>Platform</th>
                  <th>Last Used</th>
                  <th>Login Time</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {schoolAdminActiveDevices.length > 0 ? (
                  schoolAdminActiveDevices.map((row, index) => (
                    <tr key={row.token_id || `${row.admin_email}-${index}`}>
                      <td>{index + 1}</td>
                      <td>
                        <strong>{row.admin_name || "-"}</strong>
                        <small>{row.admin_email || "-"}</small>
                      </td>
                      <td>
                        <strong>{row.device_type || "Active session"}</strong>
                        <small>{row.device_model || row.token_name || "Device details pending next login"}</small>
                      </td>
                      <td>{row.ip_address || "-"}</td>
                      <td>{row.browser || "-"}</td>
                      <td>{row.platform || "-"}</td>
                      <td>{formatLoginDate(row.last_used_at || row.created_at)}</td>
                      <td>{formatLoginDate(row.logged_in_at || row.created_at)}</td>
                      <td>
                        <button
                          type="button"
                          className="sai-danger-btn"
                          onClick={() => logoutSchoolAdminDevice(row)}
                          disabled={loggingOutTokenId === row.token_id}
                        >
                          {loggingOutTokenId === row.token_id ? "Logging out..." : "Log out"}
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="9" className="sai-empty-row">
                      No active school-admin device is currently logged in for this school.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <h4 className="sai-login-subtitle">Recent Login History</h4>
          <div className="sai-table-wrap">
            <table className="sai-login-table" data-sticky-identity="two">
              <thead>
                <tr>
                  <th>S/N</th>
                  <th>Admin</th>
                  <th>Login Time</th>
                  <th>IP Address</th>
                  <th>Device</th>
                  <th>Browser</th>
                  <th>Platform</th>
                  <th>PC Name</th>
                  <th>Location</th>
                </tr>
              </thead>
              <tbody>
                {schoolAdminLogins.length > 0 ? (
                  schoolAdminLogins.map((row, index) => (
                    <tr key={row.id || `${row.admin_email}-${index}`}>
                      <td>{index + 1}</td>
                      <td>
                        <strong>{row.admin_name || "-"}</strong>
                        <small>{row.admin_email || "-"}</small>
                      </td>
                      <td>{formatLoginDate(row.logged_in_at)}</td>
                      <td>{row.ip_address || row.forwarded_ip || "-"}</td>
                      <td>
                        <strong>{row.device_type || "Unknown"}</strong>
                        <small>{row.device_model || "Model unavailable"}</small>
                      </td>
                      <td>{row.browser || "-"}</td>
                      <td>{row.platform || "-"}</td>
                      <td>{row.pc_name || "Not exposed by browser"}</td>
                      <td>{row.location_label || "IP only"}</td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="9" className="sai-empty-row">
                      No school-admin login has been captured yet. New successful logins will appear here.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {historyImportResult?.summary ? (
          <div className="sai-import-result">
            <h4>Last Import Summary</h4>
            <div className="sai-import-grid">
              {Object.entries(historyImportResult.summary).map(([key, value]) => (
                <span key={key}>
                  <strong>{String(key).replaceAll("_", " ")}</strong>
                  {value}
                </span>
              ))}
            </div>
            {Array.isArray(historyImportResult.warnings) && historyImportResult.warnings.length > 0 ? (
              <div className="sai-import-warnings">
                <strong>Warnings</strong>
                {historyImportResult.warnings.map((warning, index) => (
                  <p key={`${warning}-${index}`}>{warning}</p>
                ))}
              </div>
            ) : null}
          </div>
        ) : null}
      </section>
    </div>
  );
}
