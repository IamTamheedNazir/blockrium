import { StyleSheet } from "react-native";
import { widthPercentageToDP, heightPercentageToDP } from "react-native-responsive-screen";
import colors from "./colors";

export const defaultTheme = 'dark'

const styleConstants = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: colors.background
    }
})

export const { wp, hp }: { wp: (percentage: number) => number, hp: (percentage: number) => number } = {
    wp: widthPercentageToDP,
    hp: heightPercentageToDP,
  }

export default styleConstants
