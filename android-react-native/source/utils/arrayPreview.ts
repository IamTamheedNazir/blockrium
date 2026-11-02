export const getArrayOrEmpty = <T>(value: unknown): T[] => {
  if (!Array.isArray(value)) {
    return [];
  }

  return value as T[];
};

export const getArrayPreview = <T>(value: unknown, limit: number): T[] =>
  getArrayOrEmpty<T>(value).slice(0, Math.max(0, limit));

export const describeArrayPayload = (value: unknown) => {
  if (Array.isArray(value)) {
    return {type: 'array', item_count: value.length};
  }

  if (value === null) {
    return {type: 'null'};
  }

  if (typeof value === 'string') {
    return {
      type: 'string',
      character_count: value.length,
      looks_like_html: /<!doctype html|<html/i.test(value.slice(0, 256)),
    };
  }

  if (typeof value === 'object') {
    return {type: 'object', keys: Object.keys(value).slice(0, 20)};
  }

  return {type: typeof value};
};
