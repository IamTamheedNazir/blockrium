import {deleteApi, getApi, postApi, putApi} from '.';

export const switchRequestType = (
  requestType: string,
  endPoint: string,
  data?: any,
  requestOptions: {signal?: AbortSignal; timeout?: number} = {},
) => {
  switch (requestType) {
    case 'GET':
      return () => getApi(endPoint, requestOptions);
    case 'POST':
      return () => postApi(endPoint, data);
    case 'PUT':
      return () => putApi(endPoint, data);
    case 'DELETE':
      return () => deleteApi(endPoint, data);
    default:
      throw new Error(`Unsupported request type: ${requestType}`);
  }
};
