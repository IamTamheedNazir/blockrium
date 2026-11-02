module.exports = {
  root: true,
  extends: '@react-native',
  ignorePatterns: [
    'android/app/build/**',
    'android/build/**',
    'ios/Pods/**',
    'node_modules/**',
    'deploy-packages/**',
    '*.apk',
    '*.aab',
  ],
  rules: {
    '@typescript-eslint/no-unused-vars': 'warn',
    'react-hooks/exhaustive-deps': 'warn',
  },
};
