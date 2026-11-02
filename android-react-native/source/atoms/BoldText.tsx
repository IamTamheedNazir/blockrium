import {Text} from 'react-native';
import React from 'react';
import {RFValue} from 'react-native-responsive-fontsize';
import { titleTextProps } from '../types/titleTextProps';
import fonts from '../../assets/fonts';
import colors from '../utils/colors';

const BoldText: React.FC<titleTextProps> = ({style, text, ...props}) => {
  return (
    <Text
      style={[
        style,
        {
          fontSize: RFValue(14),
          color: style?.color ? style?.color : colors.white,
          fontFamily: fonts.jaro_regular,
          fontWeight: 'bold'
        },
      ]}
      {...props}>
      {text}
    </Text>
  );
};

export default BoldText;
