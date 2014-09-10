<?php

/*
 * DO NOT INCLUDE THIS FILE!
 * This code is for copying and pasting into the Debug Bar Console
 */

/*
 * Testing different expiration date queries
 */

function rssmi_console_expiration () {

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
	add_filter( 'posts_where', 'rssmi_filter_expiration_posts_where', 10 );
	$delete_posts = get_posts( $delete_posts_args );
	echo 'Filtered: ' . count( $delete_posts ) . '<br>';
}
