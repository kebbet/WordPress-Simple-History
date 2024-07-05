<?php

namespace Simple_History\Loggers;

/**
 * Logs changes to wordpress options
 */
class Options_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimpleOptionsLogger';

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'name'        => __( 'Options Logger', 'simple-history' ),
			'description' => __( 'Logs updates to WordPress settings', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'option_updated' => __( 'Updated option "{option}"', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'   => _x( 'Options', 'Options logger: search', 'simple-history' ),
					'options' => array(
						_x( 'Changed options', 'Options logger: search', 'simple-history' ) => array(
							'option_updated',
						),
					),
				),
			),
		];
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		// When WP posts the options page it's done to options.php or options-permalink.php.
		add_action( 'load-options.php', array( $this, 'on_load_options_page' ) );
		add_action( 'load-options-permalink.php', array( $this, 'on_load_options_page' ) );
	}

	public function on_load_options_page() {
		add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );
	}

	/**
	 * Check if the option page is a built in WordPress options page.
	 *
	 * @param string $option_page Option page name.
	 * @return bool
	 */
	protected function is_wordpress_built_in_options_page( $option_page ) {
		$valid_option_pages = [
			'general',
			'discussion',
			'media',
			'reading',
			'writing',
		];

		return in_array( $option_page, $valid_option_pages );
	}

	/**
	 * Check if the form was submitted from the permalink settings page.
	 *
	 * @return bool
	 */
	protected function is_form_submitted_from_permalink_page() {
		return strpos( wp_get_referer(), 'options-permalink.php' ) !== false;
	}

	/**
	 * Check if the option name is a built in WordPress option.
	 *
	 * @param string $option_name Option name.
	 */
	protected function is_built_in_wordpress_options_name( $option_name ) {
		return in_array( $option_name, $this->get_wordpress_options_keys() );
	}

	/**
	 * When an option is updated from the options page.
	 *
	 * @param string $option Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 */
	public function on_updated_option( $option, $old_value, $new_value ) {
		sh_error_log( 'on_updated_option', 'option', $option, 'old_value', $old_value, 'new_value', $new_value, 'request', $_REQUEST );

		$option_page = sanitize_text_field( wp_unslash( $_REQUEST['option_page'] ?? '' ) ); // general | discussion | ...
		if ( ! $this->is_wordpress_built_in_options_page( $option_page ) && ! $this->is_form_submitted_from_permalink_page() ) {
			return;
		}

		if ( ! $this->is_built_in_wordpress_options_name( $option ) ) {
			return;
		}

		// If new value is null then store as empty string.
		if ( is_null( $new_value ) ) {
			$new_value = '';
		}

		$context = [
			'option' => $option,
			'old_value' => $old_value,
			'new_value' => $new_value,
			'option_page' => $option_page,
		];

		// Store a bit more about some options
		// Like "page_on_front" we also store post title
		// Check for a method for current option in this class and calls it automagically.
		$methodname = "add_context_for_option_{$option}";
		if ( method_exists( $this, $methodname ) ) {
			$context = $this->$methodname( $context, $old_value, $new_value, $option, $option_page );
		}

		$this->info_message( 'option_updated', $context );
	}

	/**
	 * Get detailed output
	 *
	 * @param object $row Log row object.
	 */
	public function get_log_row_details_output( $row ) {
		$context = $row->context;
		$message_key = $context['_message_key'];
		$output = '';

		$option = $context['option'] ?? null;
		$option_page = $context['option_page'] ?? null;
		$new_value = $context['new_value'] ?? null;
		$old_value = $context['old_value'] ?? null;

		$tmpl_row = '
			<tr>
				<td>%1$s</td>
				<td>%2$s</td>
			</tr>
		';

		if ( 'option_updated' == $message_key ) {
			// $message = 'Old value was {old_value} and new value is {new_value}';
			$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

			// Output old and new values.
			if ( $context['new_value'] || $context['old_value'] ) {
				$option_custom_output = '';
				$methodname = "get_details_output_for_option_{$option}";

				if ( method_exists( $this, $methodname ) ) {
					$option_custom_output = $this->$methodname( $context, $old_value, $new_value, $option, $option_page, $tmpl_row );
				}

				if ( empty( $option_custom_output ) ) {
					// all other options or fallback if custom output did not find all it's stuff.
					$more = __( '&hellip;', 'simple-history' );
					$trim_length = 250;

					$trimmed_new_value = substr( $new_value, 0, $trim_length );
					$trimmed_old_value = substr( $old_value, 0, $trim_length );

					if ( strlen( $new_value ) > $trim_length ) {
						$trimmed_new_value .= $more;
					}

					if ( strlen( $old_value ) > $trim_length ) {
						$trimmed_old_value .= $more;
					}

					$output .= sprintf(
						$tmpl_row,
						__( 'New value', 'simple-history' ),
						esc_html( $trimmed_new_value )
					);

					$output .= sprintf(
						$tmpl_row,
						__( 'Old value', 'simple-history' ),
						esc_html( $trimmed_old_value )
					);
				} else {
					$output .= $option_custom_output;
				}
			} // End if().

			// If key option_page this was saved from regular settings pages.
			if ( ! empty( $option_page ) ) {
				$output .= sprintf(
					'
					<tr>
						<td>%1$s</td>
						<td><a href="%3$s">%2$s</a></td>
					</tr>
					',
					__( 'Settings page', 'simple-history' ),
					esc_html( $context['option_page'] ),
					admin_url( "options-{$option_page}.php" )
				);
			}

			// If option = permalink_structure then we did it from permalink page.
			if ( ! empty( $option ) && ( 'permalink_structure' == $option || 'tag_base' == $option || 'category_base' == $option ) ) {
				$output .= sprintf(
					'
					<tr>
						<td>%1$s</td>
						<td><a href="%3$s">%2$s</a></td>
					</tr>
					',
					__( 'Settings page', 'simple-history' ),
					'permalink',
					admin_url( 'options-permalink.php' )
				);
			}

			$output .= '</table>';
		}// End if().

		return $output;
	}

	/**
	 * Page on front = "Front page displays" -> Your latest posts / A static page
	 * value 0 = Your latest post
	 * value int n = A static page
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @return array context
	 */
	protected function add_context_for_option_page_on_front( $context, $old_value, $new_value, $option, $option_page ) {
		if ( ! empty( $old_value ) && is_numeric( $old_value ) ) {
			$old_post = get_post( $old_value );

			if ( $old_post instanceof \WP_Post ) {
				$context['old_post_title'] = $old_post->post_title;
			}
		}

		if ( ! empty( $new_value ) && is_numeric( $new_value ) ) {
			$new_post = get_post( $new_value );

			if ( $new_post instanceof \WP_Post ) {
				$context['new_post_title'] = $new_post->post_title;
			}
		}

		return $context;
	}

	/**
	 * Add context for option page_on_front for posts page.
	 *
	 * @param array $context context.
	 * @param mixed $old_value old value.
	 * @param mixed $new_value new value.
	 * @param mixed $option option name.
	 * @param mixed $option_page option page name.
	 * @return array Updated context.
	 */
	protected function add_context_for_option_page_for_posts( $context, $old_value, $new_value, $option, $option_page ) {
		// Get same info as for page_on_front.
		return call_user_func_array( array( $this, 'add_context_for_option_page_on_front' ), func_get_args() );
	}

	/**
	 * Get detailed output for page_on_front for posts page.
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @return string output
	 */
	protected function get_details_output_for_option_page_for_posts( $context, $old_value, $new_value, $option, $option_page ) {
		return call_user_func_array( array( $this, 'get_details_output_for_option_page_on_front' ), func_get_args() );
	}

	/**
	 * Add detailed output for page_on_front
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @param string $tmpl_row template row.
	 * @return string output
	 */
	protected function get_details_output_for_option_page_on_front( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {
		$output = '';

		if ( $new_value && ! empty( $context['new_post_title'] ) ) {
			if ( get_post_status( $new_value ) ) {
				$post_title_with_link = sprintf( '<a href="%1$s">%2$s</a>', get_edit_post_link( $new_value ), esc_html( $context['new_post_title'] ) );
			} else {
				$post_title_with_link = esc_html( $context['new_post_title'] );
			}

			$output .= sprintf(
				$tmpl_row,
				__( 'New value', 'simple-history' ),
				sprintf(
					/* translators: %s post title with link. */
					__( 'Page %s', 'simple-history' ),
					$post_title_with_link
				)
			);
		}
		if ( (int) $new_value == 0 ) {
			$output .= sprintf(
				$tmpl_row,
				__( 'New value', 'simple-history' ),
				__( 'Your latests posts', 'simple-history' )
			);
		}

		if ( $old_value && ! empty( $context['old_post_title'] ) ) {
			if ( get_post_status( $old_value ) ) {
				$post_title_with_link = sprintf( '<a href="%1$s">%2$s</a>', get_edit_post_link( $old_value ), esc_html( $context['old_post_title'] ) );
			} else {
				$post_title_with_link = esc_html( $context['old_post_title'] );
			}

			$output .= sprintf(
				$tmpl_row,
				__( 'Old value', 'simple-history' ),
				sprintf(
					/* translators: %s post title with link. */
					__( 'Page %s', 'simple-history' ),
					$post_title_with_link
				)
			);
		}

		if ( (int) $old_value == 0 ) {
			$output .= sprintf(
				$tmpl_row,
				__( 'Old value', 'simple-history' ),
				__( 'Your latests posts', 'simple-history' )
			);
		}

		return $output;
	}

	/**
	 * "default_category" = Writing Settings » Default Post Category
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 */
	protected function add_context_for_option_default_category( $context, $old_value, $new_value, $option, $option_page ) {
		if ( ! empty( $old_value ) && is_numeric( $old_value ) ) {
			$old_category_name = get_the_category_by_ID( $old_value );

			if ( ! is_wp_error( $old_category_name ) ) {
				$context['old_category_name'] = $old_category_name;
			}
		}

		if ( ! empty( $new_value ) && is_numeric( $new_value ) ) {
			$new_category_name = get_the_category_by_ID( $new_value );

			if ( ! is_wp_error( $new_category_name ) ) {
				$context['new_category_name'] = $new_category_name;
			}
		}

		return $context;
	}

	/**
	 * Add context for option default_category for default_email_category.
	 *
	 * @param array $context context.
	 * @param mixed $old_value old value.
	 * @param mixed $new_value new value.
	 * @param mixed $option option name.
	 * @param mixed $option_page option page name.
	 * @return array Updated context.
	 */
	protected function add_context_for_option_default_email_category( $context, $old_value, $new_value, $option, $option_page ) {
		return call_user_func_array( array( $this, 'add_context_for_option_default_category' ), func_get_args() );
	}

	/**
	 * Add detailed output for default_category
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @param string $tmpl_row template row.
	 * @return string output
	 */
	protected function get_details_output_for_option_default_category( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {
		$old_category_name = $context['old_category_name'] ?? null;
		$new_category_name = $context['new_category_name'] ?? null;
		$output = '';

		if ( $old_category_name ) {
			$output .= sprintf(
				$tmpl_row,
				__( 'Old value', 'simple-history' ),
				esc_html( $old_category_name )
			);
		}

		if ( $new_category_name ) {
			$output .= sprintf(
				$tmpl_row,
				__( 'New value', 'simple-history' ),
				esc_html( $new_category_name )
			);
		}

		return $output;
	}

	/**
	 * Get detailed output for default_category for default_email_category.
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @param string $tmpl_row template row.
	 * @return string output
	 */
	protected function get_details_output_for_option_default_email_category( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {
		return call_user_func_array( array( $this, 'get_details_output_for_option_default_category' ), func_get_args() );
	}

	/**
	 * Get all keys for built in WordPress options.
	 *
	 * @return array
	 */
	protected function get_wordpress_options_keys() {
		$keys = [];

		foreach ( $this->get_wordpress_built_in_options() as $option_page => $options ) {
			foreach ( $options as $key => $label ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Get a list of all built in WordPress options.
	 *
	 * @return array
	 */
	protected function get_wordpress_built_in_options() {
		return [
			'general' => [
				'siteurl' => __( 'WordPress Address (URL)', 'simple-history' ),
				'home' => __( 'Site Address (URL)', 'simple-history' ),
				'blogname' => __( 'Site Title', 'simple-history' ),
				'blogdescription' => __( 'Tagline', 'simple-history' ),
				'site_icon' => __( 'Site Icon', 'simple-history' ),
				'admin_email' => __( 'Email Address', 'simple-history' ),
				'new_admin_email' => __( 'New Email Address', 'simple-history' ),
				'users_can_register' => __( 'Membership', 'simple-history' ),
				'default_role' => __( 'New User Default Role', 'simple-history' ),
				'timezone_string' => __( 'Timezone', 'simple-history' ),
				'date_format' => __( 'Date Format', 'simple-history' ),
				'time_format' => __( 'Time Format', 'simple-history' ),
				'start_of_week' => __( 'Week Starts On', 'simple-history' ),
				'WPLANG' => __( 'Site Language', 'simple-history' ),
			],
			'writing' => [
				'default_category' => __( 'Default Post Category', 'simple-history' ),
				'default_post_format' => __( 'Default Post Format', 'simple-history' ),
				'post_by_email' => __( 'Post via Email settings (legacy)', 'simple-history' ),
				'mailserver_url' => __( 'Mail Server', 'simple-history' ),
				'mailserver_login' => __( 'Login Name', 'simple-history' ),
				'mailserver_pass' => __( 'Password', 'simple-history' ),
				'mailserver_port' => __( 'Default Mail Server Port', 'simple-history' ),
				'default_pingback_flag' => __( 'Attempt to notify any blogs linked to from the article', 'simple-history' ),
				'default_ping_status' => __( 'Allow link notifications from other blogs (pingbacks and trackbacks)', 'simple-history' ),
				'default_comment_status' => __( 'Allow people to submit comments on new posts', 'simple-history' ),
				'ping_sites' => __( 'Update Services', 'simple-history' ),
			],
			'reading' => [
				'posts_per_page' => __( 'Blog pages show at most', 'simple-history' ),
				'posts_per_rss' => __( 'Syndication feeds show the most recent', 'simple-history' ),
				'rss_use_excerpt' => __( 'For each article in a feed, show', 'simple-history' ),
				'show_on_front' => __( 'Front page displays', 'simple-history' ),
				'page_on_front' => __( 'Front page', 'simple-history' ),
				'page_for_posts' => __( 'Posts page', 'simple-history' ),
				'blog_public' => __( 'Discourage search engines from indexing this site', 'simple-history' ),
			],
			'discussion' => [
				'default_article_visibility' => __( 'Default article visibility', 'simple-history' ),
				'default_comment_status' => __( 'Allow people to submit comments on new posts', 'simple-history' ),
				'require_name_email' => __( 'Comment author must fill out name and email', 'simple-history' ),
				'comment_registration' => __( 'Users must be registered and logged in to comment', 'simple-history' ),
				'close_comments_for_old_posts' => __( 'Automatically close comments on posts older than', 'simple-history' ),
				'close_comments_days_old' => __( 'Days before comments are closed', 'simple-history' ),
				'thread_comments' => __( 'Enable threaded (nested) comments', 'simple-history' ),
				'thread_comments_depth' => __( 'Max depth for threaded comments', 'simple-history' ),
				'page_comments' => __( 'Break comments into pages', 'simple-history' ),
				'comments_per_page' => __( 'Top level comments per page', 'simple-history' ),
				'default_comments_page' => __( 'Comments should be displayed with the', 'simple-history' ),
				'comment_order' => __( 'Comments order', 'simple-history' ),
				'comment_previously_approved' => __( 'Comment author must have a previously approved comment', 'simple-history' ),
				'comment_max_links' => __( 'Hold a comment in the queue if it contains', 'simple-history' ),
				'moderation_keys' => __( 'Comment Moderation', 'simple-history' ),
				'blacklist_keys' => __( 'Disallowed Comment Keys', 'simple-history' ),
				'disallowed_keys' => __( 'Disallowed Comment Keys', 'simple-history' ),
				'comment_moderation' => __( 'Comment must be manually approved', 'simple-history' ),
				'comment_whitelist' => __( 'Comment author must have a previously approved comment', 'simple-history' ),
				'comments_notify' => __( 'Email me whenever anyone posts a comment', 'simple-history' ),
				'moderation_notify' => __( 'Email me whenever a comment is held for moderation', 'simple-history' ),
				'comment_notify' => __( 'Email me whenever anyone posts a comment', 'simple-history' ),
				'show_avatars' => __( 'Show Avatars', 'simple-history' ),
				'avatar_rating' => __( 'Maximum Rating', 'simple-history' ),
				'avatar_default' => __( 'Default Avatar', 'simple-history' ),
			],
			'media' => [
				'thumbnail_size_w' => __( 'Thumbnail size width', 'simple-history' ),
				'thumbnail_size_h' => __( 'Thumbnail size height', 'simple-history' ),
				'thumbnail_crop' => __( 'Crop thumbnail to exact dimensions', 'simple-history' ),
				'medium_size_w' => __( 'Medium size width', 'simple-history' ),
				'medium_size_h' => __( 'Medium size height', 'simple-history' ),
				'large_size_w' => __( 'Large size width', 'simple-history' ),
				'large_size_h' => __( 'Large size height', 'simple-history' ),
				'uploads_use_yearmonth_folders' => __( 'Organize my uploads into month- and year-based folders', 'simple-history' ),
			],
			'permalinks' => [
				'permalink_structure' => __( 'Custom Structure', 'simple-history' ),
				'category_base' => __( 'Category base', 'simple-history' ),
				'tag_base' => __( 'Tag base', 'simple-history' ),
				'rewrite_rules' => __( 'Rewrite rules', 'simple-history' ),
			],
		];
	}
}
