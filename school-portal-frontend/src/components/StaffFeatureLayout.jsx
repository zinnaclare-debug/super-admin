import FeatureShell from "./FeatureShell";

export default function StaffFeatureLayout({ title, subtitle = "", children, showBack = true }) {
  return (
    <FeatureShell title={title} subtitle={subtitle} showBack={showBack}>
      {children}
    </FeatureShell>
  );
}
