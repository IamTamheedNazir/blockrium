import {StyleSheet, View} from 'react-native';
import React from 'react';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import {StackActions} from '@react-navigation/native';
import LinearGradient from 'react-native-linear-gradient';
import NavigationIcon from '../atoms/NavigationIcon';
import theme from '../design/theme';

const tabRootScreens: Record<string, string> = {
  Home: 'HomeFlow',
  Mining: 'MiningFlow',
  Wallet: 'BrcWalletHome',
  Community: 'CommunityFlow',
  Profile: 'ProfileFlow',
};

const CustomBottomTabs = ({state, navigation}: any) => {
  const insets = useSafeAreaInsets();

  return (
    <View pointerEvents="box-none" style={styles.wrapper}>
      <LinearGradient
        start={{x: 0, y: 0}}
        end={{x: 1, y: 1}}
        colors={['rgba(17, 26, 38, 0.98)', 'rgba(7, 11, 17, 0.99)']}
        style={[
          styles.gradient,
          {
            marginBottom: Math.max(insets.bottom, 10),
          },
        ]}>
        <View style={styles.tabBarContainer}>
          {state.routes.map(
            (
              route: {
                key: string | number;
                name: any;
                state?: {key?: string};
              },
              index: number,
            ) => {
              const isFocused = state.index === index;

              const onPress = () => {
                const event = navigation.emit({
                  type: 'tabPress',
                  target: route.key,
                });

                if (!event.defaultPrevented) {
                  const rootScreen = tabRootScreens[route.name];
                  const nestedState = state.routes[index]?.state;

                  if (nestedState?.key) {
                    navigation.dispatch({
                      ...StackActions.popToTop(),
                      target: nestedState.key,
                    });
                  }

                  navigation.navigate(
                    route.name,
                    rootScreen ? {screen: rootScreen, merge: true} : undefined,
                  );
                }
              };

              return (
                <NavigationIcon
                  key={route.key}
                  route={route.name}
                  isFocused={isFocused}
                  onPress={onPress}
                />
              );
            },
          )}
        </View>
      </LinearGradient>
    </View>
  );
};

export default CustomBottomTabs;

const styles = StyleSheet.create({
  wrapper: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'transparent',
  },
  gradient: {
    marginHorizontal: 12,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: 'rgba(151, 166, 195, 0.16)',
    overflow: 'hidden',
    ...theme.shadow,
  },
  tabBarContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 5,
    paddingVertical: 5,
  },
});
