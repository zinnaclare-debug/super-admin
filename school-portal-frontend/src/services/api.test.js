import { describe, expect, it } from "vitest";
import api from "./api";

describe("api client", () => {
  it("uses same-origin base URL by default", () => {
    expect(new URL(api.defaults.baseURL).origin).toBe(window.location.origin);
  });

  it("sends credentials and json headers", () => {
    expect(api.defaults.withCredentials).toBe(true);
    expect(api.defaults.headers.Accept).toBe("application/json");
  });
});

