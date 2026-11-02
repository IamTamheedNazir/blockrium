import {Text, TextProps} from 'react-native'
import React from 'react'
import {RFValue} from 'react-native-responsive-fontsize';
import fonts from '../../assets/fonts';

type TitleTextProps = TextProps & {
  text?: React.ReactNode;
};

const TitleText: React.FC<TitleTextProps> = ({style, text, children, ...props}) => {
  const textStyle = style as any;
  return <Text style={[style,  {fontSize: RFValue(32), color: textStyle?.color ? textStyle?.color : 'white', fontFamily: fonts.jaro_regular}]} {...props}>{text ?? children}</Text>
}

export default TitleText;
