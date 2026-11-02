import { Source } from "react-native-fast-image";

type RoundedBannerProps = {
    icon?: number | Source | undefined;
    text?: string;
    buttonTitle?: string;
    onPress?: () => void;
}