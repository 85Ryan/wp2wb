<?php
/**
 * wp2wb Sync functions.
 *
 * @package     wp2wb
 * @author      Ryan
 * @license     GPL 3.0
 * @version     1.0
 */

if ( get_option('wp2wb_sync') == 'enable' ) {
    add_action('admin_menu', 'wp2wb_sync_add_sidebox');
}

if ( !function_exists( 'wp2wb_sync_sidebox' ) ) {
    function wp2wb_sync_sidebox() {
        global $post;
        echo '<p><label for="publish_no_sync"><input id="publish_no_sync" type="checkbox" name="publish_no_sync" value="true" />'.__( ' Don&#039;t Sync Post', 'wp2wb' ).'</label></p>';
    }
}

if ( !function_exists( 'wp2wb_sync_add_sidebox' ) ) {
    function wp2wb_sync_add_sidebox() {
        add_meta_box('wp2wb_sync_sidebox', __( 'Sync Setting', 'wp2wb' ), 'wp2wb_sync_sidebox', 'post', 'side', 'high');
    }
}

add_action('publish_post', 'wp2wb_sync_publish', 1);
function wp2wb_sync_publish($post_ID, $debug = true) {
    global $post;
    $access_token = get_option( '$wp2wb_access_token' );
    if($debug){
        var_dump($access_token);
    }
    print_r($access_token);
}


























