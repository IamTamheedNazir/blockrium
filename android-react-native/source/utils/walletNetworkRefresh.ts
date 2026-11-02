export const loadWalletNetworksIndependently = async <
  Network extends string,
  Asset,
>(
  networkKeys: readonly Network[],
  loadAssets: (networkKey: Network) => Promise<Asset>,
  onAssetsLoaded: (networkKey: Network, assets: Asset) => void,
  onNetworkError: (networkKey: Network, error: unknown) => void,
) => {
  const uniqueNetworkKeys = [...new Set(networkKeys)];

  await Promise.all(
    uniqueNetworkKeys.map(async networkKey => {
      let assets: Asset;

      try {
        assets = await loadAssets(networkKey);
      } catch (error) {
        onNetworkError(networkKey, error);
        return;
      }

      onAssetsLoaded(networkKey, assets);
    }),
  );
};
