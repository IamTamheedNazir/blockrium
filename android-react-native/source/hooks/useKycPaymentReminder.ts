import {useEffect, useState} from 'react';
import {AppState, AppStateStatus} from 'react-native';

export const useKycPaymentReminder = (
  isPaymentPending: boolean,
  refreshStatus: () => Promise<boolean>,
) => {
  const [appState, setAppState] = useState<AppStateStatus>(() =>
    AppState.currentState === 'background' ||
    AppState.currentState === 'inactive'
      ? AppState.currentState
      : 'active',
  );
  const [isDismissed, setIsDismissed] = useState(false);
  const [resumeStatusReady, setResumeStatusReady] = useState(true);

  useEffect(() => {
    let previousState = AppState.currentState;
    let refreshSequence = 0;
    let isMounted = true;
    const subscription = AppState.addEventListener('change', nextState => {
      if (previousState !== 'active' && nextState === 'active') {
        const sequence = ++refreshSequence;
        setIsDismissed(false);
        setResumeStatusReady(false);
        refreshStatus()
          .then(isFresh => {
            if (isMounted && sequence === refreshSequence) {
              setResumeStatusReady(isFresh);
            }
          })
          .catch(() => {
            if (isMounted && sequence === refreshSequence) {
              setResumeStatusReady(false);
            }
          });
      }

      previousState = nextState;
      setAppState(nextState);
    });

    return () => {
      isMounted = false;
      subscription.remove();
    };
  }, [refreshStatus]);

  useEffect(() => {
    if (!isPaymentPending) {
      setIsDismissed(false);
    }
  }, [isPaymentPending]);

  return {
    visible:
      isPaymentPending &&
      appState === 'active' &&
      resumeStatusReady &&
      !isDismissed,
    dismiss: () => setIsDismissed(true),
  };
};
