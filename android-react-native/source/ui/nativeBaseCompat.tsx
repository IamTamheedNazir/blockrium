import React from 'react';
import {
  Modal,
  Pressable,
  StyleProp,
  StyleSheet,
  ScrollView as RNScrollView,
  ScrollViewProps,
  Text as RNText,
  TextProps as RNTextProps,
  TextStyle,
  TouchableOpacity,
  View,
  ViewProps,
  ViewStyle,
} from 'react-native';
import * as NativeBase from 'native-base';

type PrimitiveValue = number | string | undefined;

type StackProps = ViewProps & {
  children?: React.ReactNode;
  style?: StyleProp<ViewStyle>;
  w?: PrimitiveValue;
  width?: PrimitiveValue;
  h?: PrimitiveValue;
  height?: PrimitiveValue;
  minW?: PrimitiveValue;
  minH?: PrimitiveValue;
  maxW?: PrimitiveValue;
  maxH?: PrimitiveValue;
  p?: PrimitiveValue;
  px?: PrimitiveValue;
  py?: PrimitiveValue;
  pt?: PrimitiveValue;
  pb?: PrimitiveValue;
  pl?: PrimitiveValue;
  pr?: PrimitiveValue;
  m?: PrimitiveValue;
  mx?: PrimitiveValue;
  my?: PrimitiveValue;
  mt?: PrimitiveValue;
  mb?: PrimitiveValue;
  ml?: PrimitiveValue;
  mr?: PrimitiveValue;
  bg?: string;
  backgroundColor?: string;
  borderRadius?: PrimitiveValue;
  borderWidth?: PrimitiveValue;
  borderColor?: string;
  alignItems?: ViewStyle['alignItems'];
  justifyContent?: ViewStyle['justifyContent'];
  flexDirection?: ViewStyle['flexDirection'];
  flex?: ViewStyle['flex'];
  opacity?: ViewStyle['opacity'];
  gap?: PrimitiveValue;
  space?: PrimitiveValue;
};

type CompatTextProps = RNTextProps & {
  children?: React.ReactNode;
  style?: StyleProp<TextStyle>;
  color?: string;
  fontSize?: PrimitiveValue;
  fontWeight?: TextStyle['fontWeight'];
  fontFamily?: string;
  textAlign?: TextStyle['textAlign'];
  mt?: PrimitiveValue;
  mb?: PrimitiveValue;
};

type SkeletonProps = StackProps & {
  color?: string;
  isLoaded?: boolean;
  lines?: number;
};

type ActionSheetItemProps = StackProps & {
  children?: React.ReactNode;
  onPress?: () => void;
  startIcon?: React.ReactNode;
  _text?: CompatTextProps;
  color?: string;
  fontSize?: PrimitiveValue;
  fontWeight?: TextStyle['fontWeight'];
};

const resolveSpacing = (value: PrimitiveValue) => {
  if (value === undefined) {
    return undefined;
  }

  if (value === 'full') {
    return 9999;
  }

  return value;
};

const buildBoxStyle = (props: StackProps): ViewStyle => ({
  width: resolveSpacing(props.w ?? props.width) as ViewStyle['width'],
  height: resolveSpacing(props.h ?? props.height) as ViewStyle['height'],
  minWidth: resolveSpacing(props.minW) as ViewStyle['minWidth'],
  minHeight: resolveSpacing(props.minH) as ViewStyle['minHeight'],
  maxWidth: resolveSpacing(props.maxW) as ViewStyle['maxWidth'],
  maxHeight: resolveSpacing(props.maxH) as ViewStyle['maxHeight'],
  padding: resolveSpacing(props.p) as ViewStyle['padding'],
  paddingHorizontal: resolveSpacing(props.px) as ViewStyle['paddingHorizontal'],
  paddingVertical: resolveSpacing(props.py) as ViewStyle['paddingVertical'],
  paddingTop: resolveSpacing(props.pt) as ViewStyle['paddingTop'],
  paddingBottom: resolveSpacing(props.pb) as ViewStyle['paddingBottom'],
  paddingLeft: resolveSpacing(props.pl) as ViewStyle['paddingLeft'],
  paddingRight: resolveSpacing(props.pr) as ViewStyle['paddingRight'],
  margin: resolveSpacing(props.m) as ViewStyle['margin'],
  marginHorizontal: resolveSpacing(props.mx) as ViewStyle['marginHorizontal'],
  marginVertical: resolveSpacing(props.my) as ViewStyle['marginVertical'],
  marginTop: resolveSpacing(props.mt) as ViewStyle['marginTop'],
  marginBottom: resolveSpacing(props.mb) as ViewStyle['marginBottom'],
  marginLeft: resolveSpacing(props.ml) as ViewStyle['marginLeft'],
  marginRight: resolveSpacing(props.mr) as ViewStyle['marginRight'],
  backgroundColor: props.bg ?? props.backgroundColor,
  borderRadius: resolveSpacing(props.borderRadius) as ViewStyle['borderRadius'],
  borderWidth: resolveSpacing(props.borderWidth) as ViewStyle['borderWidth'],
  borderColor: props.borderColor,
  alignItems: props.alignItems,
  justifyContent: props.justifyContent,
  flexDirection: props.flexDirection,
  flex: props.flex,
  opacity: props.opacity,
  gap: resolveSpacing(props.gap ?? props.space) as ViewStyle['gap'],
});

const Box = React.forwardRef<View, StackProps>(
  (
    {
      children,
      style,
      w,
      width,
      h,
      height,
      minW,
      minH,
      maxW,
      maxH,
      p,
      px,
      py,
      pt,
      pb,
      pl,
      pr,
      m,
      mx,
      my,
      mt,
      mb,
      ml,
      mr,
      bg,
      backgroundColor,
      borderRadius,
      borderWidth,
      borderColor,
      alignItems,
      justifyContent,
      flexDirection,
      flex,
      opacity,
      gap,
      space,
      ...viewProps
    },
    ref,
  ) => {
    const layoutProps = {
      w,
      width,
      h,
      height,
      minW,
      minH,
      maxW,
      maxH,
      p,
      px,
      py,
      pt,
      pb,
      pl,
      pr,
      m,
      mx,
      my,
      mt,
      mb,
      ml,
      mr,
      bg,
      backgroundColor,
      borderRadius,
      borderWidth,
      borderColor,
      alignItems,
      justifyContent,
      flexDirection,
      flex,
      opacity,
      gap,
      space,
    };

    return (
      <View ref={ref} {...viewProps} style={[buildBoxStyle(layoutProps), style]}>
        {children}
      </View>
    );
  },
);

Box.displayName = 'Box';

const Stack = React.forwardRef<View, StackProps>((props, ref) => (
  <Box ref={ref} {...props} />
));

Stack.displayName = 'Stack';

const HStack = React.forwardRef<View, StackProps>((props, ref) => (
  <Box ref={ref} {...props} flexDirection="row" />
));

HStack.displayName = 'HStack';

const VStack = React.forwardRef<View, StackProps>((props, ref) => (
  <Box ref={ref} {...props} flexDirection="column" />
));

VStack.displayName = 'VStack';

const Center = React.forwardRef<View, StackProps>((props, ref) => (
  <Box ref={ref} {...props} alignItems="center" justifyContent="center" />
));

Center.displayName = 'Center';

const Text = React.forwardRef<RNText, CompatTextProps>(
  ({children, style, color, fontSize, fontWeight, fontFamily, textAlign, mt, mb, ...props}, ref) => (
    <RNText
      ref={ref}
      {...props}
      style={[
        {
          color,
          fontSize: resolveSpacing(fontSize) as TextStyle['fontSize'],
          fontWeight,
          fontFamily,
          textAlign,
          marginTop: resolveSpacing(mt) as TextStyle['marginTop'],
          marginBottom: resolveSpacing(mb) as TextStyle['marginBottom'],
        },
        style,
      ]}>
      {children}
    </RNText>
  ),
);

Text.displayName = 'Text';

const SkeletonBase: React.FC<SkeletonProps> = ({
  children,
  style,
  color,
  backgroundColor,
  isLoaded,
  ...props
}) => {
  if (isLoaded) {
    return <>{children}</>;
  }

  return (
    <Box
      {...props}
      style={[
        styles.skeleton,
        {
          backgroundColor: color ?? backgroundColor ?? '#D7E0EC',
        },
        style,
      ]}
    />
  );
};

const SkeletonText: React.FC<SkeletonProps> = ({lines = 3, ...props}) => (
  <VStack {...props} space={8}>
    {Array.from({length: lines}).map((_, index) => (
      <SkeletonBase
        key={`skeleton-line-${index}`}
        height={12}
        width={index === lines - 1 ? '72%' : '100%'}
      />
    ))}
  </VStack>
);

const Skeleton = Object.assign(SkeletonBase, {
  Text: SkeletonText,
});

const ActionSheetRoot: React.FC<{
  isOpen?: boolean;
  onClose?: () => void;
  children?: React.ReactNode;
}> = ({isOpen, onClose, children}) => (
  <Modal
    visible={Boolean(isOpen)}
    transparent
    animationType="slide"
    onRequestClose={onClose}>
    <Pressable style={styles.sheetBackdrop} onPress={onClose}>
      <Pressable style={styles.sheetContainer} onPress={() => undefined}>
        {children}
      </Pressable>
    </Pressable>
  </Modal>
);

const ActionSheetContent: React.FC<StackProps> = ({children, style, ...props}) => (
  <Box {...props} style={[styles.sheetContent, style]}>
    {children}
  </Box>
);

const ActionSheetItem: React.FC<ActionSheetItemProps> = ({
  children,
  onPress,
  startIcon,
  _text,
  color,
  fontSize,
  fontWeight,
  style,
  ...props
}) => (
  <TouchableOpacity
    activeOpacity={0.85}
    onPress={onPress}
    style={[styles.sheetItem, buildBoxStyle(props), style]}>
    {startIcon ? <View style={styles.sheetItemIcon}>{startIcon}</View> : null}
    <Text
      style={styles.sheetItemText}
      color={_text?.color ?? color}
      fontSize={_text?.fontSize ?? fontSize}
      fontWeight={_text?.fontWeight ?? fontWeight}>
      {children}
    </Text>
  </TouchableOpacity>
);

const Actionsheet = Object.assign(ActionSheetRoot, {
  Content: ActionSheetContent,
  Item: ActionSheetItem,
});

const CompatView = React.forwardRef<View, StackProps>((props, ref) => (
  <Box ref={ref} {...props} />
));

CompatView.displayName = 'CompatView';

const ScrollView = React.forwardRef<RNScrollView, ScrollViewProps & StackProps>(
  (
    {
      children,
      style,
      w,
      width,
      h,
      height,
      minW,
      minH,
      maxW,
      maxH,
      p,
      px,
      py,
      pt,
      pb,
      pl,
      pr,
      m,
      mx,
      my,
      mt,
      mb,
      ml,
      mr,
      bg,
      backgroundColor,
      borderRadius,
      borderWidth,
      borderColor,
      alignItems,
      justifyContent,
      flexDirection,
      flex,
      opacity,
      gap,
      space,
      ...scrollProps
    },
    ref,
  ) => {
    const layoutProps = {
      w,
      width,
      h,
      height,
      minW,
      minH,
      maxW,
      maxH,
      p,
      px,
      py,
      pt,
      pb,
      pl,
      pr,
      m,
      mx,
      my,
      mt,
      mb,
      ml,
      mr,
      bg,
      backgroundColor,
      borderRadius,
      borderWidth,
      borderColor,
      alignItems,
      justifyContent,
      flexDirection,
      flex,
      opacity,
      gap,
      space,
    };

    return (
      <RNScrollView
        ref={ref}
        {...scrollProps}
        style={[buildBoxStyle(layoutProps), style]}>
        {children}
      </RNScrollView>
    );
  },
);

ScrollView.displayName = 'CompatScrollView';

const Divider: React.FC<StackProps> = ({style, ...props}) => (
  <Box
    {...props}
    style={[
      {
        height: 1,
        backgroundColor: '#D7E0EC',
      },
      style,
    ]}
  />
);

export const Button = (NativeBase as any).Button;
export const Card = (NativeBase as any).Card;
export {
  Actionsheet,
  Box,
  Center,
  CompatView as View,
  Divider,
  HStack,
  ScrollView,
  Skeleton,
  Stack,
  Text,
  VStack,
};

const styles = StyleSheet.create({
  skeleton: {
    borderRadius: 10,
    overflow: 'hidden',
  },
  sheetBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.45)',
    justifyContent: 'flex-end',
  },
  sheetContainer: {
    width: '100%',
  },
  sheetContent: {
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    paddingBottom: 24,
    overflow: 'hidden',
  },
  sheetItem: {
    minHeight: 54,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
  },
  sheetItemIcon: {
    marginRight: 12,
  },
  sheetItemText: {
    flex: 1,
  },
});
