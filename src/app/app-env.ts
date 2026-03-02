export const appEnv = {
  isProduction: false,
  apiBaseDev: 'http://127.0.0.1:8000',
  apiBaseProduction: 'https://apidev.miummo.com',
} as const;

export function resolveApiBaseUrl(): string {
  return appEnv.isProduction ? appEnv.apiBaseProduction : appEnv.apiBaseDev;
}
