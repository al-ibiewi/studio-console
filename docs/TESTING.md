# Testing

## Local site

You can use any local WordPress: LocalWP, wp-env (`npx @wordpress/env start`), XAMPP or the PHP built-in server with the SQLite drop-in.

1. Symlink or copy this folder into `wp-content/plugins/alibiewi-console`. The folder name matters for the plugin basename.
2. Activate **Studio Console**.
3. Optionally copy the stand-ins you need from `dev/mu-plugins/` into `wp-content/mu-plugins/`:

| File | What it fakes |
|---|---|
| `test-fake-claude.php` | The Claude API. It answers by listing or quoting the sources it was given and records the request in the option `alc_test_last_request`. Use it to test the assistant without spending credits. |
| `test-fake-woo.php` | Just enough of WooCommerce (product post type, `product_cat`, `wc_get_product`, `wc_price`, NGN) for the store logic. **Don't use it alongside real WooCommerce.** |
| `fake-elementor.php` | An active Elementor kit, for logo and font detection |
| `fake-fluent.php` | Fluent Forms, for the dashboard forms panel |
| `test-hide-sqlite-badge.php` | Hides the SQLite drop-in admin notice in screenshots |

The real Zone A site has real WooCommerce, Elementor and Fluent Forms, so none of these belong there.

## WP-CLI checks

```bash
wp eval 'echo ALC_AI_Store::facts();'
wp eval 'var_dump( ALC_AI_Store::budget( "a bag for 30k" ) );'
wp eval 'print_r( ALC_AI_Store::products_for( "bags between 25k and 50k" ) );'
wp eval 'ALC_AI_Index::rebuild(); print_r( ALC_AI_Index::search( "return policy", 6 ) );'
wp eval 'print_r( ALC_AI::answer( "best bag for 30k" ) );'    # with test-fake-claude.php
wp transient delete --all                                      # clear answer and brand caches
```

## Manual checklist before a release

**Login**
- Check the logo, headline and panel at desktop and phone widths.
- A wrong password shows the generic error. Five failures lock out the IP.
- Remember Me spacing looks right.

**Dashboard**
- Check `index.php?page=alc-home` at 1280×720, 1440×900, 1366×560 and phone size.
- Press `/` and the quick search works.
- Each panel only shows for users who can use it.

**Admin theme**
- Switch through all ten themes plus a custom accent.
- Check the sidebar, top bar, block editor accent and mobile drawer.

**Client mode**
- Log in as a Site Owner. `plugins.php` and `options-general.php` return 403 or redirect with `?alc_blocked`.
- A Site Owner can't edit administrators, and Administrator is missing from the role dropdown.

**Assistant**
- With the bubble, on the front end:
  - a welcome message, suggestion chips and the WhatsApp handoff appear
  - the bubble lifts above a WhatsApp button in the same corner when auto-avoid is on
- With test-fake-claude:
  - a budget question shows product buttons with prices
  - an unknown topic shows the Contact page button
  - no raw URLs appear in the text
- Settings > Assistant:
  - Check connection reports status
  - Relearn updates the page count
  - the tester and the log work
- The 21st question in an hour from one IP gets the rate-limit message.

**Settings**
- Export, then import. The API key is never in the export file.

**Uninstall**
- Removes the options and the `alc_ai_chunks` table.

Screenshots for the README were taken with Playwright and Chrome at a device scale factor of 2.
