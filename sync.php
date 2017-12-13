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

// Add Sync Sidebox.
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

// Sync Function.
if ( !function_exists('wp2wb_sync_publish') ) {
    function wp2wb_sync_publish($post_ID) {
        global $post;
        if (!wp_is_post_revision($post_ID) && $post->post_status != 'publish'){
            if (isset($post) && $post->post_type != 'post' || isset($_POST['publish_no_sync'])) return;
            $access_token = get_option('wp2wb_access_token');
            $headers = array();
            $headers[] = "Authorization: OAuth2 ".$access_token;
            $post_title = get_the_title($post_ID);
            $post_url = get_permalink($post_ID);
            $content = $post -> post_content;
            $excerpt = $post -> post_excerpt;
            $pic_src = wp2wb_post_img_src($post_ID);

            if ( get_option('wp2wb_weibo_type') == 'simple' ) {

                $apiurl = 'https://api.weibo.com/2/statuses/share.json';
                $status = sprintf( __( 'I just published a new article:  %1$s, click here for details: %2$s.', 'wp2wb' ), $post_title, $post_url );

                if( !empty($pic_src) ) {
                    $pic_file = str_replace(home_url(),$_SERVER["DOCUMENT_ROOT"],$pic_src);
                    if( !empty($pic_file) ) {
                        $file_content = file_get_contents($pic_file);
                    } else {
                        $file_content = file_get_contents($pic_src);
                    }
                    $array = explode('?', basename($pic_src));
                    $file_name = $array[0];
                    $sep = uniqid('------------------');
                    $mpSep = '--'.$sep;
                    $endSep = $mpSep. '--';
                    $multibody = '';
                    $multibody .= $mpSep . "\r\n";
                    $multibody .= 'Content-Disposition: form-data; name="pic"; filename="' . $file_name . '"' . "\r\n";
                    $multibody .= "Content-Type: image/unknown\r\n\r\n";
                    $multibody .= $file_content. "\r\n";
                    $multibody .= $mpSep . "\r\n";
                    $multibody .= 'content-disposition: form-data; name="status' . "\"\r\n\r\n";
                    $multibody .= urlencode($status)."\r\n";
                    $multibody .= $endSep;
                    $headers[] = "Content-Type: multipart/form-data; boundary=" . $sep;
                    $data = $multibody;
                } else {
                    $data = "status=" . urlencode($status);
                }
            }

            if ( get_option('wp2wb_weibo_type') == 'article' ) {
                $apiurl = 'https://api.weibo.com/proxy/article/publish.json';

                if( !empty($pic_src) ) {
                    $cover = str_replace(home_url(),$_SERVER["DOCUMENT_ROOT"],$pic_src);
                } else {
                    $cover = plugins_url('cover.jpg',__FILE__);
                }

                $data = array (
                    'title'     => $post_title,
                    'content'   => urlencode($content),
                    'cover'     => $cover,
                    'text'      => $post_title,
                );
            }

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $apiurl);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            $response = curl_exec($curl);
            curl_close($curl);
            //$results = json_decode($response);
            //var_dump($results);
        }
    }
}

// Get Post Image Src.
if ( !function_exists( 'wp2wb_post_img_src' ) ) {
    function wp2wb_post_img_src($post_ID) {
        global $post;
        if ( has_post_thumbnail() ) {
            $thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID),'full');
            $post_img_src = $thumbnail_src [0];
        } else {
            $content = get_post( $post_ID )->post_content;
            $output = preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $content, $strResult, PREG_PATTERN_ORDER);
            if ( $output ) {
                $post_img_src = $strResult [1][0];
            } else {
                $post_img_src = '';
            }
        }
        return $post_img_src;
    }
}
