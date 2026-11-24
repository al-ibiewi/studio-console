# Studio Console: guide for AI coding agents

Read this file first, then `docs/HANDOFF.md` (current state and next steps). `docs/ARCHITECTURE.md` explains how the code fits together and `docs/TESTING.md` explains how to run and check it.

## What this is

**Al-Ibiewi Studio Console** is a WordPress plugin that Al-Ibiewi Studio installs on every client website. It adds:

- a branded login screen
- a modern, mobile-friendly client dashboard
- a branded admin sidebar and top bar, with colour themes
- a client mode (the "Site Owner" role)
- a Claude-powered assistant that answers visitors from the site's own pages and products

- Owner: Al-Ibiewi (Abubakar Zubair Okhayole), founder of Al-Ibiewi Studio, https://al-ibiewi.com.ng
- Plugin slug: `alibiewi-console`. Display name: "Studio Console". Text domain: `alibiewi-console`.
- Current version: **1.5.1** (see `readme.txt` changelog).
- Requires WordPress 6.0+ and PHP 7.4+. Tested on WP 6.8 and WP 7.1.
- First live client site: Zone A Limited (shopzonea.com), a WooCommerce bag shop in Nigeria with Elementor, Fluent Forms and naira prices.

## Rules for working in this repo

1. **Prefixes.** PHP classes are `ALC_*`, functions are `alc_*`, options are `alc_*` and constants are `ALC_*`. CSS classes are `alc-*`, and the assistant widget uses `alc-ai__*` (BEM).
2. **No build step.** The plugin is plain PHP, vanilla JS and plain CSS. Do not add npm, React, jQuery plugins or Composer dependencies. jQuery appears only where WordPress already loads it (the colour picker on the settings screen).
3. **Security.** Check capabilities and nonces on every admin action. Escape output, and sanitize input through the schema in `class-alc-settings.php`. The assistant never renders model HTML: `assistant.js` builds DOM nodes itself.
4. **Never weaken core.** The login screen is styled with hooks and CSS only, so 2FA, CAPTCHA and SSO plugins keep working. Client mode works through real capabilities and `map_meta_cap`, never just by hiding menus.
5. **Releasing a version.** Bump `Version:` in the plugin header, `ALC_VERSION` (assets are cache-busted with it) and `Stable tag` in `readme.txt`, and add a changelog entry. Then build with `bin/build.sh` or `bin/build.ps1`.
6. **Never ship `dev/`.** Files in `dev/mu-plugins` are fake Elementor, Fluent Forms, WooCommerce and Claude stand-ins used for local testing only. `.gitattributes` and the build scripts exclude `dev/`, `docs/`, `bin/` and `screenshots/`.
7. **Writing style (owner's preference).** Never use em dashes in UI text, docs, commits or replies. Keep the tone warm, results-driven and concise. The plugin UI is plain English for non-technical clients.
8. **Git.** Commits are authored as `al-ibiewi <al-ibiewi@users.noreply.github.com>`. The remote is the **public** GitHub repo `al-ibiewi/studio-console`, so never commit API keys, client data or anything else private. Each release is tagged `vX.Y.Z`.
9. **The assistant uses the client's details, never the studio's.** Visitor-facing contact info comes only from the `ai_contact_*` settings or the site's contact page. The studio support fields (`support_*`) are only for the client's admin "Get help" button.
10. **API cost.** Don't make live Claude API calls to a client site to test something. Locally, use `dev/mu-plugins/test-fake-claude.php`, which short-circuits requests through the `alc_ai_pre_request` filter.

## Quick map

```
alibiewi-console.php           bootstrap, constants, requires, alc_boot(), upgrade routine
uninstall.php                  removes options, AI table, role (if unused)
includes/
  class-alc-settings.php       settings schema by tab, defaults, sanitize, import/export
  helpers.php                  alc_opt(), colour maths, brand CSS variables, fonts, misc
  class-alc-brand.php          colour themes, logo and font detection, resolved brand
  class-alc-admin-theme.php    admin colour scheme, sidebar/top bar, admin bar nodes
  class-alc-login.php          branded login screen
  class-alc-login-guard.php    lockout and generic login errors
  class-alc-branding.php       admin footer, studio credit
  class-alc-dashboard.php      the client home page (index.php?page=alc-home)
  class-alc-client-mode.php    Site Owner role and its safeguards
  class-alc-ai-index.php       passage index (custom table) + BM25 search
  class-alc-ai-store.php       WooCommerce facts and budget/category product lookup
  class-alc-ai.php             REST endpoint, prompt, Claude call, answer + action buttons, widget
  class-alc-admin-settings.php the Studio Console settings screen (tabs)
assets/js   assistant.js (chat widget), dashboard.js, settings.js
assets/css  login, dashboard, admin-theme, settings, assistant
dev/        local test stand-ins (never shipped)
docs/       HANDOFF, ARCHITECTURE, TESTING
bin/        build scripts
```

## Commands

```bash
# Lint every PHP file
find . -name '*.php' -not -path './dev/*' -print0 | xargs -0 -n1 php -l

# Check JS syntax
node -e "new Function(require('fs').readFileSync('assets/js/assistant.js','utf8'))"

# Build the release zip into dist/
bash bin/build.sh            # or: powershell -File bin/build.ps1
```
