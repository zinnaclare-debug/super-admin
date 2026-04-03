import FeatureShell from "./FeatureShell";

export default function StaffFeatureLayout({ title, subtitle = "", children, showBack = true, showHeader = true }) {
  return (
    <FeatureShell title={title} subtitle={subtitle} showBack={showBack} showHeader={showHeader}>
      {children}
    </FeatureShell>
  );
}
