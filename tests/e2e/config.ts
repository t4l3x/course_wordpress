const DEFAULT_BASE_URL = 'http://127.0.0.1:8080';

function resolveBaseUrl(): string {
  const url = new URL(
    process.env.PLAYWRIGHT_BASE_URL ?? DEFAULT_BASE_URL,
  );

  if (url.protocol !== 'http:' && url.protocol !== 'https:') {
    throw new Error('PLAYWRIGHT_BASE_URL must use HTTP or HTTPS.');
  }

  return url.toString();
}

export const e2eConfig = {
  baseUrl: resolveBaseUrl(),
  routes: {
    courseCatalogue: '/course-discovery/',
  },
} as const;

export function e2eUrl(path: string): string {
  return new URL(path, e2eConfig.baseUrl).toString();
}
