export type SquadMemberTier = {
  label: string;
  icon: string;
  color: string;
};

const tiers: Array<SquadMemberTier & {minimum: number}> = [
  {minimum: 100000, label: '100K Legion', icon: 'shield-star', color: '#FFC857'},
  {minimum: 10000, label: '10K Titan', icon: 'crown', color: '#C792EA'},
  {minimum: 1000, label: '1K Vanguard', icon: 'lightning-bolt', color: '#78A7FF'},
  {minimum: 500, label: '500 Trailblazer', icon: 'fire', color: '#FF986B'},
  {minimum: 0, label: 'Rising', icon: 'sprout', color: '#00D6B0'},
];

export const getSquadMemberTier = (memberCount: number): SquadMemberTier => {
  const count = Number.isFinite(memberCount) ? Math.max(0, memberCount) : 0;
  const tier = tiers.find(item => count >= item.minimum) ?? tiers[tiers.length - 1];
  return {label: tier.label, icon: tier.icon, color: tier.color};
};
