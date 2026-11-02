import React from 'react';
import {Box, VStack, Skeleton, HStack, Center} from '../../ui/nativeBaseCompat';
import { hp, wp } from '../../utils/styleConstants';

const WalletSkeleton = () => {
  return (
    <VStack space={4} px={4} py={6} alignItems="center">
      {/* Header Skeleton */}
      <Skeleton.Text lines={1} w={wp(50)} h={hp(4)} mt={2} />

      {/* Balance Card Skeleton */}
      <Box
        width="100%"
        height={hp(10)}
        borderRadius={10}
        bg="#ddd"
        mb={4}
      >
        <Skeleton width="100%" height="100%" />
      </Box>

      {/* Verified and Lockup Card Skeleton */}
      <Box
        width="100%"
        height={hp(10)}
        borderRadius={10}
        bg="#ddd"
        mb={4}
      >
        <Skeleton width="100%" height="100%" />
      </Box>
    </VStack>
  );
};

export default WalletSkeleton;
