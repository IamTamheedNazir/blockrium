import React from 'react';
import {Box, VStack, Skeleton, HStack, Center} from '../../ui/nativeBaseCompat';
import { hp, wp } from '../../utils/styleConstants';

const HomeSkeleton = () => {
  return (
    <VStack space={4} px={4} py={6} alignItems="center">
      {/* Header Skeleton */}
      <Skeleton.Text lines={1} w={wp(50)} h={hp(4)} mt={2} />

      {/* Banner Skeleton */}
      <Box
        width="100%"
        height={hp(20)}
        borderRadius={10}
        bg="#ddd"
        mb={4}
      >
        <Skeleton width="100%" height="100%" />
      </Box>

      {/* Icon Grid Skeleton */}
      <VStack space={4} width="100%" px={3}>
        <HStack justifyContent="space-around" space={5}>
          {Array.from({ length: 4 }).map((_, index) => (
            <VStack key={index} alignItems="center" space={2}>
              <Skeleton width={wp(15)} height={wp(15)} borderRadius={10} />
              <Skeleton.Text lines={1} w={wp(15)} />
            </VStack>
          ))}
        </HStack>
        <HStack justifyContent="space-around" space={5}>
          {Array.from({ length: 4 }).map((_, index) => (
            <VStack key={index} alignItems="center" space={2}>
              <Skeleton width={wp(15)} height={wp(15)} borderRadius={10} />
              <Skeleton.Text lines={1} w={wp(15)} />
            </VStack>
          ))}
        </HStack>
      </VStack>

      {/* Streak Skeleton */}
      <Box
        width="100%"
        height={hp(8)}
        borderRadius={10}
        bg="#ddd"
        p={4}
        mt={4}
      >
        <Skeleton.Text w={wp(40)} lines={1} />
        <Skeleton.Text w={wp(20)} lines={1} mt={1} />
      </Box>

      {/* Bottom Navigation Skeleton */}
      <HStack justifyContent="space-around" width="100%" mt={6}>
        {Array.from({ length: 4 }).map((_, index) => (
          <Center key={index} flex={1}>
            <Skeleton width={wp(10)} height={wp(10)} borderRadius={wp(5)} />
            <Skeleton.Text lines={1} w={wp(15)} mt={1} />
          </Center>
        ))}
      </HStack>
    </VStack>
  );
};

export default HomeSkeleton;
