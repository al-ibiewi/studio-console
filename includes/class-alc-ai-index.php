<?php
/**
 * Content index for the AI assistant.
 *
 * Published pages, posts and products are split into short passages and
 * stored with normalised search terms. Questions are matched with BM25
 * keyword scoring, so it needs no embeddings service and runs on any host.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_AI_Index {

	const DB_VERSION = 1;
	const CHUNK_SIZE = 900;

	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 20, 3 );
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_post' ) );
	}

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'alc_ai_chunks';
	}

	public static function install() {
		if ( (int) get_option( 'alc_ai_db' ) === self::DB_VERSION && self::table_exists() ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				chunk_no int(10) unsigned NOT NULL DEFAULT 0,
				title text NOT NULL,
				url text NOT NULL,
				content longtext NOT NULL,
				terms longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY post_id (post_id)
			) {$charset};"
		);
		update_option( 'alc_ai_db', self::DB_VERSION, false );
	}

	/**
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Post types included in the index.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = alc_opt( 'ai_post_types' );
		$types = is_array( $types ) ? $types : array();
		return array_values( array_filter( $types, 'post_type_exists' ) );
	}

	/* --------------------------------------------------------------------
	 * Keeping it fresh
	 * ------------------------------------------------------------------ */

	/**
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post Post.
	 */
	public static function on_status_change( $new, $old, $post ) {
		if ( 'publish' === $old && 'publish' !== $new ) {
			self::delete_post( $post->ID );
		}
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save( $post_id, $post ) {
		if ( ! alc_opt( 'ai_enabled' ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! self::table_exists() ) {
			return;
		}
		if ( self::indexable( $post ) ) {
			self::index_post( $post );
		} else {
			self::delete_post( $post_id );
		}
	}

	/**
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public static function indexable( $post ) {
		return $post instanceof WP_Post
			&& 'publish' === $post->post_status
			&& '' === $post->post_password
			&& in_array( $post->post_type, self::post_types(), true );
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public static function delete_post( $post_id ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::table(), array( 'post_id' => (int) $post_id ), array( '%d' ) );
	}

	/**
	 * Rebuild the whole index.
	 *
	 * @return array{posts:int,chunks:int}
	 */
	public static function rebuild() {
		global $wpdb;
		self::install();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . self::table() );

		$posts  = 0;
		$chunks = 0;
		$types  = self::post_types();
		if ( $types ) {
			$ids = get_posts(
				array(
					'post_type'      => $types,
					'post_status'    => 'publish',
					'posts_per_page' => 2000,
					'fields'         => 'ids',
					'has_password'   => false,
					'no_found_rows'  => true,
				)
			);
			foreach ( $ids as $id ) {
				$n = self::index_post( get_post( $id ) );
				if ( $n ) {
					++$posts;
					$chunks += $n;
				}
			}
		}

		delete_transient( 'alc_ai_auto_suggest' );
		delete_transient( 'alc_ai_store_facts' );
		update_option(
			'alc_ai_index_meta',
			array(
				'built'  => time(),
				'posts'  => $posts,
				'chunks' => $chunks,
			),
			false
		);
		return array(
			'posts'  => $posts,
			'chunks' => $chunks,
		);
	}

	/**
	 * @return array{posts:int,chunks:int,built:int}
	 */
	public static function stats() {
		global $wpdb;
		$out = array(
			'posts'  => 0,
			'chunks' => 0,
			'built'  => 0,
		);
		if ( ! self::table_exists() ) {
			return $out;
		}
		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['chunks'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$out['posts']  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$table}" );
		// phpcs:enable
		$meta         = get_option( 'alc_ai_index_meta', array() );
		$out['built'] = isset( $meta['built'] ) ? (int) $meta['built'] : 0;
		return $out;
	}

	/**
	 * Index one post. Returns the number of passages stored.
	 *
	 * @param WP_Post $post Post.
	 * @return int
	 */
	public static function index_post( $post ) {
		global $wpdb;
		if ( ! self::indexable( $post ) ) {
			return 0;
		}

		self::delete_post( $post->ID );

		$title = wp_strip_all_tags( get_the_title( $post ) );
		$text  = self::extract_text( $post );
		if ( '' === trim( $text ) && '' === $title ) {
			return 0;
		}

		$url    = get_permalink( $post );
		$pieces = self::chunk( $text );
		if ( ! $pieces ) {
			$pieces = array( $title );
		}

		foreach ( $pieces as $i => $piece ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				self::table(),
				array(
					'post_id'  => $post->ID,
					'chunk_no' => $i,
					'title'    => $title,
					'url'      => $url,
					'content'  => $piece,
					// Title words count twice: they say what the passage is about.
					'terms'    => implode( ' ', array_merge( self::tokens( $title ), self::tokens( $title ), self::tokens( $piece ) ) ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}

		return count( $pieces );
	}

	/**
	 * Readable text for a post, including store details for products.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function extract_text( $post ) {
		$html = (string) $post->post_content;

		if ( function_exists( 'do_blocks' ) && has_blocks( $html ) ) {
			$html = do_blocks( $html );
		}

		// Keep the words inside shortcodes, drop the brackets.
		$html = preg_replace( '/\[\/?[a-zA-Z0-9_\-]+[^\]]*\]/', ' ', $html );
		// Line breaks between block-level elements.
		$html = preg_replace( '#</(p|div|li|h[1-6]|tr|section|article|blockquote)>#i', "$0\n", $html );
		$html = preg_replace( '#<br\s*/?>#i', "\n", $html );

		$parts = array( wp_strip_all_tags( $html ) );

		if ( $post->post_excerpt ) {
			array_unshift( $parts, wp_strip_all_tags( $post->post_excerpt ) );
		}

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$facts = array();
				$price = $product->get_price();
				if ( '' !== (string) $price ) {
					$facts[] = sprintf( 'Price: %s', html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' ) );
				}
				if ( $product->get_sku() ) {
					$facts[] = 'SKU: ' . $product->get_sku();
				}
				$facts[] = 'Availability: ' . ( $product->is_in_stock() ? 'In stock' : 'Out of stock' );
				$cats = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
				if ( $cats && ! is_wp_error( $cats ) ) {
					$facts[] = 'Category: ' . implode( ', ', $cats );
				}
				array_unshift( $parts, implode( '. ', $facts ) . '.' );
			}
		}

		$text = html_entity_decode( implode( "\n\n", $parts ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t\x{00A0}]+/u", ' ', $text );
		$text = preg_replace( "/\n\s*\n\s*/", "\n\n", $text );

		/**
		 * Filter the text indexed for a post, for page builders that store
		 * content outside post_content.
		 *
		 * @param string  $text Text.
		 * @param WP_Post $post Post.
		 */
		return trim( (string) apply_filters( 'alc_ai_post_text', $text, $post ) );
	}

	/**
	 * Split text into passages on paragraph, then sentence, boundaries.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function chunk( $text ) {
		$paras  = preg_split( "/\n{2,}/", $text );
		$out    = array();
		$buffer = '';

		foreach ( $paras as $para ) {
			$para = trim( preg_replace( '/\s+/u', ' ', $para ) );
			if ( '' === $para ) {
				continue;
			}
			$units = mb_strlen( $para ) > self::CHUNK_SIZE ? preg_split( '/(?<=[.!?])\s+/u', $para ) : array( $para );
			foreach ( $units as $unit ) {
				if ( '' !== $buffer && mb_strlen( $buffer ) + mb_strlen( $unit ) + 1 > self::CHUNK_SIZE ) {
					$out[]  = $buffer;
					$buffer = '';
				}
				$buffer = '' === $buffer ? $unit : $buffer . ' ' . $unit;
				// A single very long sentence still gets cut.
				while ( mb_strlen( $buffer ) > self::CHUNK_SIZE * 1.5 ) {
					$out[]  = mb_substr( $buffer, 0, self::CHUNK_SIZE );
					$buffer = mb_substr( $buffer, self::CHUNK_SIZE );
				}
			}
		}
		if ( '' !== trim( $buffer ) ) {
			$out[] = $buffer;
		}
		return array_slice( $out, 0, 60 );
	}

	/**
	 * Normalised search terms.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function tokens( $text ) {
		static $stop = null;
		if ( null === $stop ) {
			$stop = array_flip( explode( ' ', 'a an and are as at be but by can do does for from has have how i if in is it its me my of on or our so that the their them there these this to us was we what when where which who why will with you your yours about any all also am been being get got into just more most not no only other out over same some such than then they too very would could should may might much many here off up down own both each few' ) );
		}
		$text  = wp_strip_all_tags( (string) $text );
		$text  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();
		foreach ( $words as $w ) {
			if ( isset( $stop[ $w ] ) || mb_strlen( $w ) < 2 ) {
				continue;
			}
			$out[] = self::stem( $w );
		}
		return $out;
	}

	/**
	 * Very light English stemming so "bags" finds "bag" and "delivered" finds "deliver".
	 *
	 * @param string $w Word.
	 * @return string
	 */
	private static function stem( $w ) {
		$len = mb_strlen( $w );
		if ( $len > 5 && preg_match( '/(ing|ies|ied|ed|es)$/u', $w, $m ) ) {
			$w = mb_substr( $w, 0, $len - mb_strlen( $m[1] ) );
			return 'ies' === $m[1] || 'ied' === $m[1] ? $w . 'y' : $w;
		}
		if ( $len > 3 && 's' === mb_substr( $w, -1 ) && 'ss' !== mb_substr( $w, -2 ) ) {
			return mb_substr( $w, 0, $len - 1 );
		}
		return $w;
	}

	/**
	 * Best passages for a question, BM25 ranked.
	 *
	 * @param string $query Question (plus a little context).
	 * @param int    $limit Max passages.
	 * @return array<int, array{post_id:int,title:string,url:string,content:string,score:float}>
	 */
	public static function search( $query, $limit = 6 ) {
		global $wpdb;
		$q = array_unique( self::tokens( $query ) );
		if ( ! $q || ! self::table_exists() ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( 'SELECT post_id, title, url, content, terms FROM ' . self::table() . ' LIMIT 20000', ARRAY_A );
		if ( ! $rows ) {
			return array();
		}

		$n     = count( $rows );
		$df    = array_fill_keys( $q, 0 );
		$tf    = array();
		$lens  = array();
		$total = 0;

		foreach ( $rows as $i => $row ) {
			$terms      = explode( ' ', $row['terms'] );
			$lens[ $i ] = count( $terms );
			$total     += $lens[ $i ];
			$counts     = array_count_values( $terms );
			foreach ( $q as $term ) {
				if ( isset( $counts[ $term ] ) ) {
					++$df[ $term ];
					$tf[ $i ][ $term ] = $counts[ $term ];
				}
			}
		}

		$avg    = $total / max( 1, $n );
		$k1     = 1.4;
		$b      = 0.75;
		$scores = array();

		foreach ( $tf as $i => $terms ) {
			$score = 0.0;
			foreach ( $terms as $term => $freq ) {
				$idf    = log( 1 + ( $n - $df[ $term ] + 0.5 ) / ( $df[ $term ] + 0.5 ) );
				$score += $idf * ( $freq * ( $k1 + 1 ) ) / ( $freq + $k1 * ( 1 - $b + $b * $lens[ $i ] / $avg ) );
			}
			$scores[ $i ] = $score;
		}

		arsort( $scores );

		$out      = array();
		$per_post = array();
		foreach ( $scores as $i => $score ) {
			$pid = (int) $rows[ $i ]['post_id'];
			if ( isset( $per_post[ $pid ] ) && $per_post[ $pid ] >= 2 ) {
				continue;
			}
			$per_post[ $pid ] = isset( $per_post[ $pid ] ) ? $per_post[ $pid ] + 1 : 1;
			$out[]            = array(
				'post_id' => $pid,
				'title'   => $rows[ $i ]['title'],
				'url'     => $rows[ $i ]['url'],
				'content' => $rows[ $i ]['content'],
				'score'   => round( $score, 3 ),
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}
}
