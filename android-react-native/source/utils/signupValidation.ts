export type SignupField = 'username' | 'phoneNumber' | 'email' | 'password';

export type SignupFieldErrors = Partial<Record<SignupField, string>>;

const backendFieldMap: Record<string, SignupField> = {
  username: 'username',
  name: 'username',
  phone_number: 'phoneNumber',
  phoneNumber: 'phoneNumber',
  email: 'email',
  password: 'password',
};

export const getSignupFieldErrors = (payload: unknown): SignupFieldErrors => {
  let parsedPayload = payload;

  if (typeof parsedPayload === 'string') {
    try {
      parsedPayload = JSON.parse(parsedPayload);
    } catch {
      return {};
    }
  }

  if (!parsedPayload || typeof parsedPayload !== 'object') {
    return {};
  }

  const errors = (parsedPayload as {errors?: unknown}).errors;
  if (!errors || typeof errors !== 'object' || Array.isArray(errors)) {
    return {};
  }

  const fieldErrors: SignupFieldErrors = {};
  Object.entries(errors as Record<string, unknown>).forEach(([key, value]) => {
    const field = backendFieldMap[key];
    if (!field) {
      return;
    }

    const messages = Array.isArray(value) ? value : [value];
    const message = messages.find(
      item => typeof item === 'string' && item.trim().length > 0,
    );

    if (typeof message === 'string') {
      fieldErrors[field] = fieldErrors[field]
        ? `${fieldErrors[field]} ${message}`
        : message;
    }
  });

  return fieldErrors;
};

export const validateSignupFields = (fields: {
  username: string;
  phoneNumber: string;
  email: string;
  password: string;
}): SignupFieldErrors => {
  const errors: SignupFieldErrors = {};

  if (!fields.username) {
    errors.username = 'Enter a username.';
  } else if (fields.username.length < 2 || fields.username.length > 50) {
    errors.username = 'Username must be between 2 and 50 characters.';
  }

  if (!fields.phoneNumber) {
    errors.phoneNumber = 'Enter your phone number.';
  }

  if (!fields.email) {
    errors.email = 'Enter your email address.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.email)) {
    errors.email = 'Enter a valid email address.';
  }

  if (!fields.password) {
    errors.password = 'Enter a password.';
  } else if (fields.password.length < 6) {
    errors.password = 'Password must be at least 6 characters.';
  }

  return errors;
};
