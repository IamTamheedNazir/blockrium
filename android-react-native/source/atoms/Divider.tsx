import { StyleSheet, Text, View } from 'react-native'
import React from 'react'
import {Box} from '../ui/nativeBaseCompat'
import { hp } from '../utils/styleConstants'
import colors from '../utils/colors'

const Divider = () => {
  return (
    <Box style={{width: '92%', height: hp(0.4), backgroundColor: colors.grey, marginVertical: hp(1)}}>
    </Box>
  )
}

export default Divider

const styles = StyleSheet.create({})
