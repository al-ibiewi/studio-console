<?php
// TEST ONLY: seeds the Al-Ibiewi Studio demo site for README screenshots. All data is made up.
// Needs the dev/mu-plugins stand-ins (fake Claude, Woo, Woo orders, Fluent). See docs/TESTING.md.
// Run with: wp eval-file seed-demo.php
global $wpdb;

update_option( 'blogname', 'Al-Ibiewi Studio' );
update_option( 'blogdescription', 'Websites that work as hard as you do' );
update_option( 'show_avatars', 0 );
update_option( 'timezone_string', 'Africa/Lagos' );
update_option( 'permalink_structure', '/%postname%/' );

foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1, 'post_status' => 'any' ) ) as $p ) {
	wp_delete_post( $p->ID, true );
}

// Admin: the studio owner.
$admin = get_user_by( 'login', 'studio' );
wp_update_user( array( 'ID' => $admin->ID, 'first_name' => 'Abubakar', 'last_name' => 'Okhayole', 'display_name' => 'Abubakar' ) );

// Pages.
$pages = array(
	'Home'               => '<p>Al-Ibiewi Studio builds fast, mobile-first websites, online shops and school portals for growing businesses in Nigeria, then keeps them secure and up to date.</p><p>Tell us what you need and our assistant will point you to the right package.</p>',
	'About the Studio'   => '<p>Al-Ibiewi Studio is a web design studio that builds websites around how each business actually works. Every site is designed in Figma, built on WordPress and handed over with training.</p>',
	'Services'           => '<p>We build business websites, WooCommerce online shops, school and academy websites, and automations that connect your forms to WhatsApp, email and your CRM.</p>',
	'Our Process'        => '<p>Every project follows five steps: discover and map, design in Figma, build, optimise for search, then launch and support. A business website takes 2 to 4 weeks. An online shop takes 3 to 6 weeks.</p>',
	'Care Plans'         => '<p>Care plans keep your website fast and safe: updates, daily backups, uptime checks and small content changes every month. Plans start from 35,000 naira a month.</p>',
	'Portfolio'          => '<p>Selected work: online shops, business websites and school portals for clients across Nigeria.</p>',
	'FAQs'               => '<p>Payment is 60 percent to start and 40 percent at launch. You own your domain, content and website. Hosting is included for the first year on every package.</p>',
	'Contact'            => '<p>Message us on WhatsApp or send a project enquiry and we will reply within one working day. Our hours are Monday to Friday, 9am to 5pm WAT.</p>',
	'Privacy Policy'     => '<p>We only collect what you send us through our forms and use it to reply to you.</p>',
);
$ids = array();
foreach ( $pages as $title => $content ) {
	$ids[ $title ] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content ) );
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ids['Home'] );

// Blog posts.
$posts = array(
	'How much does a website cost in Nigeria in 2026?',
	'5 signs your business website is losing you customers',
	'WooCommerce or a marketplace: where should your shop live?',
	'Why your online shop needs WhatsApp ordering',
	'What a website care plan actually covers',
	'Getting found on Google: a plain-English guide',
	'How our AI assistant answers your customers at 2am',
);
$post_ids = array();
foreach ( $posts as $i => $title ) {
	$post_ids[] = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '<p>' . $title . ' A short guide from Al-Ibiewi Studio.</p>', 'post_date' => wp_date( 'Y-m-d H:i:s', time() - ( $i + 1 ) * 4 * DAY_IN_SECONDS ) ) );
}
$comments = array(
	array( 'Chiamaka Obi', 'This cleared things up for me. Do you also build sites for churches?' ),
	array( 'Ibrahim Sule', 'Great breakdown of the costs, thank you.' ),
	array( 'Funke Adebayo', 'Can the WhatsApp ordering work with Paystack too?' ),
);
foreach ( $comments as $i => $c ) {
	wp_insert_comment( array( 'comment_post_ID' => $post_ids[ $i ], 'comment_author' => $c[0], 'comment_author_email' => 'reader' . $i . '@example.com', 'comment_content' => $c[1], 'comment_approved' => 0 ) );
}

// Packages as products.
foreach ( get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'post_status' => 'any' ) ) as $p ) {
	wp_delete_post( $p->ID, true );
}
$products = array(
	array( 'Starter Website', 'Business websites', 350000, 'A 5-page mobile-first website with a contact form, WhatsApp button and Google setup.' ),
	array( 'Growth Website', 'Business websites', 650000, 'Up to 12 pages with case studies, a blog, enquiry forms and search optimisation.' ),
	array( 'WooCommerce Starter Shop', 'Online shops', 550000, 'An online shop for up to 50 products with Paystack checkout and WhatsApp ordering.' ),
	array( 'WooCommerce Pro Shop', 'Online shops', 950000, 'A full online shop with unlimited products, delivery zones, discount codes and stock alerts.' ),
	array( 'School Website', 'Schools and academies', 600000, 'A school website with admissions forms, a news section and a staff directory.' ),
	array( 'Academy Portal', 'Schools and academies', 1200000, 'Course pages, student registration and automated email updates for academies.' ),
	array( 'Essential Care Plan (monthly)', 'Care plans', 35000, 'Monthly updates, daily backups, uptime checks and one hour of content changes.' ),
	array( 'Priority Care Plan (monthly)', 'Care plans', 75000, 'Everything in Essential plus same-day fixes and three hours of changes a month.' ),
	array( 'AI Assistant Setup', 'Add-ons', 120000, 'A Claude-powered assistant that answers visitors from your own pages and products.' ),
	array( 'WhatsApp Order Routing', 'Add-ons', 60000, 'Send every order and enquiry straight to your business WhatsApp.' ),
	array( 'n8n Automation Setup', 'Add-ons', 150000, 'Connect your forms to Google Sheets, Airtable, email and your CRM.' ),
	array( 'SEO Setup', 'Add-ons', 90000, 'Search Console, sitemaps, page titles and Google Business Profile setup.' ),
	array( 'Logo Refresh', 'Add-ons', 80000, 'A cleaner version of your logo in every size your website and socials need.' ),
	array( 'Extra Page', 'Add-ons', 25000, 'One more designed page for any package.' ),
);
foreach ( $products as $p ) {
	$id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => $p[0], 'post_excerpt' => $p[3], 'post_content' => '<p>' . $p[3] . '</p>' ) );
	update_post_meta( $id, '_price', $p[2] );
	update_post_meta( $id, '_stock_status', 'instock' );
	wp_set_object_terms( $id, $p[1], 'product_cat' );
}

// Orders this month (newest first).
$people = array( 'Adaeze Nwosu', 'Tunde Bakare', 'Hauwa Garba', 'Emeka Eze', 'Bisola Ajayi', 'Musa Abdullahi', 'Ngozi Okafor', 'Segun Oladipo', 'Amina Yusuf', 'Kelechi Umeh', 'Folake Johnson', 'Yakubu Danjuma', 'Ifeoma Chukwu', 'Damilola Ade', 'Zainab Lawal', 'Chinedu Okeke', 'Rukayat Bello', 'Obinna Agu' );
$prices = array( 650000, 35000, 550000, 120000, 75000, 350000, 950000, 60000, 35000, 600000, 150000, 90000, 350000, 75000, 550000, 25000, 35000, 120000 );
$status = array( 'processing', 'processing', 'on-hold', 'processing', 'completed', 'processing', 'completed', 'completed', 'completed', 'completed', 'completed', 'completed', 'completed', 'completed', 'completed', 'completed', 'completed', 'completed' );
$orders = array();
$hours  = array( 2, 5, 9, 20, 30, 50, 70, 95, 120, 150, 190, 230, 280, 330, 380, 420, 470, 520 );
foreach ( $people as $i => $name ) {
	$orders[] = array( 'number' => (string) ( 1284 - $i ), 'name' => $name, 'total' => $prices[ $i ], 'status' => $status[ $i ], 'time' => time() - $hours[ $i ] * HOUR_IN_SECONDS );
}
update_option( 'alc_test_orders', $orders, false );
delete_transient( 'alc_woo_month_sales' );

// Fluent Forms tables and entries.
$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fluentform_forms (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, title varchar(255) NOT NULL, PRIMARY KEY (id))" );
$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fluentform_submissions (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, form_id bigint(20) unsigned NOT NULL, status varchar(45) DEFAULT NULL, created_at datetime DEFAULT NULL, response longtext, PRIMARY KEY (id))" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}fluentform_forms" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}fluentform_submissions" );
foreach ( array( 1 => 'Project enquiry', 2 => 'Contact form', 3 => 'Care plan request' ) as $fid => $ftitle ) {
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}fluentform_forms (id, title) VALUES (%d, %s)", $fid, $ftitle ) );
}
$entries = array(
	array( 'Grace Effiong', 1, 'unread', 400 ), array( 'Yusuf Ibrahim', 3, 'unread', 1500 ), array( 'Blessing Okon', 1, 'unread', 4000 ),
	array( 'Temitope Alabi', 2, 'unread', 9000 ), array( 'Aliyu Mohammed', 1, 'read', 26000 ), array( 'Nneka Igwe', 2, 'read', 50000 ),
	array( 'Olumide Coker', 1, 'read', 90000 ), array( 'Halima Sani', 3, 'read', 130000 ),
);
foreach ( array_reverse( $entries ) as $e ) {
	$parts = explode( ' ', $e[0] );
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}fluentform_submissions (form_id, status, created_at, response) VALUES (%d, %s, %s, %s)", $e[1], $e[2], wp_date( 'Y-m-d H:i:s', time() - $e[3] ), wp_json_encode( array( 'names' => array( 'first_name' => $parts[0], 'last_name' => $parts[1] ), 'email' => strtolower( $parts[0] ) . '@example.com' ) ) ) );
}

// Shop manager role, as real WooCommerce has, so client mode can pass store caps to Site Owners.
if ( ! get_role( 'shop_manager' ) ) {
	add_role( 'shop_manager', 'Shop manager', array( 'read' => true, 'manage_woocommerce' => true, 'edit_shop_orders' => true, 'edit_others_shop_orders' => true, 'edit_products' => true, 'edit_others_products' => true, 'publish_products' => true ) );
}

// Studio Console settings.
$opts = (array) get_option( 'alc_settings', array() );
update_option( 'alc_settings', array_merge( $opts, array(
	'client_name'         => 'Al-Ibiewi Studio',
	'theme'               => 'studio',
	'accent'              => '#D61B3F',
	'client_mode'         => 1,
	'ai_enabled'          => 1,
	'ai_api_key'          => 'sk-ant-api03-demo-only-not-a-real-key-x7Qa',
	'ai_name'             => '',
	'ai_contact_whatsapp' => '2348000000000',
	'ai_contact_url'      => get_permalink( $ids['Contact'] ),
	'ai_suggestions'      => "Which package fits my budget?\nHow long does a website take?\nWhat does a care plan cover?",
	'support_whatsapp'    => '2348000000000',
	'support_email'       => 'hello@example.com',
) ) );

// Assistant usage and recent questions.
update_option( 'alc_ai_usage', array( 'month' => gmdate( 'Y-m' ), 'questions' => 412, 'input' => 1286400, 'output' => 98250 ), false );
$asked = array(
	array( 'Which package fits a 600k budget for an online shop?', 1, 1200 ),
	array( 'Do you build church websites?', 0, 5400 ),
	array( 'How long does a school website take?', 1, 9800 ),
	array( 'Is hosting included?', 1, 16000 ),
	array( 'Can I pay in two parts?', 1, 30000 ),
);
$log = array();
foreach ( array_reverse( $asked ) as $a ) {
	$log[] = array( 't' => time() - $a[2], 'q' => $a[0], 'f' => $a[1] );
}
update_option( 'alc_ai_log', $log, false );
delete_option( 'alc_ai_last_error' );

// A Site Owner for the client-mode screenshot.
if ( ! get_user_by( 'login', 'zainab' ) ) {
	wp_insert_user( array( 'user_login' => 'zainab', 'user_pass' => 'zainab-local-only', 'user_email' => 'zainab@example.com', 'first_name' => 'Zainab', 'last_name' => 'Bello', 'display_name' => 'Zainab', 'role' => 'subscriber' ) );
}

flush_rewrite_rules();
ALC_AI_Index::rebuild();
echo 'pages ' . count( $ids ) . ', posts ' . count( $post_ids ) . ', products ' . count( $products ) . ', orders ' . count( $orders ) . "\n";
