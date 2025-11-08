const { test, expect } = require('@playwright/test');

// We'll store our test user and admin details as constants
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'password';
const TEST_USER = 'test_user';
const TEST_PASS = 'test_password';

// Unique form submission title using the test's ID
let requestTitle = 'E2E Test Request';

test.beforeAll(({}, testInfo) => {
  // Make the title unique for this specific test run
  requestTitle = `E2E Test Request ${testInfo.testId}`;
});

async function login(page, username, password) {
  await page.goto('/wp-login.php');
  await page.fill('input#user_login', username);
  await page.fill('input#user_pass', password);
  await page.click('input#wp-submit');

  // FIX: Instead of waiting for the admin bar (which subscribers don't see),
  // we'll wait for the page to finish reloading and then assert
  // that the "#login_error" element is NOT visible.
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('#login_error')).not.toBeVisible();
}

async function logout(page) {
  await page.goto('/wp-login.php?action=logout');
  // Need to find and click the "log out" confirmation link
  const logoutLink = page.locator('a:has-text("log out")');
  if (await logoutLink.isVisible()) {
    await logoutLink.click();
  }
  await expect(page.locator('body.logged-out')).toBeVisible();
}

test.describe('Trust & Verification Workflow', () => {
  
  test.beforeEach(async ({ page }) => {
    // Note: In a real CI, we'd need to ensure the test user exists
    // and that a form exists at '/submit-listing'
  });

  test('full user submission and admin approval flow', async ({ page }) => {
    
    // --- 1. SUBMITTER: Log in and submit form ---
    await login(page, TEST_USER, TEST_PASS);
    
    // Go to the WPUF form page (this URL must exist in the test WP site)
    await page.goto('/submit-listing'); 
    
    // Fill out the form
    // Note: 'post_title' is the field name for the WPUF post title
    await page.fill('input[name="post_title"]', requestTitle);
    
    // Find and click the submit button
    // This selector targets WPUF's default submit button ID
    await page.click('input#wpuf-submit-post'); 

    // Wait for the confirmation message
    await expect(page.locator('.wpuf-success')).toBeVisible();
    await logout(page);

    
    // --- 2. ADMIN: Log in and find the request ---
    await login(page, ADMIN_USER, ADMIN_PASS);

    // Go to the TV Requests panel
    await page.goto('/wp-admin/admin.php?page=yardlii-core-settings&tab=trust-verification&tvsection=requests');

    // Find the row for our specific request
    // It should be pending, so we'll look in the 'pending' view
    await page.click('a.status-pending');
    
    // Find the table row that contains our unique request title
    const row = page.locator('tr', { hasText: requestTitle });
    await expect(row).toBeVisible();

    // Verify it has the "Pending" status badge
    await expect(row.locator('span.status-badge--pending')).toBeVisible();

    
    // --- 3. ADMIN: Approve the request ---
    
    // Find and click the "Approve" link within that row
    await row.locator('a.row-action-approve').click();

    
    // --- 4. ADMIN: Verify the approval ---
    
    // The page reloads with a success notice
    await expect(page.locator('.yardlii-banner--success')).toBeVisible();
    await expect(page.locator('.yardlii-banner--success')).toContainText('Request approved.');

    // The request is no longer in the "Pending" list
    await expect(row).not.toBeVisible();

    // Go to the "Approved" list
    await page.click('a.status-approved');

    // Find the row again
    const approvedRow = page.locator('tr', { hasText: requestTitle });
    await expect(approvedRow).toBeVisible();

    // Verify it has the "Approved" status badge
    await expect(approvedRow.locator('span.status-badge--approved')).toBeVisible();
  });
});