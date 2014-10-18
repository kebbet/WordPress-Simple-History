<?php

/*
Dropin Name: Global RSS Feed
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

/**
 * Simple History RSS Feed drop-in
 */
class SimpleHistoryRSSDropin {

	function __construct($sh) {
		
		$this->sh = $sh;

		if ( ! function_exists('get_editable_roles') ) {
			require_once( ABSPATH . '/wp-admin/includes/user.php' );
		}

		add_action( 'init', array($this, 'check_for_rss_feed_request') );

		// Add settings with prio 11 so it' added after the main Simple History settings
		add_action( 'admin_menu', array($this, 'add_settings'), 11 );

	}

	/**
	 * Add settings for the RSS feed 
	 * + also regenerates the secret if requested
	 */
	public function add_settings() {

		/**
		 * Start new section for RSS feed
		 */
		$settings_section_rss_id = "simple_history_settings_section_rss";

		add_settings_section(
			$settings_section_rss_id, 
			_x("RSS feed", "rss settings headline", "simple-history"), // No title __("General", "simple-history"), 
			array($this, "settings_section_output"), 
			SimpleHistory::SETTINGS_MENU_SLUG // same slug as for options menu page
		);

		// RSS address
		add_settings_field(
			"simple_history_rss_feed", 
			__("Address", "simple-history"),
			array($this, "settings_field_rss"),
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_rss_id
		);

		// Regnerate address
		add_settings_field(
			"simple_history_rss_feed_regenerate_secret", 
			__("Regenerate", "simple-history"),
			array($this, "settings_field_rss_regenerate"),
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_rss_id
		);

		// Create new RSS secret
		$create_new_secret = false;
		$create_secret_nonce_name = "simple_history_rss_secret_regenerate_nonce";
		
	    if ( isset( $_GET[$create_secret_nonce_name] ) && wp_verify_nonce( $_GET[$create_secret_nonce_name], 'simple_history_rss_update_secret')) {

			$create_new_secret = true;
			$this->update_rss_secret();

			// Add updated-message and store in transient and then redirect
			// This is the way options.php does it.
			$msg = __("Created new secret RSS address", 'simple-history');
			add_settings_error( "simple_history_rss_feed_regenerate_secret", "simple_history_rss_feed_regenerate_secret", $msg, "updated" );
			set_transient('settings_errors', get_settings_errors(), 30);

			$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
			wp_redirect( $goback );
			exit;

		}

	} // settings

	/**
	 * Check if current request is a request for the RSS feed
	 */
	function check_for_rss_feed_request() {
		
		// check for RSS
		// don't know if this is the right way to do this, but it seems to work!
		if ( isset( $_GET["simple_history_get_rss"] ) ) {

			$this->output_rss();
			exit;

		}
		
	}

	/**
	 * Modify capability check so all users reading rss feed (logged in or not) can read all loggers
	 */
	public function on_can_read_single_logger( $user_can_read_logger, $logger_instance, $user_id ) {

		$user_can_read_logger = true;

		return $user_can_read_logger;

	}

	/**
	 * Output RSS
	 */
	function output_rss() {

			$rss_secret_option = get_option("simple_history_rss_secret");
			$rss_secret_get = isset( $_GET["rss_secret"] ) ? $_GET["rss_secret"] : "";

			if ( empty( $rss_secret_option ) || empty( $rss_secret_get ) ) {
				die();
			}

			$rss_show = true;
			$rss_show = apply_filters("simple_history/rss_feed_show", $rss_show);
			if( ! $rss_show ) {
				wp_die( 'Nothing here.' );
			}

			header ("Content-Type:text/xml");
			echo '<?xml version="1.0" encoding="UTF-8"?>';
			$self_link = $this->get_rss_address();
	
			if ( $rss_secret_option === $rss_secret_get ) {
				
				?>
				<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
					<channel>
						<title><?php printf(__("History for %s", 'simple-history'), get_bloginfo("name")) ?></title>
						<description><?php printf(__("WordPress History for %s", 'simple-history'), get_bloginfo("name")) ?></description>
						<link><?php echo get_bloginfo("url") ?></link>
						<atom:link href="<?php echo $self_link; ?>" rel="self" type="application/atom+xml" />
						<?php

						// Override capability check: if you have a valid rss_secret_key you can read it all
						$action_tag = "simple_history/loggers_user_can_read/can_read_single_logger";
						add_action( $action_tag, array($this, "on_can_read_single_logger") );					

						// Modify header time output so it does not show relative date or time ago-format
						// Because we don't know when a user reads the RSS feed, time ago format may be very inaccurate
						add_action("simple_history/header_just_now_max_time", "__return_zero");
						add_action("simple_history/header_time_ago_max_time", "__return_zero");

						// Get log rows
						$args = array(
							"posts_per_page" => 10
						);

						$args = apply_filters("simple_history/rss_feed_args", $args);
						
						$logQuery = new SimpleHistoryLogQuery();
						$queryResults = $logQuery->query($args);

						// Remove capability override after query is done
						// remove_action( $action_tag, array($this, "on_can_read_single_logger") );

						foreach ($queryResults["log_rows"] as $row) {
							
							$header_output = $this->sh->getLogRowHeaderOutput( $row );
							$text_output = $this->sh->getLogRowPlainTextOutput( $row );
							$details_output = $this->sh->getLogRowDetailsOutput( $row );
							$item_guid = home_url() . "?SimpleHistoryGuid=" . $row->id;

							$item_title = wp_kses( $header_output . ": " . $text_output, array() );
							
							?>
							<item>
								<title><![CDATA[<?php echo $item_title; ?>]]></title>
								<description><![CDATA[
									<p><?php echo $header_output ?></p>
									<p><?php echo $text_output ?></p>
									<div><?php echo $details_output ?></div>
									<?php
									$occasions = $row->subsequentOccasions - 1;
									if ( $occasions ) {
										printf( _n('+%1$s occasion', '+%1$s occasions', "simple-history"), $occasions );
									}
									?>
								]]></description>
								<author><?php echo $row->initiator ?></author>
								<pubDate><?php echo date("D, d M Y H:i:s", strtotime($row->date)) ?> GMT</pubDate>
								<guid isPermaLink="false"><?php echo $item_guid ?></guid>
								<link><?php echo $item_guid ?></link>
							</item>
							<?php
							/*
				            [0] =&gt; stdClass Object
				                (
				                    [id] =&gt; 27324
				                    [logger] =&gt; SimplePluginLogger
				                    [level] =&gt; info
				                    [date] =&gt; 2014-10-15 06:50:01
				                    [message] =&gt; Updated plugin &quot;{plugin_name}&quot; from {plugin_prev_version} to {plugin_version}
				                    [type] =&gt; 
				                    [initiator] =&gt; wp_user
				                    [occasionsID] =&gt; 75e8aeab3e43b37f8a458f3744c4995f
				                    [subsequentOccasions] =&gt; 1
				                    [rep] =&gt; 1
				                    [repeated] =&gt; 1
				                    [occasionsIDType] =&gt; 75e8aeab3e43b37f8a458f3744c4995f
				                    [context] =&gt; Array
				                        (
				                            [plugin_slug] =&gt; google-analytics-for-wordpress
				                            [plugin_name] =&gt; Google Analytics by Yoast
				                            [plugin_title] =&gt; &lt;a href=&quot;https://yoast.com/wordpress/plugins/google-analytics/#utm_source=wordpress&amp;#038;utm_medium=plugin&amp;#038;utm_campaign=wpgaplugin&amp;#038;utm_content=v504&quot;&gt;Google Analytics by Yoast&lt;/a&gt;
				                            [plugin_description] =&gt; This plugin makes it simple to add Google Analytics to your WordPress blog, adding lots of features, eg. error page, search result and automatic clickout and download tracking. &lt;cite&gt;By &lt;a href=&quot;https://yoast.com/&quot;&gt;Team Yoast&lt;/a&gt;.&lt;/cite&gt;
				                            [plugin_author] =&gt; &lt;a href=&quot;https://yoast.com/&quot;&gt;Team Yoast&lt;/a&gt;
				                            [plugin_version] =&gt; 5.0.7
				                            [plugin_url] =&gt; https://yoast.com/wordpress/plugins/google-analytics/#utm_source=wordpress&amp;#038;utm_medium=plugin&amp;#038;utm_campaign=wpgaplugin&amp;#038;utm_content=v504
				                            [plugin_update_info_plugin] =&gt; google-analytics-for-wordpress/googleanalytics.php
				                            [plugin_update_info_package] =&gt; https://downloads.wordpress.org/plugin/google-analytics-for-wordpress.5.0.7.zip
				                            [plugin_prev_version] =&gt; 5.0.6
				                            [_message_key] =&gt; plugin_bulk_updated
				                            [_user_id] =&gt; 1
				                            [_user_login] =&gt; admin
				                            [_user_email] =&gt; par.thernstrom@gmail.com
				                            [_server_remote_addr] =&gt; ::1
				                            [_server_http_referer] =&gt; http://playground-root.ep/wp-admin/update-core.php?action=do-plugin-upgrade
				                        )

				                )
							*/

						}

						/*
						$arr_items = simple_history_get_items_array($args);
						foreach ($arr_items as $one_item) {
							$object_type = ucwords($one_item->object_type);
							$object_name = esc_html($one_item->object_name);
							$user = get_user_by("id", $one_item->user_id);
							$user_nicename = esc_html(@$user->user_nicename);
							$user_email = esc_html(@$user->user_email);
							$description = "";
							if ($user_nicename) {
								$description .= sprintf(__("By %s", 'simple-history'), $user_nicename);
								$description .= "<br />";
							}
							if ($one_item->occasions) {
								$description .= sprintf(__("%d occasions", 'simple-history'), sizeof($one_item->occasions));
								$description .= "<br />";
							}
							$description = apply_filters("simple_history_rss_item_description", $description, $one_item);
	
							$item_title = esc_html($object_type) . " \"" . esc_html($object_name) . "\" {$one_item->action}";
							$item_title = html_entity_decode($item_title, ENT_COMPAT, "UTF-8");
							$item_title = apply_filters("simple_history_rss_item_title", $item_title, $one_item);

							$item_guid = home_url() . "?SimpleHistoryGuid=" . $one_item->id;

							?>
							  <item>
								 <title><![CDATA[<?php echo $item_title; ?>]]></title>
								 <description><![CDATA[<?php echo $description ?>]]></description>
								 <author><?php echo $user_email . ' (' . $user_nicename . ')' ?></author>
								 <pubDate><?php echo date("D, d M Y H:i:s", strtotime($one_item->date)) ?> GMT</pubDate>
								 <guid isPermaLink="false"><?php echo $item_guid ?></guid>
								 <link><?php echo $item_guid ?></link>
							  </item>
							<?php
						}
						*/
						?>
					</channel>
				</rss>
				<?php
			} else {

				// RSS secret was not ok
				?>
				<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
					<channel>
						<title><?php printf(__("History for %s", 'simple-history'), get_bloginfo("name")) ?></title>
						<description><?php printf(__("WordPress History for %s", 'simple-history'), get_bloginfo("name")) ?></description>
						<link><?php echo home_url() ?></link>
						<item>
							<title><?php _e("Wrong RSS secret", 'simple-history')?></title>
							<description><?php _e("Your RSS secret for Simple History RSS feed is wrong. Please see WordPress settings for current link to the RSS feed.", 'simple-history')?></description>
							<pubDate><?php echo date("D, d M Y H:i:s", time()) ?> GMT</pubDate>
							<guid><?php echo home_url() . "?SimpleHistoryGuid=wrong-secret" ?></guid>
						</item>
					</channel>
				</rss>
				<?php
	
			}

	} // rss


	/**
	 * Create a new RSS secret
	 *
	 * @return string new secret
	 */
	function update_rss_secret() {
		
		$rss_secret = "";
		
		for ($i=0; $i<20; $i++) {
			$rss_secret .= chr(rand(97,122));
		}

		update_option("simple_history_rss_secret", $rss_secret);

		return $rss_secret;
	}

	/**
	 * Output for settings field that show current RSS address
	 */
	function settings_field_rss() {

		$rss_address = $this->get_rss_address();

		echo "<p><code><a href='$rss_address'>$rss_address</a></code></p>";

	}

	/**
	 * Output for settings field that regenerates the RSS adress/secret
	 */
	function settings_field_rss_regenerate() {
			
		$update_link = add_query_arg("", "");
		$update_link = wp_nonce_url( $update_link, "simple_history_rss_update_secret", "simple_history_rss_secret_regenerate_nonce" );

		echo "<p>";
		_e("You can generate a new address for the RSS feed. This is useful if you think that the address has fallen into the wrong hands.", 'simple-history');
		echo "</p>";
		echo "<p>";
		printf( __('<a class="button" href="%s">Generate new address</a>'), $update_link );
		echo "</p>";

	}


	/**
	 * Get the URL to the RSS feed
	 * @return string URL
	 */
	function get_rss_address() {
		
		$rss_secret = get_option("simple_history_rss_secret");
		$rss_address = add_query_arg(array("simple_history_get_rss" => "1", "rss_secret" => $rss_secret), get_bloginfo("url") . "/");
		$rss_address = htmlspecialchars($rss_address, ENT_COMPAT, "UTF-8");

		return $rss_address;

	}

	/**
	 * Content for section intro. Leave it be, even if empty.
	 * Called from add_sections_setting.
	 */
	function settings_section_output() {

		echo "<p>";
		_e("Simple History has a RSS feed which you can subscribe to and receive log updates. Make sure you only share the feed with people you trust, since it can contain sensitive or confidential information.", 'simple-history');
		echo "</p>";

	}

} // end rss class
