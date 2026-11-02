export const SLOW_API_THRESHOLD_MS = 2000;

const reportedRequests = new Set<string>();

export const claimSlowApiReport = (
  endpoint: string,
  method: string,
  durationMs: number,
) => {
  if (!endpoint || durationMs < SLOW_API_THRESHOLD_MS) {
    return false;
  }

  const key = `${method.toUpperCase()}:${endpoint}`;
  if (reportedRequests.has(key)) {
    return false;
  }

  reportedRequests.add(key);
  return true;
};
