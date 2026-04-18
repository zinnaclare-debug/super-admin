import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import LiveClassRoom from "../../shared/live-class/LiveClassRoom";

export default function StaffLiveClassRoom() {
  return (
    <StaffFeatureLayout title="Live Class Room" showHeader={false}>
      <LiveClassRoom audience="staff" />
    </StaffFeatureLayout>
  );
}
