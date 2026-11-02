import React from 'react';
import {Box, VStack, Skeleton, HStack, Center} from '../../ui/nativeBaseCompat';
import { hp, wp } from '../../utils/styleConstants';

const MiningAreaSkeleton = () => {
  return (
    <VStack space={4} px={4} py={6} alignItems="center">
      {/* Header Skeleton */}
      <Skeleton.Text lines={1} w={wp(50)} h={hp(4)} mt={2} />

      {/* Bitcoin Image Skeleton */}
      <Skeleton width={wp(30)} height={hp(20)} borderRadius={10} mb={4} />

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

      {/* Rewards Card Skeleton */}
      <Box
        width="100%"
        height={hp(12)}
        borderRadius={10}
        bg="#ddd"
        mb={4}
        p={4}
      >
        <Skeleton.Text lines={2} w="90%" />
        <Skeleton.Text lines={1} w="80%" mt={2} />
      </Box>

      {/* Booster Card Skeleton */}
      <Box
        width="100%"
        height={hp(12)}
        borderRadius={10}
        bg="#ddd"
        mb={4}
      >
        <Skeleton width="100%" height="100%" />
      </Box>

      {/* Daily Rewards Skeleton */}
      <HStack justifyContent="space-around" width="100%">
        {Array.from({ length: 4 }).map((_, index) => (
          <Center key={index} flex={1}>
            <Skeleton width={wp(15)} height={wp(15)} borderRadius={10} />
            <Skeleton.Text lines={1} w={wp(15)} mt={1} />
          </Center>
        ))}
      </HStack>
    </VStack>
  );
};

export default MiningAreaSkeleton;
