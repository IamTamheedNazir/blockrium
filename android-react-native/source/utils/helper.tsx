import {
  KeyboardAvoidingView,
  ScrollView,
  NativeModules,
  View,
  StyleSheet,
} from 'react-native';
import styleConstants from './styleConstants';
import React from 'react';
import LinearGradient from 'react-native-linear-gradient';
import {navigationRef} from './navigationRef';
import {getErrorMessages} from './errorMessages';

const {Clipboard} = NativeModules;

const MainContainer: React.FC<MainContainerProps> = ({
  children,
  isScrollView = false,
  isKeyboardAvoidingView = false,
}) => {
  return isScrollView ? (
    <ScrollView style={{...styleConstants.container}}>
      {/* <ImageBackground
        style={{...styleConstants.container}}
        resizeMode="cover"
        source={imageBackground ? imageBackground : images.backgroundImage}>
        {children}
      </ImageBackground> */}
      <LinearGradient
        start={{x: 0.1, y: 0}}
        end={{x: 0.7, y: 1}}
        colors={[
          '#0A1A2F',
          '#06D5C7',
          '#0A1A2F',
          '#0A1A2F',
          '#0A1A2F',
          '#1D2570',
        ]}
        style={helperStyles.gradientFill}>
        {children}
      </LinearGradient>
    </ScrollView>
  ) : isKeyboardAvoidingView ? (
    <KeyboardAvoidingView
      style={{...styleConstants.container}}
      keyboardVerticalOffset={0}
      behavior={'position'}>
      {/* <ImageBackground
        style={{...styleConstants.container}}
        resizeMode="cover"
        source={imageBackground ? imageBackground : images.backgroundImage}>
        {children}
      </ImageBackground> */}
      <LinearGradient
        start={{x: 0.1, y: 0}}
        end={{x: 0.7, y: 1}}
        colors={[
          '#0A1A2F',
          '#06D5C7',
          '#0A1A2F',
          '#0A1A2F',
          '#0A1A2F',
          '#1D2570',
        ]}
        style={helperStyles.gradientFill}>
        {children}
      </LinearGradient>
    </KeyboardAvoidingView>
  ) : (
    <View style={{...styleConstants.container}}>
      {/* <ImageBackground
        style={{...styleConstants.container}}
        resizeMode="cover"
        source={imageBackground ? imageBackground : images.backgroundImage}>
        {children}
      </ImageBackground> */}
      <LinearGradient
        start={{x: 0.1, y: 0}}
        end={{x: 0.7, y: 1}}
        colors={[
          '#0A1A2F',
          '#06D5C7',
          '#0A1A2F',
          '#0A1A2F',
          '#0A1A2F',
          '#1D2570',
        ]}
        style={helperStyles.gradientFill}>
        {children}
      </LinearGradient>
    </View>
  );
};

const helperStyles = StyleSheet.create({
  gradientFill: {
    flex: 1,
  },
});

const nestedRouteMap: Record<string, {name: string; params?: any}> = {
  Login: {name: 'AuthStack', params: {screen: 'Login'}},
  SignUp: {name: 'AuthStack', params: {screen: 'SignUp'}},
  ForgotPassword: {name: 'AuthStack', params: {screen: 'ForgotPassword'}},
  Home: {
    name: 'BottomStack',
    params: {screen: 'Home', params: {screen: 'HomeFlow'}},
  },
  HomeFlow: {
    name: 'BottomStack',
    params: {screen: 'Home', params: {screen: 'HomeFlow'}},
  },
  Tasks: {
    name: 'BottomStack',
    params: {screen: 'Home', params: {screen: 'Tasks'}},
  },
  LearnEarn: {
    name: 'BottomStack',
    params: {screen: 'Home', params: {screen: 'LearnEarn'}},
  },
  TrustCenter: {
    name: 'BottomStack',
    params: {screen: 'Home', params: {screen: 'TrustCenter'}},
  },
  VerifiedMember: {name: 'VerifiedMember'},
  PromoCodeRedeem: {name: 'PromoCodeRedeem'},
  BoostStore: {
    name: 'BottomStack',
    params: {screen: 'Home', params: {screen: 'BoostStore'}},
  },
  Community: {
    name: 'BottomStack',
    params: {screen: 'Community', params: {screen: 'CommunityFlow'}},
  },
  CommunityFlow: {
    name: 'BottomStack',
    params: {screen: 'Community', params: {screen: 'CommunityFlow'}},
  },
  Squads: {
    name: 'BottomStack',
    params: {screen: 'Community', params: {screen: 'Squads'}},
  },
  Referral: {
    name: 'BottomStack',
    params: {screen: 'Home', params: {screen: 'Referral'}},
  },
  AppSearch: {name: 'AppSearch'},
  AppSettings: {name: 'AppSettings'},
  HelpCenter: {name: 'HelpCenter'},
  AppNotifications: {name: 'AppNotifications'},
  Notifications: {
    name: 'AppNotifications',
  },
  Mining: {
    name: 'BottomStack',
    params: {screen: 'Mining', params: {screen: 'MiningFlow'}},
  },
  MiningFlow: {
    name: 'BottomStack',
    params: {screen: 'Mining', params: {screen: 'MiningFlow'}},
  },
  Wallet: {
    name: 'BottomStack',
    params: {screen: 'Wallet', params: {screen: 'BrcWalletHome'}},
  },
  WalletFlow: {
    name: 'BottomStack',
    params: {screen: 'Wallet', params: {screen: 'BrcWalletHome'}},
  },
  BlockchainWallet: {
    name: 'BottomStack',
    params: {
      screen: 'Wallet',
      params: {screen: 'BlockchainWallet', params: {screen: 'WalletFlow'}},
    },
  },
  LeaderBoard: {
    name: 'BottomStack',
    params: {screen: 'Community', params: {screen: 'LeaderBoardFlow'}},
  },
  LeaderBoardFlow: {
    name: 'BottomStack',
    params: {screen: 'Community', params: {screen: 'LeaderBoardFlow'}},
  },
  ProfileFlow: {
    name: 'BottomStack',
    params: {screen: 'Profile', params: {screen: 'ProfileFlow'}},
  },
  Profile: {
    name: 'BottomStack',
    params: {screen: 'Profile', params: {screen: 'ProfileFlow'}},
  },
  EditScreen: {
    name: 'BottomStack',
    params: {screen: 'Profile', params: {screen: 'EditScreen'}},
  },
};

function restartApp() {
  try {
    const RNRestart = require('react-native-restart').default;
    RNRestart.Restart();
  } catch (error) {
    console.log('Restart module unavailable', error);
  }
}

function navigate(name: string, params?: any) {
  if (!navigationRef.current?.isReady()) {
    return;
  }

  const nestedRoute = nestedRouteMap[name];

  if (nestedRoute) {
    const routeParams = nestedRoute.params ?? {};
    const childParams = routeParams.params;
    const nextParams =
      params && childParams?.screen
        ? {
            ...routeParams,
            params: {
              ...childParams,
              params: {
                ...(childParams.params ?? {}),
                ...params,
              },
            },
          }
        : {
            ...routeParams,
            ...(params
              ? {params: {...(routeParams.params ?? {}), ...params}}
              : {}),
          };

    navigationRef.current.navigate(
      nestedRoute.name as never,
      nextParams as never,
    );
    return;
  }

  navigationRef.current.navigate(name as never, params as never);
}

function resetNavigation(name: string, params?: any) {
  if (!navigationRef.current?.isReady()) {
    return;
  }

  const nestedRoute = nestedRouteMap[name];
  const routeParams = nestedRoute?.params ?? {};
  const childParams = routeParams.params;
  const route = nestedRoute
    ? {
        name: nestedRoute.name,
        params:
          params && childParams?.screen
            ? {
                ...routeParams,
                params: {
                  ...childParams,
                  params: {
                    ...(childParams.params ?? {}),
                    ...params,
                  },
                },
              }
            : {
                ...routeParams,
                ...(params
                  ? {params: {...(routeParams.params ?? {}), ...params}}
                  : {}),
              },
      }
    : {name, params};

  navigationRef.current.reset({
    index: 0,
    routes: [route as never],
  });
}

const copyToClipboard = (text: string) => {
  if (Clipboard?.setString) {
    Clipboard.setString(text);
  }
};

const fetchCopiedText = async () => {
  if (Clipboard?.getString) {
    return Clipboard.getString();
  }

  return '';
};

export {
  MainContainer,
  navigate,
  resetNavigation,
  getErrorMessages,
  restartApp,
  copyToClipboard,
  fetchCopiedText,
  navigationRef,
};
