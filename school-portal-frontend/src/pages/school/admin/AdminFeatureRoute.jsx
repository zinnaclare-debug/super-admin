import { useParams } from "react-router-dom";

export default function AdminFeatureRouter() {
  const { feature } = useParams();

  return (
    <div>
      <h2>{feature.replace("_", " ").toUpperCase()}</h2>
      <p>Feature content goes here</p>
    </div>
  );
}
