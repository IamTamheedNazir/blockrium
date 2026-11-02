export type TrustCenterSection = {
  key: string;
  title: string;
  body: string;
  sortOrder: number;
};

export type TrustCenterLinks = {
  privacyPolicy?: string;
  terms?: string;
  deleteAccount?: string;
  support?: string;
};

export type TrustCenterData = {
  sections: TrustCenterSection[];
  links: TrustCenterLinks;
  updatedAt?: string;
};

export type LearnModuleStatus =
  | 'available'
  | 'completed'
  | 'locked'
  | 'in_progress'
  | string;

export type LearnModule = {
  id: number;
  key: string;
  title: string;
  description: string;
  youtubeUrl?: string;
  youtubeVideoId?: string;
  estimatedMinutes: number;
  rewardXp: number;
  rewardBrc: number;
  status: LearnModuleStatus;
  completed: boolean;
  locked: boolean;
  sortOrder: number;
};

export type LearnQuestion = {
  id: number;
  question: string;
  options: Record<'A' | 'B' | 'C' | 'D', string>;
  sortOrder: number;
};

export type LearnModuleDetail = {
  module: LearnModule & {
    passingScore: number;
    content?: string;
  };
  questions: LearnQuestion[];
  progress: {
    status: LearnModuleStatus;
    completed: boolean;
    score: number;
    completedAt?: string;
    rewardClaimedAt?: string;
  };
};

export type LearnCompleteResult = {
  message: string;
  completed: boolean;
  score: number;
  rewardXp: number;
  rewardBrc: number;
  alreadyCompleted?: boolean;
};
