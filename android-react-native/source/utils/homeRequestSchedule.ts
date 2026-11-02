export type HomeRequestStage = 'secondary' | 'activity' | 'squad';

type InteractionTask = {
  cancel: () => void;
};

type RunAfterInteractions = (callback: () => void) => InteractionTask;

export const scheduleHomeRequestStages = (
  runAfterInteractions: RunAfterInteractions,
  onStageReady: (stage: HomeRequestStage) => void,
) => {
  let cancelled = false;
  let secondaryTimer: ReturnType<typeof setTimeout> | undefined;
  let activityTimer: ReturnType<typeof setTimeout> | undefined;
  let squadTimer: ReturnType<typeof setTimeout> | undefined;

  const interactionTask = runAfterInteractions(() => {
    if (cancelled) {
      return;
    }

    activityTimer = setTimeout(() => onStageReady('activity'), 300);
    squadTimer = setTimeout(() => onStageReady('squad'), 900);
    secondaryTimer = setTimeout(() => onStageReady('secondary'), 1600);
  });

  return () => {
    cancelled = true;
    interactionTask.cancel();
    if (secondaryTimer) {
      clearTimeout(secondaryTimer);
    }
    if (activityTimer) {
      clearTimeout(activityTimer);
    }
    if (squadTimer) {
      clearTimeout(squadTimer);
    }
  };
};
