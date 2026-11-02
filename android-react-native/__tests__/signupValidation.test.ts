import {
  getSignupFieldErrors,
  validateSignupFields,
} from '../source/utils/signupValidation';

describe('signup validation feedback', () => {
  it('maps Laravel validation errors to the signup form fields', () => {
    expect(
      getSignupFieldErrors({
        message: 'Please correct the signup details and try again.',
        errors: {
          email: ['The email has already been taken.'],
          phone_number: ['The phone number has already been taken.'],
        },
      }),
    ).toEqual({
      email: 'The email has already been taken.',
      phoneNumber: 'The phone number has already been taken.',
    });
  });

  it('accepts a JSON-string validation payload and maps name to username', () => {
    expect(
      getSignupFieldErrors(
        JSON.stringify({errors: {name: ['The name field is required.']}}),
      ),
    ).toEqual({username: 'The name field is required.'});
  });

  it('returns no field errors for non-validation or malformed responses', () => {
    expect(getSignupFieldErrors({message: 'Service unavailable'})).toEqual({});
    expect(getSignupFieldErrors('{not-json')).toEqual({});
  });

  it('identifies invalid local inputs before the API request', () => {
    expect(
      validateSignupFields({
        username: 'x',
        phoneNumber: '',
        email: 'not-an-email',
        password: '123',
      }),
    ).toEqual({
      username: 'Username must be between 2 and 50 characters.',
      phoneNumber: 'Enter your phone number.',
      email: 'Enter a valid email address.',
      password: 'Password must be at least 6 characters.',
    });
  });
});
