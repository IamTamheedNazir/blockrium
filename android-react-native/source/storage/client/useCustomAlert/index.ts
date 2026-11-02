import { create } from "zustand";

const useCustomAlert = create<CUSTOM_ALERT_DATA_TYPES>()((set) => ({
    showAlert: false,
    setShowAlert: (alert: boolean) => set(() => ({showAlert: alert}))
}))

export default useCustomAlert;