import {create} from 'zustand';

const useTokenStore = create((set) => ({
  token: null,
  setToken: (token: any) => set({ token }),
}));

export default useTokenStore;
