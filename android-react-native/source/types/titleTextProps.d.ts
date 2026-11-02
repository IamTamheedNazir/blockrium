import type React from 'react';
import type {TextProps} from 'react-native';

export type titleTextProps = Omit<TextProps, 'style'> & {
    text?: React.ReactNode;
    style?: any;
};
