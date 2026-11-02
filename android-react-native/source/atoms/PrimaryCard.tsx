import React from 'react'
import LinearGradient from 'react-native-linear-gradient'
import { hp } from '../utils/styleConstants'

const PrimaryCard = ({children, styles}: {children: any, styles?: any}) => {
  return (
    <LinearGradient
      start={{x: 0, y: 0.5}}
      end={{x: 1, y: 0.4}}
      colors={['#337CE1', '#06D5C7']}
      style={{
        width: '95%',
        borderRadius: hp(1.4),
        elevation: 3,
        shadowColor: 'transparent',
        marginVertical: hp(1),
        alignSelf: 'center',
        padding: hp(1.5),
        ...styles
      }}>
        {children}
    </LinearGradient>
  )
}

export default PrimaryCard