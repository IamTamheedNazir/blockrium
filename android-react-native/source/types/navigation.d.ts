import { screenNames } from "../navigators";

type RootStackParamList = {
    [K in keyof typeof screenNames]: undefined;
};

type NavigationProp = StackNavigationProp<RootStackParamList>;

interface NavProps {
    navigate(screen: string): unknown;
    navigation: NavigationProp;
}