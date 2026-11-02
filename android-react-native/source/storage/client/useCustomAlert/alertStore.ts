import { create } from 'zustand';

// Define the Snack Mode type and the interface for the store
type SnackMode = 'DEFAULT' | 'SUCCESS' | 'ERROR'; 

interface SnackState {
  snackTitle: string | null;
  selectSnack: SnackMode;
  timer: NodeJS.Timeout | null; // Use NodeJS.Timeout for the timer type
  onCloseSnack: () => void;
  setSnackTitle: (title: string | null) => void;
  setSnackMode: (mode: SnackMode) => void;
  setTimer: (timer: NodeJS.Timeout | null) => void; // Add setTimer to the store
}

// Create the Zustand store with the defined types
const useSnackStore = create<SnackState>((set) => ({
  snackTitle: null,
  selectSnack: 'DEFAULT',
  timer: null,
  onCloseSnack: () => set({ snackTitle: null, selectSnack: 'DEFAULT' }),
  setSnackTitle: (title) => set({ snackTitle: title }),
  setSnackMode: (mode) => set({ selectSnack: mode }),
  setTimer: (timer) => set({ timer }), // Set the timer in the Zustand store
}));

export default useSnackStore;
