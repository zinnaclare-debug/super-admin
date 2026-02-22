import axios from "axios";

const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL ||
  window.location.origin;

const api = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true, // 🔴 REQUIRED FOR SANCTUM
  headers: {
   
    Accept: "application/json",
  },
});

export default api;
