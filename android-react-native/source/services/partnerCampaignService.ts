import {getApi, postApi} from '../storage/server';

export const partnerCampaignQueryKey = 'blockrium-partner-campaigns';

export type PartnerCampaignRewardMode =
  | 'click_claim'
  | 'admin_approval'
  | string;

export type PartnerCampaignReward = {
  amount: number;
  label: string;
  mode: PartnerCampaignRewardMode;
};

export type PartnerCampaignTaskReward = {
  amount: number;
  label: string;
};

export type PartnerCampaignSharedPromo = {
  enabled: boolean;
  rewardAmount?: number;
  rewardLabel?: string;
  dailyLimit?: number;
  dailyClaims?: number;
  totalLimit?: number;
  claims?: number;
  perUserLimit?: number;
};

export type PartnerCampaignStatus =
  | 'available'
  | 'clicked'
  | 'pending'
  | 'approved'
  | 'claimed'
  | 'rejected'
  | 'expired'
  | 'paused'
  | 'unavailable'
  | 'budget_exhausted'
  | string;

export type PartnerCampaignTask = {
  id: number | string;
  key?: string;
  type: string;
  title: string;
  instructions: string;
  targetUrl?: string;
  completionMode: PartnerCampaignRewardMode;
  reward: PartnerCampaignTaskReward;
  clickUrl?: string;
  submitUrl?: string;
  claimUrl?: string;
  userStatus: PartnerCampaignStatus;
  clicked: boolean;
  claimed: boolean;
  canClaim: boolean;
  raw: any;
};

export type PartnerCampaign = {
  id: number | string;
  publicId?: string;
  slug?: string;
  partnerName: string;
  title: string;
  description: string;
  imageUrl?: string;
  startsAt?: string;
  endsAt?: string;
  reward: PartnerCampaignReward;
  sharedPromo?: PartnerCampaignSharedPromo;
  tasks: PartnerCampaignTask[];
  smartLink?: string;
  clickUrl?: string;
  claimUrl?: string;
  userStatus: PartnerCampaignStatus;
  clicked: boolean;
  claimed: boolean;
  canClaim: boolean;
  raw: any;
};

export type PartnerCampaignActionKind =
  | 'start'
  | 'submit'
  | 'claim'
  | 'verify'
  | 'none';

export type PartnerCampaignActionState = {
  kind: PartnerCampaignActionKind;
  label: string;
  disabled: boolean;
  tone: 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
  helperText?: string;
};

export type PartnerTaskProofPayload = {
  evidenceUrl?: string;
  evidenceNote?: string;
};

const BLOCKED_STATUS_MESSAGES: Record<string, string> = {
  expired: 'This campaign has ended.',
  paused: 'This campaign is paused right now.',
  unavailable: 'This campaign is not available for your account.',
  budget_exhausted: 'Reward budget is exhausted for this campaign.',
  reward_budget_exhausted: 'Reward budget is exhausted for this campaign.',
  exhausted: 'Reward budget is exhausted for this campaign.',
};

const normalizeToken = (value: unknown) =>
  String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_');

const asTrimmedString = (value: unknown) => {
  const text = String(value ?? '').trim();
  return text.length > 0 ? text : undefined;
};

const asBoolean = (value: unknown) => {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return value > 0;
  }

  return ['1', 'true', 'yes'].includes(normalizeToken(value));
};

const asNumericId = (value: unknown) => {
  const numericValue = Number(value);
  return Number.isFinite(numericValue) ? numericValue : String(value ?? '');
};

const normalizeReward = (value: any): PartnerCampaignReward => ({
  amount: Number(value?.amount ?? value?.reward_amount ?? 0),
  label: asTrimmedString(value?.label ?? value?.reward_label) ?? 'BRC',
  mode: normalizeToken(value?.mode ?? value?.reward_mode ?? 'click_claim'),
});

const normalizeTaskReward = (value: any): PartnerCampaignTaskReward => ({
  amount: Number(value?.amount ?? value?.reward_amount ?? 0),
  label: asTrimmedString(value?.label ?? value?.reward_label) ?? 'BRC',
});

const getBlockedActionState = (
  status: string,
): PartnerCampaignActionState | null => {
  const helperText = BLOCKED_STATUS_MESSAGES[status];

  if (!helperText) {
    return null;
  }

  return {
    kind: 'none',
    label:
      status === 'budget_exhausted' || status === 'reward_budget_exhausted'
        ? 'Budget Exhausted'
        : status === 'expired'
        ? 'Campaign Ended'
        : status === 'paused'
        ? 'Campaign Paused'
        : 'Unavailable',
    disabled: true,
    tone: 'danger',
    helperText,
  };
};

const getCampaignStatus = (campaign: PartnerCampaign) => {
  if (campaign.claimed || normalizeToken(campaign.userStatus) === 'claimed') {
    return 'claimed';
  }

  const endsAt = campaign.endsAt ? new Date(campaign.endsAt).getTime() : 0;
  if (
    endsAt > 0 &&
    Number.isFinite(endsAt) &&
    endsAt < Date.now() &&
    !campaign.claimed
  ) {
    return 'expired';
  }

  return normalizeToken(campaign.userStatus || 'available');
};

const getTaskStatus = (task: PartnerCampaignTask) => {
  if (task.claimed || normalizeToken(task.userStatus) === 'claimed') {
    return 'claimed';
  }

  return normalizeToken(task.userStatus || 'available');
};

export const normalizePartnerCampaignTask = (
  value: any,
): PartnerCampaignTask | null => {
  if (!value || typeof value !== 'object') {
    return null;
  }

  const id = asNumericId(value.id ?? value.task_id ?? value.key);
  const title = asTrimmedString(value.title);

  if (!id || !title) {
    return null;
  }

  const type = normalizeToken(value.task_type ?? value.type ?? 'custom');
  const completionMode = type === 'join_telegram'
    ? 'click_claim'
    : normalizeToken(
    value.completion_mode ??
      value.completionMode ??
      value.reward?.mode ??
      'admin_approval',
    );

  return {
    id,
    key: asTrimmedString(value.task_key ?? value.key),
    type,
    title,
    instructions: asTrimmedString(value.instructions) ?? '',
    targetUrl: asTrimmedString(value.target_url ?? value.targetUrl),
    completionMode,
    reward: normalizeTaskReward(value.reward ?? value),
    clickUrl: asTrimmedString(value.click_url ?? value.clickUrl),
    submitUrl: asTrimmedString(value.submit_url ?? value.submitUrl),
    claimUrl: asTrimmedString(value.claim_url ?? value.claimUrl),
    userStatus: normalizeToken(
      value.user_status ?? value.userStatus ?? 'available',
    ),
    clicked: asBoolean(value.clicked),
    claimed: asBoolean(value.claimed),
    canClaim: asBoolean(value.can_claim ?? value.canClaim),
    raw: value,
  };
};

export const normalizePartnerCampaign = (
  value: any,
): PartnerCampaign | null => {
  if (!value || typeof value !== 'object') {
    return null;
  }

  const id = asNumericId(value.id ?? value.public_id ?? value.slug);
  const title = asTrimmedString(value.title);

  if (!id || !title) {
    return null;
  }

  const tasks = Array.isArray(value.tasks)
    ? value.tasks
        .map(normalizePartnerCampaignTask)
        .filter((item: PartnerCampaignTask | null): item is PartnerCampaignTask =>
          Boolean(item),
        )
    : [];

  return {
    id,
    publicId: asTrimmedString(value.public_id ?? value.publicId),
    slug: asTrimmedString(value.slug),
    partnerName:
      asTrimmedString(value.partner_name ?? value.partnerName) ?? 'Partner',
    title,
    description: asTrimmedString(value.description) ?? '',
    imageUrl: asTrimmedString(value.image_url ?? value.imageUrl),
    startsAt: asTrimmedString(value.starts_at ?? value.startsAt),
    endsAt: asTrimmedString(value.ends_at ?? value.endsAt),
    reward: normalizeReward(value.reward ?? value),
    sharedPromo: {
      enabled: asBoolean(value.shared_promo?.enabled ?? value.sharedPromo?.enabled),
      rewardAmount: Number(value.shared_promo?.reward_amount ?? value.sharedPromo?.rewardAmount ?? 0),
      rewardLabel: asTrimmedString(value.shared_promo?.reward_label ?? value.sharedPromo?.rewardLabel) ?? 'BRC',
      dailyLimit: Number(value.shared_promo?.daily_limit ?? value.sharedPromo?.dailyLimit ?? 0),
      dailyClaims: Number(value.shared_promo?.daily_claims ?? value.sharedPromo?.dailyClaims ?? 0),
      totalLimit: Number(value.shared_promo?.total_limit ?? value.sharedPromo?.totalLimit ?? 0),
      claims: Number(value.shared_promo?.claims ?? 0),
      perUserLimit: Number(value.shared_promo?.per_user_limit ?? value.sharedPromo?.perUserLimit ?? 1),
    },
    tasks,
    smartLink: asTrimmedString(value.smart_link ?? value.smartLink),
    clickUrl: asTrimmedString(value.click_url ?? value.clickUrl),
    claimUrl: asTrimmedString(value.claim_url ?? value.claimUrl),
    userStatus: normalizeToken(
      value.user_status ?? value.userStatus ?? 'available',
    ),
    clicked: asBoolean(value.clicked),
    claimed: asBoolean(value.claimed),
    canClaim: asBoolean(value.can_claim ?? value.canClaim),
    raw: value,
  };
};

export const normalizePartnerCampaignList = (payload: any) => {
  const campaigns =
    payload?.data?.data ??
    payload?.data?.campaigns ??
    payload?.campaigns ??
    payload?.data ??
    payload;

  if (!Array.isArray(campaigns)) {
    return [];
  }

  return campaigns
    .map(normalizePartnerCampaign)
    .filter((item): item is PartnerCampaign => Boolean(item));
};

export const fetchPartnerCampaigns = async () => {
  const response = await getApi('auth/partner-campaigns');

  if (!response?.success) {
    throw new Error(
      response?.error || 'Unable to load partner campaigns.',
    );
  }

  return normalizePartnerCampaignList(response.data);
};

const formatRewardAmount = (amount: unknown) => {
  const numericAmount = Number(amount ?? 0);
  return Number.isFinite(numericAmount)
    ? numericAmount.toLocaleString(undefined, {maximumFractionDigits: 4})
    : '0';
};

export const getPartnerCampaignRewardLabel = (campaign: PartnerCampaign) =>
  `${formatRewardAmount(campaign.reward.amount)} ${
    campaign.reward.label || 'BRC'
  }`;

export const getPartnerTaskRewardLabel = (task: PartnerCampaignTask) =>
  `${formatRewardAmount(task.reward.amount)} ${task.reward.label || 'BRC'}`;

export const formatPartnerCampaignDateRange = (campaign: PartnerCampaign) => {
  const formatDate = (value?: string) => {
    if (!value) {
      return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '';
    }

    return date.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
    });
  };

  const startsAt = formatDate(campaign.startsAt);
  const endsAt = formatDate(campaign.endsAt);

  if (startsAt && endsAt) {
    return `${startsAt} - ${endsAt}`;
  }

  return startsAt || endsAt || 'Limited time';
};

export const getPartnerCampaignActionState = (
  campaign: PartnerCampaign,
): PartnerCampaignActionState => {
  const status = getCampaignStatus(campaign);
  const blockedState = getBlockedActionState(status);

  if (blockedState) {
    return blockedState;
  }

  if (status === 'claimed') {
    return {
      kind: 'none',
      label: 'Reward Claimed',
      disabled: true,
      tone: 'success',
      helperText: 'This reward has already been added.',
    };
  }

  if (status === 'pending') {
    return {
      kind: 'none',
      label: 'Waiting for Approval',
      disabled: true,
      tone: 'warning',
      helperText: 'Partner approval is pending. Check back soon.',
    };
  }

  if (status === 'approved' || status === 'clicked') {
    const isAdminApproval = campaign.reward.mode === 'admin_approval';

    if (isAdminApproval && status === 'clicked') {
      return {
        kind: 'none',
        label: 'Waiting for Approval',
        disabled: true,
        tone: 'warning',
        helperText: 'Partner approval is pending. Check back soon.',
      };
    }

    return {
      kind: campaign.canClaim ? 'claim' : 'none',
      label: 'Claim BRC',
      disabled: !campaign.canClaim,
      tone: campaign.canClaim ? 'primary' : 'secondary',
      helperText: campaign.canClaim
        ? 'Claim only after the backend confirms eligibility.'
        : 'Reward claim will unlock after the partner task is recorded.',
    };
  }

  if (status === 'rejected') {
    return {
      kind: 'start',
      label: 'Try Again',
      disabled: false,
      tone: 'secondary',
      helperText: 'Previous attempt was rejected. You can start again.',
    };
  }

  return {
    kind: 'start',
    label:
      campaign.reward.mode === 'admin_approval'
        ? 'Start Task'
        : 'Visit Partner',
    disabled: false,
    tone: 'primary',
  };
};

export const getPartnerTaskActionState = (
  task: PartnerCampaignTask,
): PartnerCampaignActionState => {
  const status = getTaskStatus(task);
  const blockedState = getBlockedActionState(status);

  if (blockedState) {
    return blockedState;
  }

  if (status === 'claimed') {
    return {
      kind: 'none',
      label: 'Reward Claimed',
      disabled: true,
      tone: 'success',
      helperText: 'This task reward has already been added.',
    };
  }

  if (status === 'pending') {
    return {
      kind: 'none',
      label: 'Waiting for Admin',
      disabled: true,
      tone: 'warning',
      helperText: 'Your proof is waiting for admin approval.',
    };
  }

  if (status === 'approved') {
    return {
      kind: task.canClaim ? 'claim' : 'none',
      label: 'Claim BRC',
      disabled: !task.canClaim,
      tone: task.canClaim ? 'primary' : 'secondary',
      helperText: task.canClaim
        ? 'Claim after approval is confirmed by the backend.'
        : 'Claim will unlock after approval syncs.',
    };
  }

  if (status === 'clicked') {
    if (task.completionMode === 'admin_approval') {
      return {
        kind: 'submit',
        label: 'Submit Proof',
        disabled: false,
        tone: 'primary',
        helperText: 'Submit a link or short note for admin review.',
      };
    }

    return {
      kind: task.canClaim ? 'claim' : 'none',
      label: 'Claim BRC',
      disabled: !task.canClaim,
      tone: task.canClaim ? 'primary' : 'secondary',
      helperText: task.canClaim
        ? 'The click is recorded. Claim the task reward.'
        : 'Claim unlocks after the backend records this task.',
    };
  }

  if (status === 'rejected') {
    return {
      kind: 'start',
      label: 'Try Again',
      disabled: false,
      tone: 'secondary',
      helperText: 'Your proof was rejected. You can restart this task.',
    };
  }

  return {
    kind: 'start',
    label:
      task.type === 'join_telegram'
        ? 'Join Telegram'
        : task.completionMode === 'admin_approval'
          ? 'Open Task'
          : 'Start Task',
    helperText:
      task.type === 'join_telegram'
        ? 'Tap to open Telegram and receive the task reward.'
        : undefined,
    disabled: false,
    tone: 'primary',
  };
};

export const getPartnerCampaignStatusLabel = (campaign: PartnerCampaign) => {
  const status = getCampaignStatus(campaign);

  switch (status) {
    case 'available':
      return 'Available';
    case 'clicked':
      return campaign.reward.mode === 'admin_approval' ? 'Pending' : 'Clicked';
    case 'pending':
      return 'Pending approval';
    case 'approved':
      return 'Approved';
    case 'claimed':
      return 'Claimed';
    case 'rejected':
      return 'Rejected';
    case 'expired':
      return 'Expired';
    case 'paused':
      return 'Paused';
    case 'budget_exhausted':
    case 'reward_budget_exhausted':
    case 'exhausted':
      return 'Budget exhausted';
    case 'unavailable':
      return 'Unavailable';
    default:
      return status ? status.replace(/_/g, ' ') : 'Available';
  }
};

export const getPartnerTaskStatusLabel = (task: PartnerCampaignTask) => {
  const status = getTaskStatus(task);

  switch (status) {
    case 'available':
      return 'Available';
    case 'clicked':
      return task.completionMode === 'admin_approval'
        ? 'Proof needed'
        : 'Clicked';
    case 'pending':
      return 'Pending approval';
    case 'approved':
      return 'Approved';
    case 'claimed':
      return 'Claimed';
    case 'rejected':
      return 'Rejected';
    case 'expired':
      return 'Expired';
    case 'paused':
      return 'Paused';
    case 'budget_exhausted':
    case 'reward_budget_exhausted':
    case 'exhausted':
      return 'Budget exhausted';
    case 'unavailable':
      return 'Unavailable';
    default:
      return status ? status.replace(/_/g, ' ') : 'Available';
  }
};

export const getCampaignErrorMessage = (response: any, fallback: string) =>
  response?.error ||
  response?.data?.message ||
  response?.data?.error ||
  response?.data?.data?.message ||
  fallback;

const extractResponseData = (response: any) =>
  response?.data?.data ?? response?.data ?? {};

export const getClickDestinationUrl = (response: any) => {
  const data = extractResponseData(response);
  return asTrimmedString(data.destination_url ?? data.destinationUrl);
};

const assertMutationResponse = (response: any, fallback: string) => {
  if (!response?.success) {
    throw new Error(getCampaignErrorMessage(response, fallback));
  }

  const data = extractResponseData(response);

  if (data?.status === 0 || data?.success === false) {
    throw new Error(getCampaignErrorMessage(response, fallback));
  }

  return data;
};

export const trackPartnerCampaignClick = async (campaign: PartnerCampaign) => {
  const response = await postApi(
    `auth/partner-campaigns/${campaign.id}/click`,
    {},
  );

  assertMutationResponse(response, 'Unable to start this partner campaign.');

  const destinationUrl = getClickDestinationUrl(response);

  if (!destinationUrl) {
    throw new Error('Partner link is not available yet.');
  }

  return {
    response,
    destinationUrl,
  };
};

export const claimPartnerCampaignReward = async (campaign: PartnerCampaign) => {
  const response = await postApi(
    `auth/partner-campaigns/${campaign.id}/claim`,
    {},
  );

  assertMutationResponse(response, 'Unable to claim this partner reward.');

  return response;
};

export const trackPartnerCampaignTaskClick = async (
  campaign: PartnerCampaign,
  task: PartnerCampaignTask,
) => {
  const response = await postApi(
    `auth/partner-campaigns/${campaign.id}/tasks/${task.id}/click`,
    {},
  );

  assertMutationResponse(response, 'Unable to open this partner task.');

  const destinationUrl = getClickDestinationUrl(response);

  if (!destinationUrl) {
    throw new Error('Partner task link is not available yet.');
  }

  return {
    response,
    destinationUrl,
  };
};

export const submitPartnerCampaignTaskProof = async (
  campaign: PartnerCampaign,
  task: PartnerCampaignTask,
  proof: PartnerTaskProofPayload,
) => {
  const body: Record<string, string> = {};
  const evidenceUrl = asTrimmedString(proof.evidenceUrl);
  const evidenceNote = asTrimmedString(proof.evidenceNote);

  if (evidenceUrl) {
    body.evidence_url = evidenceUrl;
  }

  if (evidenceNote) {
    body.evidence_note = evidenceNote;
  }

  const response = await postApi(
    `auth/partner-campaigns/${campaign.id}/tasks/${task.id}/submit`,
    body,
  );

  assertMutationResponse(response, 'Unable to submit this task proof.');

  return response;
};

export const claimPartnerCampaignTaskReward = async (
  campaign: PartnerCampaign,
  task: PartnerCampaignTask,
) => {
  const response = await postApi(
    `auth/partner-campaigns/${campaign.id}/tasks/${task.id}/claim`,
    {},
  );

  assertMutationResponse(response, 'Unable to claim this task reward.');

  return response;
};
