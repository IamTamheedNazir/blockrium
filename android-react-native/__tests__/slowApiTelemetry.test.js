const {
  claimSlowApiReport,
  SLOW_API_THRESHOLD_MS,
} = require('../source/utils/slowApiTelemetry');

describe('slow API telemetry', () => {
  it('ignores requests below the reporting threshold', () => {
    expect(
      claimSlowApiReport('auth/profile', 'GET', SLOW_API_THRESHOLD_MS - 1),
    ).toBe(false);
  });

  it('claims at most one slow report per endpoint and method in a session', () => {
    expect(claimSlowApiReport('auth/profile', 'GET', SLOW_API_THRESHOLD_MS)).toBe(
      true,
    );
    expect(claimSlowApiReport('auth/profile', 'GET', 3500)).toBe(false);
    expect(claimSlowApiReport('auth/profile', 'POST', 3500)).toBe(true);
    expect(claimSlowApiReport('auth/wallet', 'GET', 3500)).toBe(true);
  });
});
