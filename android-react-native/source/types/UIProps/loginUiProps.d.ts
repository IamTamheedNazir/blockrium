type loginUiProps = {
    width?: number,
    height?: number,
    isSubmitting?: boolean,
    onPressLogin: () => void | Promise<void>,
    onPressSignUp: () => void | Promise<void>
}
