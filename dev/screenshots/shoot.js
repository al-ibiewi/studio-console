// Takes every README screenshot from the local Al-Ibiewi Studio demo site. See docs/TESTING.md.
// Needs playwright-core (npm i playwright-core in a scratch folder, not in this repo) and Chrome.
// Usage: node shoot.js <outDir> [only]   e.g. only = "02,07" to retake some shots.
// Env: ALC_PHP (php binary), ALC_WPCLI (wp-cli.phar), ALC_SITE (WordPress root),
//      ALC_CHROME (Chrome path), ALC_BASE (site URL, default http://localhost:8899).
const { chromium } = require('playwright-core');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const OUT = process.argv[2];
const ONLY = process.argv[3] || '';
const BASE = process.env.ALC_BASE || 'http://localhost:8899';
const PHP = process.env.ALC_PHP || 'php';
const WPCLI = process.env.ALC_WPCLI || 'wp-cli.phar';
const SITE = process.env.ALC_SITE || '.';
const CHROME = process.env.ALC_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const want = id => !ONLY || ONLY.split(',').includes(id);

function wp(code) {
	return execFileSync(PHP, [WPCLI, 'eval', code], { cwd: SITE, encoding: 'utf8' });
}
function setOpts(obj) {
	wp(`$o = (array) get_option('alc_settings', array()); update_option('alc_settings', array_merge($o, json_decode('${JSON.stringify(obj)}', true)));`);
}

async function login(browser, user, pass, viewport, scale) {
	const ctx = await browser.newContext({ viewport, deviceScaleFactor: scale });
	const page = await ctx.newPage();
	await page.goto(`${BASE}/wp-login.php`);
	await page.fill('#user_login', user);
	await page.fill('#user_pass', pass);
	await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
	return { ctx, page };
}

async function settle(page) {
	await page.waitForLoadState('networkidle');
	await page.evaluate(() => document.fonts.ready);
	await page.mouse.move(1400, 150);
	await page.waitForTimeout(400);
}

(async () => {
	const browser = await chromium.launch({ executablePath: CHROME });
	const desk = { width: 1440, height: 900 };
	const errors = [];
	const watch = (page, name) => {
		page.on('pageerror', e => errors.push(`${name}: ${e.message}`));
		page.on('console', m => { if (m.type() === 'error') errors.push(`${name}: ${m.text()}`); });
	};
	setOpts({ theme: 'studio', accent: '#D61B3F' });

	if (want('01')) {
		const ctx = await browser.newContext({ viewport: desk, deviceScaleFactor: 2 });
		const page = await ctx.newPage(); watch(page, '01');
		await page.goto(`${BASE}/wp-login.php`); await settle(page);
		await page.screenshot({ path: path.join(OUT, '01-login.png') });
		await ctx.close();
	}

	if (want('02') || want('05') || want('08')) {
		const { ctx, page } = await login(browser, 'studio', 'studio-local-only', desk, 2); watch(page, 'admin');
		if (want('02')) {
			await page.goto(`${BASE}/wp-admin/index.php?page=alc-home`); await settle(page);
			await page.screenshot({ path: path.join(OUT, '02-dashboard.png') });
		}
		if (want('05')) {
			await page.goto(`${BASE}/wp-admin/options-general.php?page=alc-settings&tab=client`); await settle(page);
			await page.screenshot({ path: path.join(OUT, '05-client-mode.png') });
		}
		if (want('08')) {
			await page.goto(`${BASE}/wp-admin/options-general.php?page=alc-settings&tab=ai`); await settle(page);
			await page.screenshot({ path: path.join(OUT, '08-ai-settings.png') });
		}
		await ctx.close();
	}

	if (want('03')) {
		const { ctx, page } = await login(browser, 'studio', 'studio-local-only', { width: 390, height: 844 }, 3); watch(page, '03');
		await page.goto(`${BASE}/wp-admin/index.php?page=alc-home`); await settle(page);
		await page.screenshot({ path: path.join(OUT, '03-dashboard-mobile.png') });
		console.log('03 placeholder:', await page.getAttribute('#alc-search-input', 'placeholder'));
		await ctx.close();
	}

	if (want('06')) {
		const { ctx, page } = await login(browser, 'zainab', 'zainab-local-only', desk, 2); watch(page, '06');
		await page.goto(`${BASE}/wp-admin/index.php?page=alc-home`); await settle(page);
		await page.screenshot({ path: path.join(OUT, '06-site-owner-view.png') });
		await ctx.close();
	}

	if (want('07')) {
		const ctx = await browser.newContext({ viewport: desk, deviceScaleFactor: 2 });
		const page = await ctx.newPage(); watch(page, '07');
		await page.goto(`${BASE}/`); await settle(page);
		await page.click('.alc-ai__launcher');
		await page.waitForSelector('.alc-ai__panel.is-in');
		await page.fill('.alc-ai__input', 'What can I get for 400k?');
		await page.click('.alc-ai__send');
		await page.waitForSelector('.alc-ai__actions', { timeout: 20000 });
		await page.waitForTimeout(900); await page.mouse.move(1000, 150);
		await page.screenshot({ path: path.join(OUT, '07-assistant.png') });
		await ctx.close();
	}

	if (want('04')) {
		const themes = ['studio', 'midnight', 'forest', 'ocean', 'royal', 'plum', 'mint', 'sand', 'cloud', 'blush'];
		const tiles = path.join(OUT, 'tiles');
		fs.mkdirSync(tiles, { recursive: true });
		const { ctx, page } = await login(browser, 'studio', 'studio-local-only', desk, 2); watch(page, '04');
		for (const t of themes) {
			setOpts({ theme: t, accent: '' });
			await page.goto(`${BASE}/wp-admin/index.php?page=alc-home`); await settle(page);
			await page.screenshot({ path: path.join(tiles, `${t}.png`), clip: { x: 0, y: 0, width: 1440, height: 640 } });
		}
		await ctx.close();
		setOpts({ theme: 'studio', accent: '#D61B3F' });

		// Lay the ten tiles out in a labelled 2 x 5 grid.
		const grid = await browser.newPage({ viewport: { width: 1440, height: 800 }, deviceScaleFactor: 2 });
		const cells = themes.map(t => `<figure><figcaption>${t[0].toUpperCase() + t.slice(1)}</figcaption><img src="data:image/png;base64,${fs.readFileSync(path.join(tiles, t + '.png')).toString('base64')}"></figure>`).join('');
		await grid.setContent(`<!doctype html><html><head><style>
			body{margin:0;padding:40px;background:#f4f3f0;font:600 22px/1.2 "Segoe UI",system-ui,sans-serif;color:#111}
			main{display:grid;grid-template-columns:1fr 1fr;gap:36px 32px}
			figure{margin:0} figcaption{margin:0 0 12px 4px}
			img{display:block;width:100%;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.08),0 8px 24px rgba(0,0,0,.08)}
		</style></head><body><main>${cells}</main></body></html>`);
		await grid.waitForLoadState('load');
		await grid.screenshot({ path: path.join(OUT, '04-color-themes.png'), fullPage: true });
		await grid.close();
	}

	await browser.close();
	if (errors.length) console.log('page errors:\n' + errors.join('\n'));
	console.log('done');
})().catch(e => { console.error(e); process.exit(1); });
