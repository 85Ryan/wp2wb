<?php
/*
Plugin Name: wp2wb
Plugin URI: https://github.com/85Ryan/wp2wb/
Description: Sync WordPress Post to Sina Weibo.
Author: Ryan
Version: 1.0
Author URI: https://iiiryan.com/
*/

// Array of options and their default values.
global $wp2wb_options;
$wp2wb_options = array (
    'wp2wb_app_key'             => '',
    'wp2wb_app_secret'          => '',
    'wp2wb_access_token'        => '',
    'wp2wb_expires_in'          => '',
    'wp2wb_create_at'           => '',
    'wp2wb_sync'                => 'disable',
    'wp2wb_content'             => 'false',
);

include_once(dirname(__FILE__) . '/sync.php');

// Register Activation Hook.
if ( !function_exists('wp2wb_activation') ) :
register_activation_hook(__FILE__, 'wp2wb_activation');
function wp2wb_activation() {
    global $wp2wb_options;
    foreach ( $wp2wb_options as $name => $val ) {
        add_option( $name, $val );
    }
}
endif;

// Register Deactivation Hook.
if ( !function_exists('wp2wb_deactivate') ) :
register_deactivation_hook(__FILE__, 'wp2wb_deactivate');
function wp2wb_deactivate() {
    global $wp2wb_options;
    foreach ( $wp2wb_options as $name => $val ) {
        delete_option( $name, $val );
    }
}
endif;

// Add Option Menu.
if ( !function_exists('wp2wb_admin_page') ) :
add_action( 'admin_menu', 'wp2wb_admin_page' );
function wp2wb_admin_page() {
    add_options_page(
        __( 'Sync to Weibo', 'wp2wb' ),
        __( 'Sync to Weibo', 'wp2wb' ),
        'manage_options',
        'wp2wb-options',
        'wp2wb_option_page'
    );
}
endif;

// Add setting links.
if ( !function_exists('wp2wb_action_links') ) :
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wp2wb_action_links' );
function wp2wb_action_links ( $links ) {

    $setting_links = array (
        'settings' => '<a href="' . admin_url( 'options-general.php?page=wp2wb-options').'">'.__( 'Settings' ).'</a>'
    );

    return array_merge( $setting_links, $links);
}
endif;

// Update Options.
if ( !function_exists('wp2wb_options_update') ) :
function wp2wb_options_update() {

    $updated = '<div class="updated settings-error notice is-dismissible"><p><strong>' . __('Settings saved.') . '</strong></p></div>';

    $authorized = '<div class="updated  settings-error notice is-dismissible"><p><strong>' . __('Authorized Success.', 'wp2wb') . '</strong></p></div>';

    if (isset($_GET['code'])) {
        $code = $_GET['code'];
        $output = wp2wb_get_access_token($code);

        $get_token_info_url = "https://api.weibo.com/oauth2/get_token_info?access_token=".$output['access_token'];
        $get_token_info = wp_remote_get( $get_token_info_url, array(
            'method' => 'POST',
        ) );
        $token_info = json_decode($get_token_info['body'] , true);

        update_option('wp2wb_access_token', $output['access_token']);
        update_option('wp2wb_expires_in', $output['expires_in']);
        update_option('wp2wb_create_at', $token_info['create_at']);
        echo $authorized;
    }

    if (isset($_POST['update_options'])) {
        update_option('wp2wb_app_key', $_POST['wp2wb_app_key']);
        update_option('wp2wb_app_secret', $_POST['wp2wb_app_secret']);
        update_option('wp2wb_sync', $_POST['wp2wb_sync']);

        $wp2wb_content = !empty($_POST['wp2wb_content']) ? $_POST['wp2wb_content'] : 'false';
        update_option('wp2wb_content', $wp2wb_content);

        if ( isset($_POST['wp2wb_app_key']) || isset($_POST['wp2wb_app_secret']) ) {
            update_option('wp2wb_access_token', '');
            update_option('wp2wb_expires_in', '');
            update_option('wp2wb_create_at', '');
        }

        echo $updated;
    }
}
endif;

// Weibo OAuth Url.
if ( !function_exists('wp2wb_oauth_url') ) :
function wp2wb_oauth_url(){
    $url = 'https://api.weibo.com/oauth2/authorize?client_id=' . get_option('wp2wb_app_key') . '&response_type=code&redirect_uri=' . urlencode (admin_url('options-general.php?page=wp2wb-options'));
    return $url;
}
endif;

// Get Sina Access Token.
if ( !function_exists('wp2wb_get_access_token') ) :
function wp2wb_get_access_token($code){
    $url = "https://api.weibo.com/oauth2/access_token";

    $data = array(
        'client_id' => get_option('wp2wb_app_key'),
        'client_secret' => get_option('wp2wb_app_secret'),
        'grant_type' => 'authorization_code',
        'redirect_uri' => admin_url('options-general.php?page=wp2wb-options'),
        'code' => $code,
        );

    $response = wp_remote_post( $url, array(
            'method' => 'POST',
            'body' => $data,
        )
    );

    $output = json_decode($response['body'],true);
    return $output;
}
endif;

// Define authorization time message.
if ( !function_exists('wp2wb_oauth_time') ) :
function wp2wb_oauth_time() {
    $current_time = time();
    $gmt = get_option('gmt_offset');

    if ( get_option('wp2wb_access_token') != '' ) {
        $oauth_creat_time = get_option('wp2wb_create_at');
        $oauth_expires_in = get_option('wp2wb_expires_in');
        $oauth_expires_time = $oauth_creat_time + $oauth_expires_in;

        if ( $current_time <= $oauth_expires_time ) {
            echo _e( 'Authorization will expire at: ', 'wp2wb' );
            echo date("Y-m-d H:i:s",$oauth_expires_time + $gmt*3600);
        } else {
            echo _e('Authorization has expired, please re-authorization.', 'wp2wb');
        }
    }
}
endif;

// Define Notice Messages.
if ( !function_exists( 'wp2wb_option_notice' ) ) :
function wp2wb_option_notice() {

    $open_sina = 'http://open.weibo.com';
    $oauth_url = wp2wb_oauth_url();

    if ( !get_option('wp2wb_app_key') || !get_option('wp2wb_app_secret') ) {
    ?>
        <div class="error"><p><?php printf( __( '<strong style="color:red;">STEP 1:</strong> Please enter your sina <strong>APP Key</strong> and <strong>APP Secret</strong>, then click the save button! You can go to <strong><a href="%s">Sina Open Platform</a></strong> to apply for them.', 'wp2wb' ), esc_url( $open_sina ) ); ?></p></div>
    <?php }
    else if ( !get_option('wp2wb_access_token') ) {
        ?>
        <div class="error"><p><?php printf( __( '<strong style="color:red;">STEP 2:</strong> Nice! Next step you must to <a href="%s">Authorization</a> , click the link to do it.', 'wp2wb' ), esc_url( $oauth_url ) ); ?></p></div>
    <?php }
}
endif;

// Define Option Page.
if ( !function_exists('wp2wb_option_page') ) :
function wp2wb_option_page() {
?>
    <div class="wrap">
        <h1><?php _e('Sync to Weibo Settings', 'wp2wb') ?></h1>
        <?php wp2wb_options_update(); ?>
        <?php wp2wb_option_notice(); ?>
        <form method="post" action="<?php echo admin_url( 'options-general.php?page=wp2wb-options' ); ?>">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="wp2wb_app_key"><?php _e( 'APP Key', 'wp2wb' ); ?></label></th>
                    <td><input name="wp2wb_app_key" type="text" id="wp2wb_app_key" value="<?php print( get_option( 'wp2wb_app_key' ) ); ?>" size="40" class="regular-text" /><p class="description"><?php _e( 'Please enter your sina App Key.', 'wp2wb' ); ?></p></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wp2wb_app_secret"><?php _e( 'APP Secret', 'wp2wb' ); ?></label></th>
                    <td><input name="wp2wb_app_secret" type="text" id="wp2wb_app_secret" value="<?php print( get_option( 'wp2wb_app_secret' ) ); ?>" size="40" class="regular-text" /><p class="description"><?php _e( 'Please enter your sina App Secret.', 'wp2wb' ); ?></p></td>
                </tr>
                <?php if( get_option('wp2wb_access_token') != '' ) : ?>
                <tr valign="top">
                    <th scope="row"><label for="wp2wb_access_token"><?php _e('Access Token', 'wp2wb'); ?></label></th>
                    <td><input name="wp2wb_access_token" type="text" id="wp2wb_access_token" value="<?php print(get_option('wp2wb_access_token')); ?>" size="40" class="regular-text" readonly />
                    <p class="description"><?php echo wp2wb_oauth_time(); ?></p></td>
                </tr>
                <?php endif; ?>
            </table>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Sync Enable', 'wp2wb'); ?></th>
                    <td><p><input id="sync_enable" class="wp2wb_sync" type="radio" name="wp2wb_sync" value="enable" <?php checked( 'enable', get_option( 'wp2wb_sync' ) ); ?> /><label for="sync_enable"><?php _e( 'Sync Enable', 'wp2wb' ); ?></label></p>
					<p><input id="sync_disable" class="wp2wb_sync" type="radio" name="wp2wb_sync" value="disable" <?php checked( 'disable', get_option( 'wp2wb_sync' ) ); ?> /><label for="sync_disable"><?php _e( 'Sync Disable', 'wp2wb' ); ?></label></p>
                    </td>
                </tr>
            </table>
            <table id="wp2wb_enable" class="form-table wp2wb_enable">
                <tr valign="top">
                    <th scope="row"><?php _e('Weibo Content', 'wp2wb'); ?></th>
                    <td><label for="wp2wb_content"><input name="wp2wb_content" type="checkbox" id="wp2wb_content" value="true" <?php checked('true', get_option('wp2wb_content')); ?> /><?php _e(' Weibo content includes the post excerpt.'); ?></label><p class="description"><?php _e( 'Select whether the Weibo content includes the post excerpt, if not, only publish the post title.', 'wp2wb' ); ?></p></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="update_options" class="button-primary" value="<?php _e('Save Changes'); ?>" />
            </p>
        </form>
        <script type="text/javascript">
            var isChecked = function ( wp2wb ) {
				jQuery( '.wp2wb_enable' ).hide();
				jQuery( '#wp2wb_' + wp2wb ).show();
            };
            jQuery( document ).ready( function () {
				isChecked( jQuery( 'input.wp2wb_sync:checked' ).val() );
				jQuery( 'input.wp2wb_sync' ).on( 'change', function ( e ) {
				    isChecked( jQuery( e.target ).val() );
				} );
            } );
        </script>
    </div><!-- .wrap -->
<?php
}
endif;
