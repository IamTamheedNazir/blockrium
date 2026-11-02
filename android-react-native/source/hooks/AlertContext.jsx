// AlertContext.js
import React, { createContext, useState, useContext } from 'react';

const AlertContext = createContext();

const formatAlertTitle = (value) => {
  if (Array.isArray(value)) {
    const message = value.filter(Boolean).join('\n');
    return message || 'Something went wrong. Please try again.';
  }

  if (typeof value === 'string') {
    const message = value.trim();

    if (!message) {
      return 'Something went wrong. Please try again.';
    }

    if (/SQLSTATE|Column not found|Unknown column|Integrity constraint violation|SQL: /i.test(message)) {
      return 'Server reward sync is being updated. Please try again shortly.';
    }

    if (/<!doctype html|<html|route \[login\] not defined|whoops|laravel/i.test(message)) {
      return 'Session needs a refresh. Please log in again and try once more.';
    }

    return message;
  }

  if (value && typeof value === 'object') {
    return formatAlertTitle(
      value.message ||
        value.error ||
        value.msg ||
        'Something went wrong. Please try again.',
    );
  }

  return 'Something went wrong. Please try again.';
};

export const AlertProvider = ({ children }) => {
  const [alertConfig, setAlertConfig] = useState({
    show: false,
    title: '',
    mode: 'DEFAULT',
  });

  const showAlert = (title, mode = 'DEFAULT') => {
    setAlertConfig({ show: true, title: formatAlertTitle(title), mode });
  };

  const hideAlert = () => {
    setAlertConfig((prev) => ({ ...prev, show: false }));
  };

  return (
    <AlertContext.Provider value={{ ...alertConfig, showAlert, hideAlert }}>
      {children}
    </AlertContext.Provider>
  );
};

export const useThisAlert = () => useContext(AlertContext);
