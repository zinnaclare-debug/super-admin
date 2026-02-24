import axios from "axios";
import { getStoredToken } from "../utils/authStorage";

const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL ||
  window.location.origin;

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    "X-Requested-With": "XMLHttpRequest",
    Accept: "application/json",
  },
  // Bearer-token auth only; avoid cookie/session auth bleed between roles.
  withCredentials: false,
});

api.interceptors.request.use((config) => {
  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
