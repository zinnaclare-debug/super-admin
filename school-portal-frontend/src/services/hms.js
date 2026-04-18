import { HMSReactiveStore } from "@100mslive/hms-video-store";

const hms = new HMSReactiveStore();

if (typeof hms.triggerOnSubscribe === "function") {
  hms.triggerOnSubscribe();
}

export const hmsActions = hms.getHMSActions();
export const hmsStore = hms.getStore();
export const hmsNotifications = hms.getNotifications();

export default hms;
