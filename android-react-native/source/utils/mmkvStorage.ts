import AsyncStorage from '@react-native-async-storage/async-storage';

const AUTH_TOKEN_KEY = 'authToken';
const AUTH_TOKEN_SERVICE = 'blockrium.auth.token.v1';

let keychainModule: typeof import('react-native-keychain') | null | undefined;

const getKeychain = () => {
  try {
    if (keychainModule === undefined) {
      keychainModule = require('react-native-keychain');
    }
    return keychainModule;
  } catch (error) {
    console.log('Keychain unavailable, using AsyncStorage token fallback', error);
    keychainModule = null;
    return null;
  }
};

const getSecureKeychainOptions = () => {
  const Keychain = getKeychain();

  if (!Keychain) {
    return undefined;
  }

  return {
    service: AUTH_TOKEN_SERVICE,
    accessible: Keychain.ACCESSIBLE.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
  };
};

async function setStorageId(key: string, value: string | number) {
  const normalizedValue = String(value);
  await AsyncStorage.setItem(key, normalizedValue);
  return normalizedValue;
}

async function getStorageId(key: string) {
  const storedValue = await AsyncStorage.getItem(key);
  if (storedValue == null) {
    return undefined;
  }

  const numericValue = Number(storedValue);
  return Number.isFinite(numericValue) ? numericValue : undefined;
}

async function setStorageToken(key: string, value: string) {
  if (key === AUTH_TOKEN_KEY) {
    const Keychain = getKeychain();
    const secureKeychainOptions = getSecureKeychainOptions();

    if (!value) {
      if (Keychain) {
        await Keychain.resetGenericPassword({service: AUTH_TOKEN_SERVICE});
      }
      await AsyncStorage.removeItem(key);
      return value;
    }

    if (Keychain && secureKeychainOptions) {
      await Keychain.setGenericPassword('blockrium-auth-token', value, secureKeychainOptions);
      await AsyncStorage.removeItem(key);
      return value;
    }

    await AsyncStorage.setItem(key, value);
    return value;
  }

  await AsyncStorage.setItem(key, value);
  return value;
}

async function getStorageToken(key: string) {
  if (key === AUTH_TOKEN_KEY) {
    const Keychain = getKeychain();

    if (Keychain) {
      const credentials = await Keychain.getGenericPassword({
        service: AUTH_TOKEN_SERVICE,
      });

      if (credentials && credentials.password) {
        return credentials.password;
      }
    }

    const legacyToken = await AsyncStorage.getItem(key);
    if (legacyToken) {
      await setStorageToken(key, legacyToken);
      return legacyToken;
    }

    return undefined;
  }

  const storedValue = await AsyncStorage.getItem(key);
  return storedValue ?? undefined;
}

export {setStorageId, getStorageId, getStorageToken, setStorageToken};
