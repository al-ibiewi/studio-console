<?php
/**
 * Live store view for the AI assistant, when WooCommerce is active.
 *
 * Keyword search alone cannot count products or match "30k" to "₦30,000",
 * so this adds store facts (product count, categories, price range) and
 * a budget-aware product lookup straight from WooCommerce.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_AI_Store {

	const FACTS = 'alc_ai_store_facts';

	public static function init() {
		add_action( 'save_post_product', array( __CLASS__, 'flush' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'flush' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'flush' ) );
	}

	public static function flush() {
		delete_transient( self::FACTS );
	}

	/**
	 * WooCommerce is running and products are part of what the assistant learns from.
	 *
	 * @return bool
	 */
	public static function active() {
		return function_exists( 'wc_get_product' ) && post_type_exists( 'product' ) && in_array( 'product', ALC_AI_Index::post_types(), true );
	}

	/**
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function money( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
		}
		return number_format_i18n( (float) $amount, 2 );
	}

	/**
	 * Store facts as plain text, cached for an hour.
	 *
	 * @return string
	 */
	public static function facts() {
		if ( ! self::active() ) {
			return '';
		}
		$cached = get_transient( self::FACTS );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$count = (int) wp_count_posts( 'product' )->publish;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$prices = $wpdb->get_col(
			"SELECT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_price' AND pm.meta_value <> '' AND p.post_type = 'product' AND p.post_status = 'publish' LIMIT 5000"
		);
		$prices = array_filter( array_map( 'floatval', (array) $prices ) );

		$cats  = array();
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 15,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( 'uncategorized' !== $term->slug ) {
					$cats[] = $term->name . ' (' . (int) $term->count . ')';
				}
			}
		}

		$lines   = array();
		/* translators: %d: number of products. */
		$lines[] = sprintf( 'Products for sale: %d.', $count );
		if ( $cats ) {
			$lines[] = 'Categories (product count): ' . implode( ', ', $cats ) . '.';
		}
		if ( $prices ) {
			$lines[] = sprintf( 'Prices range from %1$s to %2$s.', self::money( min( $prices ) ), self::money( max( $prices ) ) );
		}
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$lines[] = 'Currency: ' . get_woocommerce_currency() . '.';
		}

		$text = implode( "\n", $lines );
		set_transient( self::FACTS, $text, HOUR_IN_SECONDS );
		return $text;
	}

	/**
	 * Read a budget from a question: "30k", "₦30,000", "under 50 thousand", "between 20k and 40k".
	 *
	 * @param string $question Question.
	 * @return array{min: float|null, max: float|null}|null
	 */
	public static function budget( $question ) {
		$q = strtolower( (string) $question );
		$q = preg_replace( '/(\d),(\d{3})/', '$1$2', $q );
		$q = preg_replace( '/(\d),(\d{3})/', '$1$2', $q );

		$num = '(?:₦|ngn|n|\$|usd|£|gbp|€)?\s*(\d+(?:\.\d+)?)\s*(k|thousand|m|million|mil)?\b\s*(?:naira|dollars?|pounds?)?';
		preg_match_all( '/' . $num . '/u', $q, $all, PREG_SET_ORDER );

		$amounts = array();
		foreach ( $all as $m ) {
			$value  = (float) $m[1];
			$suffix = isset( $m[2] ) ? $m[2] : '';
			$marked = '' !== $suffix || preg_match( '/^(₦|ngn|\$|usd|£|gbp|€)/u', ltrim( $m[0] ) ) || preg_match( '/(naira|dollar|pound)/', $m[0] );
			$year   = ! $marked && $value >= 1900 && $value <= 2100 && floor( $value ) === $value;
			$money  = $marked || ( $value >= 500 && ! $year );
			if ( ! $money ) {
				continue;
			}
			if ( in_array( $suffix, array( 'k', 'thousand' ), true ) ) {
				$value *= 1000;
			} elseif ( in_array( $suffix, array( 'm', 'million', 'mil' ), true ) ) {
				$value *= 1000000;
			}
			$amounts[] = $value;
		}
		if ( ! $amounts ) {
			return null;
		}

		if ( count( $amounts ) >= 2 && preg_match( '/\b(between|from)\b|\d\s*(-|to|and)\s*\D{0,4}\d/', $q ) ) {
			return array(
				'min' => min( $amounts[0], $amounts[1] ),
				'max' => max( $amounts[0], $amounts[1] ),
			);
		}
		if ( preg_match( '/\b(over|above|more than|at least|from|minimum|starting)\b/', $q ) ) {
			return array(
				'min' => $amounts[0],
				'max' => null,
			);
		}
		// "under 30k", "for 30k", "around 30k", "30k budget": treat as a ceiling.
		return array(
			'min' => null,
			'max' => $amounts[0],
		);
	}

	/**
	 * Products that fit the question's budget or named category, as passages.
	 *
	 * @param string $question Question.
	 * @param int    $limit    Max products.
	 * @return array<int, array{post_id:int,title:string,url:string,content:string,price:string}>
	 */
	public static function products_for( $question, $limit = 6 ) {
		if ( ! self::active() ) {
			return array();
		}

		$budget = self::budget( $question );
		$args   = array(
			'post_type'        => 'product',
			'post_status'      => 'publish',
			'posts_per_page'   => $limit,
			'has_password'     => false,
			'no_found_rows'    => true,
			'suppress_filters' => false,
		);

		// A category named in the question narrows the list.
		$cat   = null;
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 60,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$name = strtolower( $term->name );
				$stem = rtrim( $name, 's' );
				if ( 'uncategorized' !== $term->slug && strlen( $stem ) > 2 && false !== strpos( strtolower( $question ), $stem ) ) {
					$cat = $term;
					break;
				}
			}
		}

		if ( ! $budget && ! $cat ) {
			return array();
		}

		if ( $cat ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $cat->term_id,
				),
			);
		}

		$over = false;
		if ( $budget ) {
			$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['orderby']  = 'meta_value_num';
			$meta             = array();
			if ( null !== $budget['max'] ) {
				$meta[]        = array(
					'key'     => '_price',
					'value'   => $budget['max'],
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
				$args['order'] = 'DESC'; // Closest to the budget first.
			}
			if ( null !== $budget['min'] ) {
				$meta[] = array(
					'key'     => '_price',
					'value'   => $budget['min'],
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
				if ( null === $budget['max'] ) {
					$args['order'] = 'ASC';
				}
			}
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$posts = get_posts( $args );

		// Nothing under the ceiling: show the cheapest few above it, flagged.
		if ( ! $posts && $budget && null !== $budget['max'] ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_price',
					'value'   => $budget['max'],
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			);
			$args['order']          = 'ASC';
			$args['posts_per_page'] = 3;
			$posts                  = get_posts( $args );
			$over                   = true;
		}

		$out = array();
		foreach ( $posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}
			$price = '' !== (string) $product->get_price() ? self::money( $product->get_price() ) : '';
			$bits  = array();
			if ( $price ) {
				$bits[] = 'Price: ' . $price;
			}
			$bits[] = 'Availability: ' . ( $product->is_in_stock() ? 'In stock' : 'Out of stock' );
			$names  = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
			if ( $names && ! is_wp_error( $names ) ) {
				$bits[] = 'Category: ' . implode( ', ', $names );
			}
			if ( $over ) {
				$bits[] = 'Note: priced above the budget asked about';
			}
			$desc = trim( wp_strip_all_tags( (string) $product->get_short_description() ) );
			if ( '' === $desc ) {
				$desc = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 40 );
			}
			$out[] = array(
				'post_id' => (int) $post->ID,
				'title'   => $product->get_name(),
				'url'     => get_permalink( $post ),
				'content' => implode( '. ', $bits ) . '.' . ( $desc ? ' ' . mb_substr( $desc, 0, 300 ) : '' ),
				'price'   => $price,
			);
		}
		return $out;
	}
}
