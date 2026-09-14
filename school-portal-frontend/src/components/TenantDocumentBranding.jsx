import { useEffect } from "react";
import api from "../services/api";

function setIcon(rel, href) {
  let link = document.querySelector(`link[rel="${rel}"]`);
  if (!link) {
    link = document.createElement("link");
    link.rel = rel;
    document.head.appendChild(link);
  }
  link.removeAttribute("type");
  link.href = href;
}

export default function TenantDocumentBranding() {
  useEffect(() => {
    let active = true;

    api.get("/api/tenant/context")
      .then((response) => {
        if (!active) return;
        const school = response.data?.is_tenant ? response.data.school : null;
        if (!school?.name) return;

        document.title = school.name;
        const source = school.logo_url || "/tenant-pwa-icon/192.png";
        const separator = source.includes("?") ? "&" : "?";
        const icon = `${source}${separator}favicon=${Date.now()}`;
        setIcon("icon", icon);
        setIcon("apple-touch-icon", icon);
      })
      .catch(() => undefined);

    return () => {
      active = false;
    };
  }, []);

  return null;
}