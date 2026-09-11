import axios from "axios";
import { getStoredToken } from "../utils/authStorage";
import { clearMobileSchool as clearStoredMobileSchool, getMobileSchool, setMobileSchool } from "../utils/mobileSchool";

export const isMobileBuild = import.meta.env.MODE === "mobile";
export const centralMobileApiBaseUrl = (
  import.meta.env.VITE_CENTRAL_API_BASE_URL || "https://lyt.com.ng"
).replace(/\/$/, "");

const selectedMobileSchool = isMobileBuild ? getMobileSchool() : null;
const API_BASE_URL = isMobileBuild
  ? selectedMobileSchool?.api_base_url || centralMobileApiBaseUrl
  : import.meta.env.VITE_API_BASE_URL || window.location.origin;

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    "X-Requested-With": "XMLHttpRequest",
    Accept: "application/json",
  },
  // Bearer-token auth only; avoid cookie/session auth bleed between roles.
  withCredentials: false,
});

export function selectMobileSchool(school) {
  const selectedSchool = setMobileSchool(school);
  api.defaults.baseURL = selectedSchool.api_base_url;
  return selectedSchool;
}

export function clearMobileSchool() {
  clearStoredMobileSchool();
  api.defaults.baseURL = centralMobileApiBaseUrl;
}

api.interceptors.request.use((config) => {
  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;