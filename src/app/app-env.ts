export const appEnv = {
  isProduction: true,
  apiBaseDev: 'http://127.0.0.1:8000',
  apiBaseProduction: 'https://apidev.miummo.com/taptoearn',
  telegramBotUrl: 'https://t.me/ttotaptoearnbot',
} as const;

export function resolveApiBaseUrl(): string {
  return appEnv.isProduction ? appEnv.apiBaseProduction : appEnv.apiBaseDev;
}
