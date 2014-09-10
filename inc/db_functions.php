<?php

// TODO: Refactor into an AJAX file, namespace the action
function rssmi_restore_all_delete() {
	rssmi_restore_all();
}

add_action( 'wp_ajax_restore_all', 'restore_all_callback_delete' );


// TODO: Refactor into an AJAX file, namespace the action
function restore_all_callback_delete() {

	rssmi_restore_all_delete();
	echo '<strong>Everything has been deleted.</strong>';
	die();
}

add_action( 'wp_ajax_getFeeds_Now', 'rssmi_import_all_feed_items' );


// TODO: Refactor into an AJAX file, namespace the action
function rssmi_import_all_feed_items() {
	rssmi_fetch_all_feed_items();
	echo '<strong>The feeds database has been updated.</strong>';
	die();

}

add_action( 'wp_ajax_deleteFeeds_Now', 'rssmi_delete_all_feed_items' );


// TODO: Refactor into an AJAX file, namespace the action
function rssmi_delete_all_feed_items() {
	rssmi_delete_all_custom_posts();
	echo '<strong>The feeds database has been cleared.</strong>';
	die();

}

add_action( 'wp_ajax_upgrade_feeds', 'upgradefeeds_callback_activate' );


// TODO: Refactor into an AJAX file, namespace the action
function upgradefeeds_callback_activate() {

	rssmi_activate_upgrade();

	echo '<h3>Your data has been transferred.</h3><div style="margin-left:60px;"><input type="submit" name="submit" value="Reload This Page Now" onClick="window.location.reload()" /></div>';
	die();
}


/**
 * Delete the attachments of a parent post
 *
 * @param $pid
 */
function rssmi_delete_attachment( $pid ) {

	$attachments = get_children( array(
		'post_type'      => 'attachment',
		'posts_per_page' => - 1,
		'post_status'    => 'any',
		'post_parent'    => $pid
	) );

	if ( empty( $attachments ) ) {
		return;
	}

	foreach ( $attachments as $attachment ) {
		wp_delete_attachment( $attachment->ID, TRUE );
	}
}

/**
 * Timed delete of Auto Posts from import_posts.php
 */
function rssmi_delete_posts() {

	$wp_version = (float) get_bloginfo( 'version' );
	$options    = get_option( 'rss_post_options' );

	// If no expiration is set or auto delete is not on, do nothing
	if ( empty( $options['expiration'] ) || empty( $options['autoDelete'] ) ) {
		return;
	}

	// Expiration, in days
	$expiration = intval( $options['expiration'] );

	// If no status is present, set to Trash (not permanently delete)
	$set_post_status = isset( $options['oldPostStatus'] ) ? intval( $options['oldPostStatus'] ) : 1;

	// Keep posts with comments
	$keep_commented = empty( $options['keepcomments'] ) ? FALSE : TRUE;

	// Default arguments
	$delete_posts_args = array(
		'post_type'      => 'post',
		'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'private', 'inherit', 'trash' ),
		'meta_key'       => 'rssmi_source_link',
		'posts_per_page' => - 1,
	);

	// Use date_query if we're using a high enough version
	if ( $wp_version >= 3.7 ) {
		$date_query                            = TRUE;
		$delete_posts_args['suppress_filters'] = TRUE;
		$delete_posts_args['date_query']       = array(
			'before' => '-' . $expiration . ' days'
		);
	}
	// Otherwise, need to filter the WHERE clause
	else {
		$date_query                            = FALSE;
		$delete_posts_args['suppress_filters'] = FALSE;
		add_filter( 'posts_where', 'rssmi_filter_expiration_posts_where', 10 );
	}

	$delete_posts = get_posts( $delete_posts_args );

	// Remove the filter, if added
	if ( ! $date_query ) {
		remove_filter( 'posts_where', 'rssmi_filter_expiration_posts_where', 10 );
	}

	// No posts? Nothing to do...
	if ( empty( $delete_posts ) ) {
		return;
	}

	foreach ( $delete_posts as $delete_me ) {

		$pid = $delete_me->ID;

		// Protected post, skip
		// TODO: Add this as a meta_query if version >= 3.5
		if ( get_post_meta( $pid, 'rssmi_source_protect', TRUE ) ) {
			continue;
		}

		// If the post has comments and $keep_commented is true, skip
		if ( $keep_commented && get_comments_number( $pid ) ) {
			add_post_meta( $pid, 'rssmi_source_protect', 1 );
			continue;
		}

		// Set status or delete, depending on settings
		switch ( $set_post_status ) {

			// Delete permanently
			case 0:
				rssmi_delete_attachment( $pid );
				wp_delete_post( $pid, TRUE );
				break;

			// Move to trash
			case 1:
				wp_delete_post( $pid, FALSE );
				break;

			// Change to pending
			case 2:
				wp_update_post( array(
					'ID'          => $pid,
					'post_status' => 'pending'
				) );
				break;
		}

	}
}

/**
 * Filter for expiration data added to SQL query
 *
 * @param $where
 *
 * @return string
 */
function rssmi_filter_expiration_posts_where( $where ) {

	$options = get_option( 'rss_post_options' );

	// If no expiration is set, do nothing
	if ( ! empty( $options['expiration'] ) ) {
		$expiration = intval( $options['expiration'] );
		$where .= " AND post_date < '" . date( 'Y-m-d 00:00:00', strtotime( '-' . $expiration . ' days' ) ) . "'";
	}

	return $where;
}


function rssmi_delete_custom_posts() { // TIMED DELETE ITEMS USED FOR FEED ITEMS

	global $wpdb;
	$custom_post_options_delete = get_option( 'rssmi_global_options' );
	$post_options               = get_option( 'rss_post_options' );
	$expiration                 = $custom_post_options_delete['expiration'];
	$serverTimezone             = $post_options['timezone'];


	if ( isset( $serverTimezone ) && $serverTimezone != '' ) {
		date_default_timezone_set( $serverTimezone );
	}

	if ( isset( $expiration ) && $expiration != 0 ) {

		$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'rssmi_feed_item' AND DATEDIFF(NOW(), `post_date`) > " . $expiration;


		$ids = $wpdb->get_results( $query );
//	var_dump($ids);
//	exit;


		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				wp_delete_post( $id->ID, true );
			}

		}
	}
}


function rssmi_delete_all_custom_posts() { // DELETE ALL FEED ITEMS

	global $wpdb;


	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'rssmi_feed_item'";


	$ids = $wpdb->get_results( $query );
	if ( ! empty( $ids ) ) {
		foreach ( $ids as $id ) {
			wp_delete_post( $id->ID, true );
		}

	}

}


function rssmi_delete_autoposts() { //  USE FOR QUICK DELETE OF BLOG POSTS ONLY

	global $wpdb;

	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type != 'rssmi_feed'";

	$ids = $wpdb->get_results( $query );

	foreach ( $ids as $id ) {

		$mypostids = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE  meta_key = 'rssmi_source_link' AND post_id = " . $id->ID );

		if ( ! empty( $mypostids ) ) {
			rssmi_delete_attachment( $id->ID );
			wp_delete_post( $id->ID, true );
		}
	}

}


add_action( 'delete_post', 'rssmi_on_delete' );

function rssmi_on_delete( $post_id ) {
	$post = get_post( $post_id );
	if ( $post->post_type == 'rssmi_feed' ) {
		rssmi_delete_all_for_feed( $post_id );
		rssmi_delete_all_posts_for_feed( $post_id );
	}
}


function rssmi_delete_all_for_feed( $id ) { //  THIS DELETES FEED ITEMS FOR A SPECIFIC FEED ID

	global $wpdb;

	$myquery = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rssmi_item_feed_id' AND meta_value = " . $id;
//	echo $myquery;

	$myids = $wpdb->get_results( $myquery );

	if ( ! empty( $myids ) ) {

		foreach ( $myids as $id ) {
			delete_post_meta( $id->post_id, "rssmi_item_date" );
			delete_post_meta( $id->post_id, "rssmi_item_description" );
			delete_post_meta( $id->post_id, "rssmi_item_feed_id" );
			delete_post_meta( $id->post_id, "rssmi_item_permalink" );
			rssmi_delete_attachment( $id->post_id );
			wp_delete_post( $id->post_id, true );
		}

	}

}


function rssmi_delete_all_posts_for_feed( $id ) { //  THIS DELETES AUTOPOSTS FOR A SPECIFIC FEED ID

	global $wpdb;

	$query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'rssmi_source_feed' and meta_value = " . $id;

	$mypostids = $wpdb->get_results( $query );

	if ( ! empty( $mypostids ) ) {
		foreach ( $mypostids as $mypostid ) {
			rssmi_delete_attachment( $mypostid->post_id );
			wp_delete_post( $mypostid->post_id, true );
		}
	}

}


function rssmi_delete_posts_admin() { //  USE FOR QUICK DELETE OF ALL AUTOPOSTS

	global $wpdb;
	$query = "SELECT * FROM $wpdb->postmeta WHERE meta_key = 'rssmi_source_link'";
	$ids   = $wpdb->get_results( $query );

	if ( ! empty( $ids ) ) {
		foreach ( $ids as $id ) {
			rssmi_delete_attachment( $id->ID );
			wp_delete_post( $id->ID, true );
		}
	}


}


function rssmi_delete_posts_admin_attachment( $postid ) { //  USE FOR  DELETE OF MANUALLY DELETED AUTOPOST ATTACHMENT

	$ids = get_post_meta( $postid, 'rssmi_source_link', true );
	if ( ! empty( $ids ) ) {
		rssmi_delete_attachment( $postid );
	}
}


function rssmi_restore_all() { //  DELETES EVERYTHING CAUSED BY THIS PLUGIN IN THE POST AND POST META TABLES
	global $wpdb;
	rssmi_delete_posts_admin();

	$query = "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND (post_type = 'rssmi_feed_item' OR post_type = 'rssmi_feed')";

	$ids = $wpdb->get_results( $query );

	if ( ! empty( $ids ) ) {
		foreach ( $ids as $id ) {
			rssmi_delete_attachment( $id->ID );
			wp_delete_post( $id->ID, true );
		}

	}

}


function rssmi_delete_widow_links() {
	global $wpdb;

	$query = 'SELECT post_id from $wpdb->postmeta where (meta_key="rssmi_item_permalink" OR meta_key="rssmi_source_link")';

	$ids = $wpdb->get_results( $query );

	foreach ( $ids as $id ) {

		$mypostids = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE ID = " . $id->ID );

		if ( empty( $mypostids ) ) {
			delete_post_meta( $id->ID, "rssmi_item_date" );
			delete_post_meta( $id->ID, "rssmi_item_description" );
			delete_post_meta( $id->ID, "rssmi_item_feed_id" );
			delete_post_meta( $id->ID, "rssmi_item_permalink" );
			delete_post_meta( $id->ID, "rssmi_source_link" );
			delete_post_meta( $id->ID, "rssmi_source_feed" );

		}
	}

}

/*
 * DEPRECATED
 */

// TODO: Deprecate me, these transients are not being set
function delete_db_transients() {

	global $wpdb;
	$expired = $wpdb->get_col( "
		SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_wprssmi_%';
	" );

	foreach ( $expired as $transient ) {
		$key = str_replace( '_transient_', '', $transient );
		delete_transient( $key );
	}
}

// TODO: Deprecate me, unused
function rssmi_list_the_plugins() {
	$plugins = get_option( 'active_plugins', array() );
	foreach ( $plugins as $plugin ) {
		echo "<li>$plugin</li>";
	}
}

// TODO: Deprecate me, unused
function rssmi_list_options() {

	$options = get_option( 'rss_import_options' );

	foreach ( $options as $option ) {
		echo "<li>$option</li>";
	}

}

// TODO: Deprecate me, changed rssmi_delete_attachment() to process in-line
function rssmi_change_post_status( $post_id, $status ) {
	$current_post                = get_post( $post_id, 'ARRAY_A' );
	$current_post['post_status'] = $status;
	wp_update_post( $current_post );
}