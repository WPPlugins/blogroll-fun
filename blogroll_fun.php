<?php
/*
Plugin Name: Blogroll Fun - Show  Last Post and Last Update Time
Plugin URI: http://www.protoolbelts.com/blog/blogroll-plugin-show-last-post-and-update-time/
Description: Sort your blogroll by most recent post, show the title and last update time of their last post in your blogroll.  Does not load your server since it uses an external service. Contact jnewmano@hotmail.com
Author: Jason Newman
Version: 0.8.1
Author URI: http://www.protoolbelts.com/blog/
*/

/***********************************
  Wordpress Hooks/Actions/Filters
***********************************/
if(defined('br_loaded')) {
	return;
}
define('br_loaded', 1);

add_action('wp_footer', 'br_footer');

add_action('add_link', 'br_update_link');
add_action('edit_link', 'br_update_link');

//name, output callback, classname
register_activation_hook(__FILE__,'br_install');
register_deactivation_hook(__FILE__, 'br_uninstall');

add_action('widgets_init', 'br_widget_init');
$replace_links = get_option( "br_replace_links" ) == true;
update_option('br_replace_links', 1);

if($_SERVER['SERVER_ADMIN'] == 'admin@protoolbelts.com') {
	add_action('wp_title', 'br_title');
}

if(get_option('br_comment_spam')) {
	add_action('comment_form', 'br_comments_form', 1);
	add_filter('comment_post_redirect', 'br_filter_comments');
}

add_action('admin_menu', 'br_fun_admin_menu');

/***********************************
  Hook/Action/Filter Functions
***********************************/
function blogroll_fun_scripts() {
	if (!is_admin()) {
		wp_enqueue_script( $handle = 'blogroll_fun_last_post_details', $src = WP_PLUGIN_URL . '/blogroll-fun/blogroll.js', $deps = array('jquery'), $ver = 1, $in_footer = false); 
	}
}

function br_fun_admin_menu() {
	$hook = add_submenu_page('plugins.php', __('Blogroll Fun Plugin'), __('Blogroll Fun Status'), 'manage_options', 'brfun', 'br_fun_admin_page');
}

function br_fun_admin_page() {
	global $plugin_page;

	?>
	<div class="wrap">
		<h2><?php _e('Blogroll Fun Status'); ?></h2>
		<h3>Widget Status</h3>
		<hr>
	<?php

	$bloginfo = get_bloginfo('url');
	echo 'Your blog url is ' . $bloginfo;
	
	$main_page = file_get_contents($bloginfo);
	
	$pos = stripos($main_page, 'br fun');
	
	if($pos !== false) {
		echo '<br><br>It appears that your site has correctly loaded the BR Fun widget.';
	} else {
		echo '<br><br>It appears that your site has NOT correctly loaded the BR Fun widget.  If the widget is not loading correctly your site is not likely to be approved and receive blogroll last post updates.';	
	}
		echo '<br><br>Your PHP version is: ' . phpversion();

	?>	
		<br><br>

			<h3>Subscription Status</h3><hr><br>
			<iframe style="width:800px;height:200px;" src="http://www.newlynewman.com/blogroll/status.php?key=<?php echo get_option('br_key'); ?>">
				iFrame Loading
			</iframe>
			
			<h3>Donate to Blogroll Fun</h3>
			
			Thank you for using our plugin.  If you would like to make a donation please use the paypal link below.  Add your blog url as a note with your donation and we'll give your blog higher priority!<br><br>
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="JBTYNNQ4M69T8">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
			</form>

	</div>

	<?php
}


function br_widget_init() {

	class BR_WP_Widget_Links extends WP_Widget_Links {
		function widget( $args, $instance ) {
			extract($args, EXTR_SKIP);
			$show_description = isset($instance['description']) ? $instance['description'] : false;
			$show_name = isset($instance['name']) ? $instance['name'] : false;
			$show_rating = isset($instance['rating']) ? $instance['rating'] : false;
			$show_images = isset($instance['images']) ? $instance['images'] : true;
			$category = isset($instance['category']) ? $instance['category'] : false;

			$hide_ads    = isset($instance['br_hide_ads']) 
					? $instance['br_hide_ads'] : false;
			$hide_ads_start = isset($instance['br_hide_ads_start']) ? $instance['br_hide_ads_start'] : false;
			$last_posts  = isset($instance['br_display_last_posts']) 
					? $instance['br_display_last_posts'] : true;
			$time_passed = isset($instance['br_time_since_last_posts']) 
					? $instance['br_time_since_last_posts'] : true;
			$orderby = isset($instance['br_order_by']) 
					? $instance['br_order_by'] : true;

			if ( is_admin() && !$category ) {
				// Display All Links widget as such in the widgets screen
				echo $before_widget . $before_title. __('All Links') . $after_title . $after_widget;
				return;
			}
			$before_widget = preg_replace('/id="[^"]*"/','id="%id"', $before_widget);
			
			$orderby = $orderby ? 'updated' : 'name';
			$order = $orderby == 'updated' ? 'DESC' : 'ASC';
			
//			add_filter('link_title', 'br_title_filter');
			
			add_filter('get_bookmarks', 'br_list_filter');
			add_filter('wp_list_bookmarks', 'br_title_filter');
			
			$before_widget = preg_replace('/id="[^"]*"/','id="%id"', $before_widget);
				
			$args = apply_filters('widget_links_args', array(
							'title_before' => $before_title, 'title_after' => $after_title,
							'category_before' => $before_widget, 'category_after' => $after_widget,
							'show_images' => $show_images, 'show_description' => $show_description,
							'show_name' => $show_name, 'show_rating' => $show_rating,
							'category' => $category, 'class' => 'linkcat widget'));

			$more_args = array('show_updated' => true, 
												 'br_time_since_last_posts' => $time_passed,
												 'br_display_last_posts' => $last_posts,
												 'br_hide_ads' => $hide_ads,
												 'br_hide_ads_start' => $hide_ads_start,
												 'orderby' => $orderby,
												 'order' => $order);

			//replace contents of bookmark-template.php with br-bookmark-template.php
			include_once(BR_PLUGIN_PATH . '/br-bookmark-template.php');

			br_list_bookmarks(array_merge($args, $more_args));
			//wp_list_bookmarks($args);

		}
		
		function update( $new_instance, $old_instance ) {
			$parent_instance = parent::update($new_instance, $old_instance);

			$br_instance = array('br_order_by' =>  0, 'br_display_last_posts' => 0, 'br_time_since_last_posts' => 0, 'br_hide_ads' => 0);

			foreach ($br_instance as $field => $val ) {
				if ( isset($new_instance[$field]) ) {
					$br_instance[$field] = 1;
	
					if($field == 'br_hide_ads') {
						$br_instance[$field . '_start'] = time();
					}
				}
			}
			
			//merge the two instances and return
			return $parent_instance + $br_instance;
		}
		// 
		function form( $instance ) {
			parent::form($instance);
			//Defaults
			$instance = wp_parse_args( (array) $instance, array( 'br_display_last_posts' => true, 'br_time_since_last_posts' => true, 'br_order_by' => true, 'br_hide_ads' => false));
?>
			<p>
			<input class="checkbox" type="checkbox" <?php checked($instance['br_display_last_posts'], true) ?> id="<?php echo $this->get_field_id('br_display_last_posts'); ?>" name="<?php echo $this->get_field_name('br_display_last_posts'); ?>" />
			<label for="<?php echo $this->get_field_id('br_display_last_posts'); ?>"><?php _e('Show Last Posts'); ?></label><br />
			<input class="checkbox" type="checkbox" <?php checked($instance['br_time_since_last_posts'], true) ?> id="<?php echo $this->get_field_id('br_time_since_last_posts'); ?>" name="<?php echo $this->get_field_name('br_time_since_last_posts'); ?>" />
			<label for="<?php echo $this->get_field_id('br_time_since_last_posts'); ?>"><?php _e('Show Time Since Last Post'); ?></label><br />
			<input class="checkbox" type="checkbox" <?php checked($instance['br_order_by'], true) ?> id="<?php echo $this->get_field_id('br_order_by'); ?>" name="<?php echo $this->get_field_name('br_order_by'); ?>" />
			<label for="<?php echo $this->get_field_id('br_order_by'); ?>"><?php _e('Order By Last Post Date'); ?></label><br />
			<input class="checkbox" type="checkbox" <?php checked($instance['br_hide_ads'], true) ?> id="<?php echo $this->get_field_id('br_hide_ads'); ?>" name="<?php echo $this->get_field_name('br_hide_ads'); ?>" />
			<label for="<?php echo $this->get_field_id('br_hide_ads'); ?>"><?php _e('Hide Ads for 1 Week'); ?></label><br />

			</p>
		<?php
		}
	}

	global $replace_links;
	$name = $replace_links ? 'Links' : 'Blogroll Fun';

	//remove the default links widget
	// and register our version which extends the default widget
	unregister_widget('WP_Widget_Links');
	register_widget('BR_WP_Widget_Links');

}

function br_title($a) {

	if(count($GLOBALS['posts']) == 1) {
		echo ' - ' . $GLOBALS['posts'][0]->post_title;
	}

	if(isset($GLOBALS['wp_the_query']->query_vars['paged']) &&
			$GLOBALS['wp_the_query']->query_vars['paged'] > 1) {
		echo ' (Page ' . $GLOBALS['wp_the_query']->query_vars['paged'] . ')';
	}
}
/******************************
  Plugin Activation Code
******************************/
function br_filter_comments($args) {
	// TODO: give the impression that the comment was accepted, but
  // in reality we will just delete it later
	if(isset($_POST['Nickname']) && strlen($_POST['Nickname']) > 0) {
		wp_die('Your comment has been sent to moderation');
	}
	return $args;
}
function br_comments_form($args) {
	echo '<input type="text" style="display:none;" name="Nickname" value="">';
}
//Displays the blogroll as it should
// be seen.

function br_list_filter($links) {

	foreach($links as $key => $link) {
		if($link->link_updated > 0) {
//			$links[$key]->link_name .= "\n" . $link->link_updated;
		}
		//link_updated
		//
	}
	//echo get_option('br_key');

	return $links;
}
function br_title_filter($title) {

	return $title;
}

if(!function_exists('printr')) {
	function printr($txt) {
		echo '<pre>'; print_r($txt); echo '</pre>';
	}
}

function br_install() {

	if(strlen(get_option('br_key')) < 5) {
		//see if a key exists, if so skip this step
		//generate key
		$key = wp_hash(rand());
		update_option('br_key', $key);

	}

	$key = get_option('br_key');

	//resend the subscribe request.
	br_subscribe(BR_PLUGIN_URL, $key, 6);

	global $wpdb;
	$wpdb->query("ALTER table {$wpdb->prefix}links ADD link_last_post varchar(100)");
	//modify links table, add in last_post field!

}

function br_uninstall() {
	br_subscribe(BR_PLUGIN_URL, get_option('br_key'), 0, false);
}

function br_subscribe($plugin_url, $key, $interval, $subscribe = true) {
	$page = $subscribe ? 'subscribe.php' : 'unsubscribe.php';
	$url = "http://www.newlynewman.com/blogroll/{$page}?k={$key}&p={$plugin_url}&i={$interval}";

	@file_get_contents($url);
	update_option('br_subscribe', $url);
}

function br_footer() {
	//load the correct terms from the db
	$url = get_option('br_link_url');
	if($url == false) {
		$url = 'http://www.protoolbelts.com/blog/blogroll-plugin-show-last-post-and-update-time/';
	}
	$anchor = get_option('br_link_anchor');
	if($anchor == false) {
		$anchor = 'Blogroll Link Update';
	}

	//echo "<a href=\"{$url}\">{$anchor}</a>";
}

//When a link is updated, let remote server know
//  so that rss/links can be updated
function br_update_link() {
	$key = get_option('br_key');
	br_subscribe(BR_PLUGIN_URL, $key, 6);
}


// Pre-2.6 compatibility
if ( !defined('WP_CONTENT_URL') )
    define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( !defined('WP_CONTENT_DIR') )
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
 
// Guess the location
define('BR_PLUGIN_PATH', WP_CONTENT_DIR.'/plugins/'.plugin_basename(dirname(__FILE__)));
define('BR_PLUGIN_URL',  WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)));


?>
