import { KeyboardTypeOptions } from "react-native"

type InputProps = {
    value?: string
    style?: any
    placeholder?: string
    onChangeText?: (value: string) => void
    keyboardType?: KeyboardTypeOptions,
    styles?: any,
    editable?: boolean
}