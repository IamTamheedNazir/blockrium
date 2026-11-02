export type PartnerPromoRedemption = {
  id: number | string;
  campaign_title?: string | null;
  reward_amount: number | string;
  reward_label?: string | null;
  created_at?: string | null;
};

export type PartnerPromoClaim = {
  amount: number;
  label: string;
  claimed_at?: string | null;
};

export type PartnerPromoRedeemResult = {
  already_claimed?: boolean;
  message?: string;
  redemption_reference?: string | null;
  claim?: PartnerPromoClaim | null;
};
