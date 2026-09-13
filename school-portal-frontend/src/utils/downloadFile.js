import { Capacitor } from "@capacitor/core";
import { Directory, Filesystem } from "@capacitor/filesystem";

const safeName = (name) => String(name || "document.pdf").replace(/[^a-z0-9._-]+/gi, "_");

const blobToBase64 = (blob) => new Promise((resolve, reject) => {
  const reader = new FileReader();
  reader.onerror = () => reject(reader.error || new Error("Unable to read download."));
  reader.onloadend = () => resolve(String(reader.result || "").split(",")[1] || "");
  reader.readAsDataURL(blob);
});

export async function saveDownload(blob, filename = "document.pdf") {
  const fileName = safeName(filename);
  if (Capacitor.isNativePlatform()) {
    await Filesystem.writeFile({
      path: `School Portal/${fileName}`,
      data: await blobToBase64(blob),
      directory: Directory.Documents,
      recursive: true,
    });
    return { native: true, message: `Saved to Documents/School Portal/${fileName}` };
  }

  const url = window.URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = fileName;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => window.URL.revokeObjectURL(url), 1000);
  return { native: false, message: "Download started." };
}