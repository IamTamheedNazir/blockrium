import React from 'react';
import {Box, VStack, Skeleton, HStack} from '../../ui/nativeBaseCompat';
import { hp, wp } from '../../utils/styleConstants';

const ProfileSkeleton = () => {
  return (
    <VStack space={4} px={4} py={6}>
      {/* Header */}
      <HStack alignItems="center" mb={4}>
        <Skeleton width={wp(8)} height={wp(8)} borderRadius={wp(4)} />
        <Skeleton.Text ml={2} lines={1} w={wp(50)} h={hp(3)} />
      </HStack>

      {/* Profile Card Skeleton */}
      <Box
        width="100%"
        height={hp(12)}
        borderRadius={10}
        bg="#ddd"
        p={4}
        alignItems="center"
      >
        <Skeleton width={hp(8)} height={hp(8)} borderRadius={hp(4)} />
        <Skeleton.Text mt={2} w={wp(50)} lines={1} />
        <Skeleton.Text w={wp(40)} lines={1} mt={1} />
      </Box>

      {/* List Skeleton Items */}
      <VStack space={4} mt={4}>
        {Array.from({ length: 5 }).map((_, index) => (
          <HStack
            key={index}
            alignItems="center"
            justifyContent="space-between"
            bg="#ddd"
            p={4}
            borderRadius={10}
            height={hp(7)}
          >
            <Skeleton.Text w={wp(30)} lines={1} />
            <Skeleton width={wp(5)} height={wp(5)} borderRadius={wp(2.5)} />
          </HStack>
        ))}
      </VStack>
    </VStack>
  );
};

export default ProfileSkeleton;
