import { create } from "zustand";

const useActionSheet = create()((set) => ({
    showActionSheet: false,
    setShowActionSheet: (data: boolean) => set({ showActionSheet: data }),
}));

export default useActionSheet;