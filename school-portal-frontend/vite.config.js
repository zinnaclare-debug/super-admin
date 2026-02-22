import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  test: {
    environment: "jsdom",
  },

  // Build into Laravel public/build
  build: {
    outDir: "../public/build",
    emptyOutDir: true,
  },

  // IMPORTANT: assets will be served from /build/...
  base: "/build/",
});
