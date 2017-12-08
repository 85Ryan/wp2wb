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
    add_action('publish_post', 'wp2wb_sync_publish', 1);
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

if ( !function_exists( 'wp2wb_sync_publish' ) ) {
    function wp2wb_sync_publish($post_ID, $debug = true) {
        global $post;
        if (!wp_is_post_revision($post_ID) && $post->post_status != "publish" || $debug == true) {
            if (isset($post) && $post->post_type != "post") return;
            $access_token = get_option( 'wp2wb_access_token' );
            $headers = array();
            $headers[] = "Authorization: OAuth2 ".$access_token;
            $url = 'https://api.weibo.com/2/statuses/share.json';
            $status = "我刚刚发布了新文章《".get_the_title($post_ID)."》，快来看看吧。详细内容请点击：".get_permalink($post_ID);
            if (has_post_thumbnail()) {
                $post_thumbnail_id = get_post_thumbnail_id($post_ID);
                $img_src = wp_get_attachment_url( $post_thumbnail_id );
            } else {
                $content = get_post( $post_ID )->post_content;
                preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $content, $strResult, PREG_PATTERN_ORDER);
                $img_src = $strResult[1][0];
            }

            if ( !empty( $img_src ) ) {
                $picfile = str_replace(home_url(),$_SERVER["DOCUMENT_ROOT"],$img_src);

                if ( !empty( $picfile ) ) {
                    $filecontent = file_get_contents($picfile);
                } else {
                    $filecontent = file_get_contents($img_src);
                }

                $array = explode('?', basename($img_src));
                $filename = $array[0];
                $boundary = uniqid('------------------');
                $MPboundary = '--'.$boundary;
                $endMPboundary = $MPboundary. '--';
                $multipartbody = '';
                $multipartbody .= $MPboundary . "\r\n";
                $multipartbody .= 'Content-Disposition: form-data; name="pic"; filename="' . $filename . '"' . "\r\n";
                $multipartbody .= "Content-Type: image/unknown\r\n\r\n";
                $multipartbody .= $filecontent. "\r\n";
                $multipartbody .= $MPboundary . "\r\n";
                $multipartbody .= 'content-disposition: form-data; name="status' . "\"\r\n\r\n";
                $multipartbody .= urlencode($status)."\r\n";
                $multipartbody .= $endMPboundary;
                $headers[] = "Content-Type: multipart/form-data; boundary=" . $boundary;
                $data = $multipartbody;
            } else {
                $data = "status=" . urlencode($status);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $curlResponse = curl_exec($ch);
            curl_close($ch);
            $output = json_decode($curlResponse);

            if ( $debug ) {
                var_dump($output);
                echo '<hr />';
                var_dump($data);
            }
        }
    }
}
