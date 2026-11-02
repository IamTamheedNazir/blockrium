import { KeyboardTypeOptions } from "react-native"

type customTextInput = {
    title?: string
    placeholder?: string
    value?: string
    onChangeText?: (value: string) => void
    keyboardType?: KeyboardTypeOptions
    maxLength?: number
    styles?: any
    leftIcon?: string
    rightIcon?: string
    editable?: boolean
}