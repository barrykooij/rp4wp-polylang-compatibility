<?php
/*
Plugin Name: Related Posts for WordPress - Polylang Compatibility
Plugin URI: https://docs.relatedpostsforwp.com/multilingual/polylang-compatibility/
Version: 3.0.0
Author: Aucor Oy, Never5
Author URI: https://www.aucor.fi
Description: Keeps related posts in the language of the post on Polylang sites. Works with Related Posts for WordPress 3.0 or newer (free or Premium), and with Premium 2.x.
Requires at least: 6.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'Get out of here stalker!' );

class RP4WP_Polylang {

	const PUBLISH_HOOKS = array( 'RP4WP_Hook_Related_Save_Words', 'RP4WP_Hook_Related_Auto_Link' );

	/**
	 * Posts published before Polylang stored their language: post ID => the status before publishing.
	 *
	 * @var array<int, string>
	 */
	private $held = array();

	/**
	 * The on-publish callbacks that are taken off while a post is held.
	 *
	 * @var array<string, object>
	 */
	private $unhooked = array();

	/**
	 * Constructor. Runs after Related Posts for WordPress (plugins_loaded 20) and the safe mode check of Premium (30).
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'setup' ), 40 );
	}

	/**
	 * Hook in, depending on the version of Related Posts for WordPress.
	 */
	public function setup() {
		if ( ! function_exists( 'pll_register_string' ) ) {
			add_action( 'admin_notices', array( $this, 'dep_notice_polylang' ) );

			return;
		}

		if ( class_exists( '\LV2\WordPress\RelatedPostsForWP\Main' ) && \LV2\WordPress\RelatedPostsForWP\Main::get()->is_set_up() ) {
			$this->setup_3();

			return;
		}

		if ( class_exists( 'RP4WP_Constants' ) && defined( 'RP4WP_Constants::PREMIUM' ) && true === RP4WP_Constants::PREMIUM ) {
			$this->setup_2();

			return;
		}

		add_action( 'admin_notices', array( $this, 'dep_notice_rp4wp' ) );
	}

	/**
	 * Related Posts for WordPress 3.x, free or Premium.
	 */
	private function setup_3() {
		add_filter( 'rp4wp_finder_clauses', array( $this, 'filter_finder_clauses' ), 10, 3 );
		add_filter( 'rp4wp_fill_conditions', array( $this, 'filter_fill_conditions' ), 10, 3 );

		// The plugin reads and links a post when it is published, which is before Polylang stores the language of a post that
		// is created and published at once (the REST API, an import, WP-CLI). Such posts wait until Polylang is done.
		add_action( 'transition_post_status', array( $this, 'hold' ), 9, 3 );
		add_action( 'transition_post_status', array( $this, 'rehook' ), 12, 0 );
		add_action( 'wp_after_insert_post', array( $this, 'release' ), 99, 1 );
		add_action( 'shutdown', array( $this, 'release_all' ), 0, 0 );
	}

	/**
	 * Related Posts for WordPress Premium 2.x.
	 */
	private function setup_2() {
		add_filter( 'rp4wp_get_related_posts_sql', array( $this, 'filter_related_posts_sql' ), 10, 4 );
	}

	/**
	 * Dependency RP4WP notice
	 */
	public function dep_notice_rp4wp() {
		?>
		<div class="notice notice-error"><p><strong><a
						href="https://www.relatedpostsforwp.com/?utm_source=rp4wp-polylang-compatibility&utm_medium=link&utm_campaign=notice"
						target="_blank">Related Posts for WordPress</a> 3.0 or higher</strong>, or Related Posts for WordPress Premium 2.x, must be
				installed and activated to use <strong>Related Posts for WordPress - Polylang Compatibility</strong>.</p>
		</div><?php
	}

	/**
	 * Dependency Polylang notice
	 */
	public function dep_notice_polylang() {
		?>
		<div class="notice notice-error"><p><strong>Polylang</strong> must be installed and activated to use <strong>Related
					Posts for WordPress - Polylang Compatibility</strong>.</p></div><?php
	}

	/**
	 * The term taxonomy ID of the Polylang language of a post, or 0 when Polylang has none (yet).
	 *
	 * @param int $post_id The post.
	 *
	 * @return int
	 */
	private function language( $post_id ) {
		$terms = wp_get_object_terms( $post_id, 'language', array( 'fields' => 'tt_ids' ) );

		return is_array( $terms ) && count( $terms ) > 0 ? (int) $terms[0] : 0;
	}

	/**
	 * The SQL condition that keeps a candidate related post in the language of the post, or an empty string when the
	 * post has no language (yet). Posts of post types Polylang does not translate have no language, and stay candidates.
	 *
	 * @param int    $post_id The post.
	 * @param string $column  The column of the candidate's ID in the query.
	 *
	 * @return string
	 */
	private function condition( $post_id, $column ) {
		global $wpdb;

		$language = $this->language( $post_id );
		if ( 0 === $language ) {
			return '';
		}

		return "( EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} RP4WP_TR WHERE RP4WP_TR.object_id = {$column} AND RP4WP_TR.term_taxonomy_id = {$language} )
			OR NOT EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} RP4WP_TR2 INNER JOIN {$wpdb->term_taxonomy} RP4WP_TT2 ON RP4WP_TT2.term_taxonomy_id = RP4WP_TR2.term_taxonomy_id WHERE RP4WP_TR2.object_id = {$column} AND RP4WP_TT2.taxonomy = 'language' ) )";
	}

	/**
	 * Only find related posts in the language of the post (3.x).
	 *
	 * @param array  $clauses   The extra joins and conditions; the candidate related post is R.post_id.
	 * @param int    $post_id   The post.
	 * @param string $post_type The post type of the post.
	 *
	 * @return array
	 */
	public function filter_finder_clauses( $clauses, $post_id, $post_type ) {
		$condition = $this->condition( $post_id, 'R.post_id' );
		if ( '' !== $condition ) {
			$clauses['where'][] = $condition;
		}

		return $clauses;
	}

	/**
	 * Only fill up related posts with posts in the language of the post (Premium 3.0.1 and newer).
	 *
	 * @param string[] $conditions The conditions; {ID} is the post that may fill up.
	 * @param int      $post_id    The post.
	 * @param string   $post_type  The post type of the post.
	 *
	 * @return string[]
	 */
	public function filter_fill_conditions( $conditions, $post_id, $post_type ) {
		$condition = $this->condition( $post_id, '{ID}' );
		if ( '' !== $condition ) {
			$conditions[] = $condition;
		}

		return $conditions;
	}

	/**
	 * When a post is published before Polylang stored its language, take the on-publish callbacks of the plugin off for it,
	 * so it is read and linked once its language is known (3.x).
	 *
	 * @param string   $new_status The new status.
	 * @param string   $old_status The old status.
	 * @param \WP_Post $post       The post.
	 */
	public function hold( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! $post instanceof \WP_Post || 0 !== $this->language( (int) $post->ID ) ) {
			return;
		}

		if ( ! class_exists( '\LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks' ) ) {
			return;
		}

		foreach ( self::PUBLISH_HOOKS as $name ) {
			$hook = \LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks::get( $name );
			if ( $hook && remove_action( $hook->get_tag(), array( $hook, 'run' ), $hook->get_priority() ) ) {
				$this->unhooked[ $name ] = $hook;
			}
		}

		if ( count( $this->unhooked ) > 0 ) {
			$this->held[ (int) $post->ID ] = (string) $old_status;
		}
	}

	/**
	 * Put the on-publish callbacks back, so other posts are handled as usual (3.x).
	 */
	public function rehook() {
		foreach ( $this->unhooked as $hook ) {
			add_action( $hook->get_tag(), array( $hook, 'run' ), $hook->get_priority(), $hook->get_args() );
		}

		$this->unhooked = array();
	}

	/**
	 * Read and link a held post, now that Polylang stored its language (3.x).
	 *
	 * @param int $post_id The post.
	 */
	public function release( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! isset( $this->held[ $post_id ] ) ) {
			return;
		}

		$old_status = $this->held[ $post_id ];
		unset( $this->held[ $post_id ] );

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		foreach ( self::PUBLISH_HOOKS as $name ) {
			$hook = \LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks::get( $name );
			if ( $hook ) {
				$hook->run( 'publish', $old_status, $post );
			}
		}
	}

	/**
	 * Read and link the posts that are still held at the end of the request, for code that publishes a post without
	 * wp_insert_post() (3.x).
	 */
	public function release_all() {
		foreach ( array_keys( $this->held ) as $post_id ) {
			$this->release( $post_id );
		}
	}

	/**
	 * Add language to related posts fetch SQL so we only fetch posts of current post's language (2.x)
	 *
	 * @param String $sql
	 * @param int $post_id
	 * @param String $post_type
	 * @param int $limit
	 *
	 * @return String
	 */
	public function filter_related_posts_sql( $sql, $post_id, $post_type, $limit ) {
		global $wpdb;

			$sql_replace = "
		INNER JOIN " . $wpdb->term_relationships . " ON (R.`post_id` = " . $wpdb->term_relationships . ".object_id)
		INNER JOIN " . $wpdb->term_taxonomy . " ON (" . $wpdb->term_relationships . ".term_taxonomy_id = " . $wpdb->term_taxonomy . ".term_taxonomy_id)
		WHERE 1=1
		AND " . $wpdb->term_taxonomy . ".taxonomy = 'language'
		AND " . $wpdb->term_taxonomy . ".term_id IN ( SELECT TT.term_id FROM " . $wpdb->term_taxonomy . " TT INNER JOIN " . $wpdb->term_relationships . " TR ON TR.term_taxonomy_id = TT.term_taxonomy_id WHERE TR.object_id = " . $post_id . " )
		";

		return str_ireplace( 'WHERE 1=1', $sql_replace, $sql );
	}
}

new RP4WP_Polylang();
