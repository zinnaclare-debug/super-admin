import { useParams } from "react-router-dom";

export default function FeaturePage() {
  const { featureKey } = useParams();

  return (
    <div>
      <h2>Feature: {featureKey}</h2>
      <p>This is where you’ll build the feature UI later.</p>
    </div>
  );
}
