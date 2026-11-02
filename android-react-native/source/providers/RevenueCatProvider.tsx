import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import Purchases, {
  CustomerInfo,
  MakePurchaseResult,
  PurchasesOffering,
  PurchasesPackage,
} from 'react-native-purchases';
import {PAYWALL_RESULT} from 'react-native-purchases-ui';
import {
  configureRevenueCat,
  fetchRevenueCatCustomerInfo,
  fetchRevenueCatOfferings,
  getBlockriumProEntitlement,
  getBlockriumSubscriptionSnapshot,
  getStoredRevenueCatAppUserId,
  isBlockriumProCustomer,
  isSuccessfulPaywallResult,
  normalizeRevenueCatError,
  presentRevenueCatCustomerCenter,
  presentRevenueCatPaywall,
  presentRevenueCatPaywallIfNeeded,
  purchaseRevenueCatPackage,
  restoreRevenueCatPurchases,
  sortRevenueCatPackages,
} from '../services/revenueCatService';

type RevenueCatContextValue = {
  customerInfo: CustomerInfo | null;
  currentOffering: PurchasesOffering | null;
  packages: PurchasesPackage[];
  isConfigured: boolean;
  isLoading: boolean;
  isPurchasing: boolean;
  isPro: boolean;
  activePlan?: string;
  expiryDate?: string | null;
  productIdentifier?: string;
  proEntitlement: ReturnType<typeof getBlockriumProEntitlement>;
  error: string | null;
  refresh: () => Promise<void>;
  purchasePackage: (revenueCatPackage: PurchasesPackage) => Promise<MakePurchaseResult>;
  restorePurchases: () => Promise<CustomerInfo>;
  presentPaywall: () => Promise<PAYWALL_RESULT>;
  presentPaywallIfNeeded: () => Promise<PAYWALL_RESULT>;
  presentCustomerCenter: () => Promise<void>;
};

const RevenueCatContext = createContext<RevenueCatContextValue | undefined>(
  undefined,
);

export const RevenueCatProvider = ({children}: {children: React.ReactNode}) => {
  const [customerInfo, setCustomerInfo] = useState<CustomerInfo | null>(null);
  const [currentOffering, setCurrentOffering] = useState<PurchasesOffering | null>(
    null,
  );
  const [isConfigured, setIsConfigured] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isPurchasing, setIsPurchasing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setError(null);

    try {
      const [nextCustomerInfo, offerings] = await Promise.all([
        fetchRevenueCatCustomerInfo(),
        fetchRevenueCatOfferings(),
      ]);

      setCustomerInfo(nextCustomerInfo);
      setCurrentOffering(offerings.current ?? null);
    } catch (refreshError) {
      const message = normalizeRevenueCatError(refreshError);
      console.log('RevenueCat refresh error', refreshError);
      setError(message);
    }
  }, []);

  useEffect(() => {
    let isMounted = true;
    let removeCustomerInfoListener: (() => void) | undefined;

    const bootstrapRevenueCat = async () => {
      setIsLoading(true);

      try {
        const appUserId = await getStoredRevenueCatAppUserId();
        await configureRevenueCat(appUserId);

        const customerInfoListener = (nextCustomerInfo: CustomerInfo) => {
          setCustomerInfo(nextCustomerInfo);
        };

        Purchases.addCustomerInfoUpdateListener(customerInfoListener);
        removeCustomerInfoListener = () => {
          Purchases.removeCustomerInfoUpdateListener(customerInfoListener);
        };

        if (!isMounted) {
          return;
        }

        setIsConfigured(true);
        await refresh();
      } catch (bootstrapError) {
        const message = normalizeRevenueCatError(bootstrapError);
        console.log('RevenueCat bootstrap error', bootstrapError);
        if (isMounted) {
          setError(message);
        }
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    bootstrapRevenueCat();

    return () => {
      isMounted = false;
      removeCustomerInfoListener?.();
    };
  }, [refresh]);

  const purchasePackage = useCallback(
    async (revenueCatPackage: PurchasesPackage) => {
      setIsPurchasing(true);
      setError(null);

      try {
        const purchaseResult = await purchaseRevenueCatPackage(revenueCatPackage);
        setCustomerInfo(purchaseResult.customerInfo);
        return purchaseResult;
      } catch (purchaseError) {
        const message = normalizeRevenueCatError(purchaseError);
        console.log('RevenueCat purchase error', purchaseError);
        setError(message);
        throw purchaseError;
      } finally {
        setIsPurchasing(false);
      }
    },
    [],
  );

  const restorePurchases = useCallback(async () => {
    setIsPurchasing(true);
    setError(null);

    try {
      const nextCustomerInfo = await restoreRevenueCatPurchases();
      setCustomerInfo(nextCustomerInfo);
      return nextCustomerInfo;
    } catch (restoreError) {
      const message = normalizeRevenueCatError(restoreError);
      console.log('RevenueCat restore error', restoreError);
      setError(message);
      throw restoreError;
    } finally {
      setIsPurchasing(false);
    }
  }, []);

  const presentPaywall = useCallback(async () => {
    const result = await presentRevenueCatPaywall(currentOffering);

    if (isSuccessfulPaywallResult(result)) {
      await refresh();
    }

    return result;
  }, [currentOffering, refresh]);

  const presentPaywallIfNeeded = useCallback(async () => {
    const result = await presentRevenueCatPaywallIfNeeded(currentOffering);

    if (isSuccessfulPaywallResult(result)) {
      await refresh();
    }

    return result;
  }, [currentOffering, refresh]);

  const presentCustomerCenter = useCallback(async () => {
    await presentRevenueCatCustomerCenter();
    await refresh();
  }, [refresh]);

  const packages = useMemo(
    () => sortRevenueCatPackages(currentOffering),
    [currentOffering],
  );
  const isPro = isBlockriumProCustomer(customerInfo);
  const proEntitlement = getBlockriumProEntitlement(customerInfo);
  const subscription = useMemo(
    () => getBlockriumSubscriptionSnapshot(customerInfo),
    [customerInfo],
  );

  const value = useMemo(
    () => ({
      customerInfo,
      currentOffering,
      packages,
      isConfigured,
      isLoading,
      isPurchasing,
      isPro,
      activePlan: subscription.activePlan,
      expiryDate: subscription.expiryDate,
      productIdentifier: subscription.productIdentifier,
      proEntitlement,
      error,
      refresh,
      purchasePackage,
      restorePurchases,
      presentPaywall,
      presentPaywallIfNeeded,
      presentCustomerCenter,
    }),
    [
      customerInfo,
      currentOffering,
      error,
      isConfigured,
      isLoading,
      isPro,
      isPurchasing,
      packages,
      presentCustomerCenter,
      presentPaywall,
      presentPaywallIfNeeded,
      proEntitlement,
      purchasePackage,
      refresh,
      restorePurchases,
      subscription.activePlan,
      subscription.expiryDate,
      subscription.productIdentifier,
    ],
  );

  return (
    <RevenueCatContext.Provider value={value}>
      {children}
    </RevenueCatContext.Provider>
  );
};

export const useRevenueCat = () => {
  const context = useContext(RevenueCatContext);

  if (!context) {
    throw new Error('useRevenueCat must be used within RevenueCatProvider');
  }

  return context;
};
