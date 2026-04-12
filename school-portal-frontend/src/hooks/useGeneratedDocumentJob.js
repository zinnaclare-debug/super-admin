import { useEffect, useState } from "react";
import api from "../services/api";

export function fileNameFromHeaders(headers, fallback) {
  const contentDisposition = headers?.["content-disposition"] || "";
  const match = contentDisposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
  if (!match?.[1]) return fallback || "document.pdf";
  return decodeURIComponent(match[1].replace(/"/g, "").trim());
}

export async function messageFromBlobError(blob, fallback) {
  try {
    const text = await blob.text();
    if (!text) return fallback;
    try {
      const parsed = JSON.parse(text);
      return parsed?.message || fallback;
    } catch {
      return text;
    }
  } catch {
    return fallback;
  }
}

export function useGeneratedDocumentJob() {
  const [job, setJob] = useState(null);
  const [requesting, setRequesting] = useState(false);
  const [downloading, setDownloading] = useState(false);

  const isProcessing = Boolean(job?.id) && ["pending", "processing"].includes(job?.status);

  useEffect(() => {
    if (!job?.id || !["pending", "processing"].includes(job.status)) {
      return undefined;
    }

    let cancelled = false;
    const timer = window.setTimeout(async () => {
      try {
        const res = await api.get(`/api/school-admin/generated-documents/${job.id}`);
        if (!cancelled) {
          setJob(res.data?.data || null);
        }
      } catch (e) {
        if (!cancelled) {
          setJob((current) =>
            current
              ? {
                  ...current,
                  status: "failed",
                  error_message:
                    e?.response?.data?.message || "Failed to refresh document status. Please try again.",
                }
              : current
          );
        }
      }
    }, 2500);

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [job]);

  const downloadGeneratedFile = async (fallbackName = "document.pdf") => {
    if (!job?.id) return;

    setDownloading(true);
    try {
      const res = await api.get(`/api/school-admin/generated-documents/${job.id}/file`, {
        responseType: "blob",
      });

      const contentType = String(res?.headers?.["content-type"] || res?.data?.type || "").toLowerCase();
      if (contentType.includes("application/json")) {
        const message = await messageFromBlobError(res.data, "Failed to download generated PDF.");
        throw new Error(message);
      }

      const pdfBlob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: "application/pdf" });
      const blobUrl = window.URL.createObjectURL(pdfBlob);
      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = fileNameFromHeaders(res.headers, job.file_name || fallbackName);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(blobUrl);
    } finally {
      setDownloading(false);
    }
  };

  return {
    job,
    setJob,
    requesting,
    setRequesting,
    downloading,
    isProcessing,
    downloadGeneratedFile,
  };
}
