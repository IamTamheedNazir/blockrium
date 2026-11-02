export type BrcTransferHistoryState =
  | 'loading'
  | 'unavailable'
  | 'empty'
  | 'ready'
  | 'stale';

export const getBrcTransferHistoryState = (
  isLoading: boolean,
  unavailable: boolean,
  transferCount: number,
): BrcTransferHistoryState => {
  if (isLoading && transferCount === 0) {
    return 'loading';
  }
  if (unavailable) {
    return transferCount > 0 ? 'stale' : 'unavailable';
  }
  return transferCount > 0 ? 'ready' : 'empty';
};
