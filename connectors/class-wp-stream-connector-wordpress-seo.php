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
		if ( defined( 'WPSEO_VERSION' ) && version_compare( WPSEO_VERSION, self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return _x( 'WordPress SEO', 'wordpress-seo', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created'  => _x( 'Created', 'wordpress-seo', 'stream' ),
			'updated'  => _x( 'Updated', 'wordpress-seo', 'stream' ),
			'added'    => _x( 'Added', 'wordpress-seo', 'stream' ),
			'deleted'  => _x( 'Deleted', 'wordpress-seo', 'stream' ),
			'exported' => _x( 'Exported', 'wordpress-seo', 'stream' ),
			'imported' => _x( 'Imported', 'wordpress-seo', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'wpseo_dashboard'               => _x( 'Dashboard', 'wordpress-seo', 'stream' ),
			'wpseo_titles'                  => _x( 'Titles &amp; Metas', 'wordpress-seo', 'stream' ),
			'wpseo_social'                  => _x( 'Social', 'wordpress-seo', 'stream' ),
			'wpseo_xml'                     => _x( 'XML Sitemaps', 'wordpress-seo', 'stream' ),
			'wpseo_permalinks'              => _x( 'Permalinks', 'wordpress-seo', 'stream' ),
			'wpseo_internal-links'          => _x( 'Internal Links', 'wordpress-seo', 'stream' ),
			'wpseo_rss'                     => _x( 'RSS', 'wordpress-seo', 'stream' ),
			'wpseo_import'                  => _x( 'Import & Export', 'wordpress-seo', 'stream' ),
			'wpseo_bulk-title-editor'       => _x( 'Bulk Title Editor', 'wordpress-seo', 'stream' ),
			'wpseo_bulk-description-editor' => _x( 'Bulk Description Editor', 'wordpress-seo', 'stream' ),
			'wpseo_files'                   => _x( 'Files', 'wordpress-seo', 'stream' ),
			'wpseo_meta'                    => _x( 'Content', 'wordpress-seo', 'stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links     Previous links registered
	 * @param  object $record    Stream record
	 *
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		$contexts = self::get_context_labels();

		// Options
		if ( $option = wp_stream_get_meta( $record, 'option', true ) ) {
			$key = wp_stream_get_meta( $record, 'option_key', true );

			$links[ __( 'Edit', 'stream' ) ] = add_query_arg(
				array(
					'page' => $record->context,
				),
				admin_url( 'admin.php' )
			) . '#stream-highlight-' . esc_attr( $key );
		} elseif ( 'wpseo_files' === $record->context ) {
			$links[ __( 'Edit', 'stream' ) ] = add_query_arg(
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
						$links[ esc_html__( 'View', 'stream' ) ] = $view_link;
					}

					if ( $revision_id = wp_stream_get_meta( $record, 'revision_id', true ) ) {
						$links[ esc_html__( 'Revision', 'stream' ) ] = get_edit_post_link( $revision_id );
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

	public static function admin_enqueue_scripts( $hook ) {
		if ( 0 === strpos( $hook, 'seo_page_' ) ) {
			$src = WP_STREAM_URL . '/ui/js/wpseo-admin.js';
			wp_enqueue_script( 'stream-connector-wpseo', $src, array( 'jquery' ), WP_Stream::VERSION );
		}
	}

	/**
	 * Track importing settings from other plugins
	 */
	public static function callback_wpseo_handle_import() {
		$imports = array(
			'importheadspace'   => __( 'HeadSpace2', 'stream' ), # type = checkbox
			'importaioseo'      => __( 'All-in-One SEO', 'stream' ), # type = checkbox
			'importaioseoold'   => __( 'OLD All-in-One SEO', 'stream' ), # type = checkbox
			'importwoo'         => __( 'WooThemes SEO framework', 'stream' ), # type = checkbox
			'importrobotsmeta'  => __( 'Robots Meta (by Yoast)', 'stream' ), # type = checkbox
			'importrssfooter'   => __( 'RSS Footer (by Yoast)', 'stream' ), # type = checkbox
			'importbreadcrumbs' => __( 'Yoast Breadcrumbs', 'stream' ), # type = checkbox
		);

		$opts = wp_stream_filter_input( INPUT_POST, 'wpseo' );

		foreach ( $imports as $key => $name ) {
			if ( isset( $opts[ $key ] ) ) {
				self::log(
					sprintf(
						__( 'Imported settings from %1$s%2$s', 'stream' ),
						$name,
						isset( $opts['deleteolddata'] ) ? __( ', and deleted old data', 'stream' ) : ''
					),
					array(
						'key' => $key,
						'deleteolddata' => isset( $opts['deleteolddata'] ),
					),
					null,
					'wpseo_import',
					'imported'
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
				'wpseo_import',
				'exported'
			);
		} elseif ( isset( $_FILES['settings_import_file'] ) ) {
			self::log(
				sprintf(
					__( 'Tried importing settings from "%s"', 'stream' ),
					$_FILES['settings_import_file']['name']
				),
				array(
					'file' => $_FILES['settings_import_file']['name'],
				),
				null,
				'wpseo_import',
				'exported'
			);
		}
	}

	public static function callback_seo_page_wpseo_files() {
		if ( wp_stream_filter_input( INPUT_POST, 'create_robots' ) ) {
			$message = __( 'Tried creating robots.txt file', 'stream' );
		} elseif ( wp_stream_filter_input( INPUT_POST, 'submitrobots' ) ) {
			$message = __( 'Tried updating robots.txt file', 'stream' );
		} elseif ( wp_stream_filter_input( INPUT_POST, 'submithtaccess' ) ) {
			$message = __( 'Tried updating htaccess file', 'stream' );
		}

		if ( isset( $message ) ) {
			self::log(
				$message,
				array(),
				null,
				'wpseo_files',
				'updated'
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

		$post            = get_post( $object_id );
		$post_type_label = get_post_type_labels( get_post_type_object( $post->post_type ) )->singular_name;

		self::log(
			sprintf(
				__( 'Updated "%1$s" of "%2$s" %3$s', 'stream' ),
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
			'wpseo_meta',
			'updated'
		);
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public static function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		global $pagenow;

		if ( 'options.php' === $pagenow && 'settings' === $data['connector'] && wp_stream_filter_input( INPUT_POST, '_wp_http_referer' ) ) {
			if ( ! isset( $data['args']['context'] ) || ! isset( self::$option_groups[ $data['args']['context'] ] ) ) {
				return $data;
			}

			$page   = preg_match( '#page=([^&]*)#', wp_stream_filter_input( INPUT_POST, '_wp_http_referer' ), $match ) ? $match[1] : '';
			$labels = self::get_context_labels();

			if ( ! isset( $labels[ $page ] ) ) {
				return $data;
			}

			if ( ! ( $label = self::settings_labels( $data['args']['option_key'] ) ) ) {
				$data['message'] = __( '%s settings updated', 'stream' );
				$label           = $labels[ $page ];
			}

			$data['args']['label']   = $label;
			$data['args']['context'] = $page;
			$data['context']         = $page;
			$data['connector']       = self::$name;
		}

		return $data;
	}

	private static function settings_labels( $option ) {
		$labels = array(
			// wp-content/plugins/wordpress-seo/admin/pages/dashboard.php:
			'yoast_tracking'                  => _x( 'Allow tracking of this WordPress install\'s anonymous data.', 'wordpress-seo', 'stream' ), # type = checkbox
			'disableadvanced_meta'            => _x( 'Disable the Advanced part of the WordPress SEO meta box', 'wordpress-seo', 'stream' ), # type = checkbox
			'alexaverify'                     => _x( 'Alexa Verification ID', 'wordpress-seo', 'stream' ), # type = textinput
			'msverify'                        => _x( 'Bing Webmaster Tools', 'wordpress-seo', 'stream' ), # type = textinput
			'googleverify'                    => _x( 'Google Webmaster Tools', 'wordpress-seo', 'stream' ), # type = textinput
			'pinterestverify'                 => _x( 'Pinterest', 'wordpress-seo', 'stream' ), # type = textinput
			'yandexverify'                    => _x( 'Yandex Webmaster Tools', 'wordpress-seo', 'stream' ), # type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/internal-links.php:
			'breadcrumbs-enable'              => _x( 'Enable Breadcrumbs', 'wordpress-seo', 'stream' ), # type = checkbox
			'breadcrumbs-sep'                 => _x( 'Separator between breadcrumbs', 'wordpress-seo', 'stream' ), # type = textinput
			'breadcrumbs-home'                => _x( 'Anchor text for the Homepage', 'wordpress-seo', 'stream' ), # type = textinput
			'breadcrumbs-prefix'              => _x( 'Prefix for the breadcrumb path', 'wordpress-seo', 'stream' ), # type = textinput
			'breadcrumbs-archiveprefix'       => _x( 'Prefix for Archive breadcrumbs', 'wordpress-seo', 'stream' ), # type = textinput
			'breadcrumbs-searchprefix'        => _x( 'Prefix for Search Page breadcrumbs', 'wordpress-seo', 'stream' ), # type = textinput
			'breadcrumbs-404crumb'            => _x( 'Breadcrumb for 404 Page', 'wordpress-seo', 'stream' ), # type = textinput
			'breadcrumbs-blog-remove'         => _x( 'Remove Blog page from Breadcrumbs', 'wordpress-seo', 'stream' ), # type = checkbox
			'breadcrumbs-boldlast'            => _x( 'Bold the last page in the breadcrumb', 'wordpress-seo', 'stream' ), # type = checkbox

			// wp-content/plugins/wordpress-seo/admin/pages/metas.php:
			'forcerewritetitle'               => _x( 'Force rewrite titles', 'wordpress-seo', 'stream' ), # type = checkbox
			'noindex-subpages-wpseo'          => _x( 'Noindex subpages of archives', 'wordpress-seo', 'stream' ), # type = checkbox
			'usemetakeywords'                 => _x( 'Use <code>meta</code> keywords tag?', 'wordpress-seo', 'stream' ), # type = checkbox
			'noodp'                           => _x( 'Add <code>noodp</code> meta robots tag sitewide', 'wordpress-seo', 'stream' ), # type = checkbox
			'noydir'                          => _x( 'Add <code>noydir</code> meta robots tag sitewide', 'wordpress-seo', 'stream' ), # type = checkbox
			'hide-rsdlink'                    => _x( 'Hide RSD Links', 'wordpress-seo', 'stream' ), # type = checkbox
			'hide-wlwmanifest'                => _x( 'Hide WLW Manifest Links', 'wordpress-seo', 'stream' ), # type = checkbox
			'hide-shortlink'                  => _x( 'Hide Shortlink for posts', 'wordpress-seo', 'stream' ), # type = checkbox
			'hide-feedlinks'                  => _x( 'Hide RSS Links', 'wordpress-seo', 'stream' ), # type = checkbox
			'disable-author'                  => _x( 'Disable the author archives', 'wordpress-seo', 'stream' ), # type = checkbox
			'disable-date'                    => _x( 'Disable the date-based archives', 'wordpress-seo', 'stream' ), # type = checkbox

			// wp-content/plugins/wordpress-seo/admin/pages/network.php:
			'access'                          => _x( 'Who should have access to the WordPress SEO settings', 'wordpress-seo', 'stream' ), # type = select
			'defaultblog'                     => _x( 'New blogs get the SEO settings from this blog', 'wordpress-seo', 'stream' ), # type = textinput
			'restoreblog'                     => _x( 'Blog ID', 'wordpress-seo', 'stream' ), # type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/permalinks.php:
			'stripcategorybase'               => _x( 'Strip the category base (usually <code>/category/</code>) from the category URL.', 'wordpress-seo', 'stream' ), # type = checkbox
			'trailingslash'                   => _x( 'Enforce a trailing slash on all category and tag URL\'s', 'wordpress-seo', 'stream' ), # type = checkbox
			'cleanslugs'                      => _x( 'Remove stop words from slugs.', 'wordpress-seo', 'stream' ), # type = checkbox
			'redirectattachment'              => _x( 'Redirect attachment URL\'s to parent post URL.', 'wordpress-seo', 'stream' ), # type = checkbox
			'cleanreplytocom'                 => _x( 'Remove the <code>?replytocom</code> variables.', 'wordpress-seo', 'stream' ), # type = checkbox
			'cleanpermalinks'                 => _x( 'Redirect ugly URL\'s to clean permalinks. (Not recommended in many cases!)', 'wordpress-seo', 'stream' ), # type = checkbox
			'force_transport'                 => _x( 'Force Transport', 'wordpress-seo', 'stream' ), # type = select
			'cleanpermalink-googlesitesearch' => _x( 'Prevent cleaning out Google Site Search URL\'s.', 'wordpress-seo', 'stream' ), # type = checkbox
			'cleanpermalink-googlecampaign'   => _x( 'Prevent cleaning out Google Analytics Campaign & Google AdWords Parameters.', 'wordpress-seo', 'stream' ), # type = checkbox
			'cleanpermalink-extravars'        => _x( 'Other variables not to clean', 'wordpress-seo', 'stream' ), # type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/social.php:
			'opengraph'                       => _x( 'Add Open Graph meta data', 'wordpress-seo', 'stream' ), # type = checkbox
			'facebook_site'                   => _x( 'Facebook Page URL', 'wordpress-seo', 'stream' ), # type = textinput
			'og_frontpage_image'              => _x( 'Image URL', 'wordpress-seo', 'stream' ), # type = textinput
			'og_frontpage_desc'               => _x( 'Description', 'wordpress-seo', 'stream' ), # type = textinput
			'og_default_image'                => _x( 'Image URL', 'wordpress-seo', 'stream' ), # type = textinput
			'twitter'                         => _x( 'Add Twitter card meta data', 'wordpress-seo', 'stream' ), # type = checkbox
			'twitter_site'                    => _x( 'Site Twitter Username', 'wordpress-seo', 'stream' ), # type = textinput
			'twitter_card_type'               => _x( 'The default card type to use', 'wordpress-seo', 'stream' ), # type = select
			'googleplus'                      => _x( 'Add Google+ specific post meta data (excluding author metadata)', 'wordpress-seo', 'stream' ), # type = checkbox
			'plus-publisher'                  => _x( 'Google Publisher Page', 'wordpress-seo', 'stream' ), # type = textinput

			// wp-content/plugins/wordpress-seo/admin/pages/xml-sitemaps.php:
			'enablexmlsitemap'                => _x( 'Check this box to enable XML sitemap functionality.', 'wordpress-seo', 'stream' ), # type = checkbox
			'disable_author_sitemap'          => _x( 'Disable author/user sitemap', 'wordpress-seo', 'stream' ), # type = checkbox
			'xml_ping_yahoo'                  => _x( 'Ping Yahoo!', 'wordpress-seo', 'stream' ), # type = checkbox
			'xml_ping_ask'                    => _x( 'Ping Ask.com', 'wordpress-seo', 'stream' ), # type = checkbox
			'entries-per-page'                => _x( 'Max entries per sitemap page', 'wordpress-seo', 'stream' ), # type = textinput

			// Added manually
			'rssbefore'                       => _x( 'Content to put before each post in the feed', 'wordpress-seo', 'stream' ),
			'rssafter'                        => _x( 'Content to put after each post', 'wordpress-seo', 'stream' ),
		);

		$ast_labels = array(
			'title-'        => _x( 'Title template', 'wordpress-seo', 'stream' ), # type = textinput
			'metadesc-'     => _x( 'Meta description template', 'wordpress-seo', 'stream' ), # type = textarea
			'metakey-'      => _x( 'Meta keywords template', 'wordpress-seo', 'stream' ), # type = textinput
			'noindex-'      => _x( 'Meta Robots', 'wordpress-seo', 'stream' ), # type = checkbox
			'noauthorship-' => _x( 'Authorship', 'wordpress-seo', 'stream' ), # type = checkbox
			'showdate-'     => _x( 'Show date in snippet preview?', 'wordpress-seo', 'stream' ), # type = checkbox
			'hideeditbox-'  => _x( 'WordPress SEO Meta Box', 'wordpress-seo', 'stream' ), # type = checkbox
			'bctitle-'      => _x( 'Breadcrumbs Title', 'wordpress-seo', 'stream' ), # type = textinput
			'post_types-'   => _x( 'Post types', 'wordpress-seo', 'stream' ), # type = checkbox
			'taxonomies-'   => _x( 'Taxonomies', 'wordpress-seo', 'stream' ), # type = checkbox
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
