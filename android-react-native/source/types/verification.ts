export type VerificationStatus = {
  is_verified: boolean;
  badge?: {
    visible?: boolean;
    label?: string;
    icon?: string;
  };
  kyc_status: string;
  didit_status?: string | null;
  kyc_rejection_reason?: string | null;
  kyc_retry_available?: boolean;
  kyc_retry_reason?: 'rejected' | 'stale_session' | string | null;
  kyc_country?: string | null;
  payment_status: string;
  access_suspended?: boolean;
  next_action?:
    | 'join_waitlist'
    | 'waitlist'
    | 'complete_kyc'
    | 'kyc_review_pending'
    | 'payment_required'
    | 'payment_processing'
    | 'payment_support'
    | 'verified'
    | string;
  next_action_label?: string | null;
  next_action_url?: string | null;
  payment?: VerificationPayment | null;
  payment_support?: VerificationPaymentSupport;
  verified_at?: string | null;
  username?: string | null;
  brc_transfer_balance?: string | null;
  waitlist?: VerificationWaitlist;
  benefits?: {
    brc_username_transfers?: boolean;
    wallet_early_access?: boolean;
    mining_boost_eligibility?: boolean;
    presale_access?: boolean;
    mining_boost_percent?: number;
    mining_boost_active?: boolean;
  };
};

export type VerificationPayment = {
  id: number;
  provider?: string | null;
  status: string;
  amount?: number | null;
  currency?: string | null;
  checkout_url?: string | null;
  provider_payment_id?: string | null;
  created_at?: string | null;
  paid_at?: string | null;
  support_eligible?: boolean;
  next_action?: string;
};

export type VerificationPaymentSupport = {
  available?: boolean;
  open_count?: number;
  latest?: {
    id: number;
    status: string;
    provider?: string | null;
    provider_reference?: string | null;
    provider_account_email?: string | null;
    message?: string | null;
    admin_notes?: string | null;
    messages?: VerificationSupportMessage[];
    amount?: number | null;
    currency?: string | null;
    created_at?: string | null;
    resolved_at?: string | null;
  } | null;
};

export type VerificationSupportMessage = {
  id: number;
  sender_type: 'user' | 'admin' | string;
  message: string;
  created_at?: string | null;
};

export type VerificationWaitlist = {
  enabled: boolean;
  status?: 'waiting' | 'invited' | 'started' | 'converted' | 'cancelled' | null;
  country_code?: string | null;
  country_name?: string | null;
  position?: number | null;
  country_waiting_count?: number;
  country_active_count?: number;
  joined_at?: string | null;
  invited_at?: string | null;
  started_at?: string | null;
  message?: string | null;
};

export type VerificationActionPayload = {
  verification_url?: string;
  session_id?: string;
  checkout_url?: string;
  amount?: number;
  currency?: string;
  status?: VerificationStatus;
  next_action?: string;
};

export type BrcTransfer = {
  id: number;
  amount: string | number;
  fee_amount?: string | number;
  total_debit?: string | number;
  fee_rate_basis_points?: number;
  status: string;
  reference?: string | null;
  note?: string | null;
  created_at?: string | null;
  sender_user_id?: number;
  recipient_user_id?: number;
  sender_username?: string | null;
  recipient_username?: string | null;
  direction?: 'sent' | 'received' | string;
};
