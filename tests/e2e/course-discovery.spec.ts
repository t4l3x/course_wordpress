import { expect, test, type Page } from '@playwright/test';

import { e2eConfig, e2eUrl } from './config';

function catalogue(page: Page) {
  return page.locator('[data-course-discovery]');
}

async function openCatalogue(page: Page): Promise<void> {
  await page.goto(e2eConfig.routes.courseCatalogue);
  await expect(
    page.getByRole('heading', { name: 'Discover your next course' }),
  ).toBeVisible();
}

test('renders the seeded catalogue and preserves pagination state', async ({
  page,
}) => {
  await openCatalogue(page);

  const root = catalogue(page);

  await expect(
    root.getByRole('heading', { name: '40 courses found' }),
  ).toBeVisible();
  await expect(root.getByRole('article')).toHaveCount(12);

  await Promise.all([
    page.waitForURL((url) => url.searchParams.get('course_page') === '2'),
    root.getByRole('link', { name: 'Next results page' }).click(),
  ]);

  await expect(
    root.getByLabel('Results page 2, current page'),
  ).toHaveAttribute('aria-current', 'page');
  await expect(root.getByRole('article')).toHaveCount(12);
});

test('searches the catalogue through the public GET form', async ({ page }) => {
  await openCatalogue(page);

  const root = catalogue(page);
  const search = root.getByRole('searchbox', {
    name: 'Search for a course',
  });

  await search.fill('Cybersecurity');
  await Promise.all([
    page.waitForURL((url) => url.searchParams.get('q') === 'Cybersecurity'),
    root.getByRole('button', { name: 'Search', exact: true }).click(),
  ]);

  await expect(search).toHaveValue('Cybersecurity');
  await expect(
    root.getByRole('heading', { name: '2 courses found' }),
  ).toBeVisible();
  await expect(
    root.getByRole('heading', {
      name: /Cybersecurity for Cloud Teams:/,
    }),
  ).toHaveCount(2);
});

test('submits a provider filter and restores its selected state', async ({
  page,
}) => {
  await openCatalogue(page);

  const root = catalogue(page);
  const provider = root.getByRole('checkbox', {
    name: 'Oxford Global Learning',
  });

  await provider.check();
  await Promise.all([
    page.waitForURL(
      (url) => url.searchParams.getAll('provider[]').length === 1,
    ),
    root
      .getByRole('button', { name: 'Show results', exact: true })
      .click(),
  ]);

  await expect(provider).toBeChecked();
  await expect(
    root.getByRole('link', {
      name: 'Remove Oxford Global Learning filter',
    }),
  ).toBeVisible();
  await expect(
    root.getByRole('heading', { name: '6 courses found' }),
  ).toBeVisible();
  await expect(root.getByRole('article')).toHaveCount(6);
  await expect(root.getByRole('article').first()).toContainText(
    'Oxford Global Learning',
  );
});

test('opens and closes the mobile filter drawer with keyboard focus', async ({
  page,
}) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await openCatalogue(page);

  const root = catalogue(page);
  const openFilters = root.getByRole('link', {
    name: 'Filters',
    exact: true,
  });

  await expect(openFilters).toHaveAttribute('aria-expanded', 'false');
  await openFilters.click();

  const dialog = root.getByRole('dialog', { name: 'Course filters' });

  await expect(dialog).toBeVisible();
  await expect(openFilters).toHaveAttribute('aria-expanded', 'true');
  await expect(
    dialog.getByRole('heading', { name: 'Filters', exact: true }),
  ).toBeFocused();

  await page.keyboard.press('Escape');

  await expect(dialog).toBeHidden();
  await expect(openFilters).toHaveAttribute('aria-expanded', 'false');
  await expect(openFilters).toBeFocused();
});

test('keeps search functional when JavaScript is disabled', async ({
  browser,
}) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();

  try {
    await page.goto(e2eUrl(e2eConfig.routes.courseCatalogue));

    const root = catalogue(page);
    const search = root.getByRole('searchbox', {
      name: 'Search for a course',
    });

    await search.fill('Applied Data Science');
    await Promise.all([
      page.waitForURL(
        (url) => url.searchParams.get('q') === 'Applied Data Science',
      ),
      root.getByRole('button', { name: 'Search', exact: true }).click(),
    ]);

    await expect(
      root.getByRole('heading', { name: '2 courses found' }),
    ).toBeVisible();
    await expect(search).toHaveValue('Applied Data Science');
  } finally {
    await context.close();
  }
});
