import React from 'react';
import {AppState, Linking, Text} from 'react-native';
import renderer, {act} from 'react-test-renderer';
import {QueryClient, QueryClientProvider} from 'react-query';
import PartnerCampaignsSection, {
  PartnerCampaignsSectionContent,
} from '../source/molecules/PartnerCampaignsSection';
import {
  normalizePartnerCampaign,
  getPartnerTaskActionState,
  PartnerCampaign,
  fetchPartnerCampaigns,
  normalizePartnerCampaignList,
  partnerCampaignQueryKey,
  trackPartnerCampaignClick,
} from '../source/services/partnerCampaignService';

const mockGetApi = jest.fn();
const mockPostApi = jest.fn();
const mockShowAlert = jest.fn();
let appStateChangeHandler: Parameters<typeof AppState.addEventListener>[1] | undefined;
const renderedTrees: renderer.ReactTestRenderer[] = [];
const queryClients: QueryClient[] = [];

jest.mock('../source/storage/server', () => ({
  getApi: (...args: any[]) => mockGetApi(...args),
  postApi: (...args: any[]) => mockPostApi(...args),
}));

jest.mock('../source/hooks/AlertContext', () => ({
  useThisAlert: () => ({
    showAlert: mockShowAlert,
  }),
}));

jest.mock('../source/design/primitives', () => {
  const MockReact = require('react');
  const {Text: MockText, View: MockView} = require('react-native');

  const Button = ({label, children, ...props}: any) =>
    MockReact.createElement(
      MockView,
      props,
      MockReact.createElement(MockText, null, label),
      children,
    );

  return {
    GlassCard: ({children}: any) =>
      MockReact.createElement(MockView, null, children),
    PrimaryButton: Button,
    SecondaryButton: Button,
    SectionEyebrow: ({text}: any) =>
      MockReact.createElement(MockText, null, text),
    CardEntrance: ({children}: any) =>
      MockReact.createElement(MockView, null, children),
  };
});

const baseTaskPayload = (overrides: any = {}) => ({
  id: 101,
  key: 'visit-site',
  type: 'website',
  title: 'Visit DAC website',
  instructions: 'Open the partner site and return to claim.',
  target_url: 'https://partner.example/task',
  completion_mode: 'click_claim',
  reward: {
    amount: 3,
    label: 'BRC',
  },
  click_url:
    'https://api.example/api/auth/partner-campaigns/1/tasks/101/click',
  submit_url:
    'https://api.example/api/auth/partner-campaigns/1/tasks/101/submit',
  claim_url:
    'https://api.example/api/auth/partner-campaigns/1/tasks/101/claim',
  user_status: 'available',
  clicked: false,
  claimed: false,
  can_claim: false,
  ...overrides,
});

const baseCampaignPayload = (overrides: any = {}) => ({
  id: 1,
  public_id: 'dac-1',
  slug: 'dac-interstellar',
  partner_name: 'DAC Blockchain',
  title: 'Interstellar Partner Program',
  description: 'Visit partner tasks and unlock verified BRC rewards.',
  image_url: 'https://example.com/campaign.png',
  starts_at: '2026-01-01T00:00:00Z',
  ends_at: '2099-12-31T00:00:00Z',
  reward: {
    amount: 10,
    label: 'BRC',
    mode: 'click_claim',
  },
  tasks: [baseTaskPayload()],
  smart_link: 'https://partner.example',
  click_url: 'https://api.example/api/auth/partner-campaigns/1/click',
  claim_url: 'https://api.example/api/auth/partner-campaigns/1/claim',
  user_status: 'available',
  clicked: false,
  claimed: false,
  can_claim: false,
  ...overrides,
});

const normalizeCampaign = (overrides: any = {}) =>
  normalizePartnerCampaign(baseCampaignPayload(overrides)) as PartnerCampaign;

const noopHandlers = {
  onStartCampaign: jest.fn(),
  onClaimCampaign: jest.fn(),
  onStartTask: jest.fn(),
  onSubmitTaskProof: jest.fn(),
  onClaimTask: jest.fn(),
  onVerifyTask: jest.fn(),
};

const createQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        cacheTime: 0,
      },
    },
  });

const flushQueries = async () => {
  await act(async () => {
    await Promise.resolve();
    await new Promise(resolve => setTimeout(resolve, 0));
  });
};

const getTextContent = (tree: renderer.ReactTestRenderer) =>
  tree.root
    .findAllByType(Text)
    .map(node => node.props.children)
    .flat(Infinity)
    .filter(Boolean)
    .join(' ');

type PartnerCampaignsTestProps = {
  enabled?: boolean;
  onWalletRefresh?: () => void;
  onNotificationsRefresh?: () => void;
  delay?: number;
};

const renderSection = async (props: PartnerCampaignsTestProps = {}) => {
  const queryClient = createQueryClient();
  let tree: renderer.ReactTestRenderer;

  await act(async () => {
    tree = renderer.create(
      <QueryClientProvider client={queryClient}>
        <PartnerCampaignsSection {...props} />
      </QueryClientProvider>,
    );
  });
  renderedTrees.push(tree!);
  queryClients.push(queryClient);

  await flushQueries();

  return tree!;
};

describe('Partner Campaigns', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    appStateChangeHandler = undefined;
    jest.spyOn(AppState, 'addEventListener').mockImplementation(((
      _event: Parameters<typeof AppState.addEventListener>[0],
      handler: Parameters<typeof AppState.addEventListener>[1],
    ) => {
      appStateChangeHandler = handler;
      return {remove: jest.fn()};
    }) as typeof AppState.addEventListener);
    jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined as never);
  });

  afterEach(() => {
    renderedTrees.splice(0).forEach(tree => {
      act(() => {
        tree.unmount();
      });
    });
    queryClients.splice(0).forEach(client => {
      client.clear();
    });
    jest.restoreAllMocks();
  });

  it('normalizes campaigns with backend task payloads', () => {
    const campaign = normalizeCampaign();

    expect(campaign.tasks).toHaveLength(1);
    expect(campaign.tasks[0].title).toBe('Visit DAC website');
    expect(campaign.tasks[0].completionMode).toBe('click_claim');
    expect(campaign.tasks[0].reward.amount).toBe(3);
  });

  it('returns an empty list for malformed or stale campaign cache data', () => {
    expect(normalizePartnerCampaignList({campaigns: {id: 1}})).toEqual([]);
    expect(normalizePartnerCampaignList([null, {}, baseCampaignPayload()])).toHaveLength(1);
  });

  it('surfaces a safe error when the campaign API response is missing', async () => {
    mockGetApi.mockResolvedValueOnce(undefined);
    await expect(fetchPartnerCampaigns()).rejects.toThrow(
      'Unable to load partner campaigns.',
    );

    mockPostApi.mockResolvedValueOnce(undefined);
    await expect(trackPartnerCampaignClick(normalizeCampaign())).rejects.toThrow(
      'Unable to start this partner campaign.',
    );
  });

  it('waits for the Home content gate before fetching campaigns', async () => {
    const queryClient = createQueryClient();
    let tree!: renderer.ReactTestRenderer;

    await act(async () => {
      tree = renderer.create(
        <QueryClientProvider client={queryClient}>
          <PartnerCampaignsSection enabled={false} />
        </QueryClientProvider>,
      );
    });
    renderedTrees.push(tree);
    queryClients.push(queryClient);
    await flushQueries();
    expect(mockGetApi).not.toHaveBeenCalled();

    await act(async () => {
      tree.update(
        <QueryClientProvider client={queryClient}>
          <PartnerCampaignsSection enabled />
        </QueryClientProvider>,
      );
    });
    await flushQueries();
    expect(mockGetApi).toHaveBeenCalled();
  }, 15000);

  it('does not crash Home when a persisted campaign query cache has an old shape', async () => {
    const queryClient = createQueryClient();
    queryClient.setQueryData(partnerCampaignQueryKey, {campaigns: {id: 1}});
    let tree!: renderer.ReactTestRenderer;

    await act(async () => {
      tree = renderer.create(
        <QueryClientProvider client={queryClient}>
          <PartnerCampaignsSection enabled={false} onRedeemPromo={jest.fn()} />
        </QueryClientProvider>,
      );
    });
    renderedTrees.push(tree);
    queryClients.push(queryClient);

    expect(tree.root.findByProps({testID: 'partner-promo-redeem-entry'})).toBeTruthy();
    expect(mockGetApi).not.toHaveBeenCalled();
  });

  it('normalizes legacy Telegram membership tasks to one-tap click rewards', () => {
    const task = normalizeCampaign({
      tasks: [
        baseTaskPayload({
          type: 'join_telegram',
          completion_mode: 'telegram_membership',
        }),
      ],
    }).tasks[0];

    expect(task.completionMode).toBe('click_claim');
    expect(getPartnerTaskActionState(task).label).toBe('Join Telegram');
    expect(
      getPartnerTaskActionState({...task, userStatus: 'clicked', canClaim: true}).kind,
    ).toBe('claim');
  });

  it('normalizes reusable campaign reward limits', () => {
    const campaign = normalizeCampaign({
      shared_promo: {
        enabled: true,
        reward_amount: 12.5,
        reward_label: 'BRC',
        daily_limit: 10,
        daily_claims: 4,
        total_limit: 100,
        claims: 24,
        per_user_limit: 1,
      },
    });

    expect(campaign.sharedPromo?.enabled).toBe(true);
    expect(campaign.sharedPromo?.rewardAmount).toBe(12.5);
    expect(campaign.sharedPromo?.dailyLimit).toBe(10);
    expect(campaign.sharedPromo?.dailyClaims).toBe(4);
  });

  it('renders active campaigns with partner details and task rewards', () => {
    const tree = renderer.create(
      <PartnerCampaignsSectionContent
        campaigns={[normalizeCampaign()]}
        {...noopHandlers}
      />,
    );
    const text = getTextContent(tree);

    expect(text).toContain('Partner campaigns');
    expect(text).toContain('DAC Blockchain');
    expect(text).toContain('Interstellar Partner Program');
    expect(text).toContain('Visit DAC website');
    expect(text).toContain('3 BRC');
    expect(text).toContain('Start Task');
  });

  it('shows the shared-code reward CTA and daily availability', () => {
    const onRedeemPromo = jest.fn();
    const tree = renderer.create(
      <PartnerCampaignsSectionContent
        campaigns={[
          normalizeCampaign({
            shared_promo: {
              enabled: true,
              reward_amount: 12,
              reward_label: 'BRC',
              daily_limit: 10,
              daily_claims: 4,
              total_limit: 100,
              claims: 40,
              per_user_limit: 1,
            },
          }),
        ]}
        onRedeemPromo={onRedeemPromo}
        {...noopHandlers}
      />,
    );
    const button = tree.root.findByProps({testID: 'partner-campaign-promo-1'});

    const text = getTextContent(tree);
    expect(text).toContain('12');
    expect(text).toContain('6');
    expect(text).toContain('left today');
    expect(button.props.disabled).toBe(false);
    act(() => button.props.onPress());
    expect(onRedeemPromo).toHaveBeenCalledTimes(1);
  });

  it('renders no section or spacing for empty campaigns', () => {
    const tree = renderer.create(
      <PartnerCampaignsSectionContent campaigns={[]} {...noopHandlers} />,
    );

    expect(tree.toJSON()).toBeNull();
  });

  it('shows a code-redemption entry when there are no active campaigns', () => {
    const onRedeemPromo = jest.fn();
    const tree = renderer.create(
      <PartnerCampaignsSectionContent
        campaigns={[]}
        onRedeemPromo={onRedeemPromo}
        {...noopHandlers}
      />,
    );

    const button = tree.root.findByProps({testID: 'partner-promo-redeem-entry'});
    act(() => button.props.onPress());
    expect(onRedeemPromo).toHaveBeenCalledTimes(1);
    tree.unmount();
  });

  it('keeps promo entry copy minimal and avoids exposing campaign wording', () => {
    const tree = renderer.create(
      <PartnerCampaignsSectionContent
        campaigns={[]}
        onRedeemPromo={jest.fn()}
        {...noopHandlers}
      />,
    );

    const text = getTextContent(tree);
    expect(text).toContain('Have a promo code?');
    expect(text).toContain('Enter promo code');
    expect(text).not.toContain('Partner campaigns');
    expect(text).not.toContain('Verified partner tasks with BRC rewards.');
    expect(text).not.toMatch(/partner code|campaign code|shared campaign|reward eligibility/i);
    tree.unmount();
  });

  it('keeps code redemption visible alongside active campaigns', () => {
    const onRedeemPromo = jest.fn();
    const tree = renderer.create(
      <PartnerCampaignsSectionContent
        campaigns={[normalizeCampaign(baseCampaignPayload())]}
        onRedeemPromo={onRedeemPromo}
        {...noopHandlers}
      />,
    );

    const button = tree.root.findByProps({testID: 'partner-promo-redeem-entry'});
    act(() => button.props.onPress());
    expect(onRedeemPromo).toHaveBeenCalledTimes(1);
    tree.unmount();
  });

  it('shows pending approval state for admin-approved tasks', () => {
    const campaign = normalizeCampaign({
      tasks: [
        baseTaskPayload({
          id: 202,
          title: 'Share your proof',
          type: 'social',
          completion_mode: 'admin_approval',
          user_status: 'pending',
          clicked: true,
        }),
      ],
    });

    const tree = renderer.create(
      <PartnerCampaignsSectionContent
        campaigns={[campaign]}
        {...noopHandlers}
      />,
    );
    const action = tree.root.findByProps({
      testID: 'partner-campaign-task-action-1-202',
    });
    const text = getTextContent(tree);

    expect(text).toContain('Waiting for Admin');
    expect(text).toContain('Your proof is waiting for admin approval');
    expect(action.props.disabled).toBe(true);
  });

  it('tracks website task clicks before opening the partner link', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      data: {success: true, data: [baseCampaignPayload()]},
    });
    mockPostApi.mockResolvedValue({
      success: true,
      data: {
        success: true,
        data: {destination_url: 'https://partner.example/task'},
      },
    });

    const tree = await renderSection();
    const action = tree.root.findByProps({
      testID: 'partner-campaign-task-action-1-101',
    });

    await act(async () => {
      await action.props.onPress();
    });

    expect(mockPostApi).toHaveBeenCalledWith(
      'auth/partner-campaigns/1/tasks/101/click',
      {},
    );
    expect(Linking.openURL).toHaveBeenCalledWith(
      'https://partner.example/task',
    );
    expect(mockGetApi).toHaveBeenCalledTimes(1);
  });

  it('opens tracked campaign links without waiting for a redundant fetch', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      data: {
        success: true,
        data: [baseCampaignPayload({tasks: []})],
      },
    });
    mockPostApi.mockResolvedValue({
      success: true,
      data: {
        success: true,
        data: {destination_url: 'https://partner.example/campaign'},
      },
    });

    const tree = await renderSection();
    const action = tree.root.findByProps({testID: 'partner-campaign-action-1'});

    await act(async () => {
      await action.props.onPress();
    });

    expect(mockPostApi).toHaveBeenCalledWith(
      'auth/partner-campaigns/1/click',
      {},
    );
    expect(Linking.openURL).toHaveBeenCalledWith(
      'https://partner.example/campaign',
    );
    expect(mockGetApi).toHaveBeenCalledTimes(1);
  });

  it('claims a Telegram task immediately after its click without polling membership', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      data: {
        success: true,
        data: [
          baseCampaignPayload({
            tasks: [
              baseTaskPayload({
                type: 'join_telegram',
                completion_mode: 'telegram_membership',
              }),
            ],
          }),
        ],
      },
    });
    mockPostApi.mockImplementation((path: string) => Promise.resolve({
      success: true,
      data: {
        success: true,
        data: path.endsWith('/tasks/101/click')
          ? {destination_url: 'https://t.me/BlockriumOfficial'}
          : {status: 200},
      },
    }));

    const tree = await renderSection();
    const action = tree.root.findByProps({
      testID: 'partner-campaign-task-action-1-101',
    });
    await act(async () => {
      await action.props.onPress();
    });

    expect(mockPostApi).toHaveBeenCalledWith(
      'auth/partner-campaigns/1/tasks/101/click',
      {},
    );
    expect(mockPostApi).toHaveBeenCalledWith(
      'auth/partner-campaigns/1/tasks/101/claim',
      {},
    );
    expect(mockPostApi.mock.calls.some(([path]) =>
      String(path).includes('/telegram/verify'),
    )).toBe(false);
    expect(Linking.openURL).toHaveBeenCalledWith('https://t.me/BlockriumOfficial');
    expect(mockShowAlert).toHaveBeenCalledWith(
      'Telegram task reward claimed.',
      'SUCCESS',
    );
  });

  it('submits social task proof and moves into admin review', async () => {
    const socialTask = baseTaskPayload({
      id: 202,
      title: 'Post about Blockrium',
      type: 'social',
      completion_mode: 'admin_approval',
      user_status: 'clicked',
      clicked: true,
    });
    mockGetApi.mockResolvedValue({
      success: true,
      data: {success: true, data: [baseCampaignPayload({tasks: [socialTask]})]},
    });
    mockPostApi.mockResolvedValue({
      success: true,
      data: {
        success: true,
        data: {task_id: 202, status: 'pending'},
      },
    });

    const tree = await renderSection();
    const action = tree.root.findByProps({
      testID: 'partner-campaign-task-action-1-202',
    });

    await act(async () => {
      await action.props.onPress();
    });

    const proofUrl = tree.root.findByProps({
      testID: 'partner-campaign-task-proof-url-1-202',
    });
    const proofNote = tree.root.findByProps({
      testID: 'partner-campaign-task-proof-note-1-202',
    });
    const submit = tree.root.findByProps({
      testID: 'partner-campaign-task-proof-submit-1-202',
    });

    await act(async () => {
      proofUrl.props.onChangeText('https://social.example/proof');
      proofNote.props.onChangeText('Posted from my public profile.');
    });

    await act(async () => {
      await submit.props.onPress();
    });

    expect(mockPostApi).toHaveBeenCalledWith(
      'auth/partner-campaigns/1/tasks/202/submit',
      {
        evidence_url: 'https://social.example/proof',
        evidence_note: 'Posted from my public profile.',
      },
    );
    expect(mockShowAlert).toHaveBeenCalledWith(
      'Task proof submitted for admin approval.',
      'SUCCESS',
    );
  });

  it('refreshes wallet and notifications after a successful task claim', async () => {
    const onWalletRefresh = jest.fn();
    const onNotificationsRefresh = jest.fn();
    mockGetApi.mockResolvedValue({
      success: true,
      data: {
        success: true,
        data: [
          baseCampaignPayload({
            tasks: [
              baseTaskPayload({
                user_status: 'approved',
                clicked: true,
                can_claim: true,
              }),
            ],
          }),
        ],
      },
    });
    mockPostApi.mockResolvedValue({
      success: true,
      data: {success: true, message: 'Task reward claimed.'},
    });

    const tree = await renderSection({
      onWalletRefresh,
      onNotificationsRefresh,
    });
    const action = tree.root.findByProps({
      testID: 'partner-campaign-task-action-1-101',
    });

    await act(async () => {
      await action.props.onPress();
    });

    expect(mockPostApi).toHaveBeenCalledWith(
      'auth/partner-campaigns/1/tasks/101/claim',
      {},
    );
    expect(mockShowAlert).toHaveBeenCalledWith(
      'Partner task reward claimed.',
      'SUCCESS',
    );
    expect(onWalletRefresh).toHaveBeenCalled();
    expect(onNotificationsRefresh).toHaveBeenCalled();
  });

  it('shows API errors and does not open links when task click tracking fails', async () => {
    mockGetApi.mockResolvedValue({
      success: true,
      data: {success: true, data: [baseCampaignPayload()]},
    });
    mockPostApi.mockResolvedValue({
      success: false,
      error: 'This task has reached its participant limit.',
    });

    const tree = await renderSection();
    const action = tree.root.findByProps({
      testID: 'partner-campaign-task-action-1-101',
    });

    await act(async () => {
      await action.props.onPress();
    });

    expect(Linking.openURL).not.toHaveBeenCalled();
    expect(mockShowAlert).toHaveBeenCalledWith(
      'This task has reached its participant limit.',
      'ERROR',
    );
  });
});
