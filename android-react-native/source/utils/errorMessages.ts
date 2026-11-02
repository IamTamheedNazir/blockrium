export const getErrorMessages = (errorJson: any) => {
  let errorObj;

  if (typeof errorJson === 'string') {
    try {
      errorObj = JSON.parse(errorJson);
    } catch (parseError) {
      return errorJson;
    }
  } else if (typeof errorJson === 'object' && errorJson !== null) {
    errorObj = errorJson;
  } else {
    return 'Something went wrong. Please try again.';
  }

  const messages = [];

  for (const key in errorObj) {
    if (Object.prototype.hasOwnProperty.call(errorObj, key)) {
      const value = errorObj[key];
      if (Array.isArray(value)) {
        messages.push(...value);
      } else if (typeof value === 'string') {
        messages.push(value);
      }
    }
  }

  if (messages.length > 0) {
    return messages;
  }

  if (typeof errorObj?.message === 'string' && errorObj.message.trim()) {
    return errorObj.message;
  }

  if (typeof errorObj?.error === 'string' && errorObj.error.trim()) {
    return errorObj.error;
  }

  return 'Something went wrong. Please try again.';
};
