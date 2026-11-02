import { Text } from 'react-native'
import React from 'react'
import { titleTextProps } from '../types/titleTextProps'
import colors from '../utils/colors'
import fonts from '../../assets/fonts'
import { RFValue } from 'react-native-responsive-fontsize'

const NormalText: React.FC<titleTextProps> = ({style, text, ...props}) => {
    return <Text style={[style,  {fontSize: style?.fontSize ? style?.fontSize : RFValue(14), color: style?.color ? style?.color : colors.white, fontFamily: fonts["jaro_regular"], fontWeight: 'bold'}]} {...props}>{text}</Text>

}

export default NormalText;