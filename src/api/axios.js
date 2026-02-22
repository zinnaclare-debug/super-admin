import axios from "axios";

const api = axios.create({
  baseURL: window.location.origin,
  withCredentials: true,
  headers: {
    Accept: "application/json",
  },
});

export default api;
