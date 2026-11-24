# Handoff: where things stand

Last updated: 24 November 2026, at version 1.5.1.

## Status

**Done and in use.** The plugin runs on Zone A Limited (shopzonea.com). The owner has called it launch-ready and is adding it to his portfolio as a case study.

| Version | What it added |
|---|---|
| 1.0 | Branded login, client dashboard, branded admin sidebar and top bar |
| 1.1 to 1.3 | Site logo and fonts, ten colour themes plus custom colours, mobile polish |
| 1.4 | Client mode (Site Owner role) |
| 1.5.0 | AI website assistant (Claude, BM25 retrieval, citations, WhatsApp handoff, auto-avoid, limits, log) |
| 1.5.1 | Smarter shop answers (store facts, budget and category lookup), link buttons under replies, raw URLs removed |

## What to do next

1. **Check 1.5.1 on Zone A with the real Claude API.** It was only tested locally with the fake Claude stand-in. Steps:
   - upload the zip
   - go to Studio Console > Assistant and click Relearn
   - ask "how many products do we have, the best bag for 30k" and "do you sell shoes?"

   Watch the wording, the product picks and the buttons. Adjust `system_prompt()` in `includes/class-alc-ai.php` if needed.
2. **Push to GitHub.** Create an empty **private** repo `al-ibiewi/studio-console` on GitHub (no README), then:
   ```bash
   git remote add origin https://github.com/al-ibiewi/studio-console.git
   git push -u origin main --tags
   ```
   Then create a GitHub release for v1.5.1 and attach `dist/alibiewi-console-1.5.1.zip`.
3. **Portfolio.** The case study belongs on al-ibiewi.com.ng (the owner is doing this in a separate session). The case study draft is a Claude doc: https://claude.ai/artifact/VtZ6TKDUXXkjydXZ5azdxC. The screenshots are in `screenshots/`. `screenshots/07-assistant.png` predates the 1.5.1 buttons and could be retaken.

## Ideas not yet started (only if the owner asks)

- Auto-updates from the private GitHub repo (for example a small updater that checks releases), so client sites don't need manual zip uploads.
- Show the stock status in product buttons, and add an "Add to cart" action.
- Let clients see the unanswered-questions log with a one-click "create FAQ page" shortcut.
- Translations (the text domain is ready; there are no `.pot` files yet).

## Decisions already made (don't undo them without asking)

- **Colours come from presets plus custom colours, not from the site.** The owner tried auto-detecting site colours and dropped it ("it will be hard to take the site color"). The logo and fonts ARE detected from the site.
- **Admin layout sizes stay at core values.** A wider sidebar or taller bar broke the block editor and WooCommerce.
- **The assistant uses the client business's contacts and WhatsApp, never the studio's.** The owner flagged this after a reply showed the studio email.
- **Links are buttons, not URLs in the text.** The owner asked for this in 1.5.1.
- **Retrieval is BM25 with no embeddings,** so it runs on cheap shared hosting with only an Anthropic key.
- **Haiku is the default model** for cost. Sonnet is an option in settings.
- **The repo is private.** The plugin is a studio asset. The portfolio shows screenshots and the case study, not the code.
- **No em dashes anywhere.** Owner's writing preference.

## Known quirks

- WP 7 core sets `.wp-core-ui .button .dashicons{line-height:1.9}`, which misaligns icons in buttons. `settings.css` overrides it.
- Client themes often restyle `input`, `textarea` and `button` inside the chat. The `!important` block at the end of `assistant.css` locks the widget's look. Keep new widget elements inside it.
- On the login screen, core's `#login form p` margins need extra specificity (`.alc-login #login form p.forgetmenot`).
- The auto-avoid lift must be measured with CSS transitions disabled, or it miscalculates.
- `ALC_AI_Store::budget()` treats a bare number of 500 or more as money, but skips years from 1900 to 2100.

## Where the work happened

This was built in Claude sessions in the claude.ai project "Web Design Project". The project has notes on the portfolio and on Studio Console. Local test sites were plain WordPress (6.8 and 7.1) using the SQLite drop-in, plus the stand-ins in `dev/mu-plugins`. See `docs/TESTING.md`.
