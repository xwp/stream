<?php

class WP_Stream_Connector_WordPress_SEO extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'wordpress-seo';

	/**
	 * Holds tracked plugin minimum version required
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '1.5.3.3';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'wpseo_handle_import',
		'wpseo_import',
		'seo_page_wpseo_files',

		'added_post_meta',
		'updated_post_meta',
		'deleted_post_meta',
	);

	/**
	 * Tracking registered Settings, with overridden data
	 *
	 * @var array
	 */
	public static $option_groups = array();

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public static function is_dependency_satisfied() {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			//WP_Stream::notice(
			//	sprintf( __( '<strong>Stream WordPress SEO Connector</strong> requires the <a href="%1$s" target="_blank">WordPress SEO</a> plugin to be installed and activated.', 'stream' ), esc_url( 'http://wordpress.org/plugins/wordpress-seo/' ) ),
			//	true
			//);
		} elseif ( version_compare( WPSEO_VERSION, self::PLUGIN_MIN_VERSION, '<' ) ) {
			//WP_Stream::notice(
			//	sprintf( __( 'Please <a href="%1$s" target="_blank">install WordPress SEO</a> version %2$s or higher for the <strong>Stream WordPress SEO Connector</strong> plugin to work properly.', 'stream' ), esc_url( 'http://wordpress.org/plugins/wordpress-seo/' ), self::PLUGIN_MIN_VERSION ),
			//	true
			//);
		} else {
			return true;
		}
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'WordPress SEO', 'wordpress-seo' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created'    => __( 'Created', 'stream' ),
			'updated'    => __( 'Updated', 'stream' ),
			'added'      => __( 'Added', 'stream' ),
			'deleted'    => __( 'Deleted', 'stream' ),
			'exported'   => __( 'Exported', 'stream' ),
			'imported'   => __( 'Imported', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'wpseo_dashboard'               => __( 'Dashboard', 'wordpress-seo' ),
			'wpseo_titles'                  => __( 'Titles &amp; Metas', 'wordpress-seo' ),
			'wpseo_social'                  => __( 'Social', 'wordpress-seo' ),
			'wpseo_xml'                     => __( 'XML Sitemaps', 'wordpress-seo' ),
			'wpseo_permalinks'              => __( 'Permalinks', 'wordpress-seo' ),
			'wpseo_internal-links'          => __( 'Internal Links', 'wordpress-seo' ),
			'wpseo_rss'                     => __( 'RSS', 'wordpress-seo' ),
			'wpseo_import'                  => __( 'Import & Export', 'wordpress-seo' ),
			'wpseo_bulk-title-editor'       => __( 'Bulk Title Editor', 'wordpress-seo' ),
			'wpseo_bulk-description-editor' => __( 'Bulk Description Editor', 'wordpress-seo' ),

			'wpseo_files'                   => __( 'Files', 'wordpress-seo' ),
			'wpseo_meta'                    => __( 'Content', 'wordpress-seo' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links  Previous links registered
	 * @param  object $record Stream record
	 *
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		$contexts = self::get_context_labels();
		// Options
		if ( $option = wp_stream_get_meta( $record->ID, 'option', true ) ) {
			$key = wp_stream_get_meta( $record->ID, 'option_key', true );
			$links[ __( 'Edit', 'default' ) ] = add_query_arg(
				array(
					'page' => $record->context,
				),
				admin_url( 'admin.php' )
			) . '#stream-highlight-' . $key;
		}
		elseif ( 'wpseo_files' === $record->context ) {
			$links[ __( 'Edit', 'default' ) ] = add_query_arg(
				array(
					'page' => $record->context,
				),
				admin_url( 'admin.php' )
			);
		} elseif ( 'wpseo_meta' === $record->context ) {
			$post = get_post( $record->object_id );

			if ( $post ) {
				$post_type_name = WP_Stream_Connector_Posts::get_post_type_name( get_post_type( $post->ID ) );

				if ( 'trash' === $post->post_status ) {
					$untrash = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'untrash',
								'post'   => $post->ID,
							),
							admin_url( 'post.php' )
						),
						sprintf( 'untrash-post_%d', $post->ID )
					);

					$delete = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'delete',
								'post'   => $post->ID,
							),
							admin_url( 'post.php' )
						),
						sprintf( 'delete-post_%d', $post->ID )
					);

					$links[ sprintf( esc_html_x( 'Restore %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = $untrash;
					$links[ sprintf( esc_html_x( 'Delete %s Permenantly', 'Post type singular name', 'stream' ), $post_type_name ) ] = $delete;
				} else {
					$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = get_edit_post_link( $post->ID );

					if ( $view_link = get_permalink( $post->ID ) ) {
						$links[ esc_html__( 'View', 'default' ) ] = $view_link;
					}

					if ( $revision_id = wp_stream_get_meta( $record->ID, 'revision_id', true ) ) {
						$links[ esc_html__( 'Revision', 'default' ) ] = get_edit_post_link( $revision_id );
					}
				}
			}
		}
		return $links;
	}

	public static function register() {
		parent::register();

		foreach ( WPSEO_Options::$options as $class ) {
			/* @var $class WPSEO_Options */
			self::$option_groups[ $class::get_instance()->group_name ] = array(
				'class' => $class,
				'name' => $class::get_instance()->option_name,
			);
		}

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_filter( 'wp_stream_log_data', array( __CLASS__, 'log_override' ) );
	}

	public function admin_enqueue_scripts( $hook ) {
		if ( 0 === strpos( $hook, 'seo_page_' ) ) {
			$src = WP_STREAM_URL . '/ui/wpseo-admin.js';
			wp_enqueue_script( 'stream-connector-wpseo', $src, array( 'jquery' ), WP_Stream::VERSION );
		}
	}

	/**
	 * Track importing settings from other plugins
	 */
	public static function callback_wpseo_handle_import() {
		$imports = array(
			'importheadspace'                 => __( 'HeadSpace2', 'stream' ), # type = checkbox
			'importaioseo'                    => __( 'All-in-One SEO', 'stream' ), # type = checkbox
			'importaioseoold'                 => __( 'OLD All-in-One SEO', 'stream' ), # type = checkbox
			'importwoo'                       => __( 'WooThemes SEO framework', 'stream' ), # type = checkbox
			'importrobotsmeta'                => __( 'Robots Meta (by Yoast)', 'stream' ), # type = checkbox
			'importrssfooter'                 => __( 'RSS Footer (by Yoast)', 'stream' ), # type = checkbox
			'importbreadcrumbs'               => __( 'Yoast Breadcrumbs', 'stream' ), # type = checkbox
		);

		$opts = wp_stream_filter_input( INPUT_POST, 'wpseo' );

		foreach ( $imports as $key => $name ) {
			if ( isset( $opts[ $key ] ) ) {
				self::log(
					sprintf(
						__( 'Imported settings from %s%s', 'stream' ),
						$name,
						isset( $opts['deleteolddata'] ) ? __( ', and deleted old data', 'stream' ) : ''
					),
					array(
						'key' => $key,
						'deleteolddata' => isset( $opts['deleteolddata'] ),
					),
					null,
					array( 'wpseo_import' => 'imported' )
				);
			}
		}
	}

	public static function callback_wpseo_import() {
		$opts = wp_stream_filter_input( INPUT_POST, 'wpseo' );

		if ( wp_stream_filter_input( INPUT_POST, 'wpseo_export' ) ) {
			self::log(
				sprintf(
					__( 'Exported settings%s', 'stream' ),
					isset( $opts['include_taxonomy_meta'] ) ? __( ', including taxonomy meta', 'stream' ) : ''
				),
				array(
					'include_taxonomy_meta' => isset( $opts['include_taxonomy_meta'] ),
				),
				null,
				array( 'wpseo_import' => 'exported' )
			);
		}

		elseif ( isset( $_FILES['settings_import_file'] ) ) {
			self::log(
				sprintf(
					__( 'Tried importing settings from "%s"', 'stream' ),
					$_FILES['settings_import_file']['name']
				),
				array(
					'file' => $_FILES['settings_import_file']['name'],
				),
				null,
				array( 'wpseo_import' => 'exported' )
			);
		}
	}

	public static function callback_seo_page_wpseo_files() {
		if ( wp_stream_filter_input( INPUT_POST, 'create_robots' ) ) {
			$message = __( 'Tried creating robots.txt file', 'stream' );
		}
		elseif ( wp_stream_filter_input( INPUT_POST, 'submitrobots' ) ) {
			$message = __( 'Tried updating robots.txt file', 'stream' );
		}
		elseif ( wp_stream_filter_input( INPUT_POST, 'submithtaccess' ) ) {
			$message = __( 'Tried updating htaccess file', 'stream' );
		}

		if ( isset( $message ) ) {
			self::log(
				$message,
				array(),
				null,
				array( 'wpseo_files' => 'updated' )
			);
		}
	}

	public static function callback_added_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		self::meta( $object_id, $meta_key, $meta_value );
	}
	public static function callback_updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		self::meta( $object_id, $meta_key, $meta_value );
	}
	public static function callback_deleted_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		self::meta( $object_id, $meta_key, $meta_value );
	}

	private static function meta( $object_id, $meta_key, $meta_value ) {
		$prefix = WPSEO_Meta::$meta_prefix;
		WPSEO_Metabox::translate_meta_boxes();

		if ( 0 !== strpos( $meta_key, $prefix ) ) {
			return;
		}
		$key = str_replace( $prefix, '', $meta_key );

		foreach ( WPSEO_Meta::$meta_fields as $tab => $fields ) {
			if ( isset( $fields[ $key ] ) ) {
				$field = $fields[ $key ];
				break;
			}
		}

		if ( ! isset( $field, $field['title'], $tab ) || '' === $field['title'] ) {
			return;
		}

		$post = get_post( $object_id );
		$post_type_label = get_post_type_labels( get_post_type_object( $post->post_type ) )->singular_name;

		self::log(
			sprintf(
				__( 'Updated "%s" of "%s" %s', 'stream' ),
				$field['title'],
				$post->post_title,
				$post_type_label
			),
			array(
				'meta_key' => $meta_key,
				'meta_value' => $meta_value,
				'post_type' => $post->post_type,
			),
			$object_id,
			array( 'wpseo_meta' => 'updated' )
		);
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public static function log_override( array $data ) {
		global $pagenow;
		if ( 'options.php' === $pagenow && 'settings' === $data['connector'] && wp_stream_filter_input( INPUT_POST, '_wp_http_referer' ) ) {
			if ( ! isset( $data['args']['context'] ) || ! isset( self::$option_groups[ $data['args']['context'] ] ) ) {
				return $data;
			}

			$page = preg_match( '#page=([^&]*)#', wp_stream_filter_input( INPUT_POST, '_wp_http_referer' ), $match ) ? $match[1] : '';
			$labels = self::get_context_labels();
			if ( ! isset( $labels[ $page ] ) ) {
				return $data;
			}

			if ( ! ( $label = self::settings_labels( $data['args']['option_key'] ) ) ) {
				$data['message'] = __( '%s settings have been updated', 'stream' );
				$label = $labels[ $page ];
			}
			$data['args']['label'] = $label;
			$data['args']['context'] = $page;
			$data['contexts'] = array( $page => 'updated' );
			$data['connector'] = self::$name;
		}

		return $data;
	}

	private static function settings_labels( $option ) {
		$labels = array(
			// wp-content/plugins/wordpress-seo/admin/pages/dashboard.php:
			'yoast_tracking'                  => __( 'Allow tracking of this WordPress install\'s anonymous data.', 'wordpress-seo' ), # type = checkbox
			'disableadvanced_meta'            => __( 'Disable the Advanced part of the WordPress SEO meta box', 'wordpress-seo' ), # type = checkbox
			'alexaverify'                     => __( 'Alexa Verification ID', 'wordpress-seo' ), # type = textinput
			'msverify'                        => __( 'Bing Webmaster Tools', 'wordpress-seo' ), # type = textinput
			'googleverify'                    => __( 'Google Webmaster Tools', 'wordpress-seo' ), # type = textinput
			'pinterestverify'                 => __( 'Pinterest', 'wordpress-seo' ), # type = textinput
			'yandexverify'                    => __( 'Yandex Webmaster Tools', 'wordpress-seo' ), # type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/internal-links.php:
			'breadcrumbs-enable'              => __( 'Enable Breadcrumbs', 'wordpress-seo' ), # type = checkbox
			'breadcrumbs-sep'                 => __( 'Separator between breadcrumbs', 'wordpress-seo' ), # type = textinput
			'breadcrumbs-home'                => __( 'Anchor text for the Homepage', 'wordpress-seo' ), # type = textinput
			'breadcrumbs-prefix'              => __( 'Prefix for the breadcrumb path', 'wordpress-seo' ), # type = textinput
			'breadcrumbs-archiveprefix'       => __( 'Prefix for Archive breadcrumbs', 'wordpress-seo' ), # type = textinput
			'breadcrumbs-searchprefix'        => __( 'Prefix for Search Page breadcrumbs', 'wordpress-seo' ), # type = textinput
			'breadcrumbs-404crumb'            => __( 'Breadcrumb for 404 Page', 'wordpress-seo' ), # type = textinput
			'breadcrumbs-blog-remove'         => __( 'Remove Blog page from Breadcrumbs', 'wordpress-seo' ), # type = checkbox
			'breadcrumbs-boldlast'            => __( 'Bold the last page in the breadcrumb', 'wordpress-seo' ), # type = checkbox

			// wp-content/plugins/wordpress-seo/admin/pages/metas.php:
			'forcerewritetitle'               => __( 'Force rewrite titles', 'wordpress-seo' ), # type = checkbox
			'noindex-subpages-wpseo'          => __( 'Noindex subpages of archives', 'wordpress-seo' ), # type = checkbox
			'usemetakeywords'                 => __( 'Use <code>meta</code> keywords tag?', 'wordpress-seo' ), # type = checkbox
			'noodp'                           => __( 'Add <code>noodp</code> meta robots tag sitewide', 'wordpress-seo' ), # type = checkbox
			'noydir'                          => __( 'Add <code>noydir</code> meta robots tag sitewide', 'wordpress-seo' ), # type = checkbox
			'hide-rsdlink'                    => __( 'Hide RSD Links', 'wordpress-seo' ), # type = checkbox
			'hide-wlwmanifest'                => __( 'Hide WLW Manifest Links', 'wordpress-seo' ), # type = checkbox
			'hide-shortlink'                  => __( 'Hide Shortlink for posts', 'wordpress-seo' ), # type = checkbox
			'hide-feedlinks'                  => __( 'Hide RSS Links', 'wordpress-seo' ), # type = checkbox
			'disable-author'                  => __( 'Disable the author archives', 'wordpress-seo' ), # type = checkbox
			'disable-date'                    => __( 'Disable the date-based archives', 'wordpress-seo' ), # type = checkbox

			// wp-content/plugins/wordpress-seo/admin/pages/network.php:
			'access'                          => __( 'Who should have access to the WordPress SEO settings', 'wordpress-seo' ), # type = select
			'defaultblog'                     => __( 'New blogs get the SEO settings from this blog', 'wordpress-seo' ), # type = textinput
			'restoreblog'                     => __( 'Blog ID', 'wordpress-seo' ), # type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/permalinks.php:
			'stripcategorybase'               => __( 'Strip the category base (usually <code>/category/</code>) from the category URL.', 'wordpress-seo' ), # type = checkbox
			'trailingslash'                   => __( 'Enforce a trailing slash on all category and tag URL\'s', 'wordpress-seo' ), # type = checkbox
			'cleanslugs'                      => __( 'Remove stop words from slugs.', 'wordpress-seo' ), # type = checkbox
			'redirectattachment'              => __( 'Redirect attachment URL\'s to parent post URL.', 'wordpress-seo' ), # type = checkbox
			'cleanreplytocom'                 => __( 'Remove the <code>?replytocom</code> variables.', 'wordpress-seo' ), # type = checkbox
			'cleanpermalinks'                 => __( 'Redirect ugly URL\'s to clean permalinks. (Not recommended in many cases!)', 'wordpress-seo' ), # type = checkbox
			'force_transport'                 => __( 'Force Transport', 'wordpress-seo' ), # type = select
			'cleanpermalink-googlesitesearch' => __( 'Prevent cleaning out Google Site Search URL\'s.', 'wordpress-seo' ), # type = checkbox
			'cleanpermalink-googlecampaign'   => __( 'Prevent cleaning out Google Analytics Campaign & Google AdWords Parameters.', 'wordpress-seo' ), # type = checkbox
			'cleanpermalink-extravars'        => __( 'Other variables not to clean', 'wordpress-seo' ), # type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/social.php:
			'opengraph'                       => __( 'Add Open Graph meta data', 'wordpress-seo' ), # type = checkbox
			'facebook_site'                   => __( 'Facebook Page URL', 'wordpress-seo' ), # type = textinput
			'og_frontpage_image'              => __( 'Image URL', 'wordpress-seo' ), # type = textinput
			'og_frontpage_desc'               => __( 'Description', 'wordpress-seo' ), # type = textinput
			'og_default_image'                => __( 'Image URL', 'wordpress-seo' ), # type = textinput
			'twitter'                         => __( 'Add Twitter card meta data', 'wordpress-seo' ), # type = checkbox
			'twitter_site'                    => __( 'Site Twitter Username', 'wordpress-seo' ), # type = textinput
			'twitter_card_type'               => __( 'The default card type to use', 'wordpress-seo' ), # type = select
			'googleplus'                      => __( 'Add Google+ specific post meta data (excluding author metadata)', 'wordpress-seo' ), # type = checkbox
			'plus-publisher'                  => __( 'Google Publisher Page', 'wordpress-seo' ), # type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/xml-sitemaps.php:
			'enablexmlsitemap'                => __( 'Check this box to enable XML sitemap functionality.', 'wordpress-seo' ), # type = checkbox
			'disable_author_sitemap'          => __( 'Disable author/user sitemap', 'wordpress-seo' ), # type = checkbox
			'xml_ping_yahoo'                  => __( 'Ping Yahoo!', 'wordpress-seo' ), # type = checkbox
			'xml_ping_ask'                    => __( 'Ping Ask.com', 'wordpress-seo' ), # type = checkbox
			'entries-per-page'                => __( 'Max entries per sitemap page', 'wordpress-seo' ), # type = textinput

			// Added manually
			'rssbefore' => __( 'Content to put before each post in the feed', 'wordpress-seo' ),
			'rssafter' => __( 'Content to put after each post', 'wordpress-seo' ),
		);

		$ast_labels = array(
			'title-'        => __( 'Title template', 'wordpress-seo' ), # type = textinput
			'metadesc-'     => __( 'Meta description template', 'wordpress-seo' ), # type = textarea
			'metakey-'      => __( 'Meta keywords template', 'wordpress-seo' ), # type = textinput
			'noindex-'      => __( 'Meta Robots', 'wordpress-seo' ), # type = checkbox
			'noauthorship-' => __( 'Authorship', 'wordpress-seo' ), # type = checkbox
			'showdate-'     => __( 'Show date in snippet preview?', 'wordpress-seo' ), # type = checkbox
			'hideeditbox-'  => __( 'WordPress SEO Meta Box', 'wordpress-seo' ), # type = checkbox
			'bctitle-'      => __( 'Breadcrumbs Title', 'wordpress-seo' ), # type = textinput
			'post_types-'   => __( 'Post types', 'wordpress-seo' ), # type = checkbox
			'taxonomies-'   => __( 'Taxonomies', 'wordpress-seo' ), # type = checkbox
		);

		if ( $option ) {
			if ( isset( $labels[ $option ] ) ) {
				return $labels[ $option ];
			} else {
				foreach ( $ast_labels as $key => $trans ) {
					if ( 0 === strpos( $option, $key ) ) {
						return $trans;
					}
				}
				return false;
			}
		}
		return $labels;
	}

}
