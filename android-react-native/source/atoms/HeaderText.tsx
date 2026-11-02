import { StyleSheet, Text } from 'react-native'
import React from 'react'
import colors from '../utils/colors'
import fonts from '../../assets/fonts'
import { titleTextProps } from '../types/titleTextProps'
import { RFValue } from 'react-native-responsive-fontsize'

const HeaderText: React.FC<titleTextProps> = ({style, text, ...props}) => {
    const textStyle = StyleSheet.flatten(style);
    return <Text  style={[style,  {fontSize:textStyle?.fontSize ? textStyle?.fontSize : RFValue(12), color: textStyle?.color ? textStyle?.color :  colors.white, fontFamily: textStyle?.fontFamily ? textStyle?.fontFamily : fonts['jaro_regular'], zIndex: 10 }]} {...props}>{text}</Text>

}

export default HeaderText;
