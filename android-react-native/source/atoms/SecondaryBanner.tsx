import React from 'react';
import LinearGradient from 'react-native-linear-gradient';
import {hp, wp} from '../utils/styleConstants';

const SecondaryBanner = ({children}: any) => {
  return (
    <LinearGradient
      style={{
        width: '95%',
        height: hp(10),
        borderRadius: hp(2),
        alignSelf: 'center',
        padding: hp(1.5),
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingHorizontal: wp(5),
      }}
      colors={['#ED207B', '#F68069']}
      start={{x: 0.45, y: 0}}
      end={{x: 0.9, y: 0}}>
      {children}
    </LinearGradient>
  );
};

export default SecondaryBanner;
