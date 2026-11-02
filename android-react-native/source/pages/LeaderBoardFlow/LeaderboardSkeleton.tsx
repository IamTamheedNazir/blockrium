import React from 'react';
import {Box, VStack, Skeleton, HStack, Center, Text} from '../../ui/nativeBaseCompat';
import { hp, wp } from '../../utils/styleConstants';

const LeaderboardSkeleton = () => {
  return (
    <VStack space={4} px={4} py={6} alignItems="center">
      {/* Header Skeleton */}
      <Skeleton.Text lines={1} w={wp(40)} h={hp(4)} mt={2} mb={4} />
      
      {/* Top 3 Cards Skeleton */}
      <HStack justifyContent="space-between" w="100%" px={4}>
        {Array.from({ length: 3 }).map((_, index) => (
          <Box key={index} width={wp(25)} height={hp(15)} borderRadius={10} bg="#ddd" alignItems="center" p={4}>
            <Skeleton width={wp(15)} height={wp(10)} borderRadius={8} mb={2} />
            <Skeleton.Text lines={1} w="70%" />
            <Skeleton.Text lines={1} w="50%" mt={1} />
          </Box>
        ))}
      </HStack>

      {/* Lower Ranks Skeleton */}
      <VStack space={3} width="100%" mt={4}>
        {Array.from({ length: 3 }).map((_, index) => (
          <HStack key={index} alignItems="center" width="100%" bg="#ddd" borderRadius={10} p={4}>
            <Skeleton width={wp(12)} height={wp(12)} borderRadius={10} />
            <VStack ml={4} flex={1}>
              <Skeleton.Text lines={1} w="60%" />
              <Skeleton.Text lines={1} w="40%" mt={1} />
            </VStack>
            <Skeleton.Text lines={1} w="15%" />
          </HStack>
        ))}
      </VStack>
    </VStack>
  );
};

export default LeaderboardSkeleton;
