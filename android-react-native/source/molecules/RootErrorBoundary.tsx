import React from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import theme from '../design/theme';
import {logAppIssue} from '../services/appIssueLogger';

type RootErrorBoundaryState = {
  hasError: boolean;
  errorMessage?: string;
  restartFailed?: boolean;
};

const getThrownMessage = (error: unknown) =>
  error instanceof Error ? error.message : String(error ?? '');

const isRecoverableNotificationStartupError = (error?: unknown) => {
  const message = getThrownMessage(error);
  return (
    /getInitialNotification|onNotificationOpenedApp|onMessage|getToken/i.test(
      message,
    ) && /null|undefined|not available/i.test(message)
  );
};

class RootErrorBoundary extends React.Component<
  {children: React.ReactNode},
  RootErrorBoundaryState
> {
  state: RootErrorBoundaryState = {
    hasError: false,
  };

  static getDerivedStateFromError(error: Error) {
    if (isRecoverableNotificationStartupError(error)) {
      return {
        hasError: false,
        errorMessage: undefined,
      };
    }

    return {
      hasError: true,
      errorMessage:
        getThrownMessage(error) ||
        'Startup render failed before the first screen loaded.',
    };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo) {
    if (isRecoverableNotificationStartupError(error)) {
      console.log('Recoverable notification startup warning', error);
      return;
    }

    console.log('Root render error', error);
    logAppIssue({
      severity: 'fatal',
      source: 'react',
      action: 'root_error_boundary',
      errorCode: error?.name,
      message: error?.message || 'Root render error',
      metadata: {
        stack: error?.stack,
        componentStack: info?.componentStack,
      },
    }).catch(logError => {
      console.log('Unable to send root render issue log', logError);
    });
  }

  handleRestart = () => {
    try {
      const restart = require('react-native-restart').default?.Restart;
      if (typeof restart !== 'function') {
        throw new Error('Native restart is unavailable');
      }

      restart();
    } catch (error) {
      console.log('Unable to restart app from recovery screen', error);
      this.setState({restartFailed: true});
    }
  };

  render() {
    if (!this.state.hasError) {
      return this.props.children;
    }

    return (
      <View style={styles.container}>
        <View style={styles.card}>
          <Text style={styles.eyebrow}>Blockrium</Text>
          <Text style={styles.title}>Session needs a quick refresh</Text>
          <Text style={styles.copy}>
            {this.state.restartFailed
              ? 'Automatic restart is unavailable. Close and reopen Blockrium to try again.'
              : 'The app caught a startup problem before it could finish loading.'}
          </Text>
          {this.state.errorMessage ? (
            <Text style={styles.detail} numberOfLines={4}>
              {this.state.errorMessage}
            </Text>
          ) : null}
          <Pressable
            style={styles.button}
            onPress={this.handleRestart}
            disabled={this.state.restartFailed}
            testID="root-error-restart-button">
            <Text style={styles.buttonText}>Restart app</Text>
          </Pressable>
        </View>
      </View>
    );
  }
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    padding: 24,
    backgroundColor: theme.colors.background,
  },
  card: {
    borderRadius: 24,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 22,
    backgroundColor: theme.colors.surface,
  },
  eyebrow: {
    color: theme.colors.primary,
    fontSize: 12,
    letterSpacing: 2,
    textTransform: 'uppercase',
    fontFamily: theme.typography.heading,
  },
  title: {
    marginTop: 12,
    color: theme.colors.text,
    fontSize: 28,
    lineHeight: 34,
    fontFamily: theme.typography.display,
  },
  copy: {
    marginTop: 10,
    color: theme.colors.textMuted,
    fontSize: 15,
    lineHeight: 22,
    fontFamily: theme.typography.body,
  },
  detail: {
    marginTop: 12,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: 12,
    color: theme.colors.warning,
    backgroundColor: 'rgba(244, 183, 116, 0.08)',
    fontSize: 12,
    lineHeight: 17,
    fontFamily: theme.typography.body,
  },
  button: {
    marginTop: 20,
    minHeight: 54,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.primary,
  },
  buttonText: {
    color: '#06111F',
    fontSize: 16,
    fontFamily: theme.typography.heading,
  },
});

export default RootErrorBoundary;
