<?php
/**
 * Plugin Name: PhotoSwipe
 * Description: PhotoSwipe javascript plugin for WordPress
 * Version: 4.0.7.3
 * Author: Louy Alakkad
 * Author URI: http://l0uy.com/
 */
define('PHOTOSWIPE_VERSION', '4.0.7.3');

function photoswipe_enqueue() {
	if( is_singular() ) {
		wp_enqueue_script(
			'photoswipe-lib',
			plugin_dir_url( __FILE__ ) . 'lib/photoswipe.min.js',
			array(),
			PHOTOSWIPE_VERSION
		);
		wp_enqueue_script(
			'photoswipe-ui-default',
			plugin_dir_url( __FILE__ ) . 'lib/photoswipe-ui-default.min.js',
			array('photoswipe-lib'),
			PHOTOSWIPE_VERSION
		);

		wp_enqueue_script(
			'photoswipe',
			plugin_dir_url( __FILE__ ) . 'js/photoswipe.js',
			array('photoswipe-lib', 'photoswipe-ui-default', 'jquery'),
			PHOTOSWIPE_VERSION
		);

		wp_enqueue_style(
			'photoswipe-lib',
			plugin_dir_url( __FILE__ ) . 'lib/photoswipe.css',
			false,
			PHOTOSWIPE_VERSION
		);
		wp_enqueue_style(
			'photoswipe-default-skin',
			plugin_dir_url( __FILE__ ) . 'lib/default-skin/default-skin.css ',
			false,
			PHOTOSWIPE_VERSION
		);
		add_action('wp_footer', 'photoswipe_footer');
	}
}
add_action('wp_enqueue_scripts', 'photoswipe_enqueue');

function photoswipe_footer() {
	echo <<<EOF
<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="pswp__bg"></div>
    <div class="pswp__scroll-wrap">
        <div class="pswp__container">
            <div class="pswp__item"></div>
            <div class="pswp__item"></div>
            <div class="pswp__item"></div>
        </div>
        <div class="pswp__ui pswp__ui--hidden">
            <div class="pswp__top-bar">
                <div class="pswp__counter"></div>
                <button class="pswp__button pswp__button--close" title="Close (Esc)"></button>
                <button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>
                <button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>
                <div class="pswp__preloader">
                    <div class="pswp__preloader__icn">
                      <div class="pswp__preloader__cut">
                        <div class="pswp__preloader__donut"></div>
                      </div>
                    </div>
                </div>
            </div>
            <button class="pswp__button pswp__button--arrow--left" title="Previous (arrow left)">
            </button>
            <button class="pswp__button pswp__button--arrow--right" title="Next (arrow right)">
            </button>
            <div class="pswp__caption">
                <div class="pswp__caption__center"></div>
            </div>
        </div>
    </div>
</div>
EOF;

}

function photoswipe_get_attachment_link($link, $id, $size, $permalink, $icon, $text ) {
	if( $permalink === false && !$text && 'none' != $size ) {
		$_post = get_post( $id );

		$image_attributes = wp_get_attachment_image_src( $_post->ID, 'original' );

		if( $image_attributes ) {
			$link = str_replace('<a ', '<a data-size="' . $image_attributes[1] . 'x' . $image_attributes[2] . '" ', $link);
		}
	}

	return $link;
}
add_filter( 'wp_get_attachment_link', 'photoswipe_get_attachment_link', 10, 6 );

function photoswipe_save_post( $post_id, $post, $update ) {
	$post_content = $post->post_content;

	$new_content = preg_replace_callback( '/(<a((?!data\-size)[^>])+href=["\'])([^"\']*)(["\']((?!data\-size)[^>])*><img)/i', 'photoswipe_save_post_callback', $post_content );

	if( !!$new_content && $new_content !== $post_content ) {
		remove_action( 'save_post', 'photoswipe_save_post', 10, 3 );

		wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_content ) );

		add_action( 'save_post', 'photoswipe_save_post', 10, 3 );
	}
}
add_action( 'save_post', 'photoswipe_save_post', 10, 3 );

function photoswipe_save_post_callback( $matches ) {
	$before = $matches[1];
	$image_url = $matches[3];
	$after = $matches[4];

	$id = fjarrett_get_attachment_id_by_url($image_url);

	if( $id ) {
		$image_attributes = wp_get_attachment_image_src( $id, 'original' );
		if( $image_attributes ) {
			$before = str_replace('<a ', '<a data-size="' . $image_attributes[1] . 'x' . $image_attributes[2] . '" ', $before);
		}
	}

	return $before . $image_url . $after;
}

function photoswipe_kses_allow_attributes() {
	global $allowedposttags;
	$allowedposttags['a']['data-size'] = array();
}
add_action( 'init', 'photoswipe_kses_allow_attributes' );

if( !function_exists('fjarrett_get_attachment_id_by_url') ) :
/**
 * Return an ID of an attachment by searching the database with the file URL.
 *
 * First checks to see if the $url is pointing to a file that exists in
 * the wp-content directory. If so, then we search the database for a
 * partial match consisting of the remaining path AFTER the wp-content
 * directory. Finally, if a match is found the attachment ID will be
 * returned.
 *
 * @param string $url The URL of the image (ex: http://mysite.com/wp-content/uploads/2013/05/test-image.jpg)
 *
 * @return int|null $attachment Returns an attachment ID, or null if no attachment is found
 */
function fjarrett_get_attachment_id_by_url( $url ) {
	// Split the $url into two parts with the wp-content directory as the separator
	$parsed_url  = explode( parse_url( WP_CONTENT_URL, PHP_URL_PATH ), $url );

	// Get the host of the current site and the host of the $url, ignoring www
	$this_host = str_ireplace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) );
	$file_host = str_ireplace( 'www.', '', parse_url( $url, PHP_URL_HOST ) );

	// Return nothing if there aren't any $url parts or if the current host and $url host do not match
	if ( ! isset( $parsed_url[1] ) || empty( $parsed_url[1] ) || ( $this_host != $file_host ) ) {
		return;
	}

	// Now we're going to quickly search the DB for any attachment GUID with a partial path match
	// Example: /uploads/2013/05/test-image.jpg
	global $wpdb;
	$prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;

	$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$prefix}posts WHERE guid RLIKE %s;", $parsed_url[1] ) );

	// Returns null if no attachment is found
	return $attachment[0];
}
endif;
