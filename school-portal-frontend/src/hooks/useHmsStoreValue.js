import { useSyncExternalStore } from "react";
import { hmsStore } from "../services/hms";

export default function useHmsStoreValue(selector) {
  return useSyncExternalStore(
    (onStoreChange) => hmsStore.subscribe(() => onStoreChange(), selector),
    () => hmsStore.getState(selector),
    () => hmsStore.getState(selector)
  );
}
