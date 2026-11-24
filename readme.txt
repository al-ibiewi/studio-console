=== Al-Ibiewi Studio Console ===
Contributors: alibiewistudio
Tags: login, dashboard, white label, admin, branding
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A branded login, a clean client dashboard and white-label controls for websites built by Al-Ibiewi Studio.

== Description ==

Studio Console gives every client site the same polished back office, dressed in the client's own brand:

* Color themes. Pick one of 10 ready-made themes (Studio, Midnight, Forest, Ocean, Royal, Plum, Mint, Sand, Cloud, Blush) with a live preview, then fine-tune the accent and panel colors if needed. Developers can add more with the alc_color_themes filter.
* Uses the website's logo and fonts. The logo comes from the site (or Elementor site settings) and fonts are read from theme.json, Elementor, Astra, GeneratePress, Kadence or Storefront. A dark logo on a dark theme gets a white backing automatically so it never disappears.
* Branded admin. The wp-admin sidebar and top bar use the brand colors and fonts, with pill-style menu items, card flyouts, a clean white top bar, a "Get help" button and a phone-friendly drawer. Sizes stay at core values so the block editor and WooCommerce keep their layout.
* Branded login. A split-screen sign-in page with the client's logo, colors, headline and an optional background image. It only restyles the core screen through hooks and CSS, so 2FA, CAPTCHA and SSO plugins keep working.
* Login protection. Pauses sign-in for an IP after repeated failures and shows one private error message, so the form never confirms which usernames exist.
* Client mode. A "Site Owner" role for clients: pages, posts, media, menus, users below administrator, store orders and form entries, with no access to plugins, themes, updates, settings or administrator accounts. These are real capability checks, so typed-in URLs are blocked too. Pick extra menus to hide, silence plugin nags and Help tabs, and switch users between Site Owner and Administrator from the Client mode tab.
* AI website assistant. A chat bubble (or the [alc_assistant] shortcode) that answers visitor questions from the site's own published pages and products using Claude, with numbered citations linking to the pages it used. It says plainly when something is not on the site, never guesses prices or policies, keeps to per-visitor and daily limits, and logs recent questions so you can see which content is missing. The index updates itself whenever content is saved.
* Client dashboard. Replaces the default wp-admin home with a greeting, a quick search (press / to jump to any page, post or action), quick actions, key numbers, latest enquiries (Fluent Forms), store sales and orders (WooCommerce), recently edited content, website health, one-tap support and how-to guides. Every widget respects user roles.
* White-label kit. One settings screen per site, plus JSON export and import to set up a new client in seconds.

Fonts load the same way the website already loads them. Bricolage Grotesque is bundled as a fallback.

== Installation ==

1. Plugins > Add New > Upload Plugin, choose alibiewi-console.zip, then Activate.
2. Go to Settings > Studio Console.
3. Brand tab: pick a color theme. Use the custom accent and panel fields only if the client needs exact brand colors.
4. Support tab: add your WhatsApp number and email so the "Need a hand?" card goes live.
5. Click "Preview login" to check the result.

To reuse a setup, export it from Import and export on one site and import it on the next. Logos and images are stored per site, so pick them again after importing.

== Frequently Asked Questions ==

= How do I set up client mode? =

Settings > Studio Console > Client mode: turn it on, choose what Site Owners may manage, save, then use "Make Site Owner" on your client's account. Keep your own administrator account. Your account is never listed there, so you cannot lock yourself out.

= Whose contact details does the assistant share? =

Only the business's own: the Business WhatsApp, phone, email and contact page on the AI assistant tab, or details found on the site's pages. Your studio details on the Support tab are only used for the client-facing "Get help" button and dashboard card.

= How does the AI assistant work? =

Published pages, posts and products are split into short passages and indexed. When a visitor asks something, the best-matching passages (BM25 keyword ranking, no extra services) go to Claude with strict rules to answer only from them and cite each fact. Add your Claude API key on the AI assistant tab, or define ALC_ANTHROPIC_API_KEY in wp-config.php. The key is never shown again or included in exports. Claude Haiku is the default: fast and very low cost.

= What data is sent to Anthropic? =

Only the visitor's question, the last few turns of that conversation and the matching passages of public content. No names, emails or IP addresses.

= Can I still reach the classic dashboard? =

Yes. Administrators see a "Classic dashboard" link in the footer of the new dashboard, or can open /wp-admin/index.php?alc-classic=1.

= The site already runs Wordfence. Will lockouts clash? =

They will not break anything, but there is no need for two. Switch off "Limit login attempts" on the Login tab and let Wordfence handle it.

= The site is behind Cloudflare. =

Turn on "Behind Cloudflare" on the Login tab so lockouts use the visitor's real IP. Leave it off otherwise, because the header can be faked on sites that are not proxied.

= How do I add the studio mark next to the credit? =

Drop a square SVG at assets/img/studio-mark.svg. It appears automatically.

== Developer hooks ==

* alc_color_themes (filter): add or change color themes. Each: [ 'label' => '', 'panel' => '#hex', 'accent' => '#hex' ].
* alc_ai_system_prompt (filter): adjust the assistant's instructions.
* alc_ai_post_text (filter): supply text for page builders that store content outside post_content.
* alc_ai_pre_request (filter): short-circuit the API call, for tests or another provider.
* alc_client_caps (filter): adjust what Site Owners can do.
* alc_quick_actions (filter): add or remove dashboard tiles. Each item is [ label, url, dashicon, description ].
* alc_stats (filter): change the number cards (max 4 shown).
* alc_health_rows (filter): add rows to the website health card.
* alc_studio_url (filter): change the credit link.
* alc_login_locked (action): fires with the IP and username when an IP is paused. Handy for an n8n webhook alert.

== Changelog ==

= 1.5.2 =
* Assistant: a product found by both the budget lookup and the page search is now cited once, so it no longer gets two buttons.
* Assistant: a reply that mentions a "contact form" no longer adds a Contact page button. The button still appears when the reply points the visitor to a person.
* Assistant: every product button shows its price, including products found through the page search.
* Dashboard: on phones, long figures such as monthly sales no longer run into the card icon.
* Dashboard: quick action descriptions wrap onto a second line instead of being cut off.
* Dashboard: phones show a shorter search placeholder, so it is no longer cut off mid-word.

= 1.5.1 =
* Assistant: smarter shop answers. Knows the product count, categories and price range, and finds products by budget ("a bag for 30k", "under 20,000", "between 25k and 50k") or category.
* Assistant: recommends specific products by name and price, closest match first, and gives partial answers instead of giving up.
* Assistant: links now appear as buttons under the reply (products show their price), with a Contact page button when a person is needed. Raw web addresses are removed from replies.

= 1.5.0 =
* Launch release.
* The assistant now uses the business's own customer contacts (new Customer contact settings) instead of the studio support details, and finds the Contact page automatically.
* Button icons stay centred on WordPress 7.
* The assistant's input, send button and suggestions keep their look on themes that restyle every input and button.
* With no suggested questions set, the assistant suggests questions from the site's own pages.

= 1.4.2 =
* The assistant panel now fits the space left above a lifted bubble, and opens from the corner on short screens.
* Clear error messages in the widget (with the error code) instead of a generic one, plus details in the browser console.
* New "Check connection" button on the AI assistant tab to test the Claude key in one click.
* The widget calls the site with a relative address, so www and non-www mismatches cannot block it.
* PHP errors while answering are caught and shown on the AI assistant tab.

= 1.4.1 =
* The assistant bubble now moves above WhatsApp buttons, cookie bars and other floating buttons in the same corner, including ones that load late.
* New bubble position (left or right) and extra bottom spacing settings.
* WhatsApp handoff inside the assistant: offered in the welcome message and after questions the website does not answer.

= 1.4.0 =
* AI website assistant powered by Claude: grounded answers with citations, chat bubble and shortcode, rate limits, daily cap, answer cache, question log and a dashboard card.

= 1.3.0 =
* Client mode with the Site Owner role, admin protection, menu hiding, blocked direct URLs, quieter notices and Help tabs.
* Dashboard hides cards and shortcuts that point to hidden menus.

= 1.2.0 =
* Replaced color detection with 10 color themes and a live preview in settings.
* Custom accent and panel colors still override the theme.
* Logo and fonts still come from the website.
* Dark logos on dark panels (and light on light) get an automatic backing.

= 1.1.0 =
* Logo, colors and fonts now come from the website by default.
* Logo-aware panel tone for the login screen, sidebar and dashboard header.
* Branded wp-admin sidebar, top bar, buttons and block editor accent.
* Dashboard quick search, stat icons and a phone-first layout.
* Settings show what was detected, with a "Scan again" button.

= 1.0.0 =
* First release: branded login, login protection, client dashboard, white-label settings with export and import.
