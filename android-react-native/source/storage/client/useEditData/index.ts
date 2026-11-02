import { create } from 'zustand';

type EditDataTypes = {
  [key: string]: string | undefined; 
};

type useEditDataTypes = {
  fields: EditDataTypes;
  setField: (field: string, value: string) => void;
  clearFields: () => void;
};

const useEditData = create<useEditDataTypes>((set) => ({
  fields: {
    fullName: '',
    fatherName: '',
    phoneNumber: '',
    gender: '',
    referralCode: '',
    email: ''
  },
  
  setField: (field, value) =>
    set((state) => ({
      fields: { ...state.fields, [field]: value },
    })),
  
  clearFields: () => set(() => ({ fields: {} })),
}));

export default useEditData;
