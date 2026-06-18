<?php
/**
 * Custom Post Type: trece_withdrawal.
 *
 * Registers the CPT that stores every withdrawal request, along with all
 * associated meta keys. Provides CRUD helpers consumed by the rest of the
 * plugin.
 *
 * @package Trece_Withdrawal_EU
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trece_WDEU_CPT
 *
 * Handles registration of the `trece_withdrawal` custom post type, its meta
 * keys, and lifecycle transitions. Exposes helper methods for creating,
 * reading, updating, and querying withdrawal requests.
 *
 * @since 1.0.0
 */
class Trece_WDEU_CPT {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'trece_withdrawal';

	/**
	 * All registered meta keys with their sanitisation type.
	 *
	 * Keys are the full meta_key strings. Values are one of:
	 * 'string', 'email', 'absint', 'datetime', 'json'.
	 *
	 * @var array<string, string>
	 */
	private static $meta_keys = array(
		'_trece_wdeu_customer_name'  => 'string',
		'_trece_wdeu_customer_email' => 'email',
		'_trece_wdeu_order_number'   => 'string',
		'_trece_wdeu_order_date'     => 'string',
		'_trece_wdeu_scope'          => 'string',
		'_trece_wdeu_products'       => 'json',
		'_trece_wdeu_ip_address'     => 'string',
		'_trece_wdeu_user_agent'     => 'string',
		'_trece_wdeu_submitted_at'   => 'datetime',
		'_trece_wdeu_receipt_hash'   => 'string',
		'_trece_wdeu_status'         => 'string',
		'_trece_wdeu_resolved_at'    => 'datetime',
		'_trece_wdeu_admin_comment'  => 'string',
		'_trece_wdeu_email_sent'     => 'absint',
		'_trece_wdeu_email_sent_at'  => 'datetime',
		'_trece_wdeu_wc_order_id'    => 'absint',
		'_trece_wdeu_excluded_items' => 'json',
	);

	/**
	 * Allowed values for the _trece_wdeu_status meta key.
	 *
	 * @var string[]
	 */
	private static $valid_statuses = array( 'pending', 'accepted', 'rejected', 'completed' );

	/**
	 * Allowed values for the _trece_wdeu_scope meta key.
	 *
	 * @var string[]
	 */
	private static $valid_scopes = array( 'full', 'partial' );

	/**
	 * Constructor.
	 *
	 * Hooks into WordPress to register the CPT, its meta keys, and
	 * lifecycle transitions.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta_keys' ) );
		add_action( 'transition_post_status', array( $this, 'handle_status_transition' ), 10, 3 );
	}

	/*
	|----------------------------------------------------------------------
	| Registration
	|----------------------------------------------------------------------
	*/

	/**
	 * Register the `trece_withdrawal` custom post type.
	 *
	 * The CPT is intentionally private — it must not appear in search
	 * results or public queries. Admin UI is handled by custom screens,
	 * not the default post editor.
	 *
	 * @return void
	 */
	public function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Withdrawal Requests', 'Post type general name', 'trece-withdrawal-eu' ),
			'singular_name'         => _x( 'Withdrawal Request', 'Post type singular name', 'trece-withdrawal-eu' ),
			'menu_name'             => _x( 'Withdrawals', 'Admin menu text', 'trece-withdrawal-eu' ),
			'add_new'               => __( 'Add New', 'trece-withdrawal-eu' ),
			'add_new_item'          => __( 'Add New Withdrawal Request', 'trece-withdrawal-eu' ),
			'edit_item'             => __( 'Edit Withdrawal Request', 'trece-withdrawal-eu' ),
			'new_item'              => __( 'New Withdrawal Request', 'trece-withdrawal-eu' ),
			'view_item'             => __( 'View Withdrawal Request', 'trece-withdrawal-eu' ),
			'search_items'          => __( 'Search Withdrawal Requests', 'trece-withdrawal-eu' ),
			'not_found'             => __( 'No withdrawal requests found.', 'trece-withdrawal-eu' ),
			'not_found_in_trash'    => __( 'No withdrawal requests found in Trash.', 'trece-withdrawal-eu' ),
			'all_items'             => __( 'All Requests', 'trece-withdrawal-eu' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'map_meta_cap'        => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register every meta key with the REST API and sanitisation callbacks.
	 *
	 * @return void
	 */
	public function register_meta_keys() {

		foreach ( self::$meta_keys as $meta_key => $type ) {
			register_meta(
				'post',
				$meta_key,
				array(
					'object_subtype'    => self::POST_TYPE,
					'type'              => $this->wp_schema_type( $type ),
					'single'            => true,
					'sanitize_callback' => array( $this, 'sanitize_meta_value' ),
					'auth_callback'     => function () {
						return current_user_can( 'manage_options' );
					},
					'show_in_rest'      => false,
				)
			);
		}
	}

	/*
	|----------------------------------------------------------------------
	| CRUD Helpers
	|----------------------------------------------------------------------
	*/

	/**
	 * Create a new withdrawal request.
	 *
	 * @param array $data {
	 *     Withdrawal request data.
	 *
	 *     @type string $customer_name  Full name of the customer.
	 *     @type string $customer_email Customer email address.
	 *     @type string $order_number   WooCommerce order number or free-text reference.
	 *     @type string $order_date     Date the order was placed (Y-m-d).
	 *     @type string $scope          'full' or 'partial'.
	 *     @type array  $products       List of products for partial withdrawal.
	 *     @type int    $wc_order_id    WooCommerce order ID (if applicable).
	 *     @type array  $excluded_items Items excluded from withdrawal.
	 * }
	 *
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public static function create_withdrawal( $data ) {

		$customer_name  = isset( $data['customer_name'] ) ? sanitize_text_field( $data['customer_name'] ) : '';
		$customer_email = isset( $data['customer_email'] ) ? sanitize_email( $data['customer_email'] ) : '';
		$order_number   = isset( $data['order_number'] ) ? sanitize_text_field( $data['order_number'] ) : '';
		$order_date     = isset( $data['order_date'] ) ? sanitize_text_field( $data['order_date'] ) : '';
		$scope          = isset( $data['scope'] ) && in_array( $data['scope'], self::$valid_scopes, true )
			? $data['scope']
			: 'full';
		$products       = isset( $data['products'] ) && is_array( $data['products'] ) ? $data['products'] : array();
		$wc_order_id    = isset( $data['wc_order_id'] ) ? absint( $data['wc_order_id'] ) : 0;
		$excluded_items = isset( $data['excluded_items'] ) && is_array( $data['excluded_items'] ) ? $data['excluded_items'] : array();

		$submitted_at = current_time( 'mysql', true ); // UTC.

		/* translators: %1$s: customer name, %2$s: order number. */
		$title = sprintf(
			__( 'Withdrawal – %1$s – %2$s', 'trece-withdrawal-eu' ),
			$customer_name,
			$order_number
		);

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save all meta.
		update_post_meta( $post_id, '_trece_wdeu_customer_name', $customer_name );
		update_post_meta( $post_id, '_trece_wdeu_customer_email', $customer_email );
		update_post_meta( $post_id, '_trece_wdeu_order_number', $order_number );
		update_post_meta( $post_id, '_trece_wdeu_order_date', $order_date );
		update_post_meta( $post_id, '_trece_wdeu_scope', $scope );
		update_post_meta( $post_id, '_trece_wdeu_products', wp_json_encode( $products ) );
		update_post_meta( $post_id, '_trece_wdeu_ip_address', self::get_client_ip() );
		update_post_meta( $post_id, '_trece_wdeu_user_agent', isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
		update_post_meta( $post_id, '_trece_wdeu_submitted_at', $submitted_at );
		update_post_meta( $post_id, '_trece_wdeu_status', 'pending' );
		update_post_meta( $post_id, '_trece_wdeu_email_sent', 0 );
		update_post_meta( $post_id, '_trece_wdeu_wc_order_id', $wc_order_id );
		update_post_meta( $post_id, '_trece_wdeu_excluded_items', wp_json_encode( $excluded_items ) );

		// Generate receipt hash.
		$hash_data = array(
			'name'      => $customer_name,
			'email'     => $customer_email,
			'order'     => $order_number,
			'date'      => $order_date,
			'scope'     => $scope,
			'products'  => $products,
			'timestamp' => $submitted_at,
		);

		$receipt_hash = self::calculate_receipt_hash( $hash_data );
		update_post_meta( $post_id, '_trece_wdeu_receipt_hash', $receipt_hash );

		/**
		 * Fires after a new withdrawal request has been created.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $post_id The newly created post ID.
		 * @param array $data    The sanitised data used to create the request.
		 */
		do_action( 'trece_wdeu_withdrawal_created', $post_id, $data );

		return $post_id;
	}

	/**
	 * Update the status of a withdrawal request.
	 *
	 * Records the resolution timestamp when moving to a terminal status,
	 * fires a custom action, and adds a WooCommerce order note when a
	 * linked order exists.
	 *
	 * @param int    $post_id    The withdrawal post ID.
	 * @param string $new_status One of 'pending', 'accepted', 'rejected', 'completed'.
	 * @param string $comment    Optional admin comment.
	 *
	 * @return bool True on success, false on invalid input.
	 */
	public static function update_status( $post_id, $new_status, $comment = '' ) {

		if ( ! in_array( $new_status, self::$valid_statuses, true ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$old_status = get_post_meta( $post_id, '_trece_wdeu_status', true );

		update_post_meta( $post_id, '_trece_wdeu_status', $new_status );

		if ( '' !== $comment ) {
			update_post_meta( $post_id, '_trece_wdeu_admin_comment', sanitize_textarea_field( $comment ) );
		}

		// Record resolution timestamp for terminal statuses.
		if ( in_array( $new_status, array( 'accepted', 'rejected', 'completed' ), true ) ) {
			update_post_meta( $post_id, '_trece_wdeu_resolved_at', current_time( 'mysql', true ) );
		}

		/**
		 * Fires when the status of a withdrawal request changes.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $post_id    The withdrawal post ID.
		 * @param string $new_status The new status.
		 * @param string $old_status The previous status.
		 */
		do_action( 'trece_wdeu_status_changed', $post_id, $new_status, $old_status );

		// Add WooCommerce order note if linked.
		$wc_order_id = absint( get_post_meta( $post_id, '_trece_wdeu_wc_order_id', true ) );

		if ( $wc_order_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $wc_order_id );

			if ( $order ) {
				/* translators: %1$s: old status, %2$s: new status. */
				$note = sprintf(
					__( 'Withdrawal request status changed from "%1$s" to "%2$s".', 'trece-withdrawal-eu' ),
					$old_status,
					$new_status
				);

				if ( '' !== $comment ) {
					$note .= ' ' . sprintf(
						/* translators: %s: admin comment. */
						__( 'Comment: %s', 'trece-withdrawal-eu' ),
						$comment
					);
				}

				$order->add_order_note( $note, false, true );
			}
		}

		return true;
	}

	/**
	 * Retrieve all meta for a given withdrawal request.
	 *
	 * @param int $post_id The withdrawal post ID.
	 *
	 * @return array|false Associative array of meta values keyed without
	 *                     the leading underscore, or false if the post
	 *                     does not exist or is not of the correct type.
	 */
	public static function get_withdrawal( $post_id ) {

		$post = get_post( $post_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$result = array(
			'ID'         => $post_id,
			'post_title' => $post->post_title,
		);

		foreach ( array_keys( self::$meta_keys ) as $meta_key ) {
			// Strip leading underscore for a friendlier key.
			$short_key            = ltrim( $meta_key, '_' );
			$value                = get_post_meta( $post_id, $meta_key, true );
			$result[ $short_key ] = $value;
		}

		// Decode JSON fields.
		if ( isset( $result['trece_wdeu_products'] ) && is_string( $result['trece_wdeu_products'] ) ) {
			$result['trece_wdeu_products'] = json_decode( $result['trece_wdeu_products'], true );
		}

		if ( isset( $result['trece_wdeu_excluded_items'] ) && is_string( $result['trece_wdeu_excluded_items'] ) ) {
			$result['trece_wdeu_excluded_items'] = json_decode( $result['trece_wdeu_excluded_items'], true );
		}

		return $result;
	}

	/**
	 * Query withdrawal requests by customer email.
	 *
	 * @param string $email The customer email address.
	 *
	 * @return int[] Array of matching post IDs.
	 */
	public static function get_withdrawals_by_email( $email ) {

		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_trece_wdeu_customer_email',
						'value' => sanitize_email( $email ),
					),
				),
			)
		);

		return $query->posts;
	}

	/**
	 * Query a withdrawal request by its linked WooCommerce order ID.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return int|false The withdrawal post ID, or false if not found.
	 */
	public static function get_withdrawal_by_order( $order_id ) {

		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_trece_wdeu_wc_order_id',
						'value' => absint( $order_id ),
						'type'  => 'NUMERIC',
					),
				),
			)
		);

		return ! empty( $query->posts ) ? $query->posts[0] : false;
	}

	/*
	|----------------------------------------------------------------------
	| Hashing
	|----------------------------------------------------------------------
	*/

	/**
	 * Calculate a SHA-256 receipt hash for a withdrawal request.
	 *
	 * The hash is deterministic for a given set of inputs and serves as
	 * a tamper-evident receipt identifier.
	 *
	 * @param array $data {
	 *     Hash input data.
	 *
	 *     @type string $name      Customer name.
	 *     @type string $email     Customer email.
	 *     @type string $order     Order number.
	 *     @type string $date      Order date.
	 *     @type string $scope     Withdrawal scope.
	 *     @type array  $products  Products array.
	 *     @type string $timestamp Submission timestamp.
	 * }
	 *
	 * @return string 64-character hexadecimal SHA-256 hash.
	 */
	public static function calculate_receipt_hash( $data ) {

		$products_string = is_array( $data['products'] ) ? wp_json_encode( $data['products'] ) : '';

		$payload = implode(
			'|',
			array(
				isset( $data['name'] ) ? $data['name'] : '',
				isset( $data['email'] ) ? $data['email'] : '',
				isset( $data['order'] ) ? $data['order'] : '',
				isset( $data['date'] ) ? $data['date'] : '',
				isset( $data['scope'] ) ? $data['scope'] : '',
				$products_string,
				isset( $data['timestamp'] ) ? $data['timestamp'] : '',
			)
		);

		return hash( 'sha256', $payload );
	}

	/*
	|----------------------------------------------------------------------
	| Lifecycle Hooks
	|----------------------------------------------------------------------
	*/

	/**
	 * Handle WordPress post-status transitions for the CPT.
	 *
	 * This is kept intentionally light. Specific behaviour (e.g. sending
	 * emails) is handled by other modules listening to the custom actions.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       The post object.
	 *
	 * @return void
	 */
	public function handle_status_transition( $new_status, $old_status, $post ) {

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		// Nothing to do — individual module hooks handle business logic.
	}

	/*
	|----------------------------------------------------------------------
	| Internal Utilities
	|----------------------------------------------------------------------
	*/

	/**
	 * Map an internal type string to a WordPress JSON Schema type.
	 *
	 * @param string $type Internal type identifier.
	 *
	 * @return string WordPress schema type.
	 */
	private function wp_schema_type( $type ) {

		$map = array(
			'string'   => 'string',
			'email'    => 'string',
			'absint'   => 'integer',
			'datetime' => 'string',
			'json'     => 'string',
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : 'string';
	}

	/**
	 * Sanitize a meta value based on its registered type.
	 *
	 * Used as the `sanitize_callback` for `register_meta()`.
	 *
	 * @param mixed  $value    The meta value.
	 * @param string $meta_key The meta key.
	 * @param string $object_type The object type.
	 *
	 * @return mixed Sanitised value.
	 */
	public function sanitize_meta_value( $value, $meta_key, $object_type ) {

		if ( ! isset( self::$meta_keys[ $meta_key ] ) ) {
			return sanitize_text_field( $value );
		}

		switch ( self::$meta_keys[ $meta_key ] ) {
			case 'email':
				return sanitize_email( $value );

			case 'absint':
				return absint( $value );

			case 'json':
				if ( is_array( $value ) ) {
					return wp_json_encode( $value );
				}
				// Validate JSON string.
				json_decode( $value );
				return ( json_last_error() === JSON_ERROR_NONE ) ? $value : '[]';

			case 'datetime':
				return sanitize_text_field( $value );

			case 'string':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Get the client IP address, respecting common proxy headers.
	 *
	 * @return string The client IP address.
	 */
	private static function get_client_ip() {

		$headers = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip_list = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				$ips     = array_map( 'trim', explode( ',', $ip_list ) );

				foreach ( $ips as $ip ) {
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						return $ip;
					}
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}
}
