const OUTPUT_SIZE = 512;
const OUTPUT_TYPE = "image/jpeg";
const OUTPUT_EXTENSION = "jpg";
const MAX_OUTPUT_BYTES = 250 * 1024;
const MIN_QUALITY = 0.55;
const INITIAL_QUALITY = 0.82;
const DEFAULT_BRANDING_MAX_WIDTH = 1200;
const DEFAULT_BRANDING_MAX_HEIGHT = 1200;
const DEFAULT_BRANDING_MAX_BYTES = 350 * 1024;

function loadImage(file) {
  return new Promise((resolve, reject) => {
    const objectUrl = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => {
      URL.revokeObjectURL(objectUrl);
      resolve(image);
    };
    image.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error("Failed to read image."));
    };
    image.src = objectUrl;
  });
}

function canvasToBlob(canvas, type, quality) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (!blob) {
        reject(new Error("Failed to compress image."));
        return;
      }
      resolve(blob);
    }, type, quality);
  });
}

async function exportCompressedJpeg(canvas, maxBytes) {
  let quality = INITIAL_QUALITY;
  let blob = await canvasToBlob(canvas, OUTPUT_TYPE, quality);

  while (blob.size > maxBytes && quality > MIN_QUALITY) {
    quality = Math.max(MIN_QUALITY, Number((quality - 0.08).toFixed(2)));
    blob = await canvasToBlob(canvas, OUTPUT_TYPE, quality);
    if (quality === MIN_QUALITY) break;
  }

  return blob;
}

export async function compressProfilePhoto(file) {
  if (!(file instanceof File)) {
    throw new Error("Invalid image file.");
  }

  const image = await loadImage(file);
  const canvas = document.createElement("canvas");
  canvas.width = OUTPUT_SIZE;
  canvas.height = OUTPUT_SIZE;

  const context = canvas.getContext("2d", { alpha: false });
  if (!context) {
    throw new Error("Image compression is not supported in this browser.");
  }

  context.fillStyle = "#ffffff";
  context.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);

  const sourceSize = Math.min(image.width, image.height);
  const sourceX = (image.width - sourceSize) / 2;
  const sourceY = (image.height - sourceSize) / 2;

  context.drawImage(
    image,
    sourceX,
    sourceY,
    sourceSize,
    sourceSize,
    0,
    0,
    OUTPUT_SIZE,
    OUTPUT_SIZE
  );

  const blob = await exportCompressedJpeg(canvas, MAX_OUTPUT_BYTES);

  const baseName = String(file.name || "profile-photo").replace(/\.[^.]+$/, "");
  return new File([blob], `${baseName}.${OUTPUT_EXTENSION}`, {
    type: OUTPUT_TYPE,
    lastModified: Date.now(),
  });
}

export async function compressBrandingImage(
  file,
  {
    maxWidth = DEFAULT_BRANDING_MAX_WIDTH,
    maxHeight = DEFAULT_BRANDING_MAX_HEIGHT,
    maxBytes = DEFAULT_BRANDING_MAX_BYTES,
    background = "#ffffff",
  } = {}
) {
  if (!(file instanceof File)) {
    throw new Error("Invalid image file.");
  }

  const image = await loadImage(file);
  const scale = Math.min(maxWidth / image.width, maxHeight / image.height, 1);
  const outputWidth = Math.max(1, Math.round(image.width * scale));
  const outputHeight = Math.max(1, Math.round(image.height * scale));

  const canvas = document.createElement("canvas");
  canvas.width = outputWidth;
  canvas.height = outputHeight;

  const context = canvas.getContext("2d", { alpha: false });
  if (!context) {
    throw new Error("Image compression is not supported in this browser.");
  }

  context.fillStyle = background;
  context.fillRect(0, 0, outputWidth, outputHeight);
  context.drawImage(image, 0, 0, outputWidth, outputHeight);

  const blob = await exportCompressedJpeg(canvas, maxBytes);
  const baseName = String(file.name || "branding-image").replace(/\.[^.]+$/, "");
  return new File([blob], `${baseName}.${OUTPUT_EXTENSION}`, {
    type: OUTPUT_TYPE,
    lastModified: Date.now(),
  });
}

export const PROFILE_PHOTO_GUIDE = {
  size: OUTPUT_SIZE,
  maxBytes: MAX_OUTPUT_BYTES,
};

export const BRANDING_IMAGE_GUIDE = {
  maxWidth: DEFAULT_BRANDING_MAX_WIDTH,
  maxHeight: DEFAULT_BRANDING_MAX_HEIGHT,
  maxBytes: DEFAULT_BRANDING_MAX_BYTES,
};
