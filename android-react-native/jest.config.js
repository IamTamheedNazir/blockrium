module.exports = {
  preset: 'react-native',
  setupFiles: ['<rootDir>/node_modules/react-native-gesture-handler/jestSetup.js'],
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],
  transform: {
    '^.+\\.(js|jsx|ts|tsx)$': 'babel-jest',
  },
  transformIgnorePatterns: [
    'node_modules/(?!(react-native|@react-native|@react-navigation|native-base|native-base-shoutem-theme|react-native-easy-grid|react-native-vector-icons|react-native-size-matters|react-native-iphone-x-helper|react-native-linear-gradient|react-native-safe-area-context|react-native-reanimated|react-native-gesture-handler|react-native-actions-sheet|@react-native-community/netinfo|@react-native-firebase)/)',
  ],
};
