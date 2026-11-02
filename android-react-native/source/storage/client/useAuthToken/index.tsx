import {useEffect, useState} from 'react';
import {enableFreeze} from 'react-native-screens';
import {getStorageToken} from '../../../utils/mmkvStorage';

export const useAuthToken = (): string => {
  enableFreeze(true);
  const [tokenValue, setTokenValue] = useState('');

  const wantToken = async () => {
    try {
      const token = await getStorageToken('authToken');
      setTokenValue(token ?? '');
    } catch (error) {
      setTokenValue('');
      if (__DEV__) {
        console.log('Unable to restore auth token', error);
      }
    }
  };

  useEffect(() => {
    wantToken();
  }, []);

  return tokenValue;
};
