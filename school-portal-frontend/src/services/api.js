// import axios from "axios";

// const api = axios.create({
//   baseURL: "http://127.0.0.1:8000",
//   headers: {
//     Accept: "application/json",
//   },
// });

// // Attach token on every request
// api.interceptors.request.use(
//   (config) => {
//     const token = localStorage.getItem("token"); // ✅ unified key
//     const url = config.url || "";

//     // Don't attach token for auth endpoints (login/logout) to avoid interfering with auth flow
//     const isAuthEndpoint = url.includes("/api/login") || url.includes("/api/logout");

//     if (token && !isAuthEndpoint) {
//       config.headers.Authorization = `Bearer ${token}`;
//     }
//     return config;
//   },
//   (error) => Promise.reject(error)
// );

// // Global response handler: if a request returns 401/403, clear auth and redirect to login
// // Global response handler: if a request returns 401, clear auth and redirect to login.
// // For 403 (Forbidden) we keep the session and surface a permission message instead of logging out.
// api.interceptors.response.use(
//   (response) => response,
//   (error) => {
//     const status = error.response?.status;
//     const url = error.config?.url || "";
//     const isAuthEndpoint = url.includes("/api/login") || url.includes("/api/logout");

//     if (isAuthEndpoint) {
//       return Promise.reject(error);
//     }

//     if (status === 401) {
//       // Unauthorized — token invalid/expired
//       localStorage.removeItem("token");
//       localStorage.removeItem("user");
//       // force navigation to login page
//       window.location.href = "/login";
//     } else if (status === 403) {
//       // Forbidden — user does not have permission for this resource
//       console.warn("Permission denied for request:", url, error.response?.data);
//     }

//     return Promise.reject(error);
//   }
// );

// export default api; 

import axios from "axios";

const api = axios.create({
  baseURL: "http://localhost:8000",
  headers: {
    "X-Requested-With": "XMLHttpRequest",
    "Accept": "application/json",
  },
  withCredentials: true,
});

// attach token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// ensure cookies are sent for cross-site requests (Sanctum session auth)
api.defaults.withCredentials = true;

export default api;
