// Playwright config for wp-swe-bench hidden E2E tests.
// Specs live in $WPSB_SPEC_DIR (a task's tests/e2e); results go to $WPSB_PW_JSON.
import { defineConfig } from '@playwright/test';

export default defineConfig({
	testDir: process.env.WPSB_SPEC_DIR || '/tests/e2e',
	testMatch: /.*\.spec\.(m?js|ts)$/,
	outputDir: process.env.WPSB_PW_OUT || '/logs/verifier/e2e-artifacts',
	timeout: Number(process.env.WPSB_PW_TIMEOUT || 180_000),
	expect: { timeout: 30_000 },
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: [['list'], ['json', { outputFile: process.env.WPSB_PW_JSON || '/logs/verifier/e2e.playwright.json' }]],
	use: {
		baseURL: process.env.WPSB_URL || 'http://127.0.0.1:9400',
		browserName: 'chromium',
		headless: true,
		viewport: { width: 1440, height: 1000 },
		actionTimeout: 30_000,
		navigationTimeout: 120_000,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		proxy: undefined,
	},
});
