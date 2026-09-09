import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig(({ mode }) => {
  const isMobileBuild = mode === "mobile";

  return {
  plugins: [react()],
  test: {
    environment: "jsdom",
  },

  // Build into Laravel public/build
  build: {
    // Keep Laravel's web build separate from the self-contained Android bundle.
    outDir: isMobileBuild ? "dist-mobile" : "../public/build",
    emptyOutDir: true,
    cssCodeSplit: true,
    reportCompressedSize: false,
    chunkSizeWarningLimit: 900,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes("node_modules")) {
            return;
          }

          if (
            id.includes("react") ||
            id.includes("react-router-dom") ||
            id.includes("scheduler")
          ) {
            return "react-vendor";
          }

          if (id.includes("axios")) {
            return "http";
          }
        },
      },
    },
  },

  // IMPORTANT: assets will be served from /build/...
  base: isMobileBuild ? "./" : "/build/",
  };
});