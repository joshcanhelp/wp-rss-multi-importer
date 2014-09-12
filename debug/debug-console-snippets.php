<?php

/*
 * DO NOT INCLUDE THIS FILE!
 * This code is for copying and pasting into the Debug Bar Console
 */

/*
 * Testing different expiration date queries
 */

function rssmi_delete_posts_DEBUG () {

	global $wpdb;

	$options    = get_option( 'rss_post_options' );
	$expiration = intval( $options['expiration'] );

	/*
	 * original query
	 */

	$delete_wpwb = 0;

	$ids   = $wpdb->get_results( "
		SELECT ID FROM $wpdb->posts WHERE post_type = 'post' AND DATEDIFF(NOW(), `post_date`) > " . $expiration
	);

	foreach ( $ids as $id ) {
		$mypostids = $wpdb->get_results( "
			SELECT * FROM $wpdb->postmeta WHERE meta_key = 'rssmi_source_link' AND post_id = " . $id->ID
		);
		if ( ! empty( $mypostids ) ) {
			$delete_wpwb = count( $mypostids ) + $delete_wpwb;
		}
	}
	echo 'Original: ' . $delete_wpwb . '<br>';

	/*
	 * get_posts using date_query
	 */

	$delete_posts_args = array(
		'post_type'        => 'post',
		'post_status'      => array( 'publish', 'pending', 'draft', 'auto-draft', 'private', 'inherit', 'trash' ),
		'meta_key'         => 'rssmi_source_link',
		'posts_per_page'   => - 1,
		'suppress_filters' => TRUE,
		'date_query' => array(
			'before' => '-' . $expiration . ' days'
		)
	);
	$delete_posts = get_posts( $delete_posts_args );
	echo 'Date_query: ' . count( $delete_posts ) . '<br>';

	/*
	 * get_posts using posts_where filter
	 */

	unset( $delete_posts_args['date_query'] );
	$delete_posts_args['date_query'] = FALSE;
	add_filter( 'posts_where', 'rssmi_delete_posts_filter_posts_where', 10 );
	$delete_posts = get_posts( $delete_posts_args );
	echo 'Filtered: ' . count( $delete_posts ) . '<br>';
}

function rssmi_delete_custom_posts_DEBUG () {

	global $wpdb;

	// Expiration not set or set to "0" (never delete)
	if ( empty( $options['expiration'] ) ) {
		return;
	}
	$expiration = intval( $options['expiration'] );

	echo 'Expiration days: ' . $expiration . '<br>';

	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'rssmi_feed_item' AND DATEDIFF(NOW(), `post_date`) > " . $expiration;

	$posts = $wpdb->get_results( $query );

	echo count( $posts ) . ' - Original<br>';

	$delete_args = array(
		'post_type'      => 'rssmi_feed_item',
		'posts_per_page' => - 1,
		'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'private', 'inherit', 'trash' ),
		'date_query'     => array(
			'before' => $expiration . ' days ago'
		)
	);

	$posts = get_posts( $delete_args );

	echo count( $posts ) . ' - New date_query<br>';

	unset( $delete_args['date_query'] );
	$delete_args['suppress_filters'] = FALSE;
	add_filter( 'posts_where', 'rssmi_delete_custom_posts_filter_posts_where', 10 );

	$posts = get_posts( $delete_args );

	echo count( $posts ) . ' - New filter';
}

function rssmi_delete_autoposts_DEBUG() {

	global $wpdb;

	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type != 'rssmi_feed'";
	$posts = $wpdb->get_results( $query );

	$delete_count = 0;
	foreach ( $posts as $id ) {

		$mypostids = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE  meta_key = 'rssmi_source_link' AND post_id = " . $id->ID );

		if ( ! empty( $mypostids ) ) {
			$delete_count++;
		}
	}

	echo $delete_count . ' - Original<br>';

	$options = get_option( 'rss_post_options' );

	// Type of autoposts to delete
	$post_type = 'post';
	if ( ! empty( $options['custom_type_name'] ) ) {
		$post_type = sanitize_text_field( $options['custom_type_name'] );
	}

	$delete_posts_args = array(
		'post_type'      => $post_type,
		'posts_per_page' => - 1,
		'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'private', 'inherit', 'trash' ),
		'meta_key'       => 'rssmi_source_link',
	);

	$posts = get_posts( $delete_posts_args );

	echo count( $posts ) . ' - New<br>';
}

function rssmi_delete_all_posts_for_feed_DEBUG ( $pid ) {
	global $wpdb;

	$query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rssmi_source_feed' and meta_value = " . $id;
	$mypostids = $wpdb->get_results( $query );
	echo count( $mypostids ) . ' - Original<br>';

	$options = get_option( 'rss_post_options' );

	// Type of autoposts to delete
	$post_type = 'post';
	if ( ! empty( $options['custom_type_name'] ) ) {
		$post_type = sanitize_text_field( $options['custom_type_name'] );
	}

	$delete_posts_args = array(
		'post_type'      => $post_type,
		'posts_per_page' => - 1,
		'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'private', 'inherit', 'trash' ),
		'meta_query'     => array(
			array(
				'key'   => 'rssmi_source_feed',
				'value' => $pid
			)
		)
	);

	$delete_posts = get_posts( $delete_posts_args );
	echo count( $delete_posts ) . ' - New<br>';
}

function rssmi_restore_all_DEBUG (  ) {
	global $wpdb;
	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND (post_type = 'rssmi_feed_item' OR post_type = 'rssmi_feed')";
	$posts = $wpdb->get_results( $query );
	echo count( $posts ) . ' - Original<br>';

	$delete_posts_args = array(
		'post_type'      => array( 'rssmi_feed_item', 'rssmi_feed' ),
		'posts_per_page' => - 1,
		'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'private', 'inherit', 'trash' ),
	);

	$delete_posts = get_posts( $delete_posts_args );
	echo count( $delete_posts ) . ' - New<br>';
}