import { create } from 'zustand';

type AuthDataTypes = {
  [key: string]: string | undefined; 
};

type useAuthDataTypes = {
  fields: AuthDataTypes;
  setField: (field: string, value: string) => void;
  clearFields: () => void;
};

const useAuthData = create<useAuthDataTypes>((set) => ({
  fields: {
    fullName: '',
    username: '',
    authEmail: '',
    authPassword: '',
    loginEmail: '',
    loginPassword: '',
    phoneNumber: '',
    referralCode: '',
    code: '',
    updatedPassword: '',
  },
  
  setField: (field, value) =>
    set((state) => ({
      fields: { ...state.fields, [field]: value },
    })),
  
  clearFields: () => set(() => ({ fields: {} })),
}));

export default useAuthData;
