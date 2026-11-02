import React from 'react';
import FastImage from 'react-native-fast-image';
import {images} from '../../assets/images';
import LinearGradient from 'react-native-linear-gradient';
import {hp} from '../utils/styleConstants';
import { TouchableOpacity} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { navigate } from '../utils/helper';

const MiningButton = () => {
  return (
      <TouchableOpacity style={{ position: 'absolute', bottom: 30, left: '43%',}} onPress={() => {navigate('MiningStack')}}>
        <FastImage
          style={{width: 60, height: 60}}
          resizeMode="contain"
          source={images.mining}
        />
      </TouchableOpacity>
  );
};

export default MiningButton;
