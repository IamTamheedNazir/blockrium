import {useEffect} from 'react';
import {AppState} from 'react-native';

export const useAppResumeRefresh = (
  enabled: boolean,
  refresh: () => void | Promise<unknown>,
) => {
  useEffect(() => {
    let previousState = AppState.currentState;
    const subscription = AppState.addEventListener('change', nextState => {
      const resumed = previousState !== 'active' && nextState === 'active';
      previousState = nextState;
      if (enabled && resumed) {
        Promise.resolve(refresh()).catch(() => undefined);
      }
    });

    return () => subscription.remove();
  }, [enabled, refresh]);
};
