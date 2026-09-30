<?php
/**
 * This class handles the main logic for replacing images.
 *
 * @package Search_Replace_WPCode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSRW_Image_Replace
 */
class WSRW_Image_Replace {

	/**
	 * The old file path.
	 *
	 * @var string
	 */
	protected $old_file_path;

	/**
	 * The URL of the original file being replaced.
	 *
	 * @var string
	 */
	protected $old_file_url = '';

	/**
	 * The URL of the new file after replacement.
	 *
	 * @var string
	 */
	protected $new_file_url = '';

	/**
	 * WSRW_Image_Replace constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_filter( 'attachment_fields_to_edit', array( $this, 'add_button_to_edit_media_modal_fields_area' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this, 'add_button_to_media_row_actions' ), 10, 2 );

		add_filter( 'wp_get_attachment_image_src', array( $this, 'get_attachment_url' ), 10, 4 );

		add_action( 'add_meta_boxes_attachment', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Filters the attachment image source result.
	 *
	 * @param array|false  $image {
	 *     Array of image data, or boolean false if no image is available.
	 *
	 * @type string $0 Image source URL.
	 * @type int    $1 Image width in pixels.
	 * @type int    $2 Image height in pixels.
	 * @type bool   $3 Whether the image is a resized image.
	 * }
	 *
	 * @param int          $attachment_id Image attachment ID.
	 * @param string|int[] $size Requested image size. Can be any registered image size name, or
	 *                                    an array of width and height values in pixels (in that order).
	 * @param bool         $icon Whether the image should be treated as an icon.
	 *
	 * @since 4.3.0
	 */
	public function get_attachment_url( $image, $attachment_id, $size, $icon ) {
		if ( ! is_admin() && ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) ) {
			return $image;
		}
		// Let's check if the attachment has been replaced using the _wsrw_replaced meta.
		$replaced = get_post_meta( $attachment_id, '_wsrw_replaced', true );
		// If the replaced timestamp is past 24h let's just skip this.
		if ( ! empty( $replaced ) && $replaced < strtotime( '-1 day' ) ) {
			return $image;
		}

		if ( is_array( $image ) ) {
			$image[0] = add_query_arg( 'wsr', $replaced, $image[0] );
		}

		return $image;
	}

	/**
	 * Handle the image upload.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_image_upload( $request ) {
		$files = $request->get_file_params();

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( $_POST['_wpnonce'] ) : '';
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'Invalid nonce', 'search-replace-wpcode' ),
				),
				403
			);
		}

		if ( empty( $files['file'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'No file uploaded', 'search-replace-wpcode' ),
				),
				400
			);
		}
		$file     = $files['file'];
		$media_id = isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;

		// Object-level authorization: the upload_files capability alone does not tie the
		// request to this attachment, so verify the user may edit this specific media item.
		// edit_post maps to edit_others_posts when the attachment is not the user's own.
		if ( ! $media_id || ! current_user_can( 'edit_post', $media_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'You are not allowed to replace this file.', 'search-replace-wpcode' ),
				),
				403
			);
		}

		// Let's grab the path of the current image using the media_id.
		$attachment    = get_post( $media_id );
		$old_file_path = get_attached_file( $media_id, true );

		// Validate file type.
		$original_mime_type = get_post_mime_type( $media_id );
		$file_mime_type     = $file['type'];

		// Get allowed mime types.
		$allowed_mime_types = get_allowed_mime_types();

		// Check if the uploaded file type is allowed.
		$is_allowed = false;
		foreach ( $allowed_mime_types as $ext => $mime ) {
			if ( $mime === $file_mime_type ) {
				$is_allowed = true;
				break;
			}
		}

		if ( ! $is_allowed ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'File type not allowed. Please upload a file with a supported format.', 'search-replace-wpcode' ),
				),
				400
			);
		}

		// For images, check if we're replacing an image with a non-image.
		if ( strpos( $original_mime_type, 'image/' ) === 0 && strpos( $file_mime_type, 'image/' ) !== 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'You cannot replace an image with a non-image file. Please upload an image file.', 'search-replace-wpcode' ),
				),
				200
			);
		}

		// Store source metadata before replacement for nearest size calculations.
		$metadata = wp_get_attachment_metadata( $media_id );
		if ( $metadata ) {
			$this->store_source_metadata( $media_id, $metadata );
		}

		// Let's first delete all the thumbnails for the old image.
		$backup_sizes = get_post_meta( $media_id, '_wp_attachment_backup_sizes', true );
		wp_delete_attachment_files( $media_id, $metadata, $backup_sizes, $old_file_path );

		// Let's upload the new file in the same directory as the old file with the same exact name.
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$this->old_file_path = $old_file_path;
		// Let's grab the original upload time from the post publish date.
		$time = strtotime( $attachment->post_date );
		// Time should be formatted in yyyy/mm so that we use the same directory.
		$time = gmdate( 'Y/m', $time );
		// Let's move the file to the new location using wp_handle_upload.
		$move_file = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'unique_filename_callback' => array( $this, 'unique_filename_callback' ),
			),
			$time
		);

		if ( ! $move_file || isset( $move_file['error'] ) ) {
			$this->delete_source_metadata( $media_id );

			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $move_file['error'] ?? esc_html__( 'An error occurred while uploading the file', 'search-replace-wpcode' ),
				),
				200
			);
		}

		$new_file_path = $move_file['file'];

		// Let's make sure the media file is included before calling generate attachment metada.
		require_once ABSPATH . 'wp-admin/includes/image.php';

		add_filter( 'big_image_size_threshold', '__return_false' );
		// Let's update the attachment metadata.
		$attachment_data = wp_generate_attachment_metadata( $media_id, $new_file_path );
		wp_update_attachment_metadata( $media_id, $attachment_data );

		// Add post meta to mark this has been replaced.
		update_post_meta( $media_id, '_wsrw_replaced', time() );

		// Update embedded references to the replaced file across the database.
		$this->update_database_references();
		$this->delete_source_metadata( $media_id );

		$new_media = wp_get_attachment_image_src( $media_id, 'large' );

		$message = '<p>' . esc_html__( 'File uploaded successfully', 'search-replace-wpcode' ) . '</p>';

		$message .= '<p><strong style="color:red;">' . esc_html__( 'Please note that the source file has been replaced. If you see the old file, please clear your browser cache.', 'search-replace-wpcode' ) . '</strong></p>';

		$response = array(
			'success' => true,
			'message' => $message,
		);

		if ( wp_attachment_is_image( $media_id ) ) {
			$response['image_url'] = $new_media[0] ?? wp_get_attachment_url( $media_id );
		}

		return new WP_REST_Response(
			$response,
			200
		);
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'wsrw/v1',
			'/upload-image',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_image_upload' ),
				'permission_callback' => function () {
					return current_user_can( 'upload_files' );
				},
			)
		);
	}

	/**
	 * Get the new filename for the uploaded file.
	 *
	 * @param string $old_file_path The path to the old file.
	 * @param string $new_filename The new filename.
	 *
	 * @return string
	 */
	protected function get_new_filename( $old_file_path, $new_filename ) {
		// By default, use the original filename.
		return basename( $old_file_path );
	}

	/**
	 * Get the file extension to use.
	 *
	 * @param string $old_file_path The path to the old file.
	 * @param string $new_file_path The path to the new file.
	 *
	 * @return string
	 */
	protected function get_file_extension( $old_file_path, $new_file_path ) {
		// Always use the original extension in base class.
		return pathinfo( $old_file_path, PATHINFO_EXTENSION );
	}

	/**
	 * Add a button to the edit media modal fields area.
	 *
	 * @param array   $form_fields The form fields.
	 * @param WP_Post $post The post object.
	 *
	 * @return array
	 */
	public function add_button_to_edit_media_modal_fields_area( $form_fields, $post ) {
		if ( ! $this->can_user_replace_image( $post ) ) {
			return $form_fields;
		}

		if ( ! wp_attachment_is_image( $post ) ) {
			return $form_fields;
		}

		$form_fields['wsrw-replace-button'] = array(
			'label'         => '',
			'input'         => 'html',
			'html'          => '<a href="' . esc_url( self::get_replace_page_url( $post ) ) . '" class="button-secondary button-large" title="' . esc_attr__( 'Replace the source file for this image', 'search-replace-wpcode' ) . '">' . esc_html_x( 'Replace Source File', 'action for a single image', 'search-replace-wpcode' ) . '</a>',
			'show_in_modal' => true,
			'show_in_edit'  => false,
			'helps'         => esc_html__( 'Directly replace the original file\'s source without creating a duplicate.', 'search-replace-wpcode' ),
		);

		return $form_fields;
	}

	/**
	 * Get the URL for the replace page.
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return string
	 */
	public static function get_replace_page_url( $post ) {
		return wp_nonce_url( admin_url( 'admin.php?page=wsrw-search-replace&view=replace_media&media_id=' . $post->ID ), 'wsrw_replace_media' );
	}

	/**
	 * Add a button to the media row actions.
	 *
	 * @param array   $actions The actions array.
	 * @param WP_Post $post The post object.
	 *
	 * @return array
	 */
	public function add_button_to_media_row_actions( $actions, $post ) {
		if ( ! $this->can_user_replace_image( $post ) ) {
			return $actions;
		}

		$actions['wsrw-replace'] = '<a href="' . esc_url( self::get_replace_page_url( $post ) ) . '" title="' . esc_attr__( 'Replace the source file.', 'search-replace-wpcode' ) . '">' . esc_html_x( 'Replace Source File', 'action in the list of attachments', 'search-replace-wpcode' ) . '</a>';

		return $actions;
	}

	/**
	 * Permissions check for a specific attachment
	 *
	 * @param WP_Post $post The post object to check for.
	 *
	 * @return bool
	 */
	public function can_user_replace_image( $post ) {
		return current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Add meta boxes for the replace media page.
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return void
	 */
	public function add_meta_boxes( $post ) {
		if ( ! $this->can_user_replace_image( $post ) ) {
			return;
		}

		add_meta_box(
			'wsrw-replace',
			__( 'Replace Source File', 'search-replace-wpcode' ),
			array(
				$this,
				'replace_meta_box',
			),
			'attachment',
			'side',
			'low'
		);
	}

	/**
	 * Output the meta box for the replace media page.
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return void
	 */
	public function replace_meta_box( $post ) {
		?>
		<p>
			<a href="<?php echo esc_url( self::get_replace_page_url( $post ) ); ?>" class="button-secondary button-large"><?php esc_html_e( 'Replace Source File', 'search-replace-wpcode' ); ?></a>
		</p>
		<p>
			<?php esc_html_e( 'Use the button above to begin the source file replacement process.', 'search-replace-wpcode' ); ?>
		</p>
		<?php
	}

	/**
	 * Override the unique filename callback so we override the original file.
	 *
	 * @param string $dir The directory path.
	 * @param string $filename The filename.
	 * @param string $ext The file extension.
	 *
	 * @return string
	 */
	public function unique_filename_callback( $dir, $filename, $ext = '' ) {
		if ( isset( $this->old_file_path ) ) {
			$new_filename = $this->get_new_filename( $this->old_file_path, $filename );

			$filename_without_ext = pathinfo( $new_filename, PATHINFO_FILENAME );
			$new_ext              = $this->get_file_extension( $this->old_file_path, $filename . $ext );

			// Store the old and new file URLs for database updates. These are needed even when
			// the extension is not replaced, otherwise update_database_references() bails early
			// and embedded sub-size URLs are never remapped to the regenerated sizes.
			$media_id     = isset( $_POST['media_id'] ) ? absint( wp_unslash( $_POST['media_id'] ) ) : 0; //phpcs:ignore
			$old_file_url = wp_get_attachment_url( $media_id );
			if ( $old_file_url ) {
				$this->old_file_url = $old_file_url;
				$this->new_file_url = preg_replace(
					'/\.' . preg_quote( pathinfo( $old_file_url, PATHINFO_EXTENSION ), '/' ) . '$/i',
					'.' . $new_ext,
					$old_file_url
				);
			}

			// If the extension is unchanged, keep the original filename.
			if ( '' === $new_ext || pathinfo( $new_filename, PATHINFO_EXTENSION ) === $new_ext ) {
				return $new_filename;
			}

			// Otherwise, rebuild the filename using the new extension.
			return $filename_without_ext . '.' . $new_ext;
		} else {
			return $filename;
		}
	}

	/**
	 * Update database references from the old file URL(s) to the new file URL(s).
	 *
	 * Runs after a media file is replaced so embedded references (including the
	 * regenerated sub-size URLs) keep pointing at existing files. Each table is
	 * read in pages and scanned a single time, applying every URL mapping per
	 * row, so a replace never loads a whole table into memory and never re-reads
	 * the same table once per image size.
	 *
	 * @return void
	 */
	protected function update_database_references() {
		if ( empty( $this->old_file_url ) || empty( $this->new_file_url ) ) {
			return;
		}

		// Require the search and replace class when needed.
		if ( ! class_exists( 'WSRW_Search_Replace' ) ) {
			require_once WSRW_PLUGIN_PATH . 'includes/class-wsrw-search-replace.php';
		}

		// Nonce and capability are verified in handle_image_upload() before this runs.
		$media_id = isset( $_POST['media_id'] ) ? absint( wp_unslash( $_POST['media_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $media_id ) {
			return;
		}

		// Build the old => new URL mappings (main file plus each image size).
		$source_metadata = $this->get_source_metadata( $media_id );
		$target_metadata = wp_get_attachment_metadata( $media_id );
		$url_mappings    = $this->get_url_mappings_with_nearest_sizes( $source_metadata, $target_metadata );

		// Precompute each mapping's URL variations once and drop no-op mappings.
		$uploads_url   = trailingslashit( wp_upload_dir()['baseurl'] );
		$mappings      = array();
		$old_basenames = array();

		foreach ( $url_mappings as $old_url => $new_url ) {
			if ( $old_url === $new_url ) {
				continue;
			}

			$mappings[]      = array(
				'variations'   => $this->get_url_variations( $old_url ),
				'new_url'      => $new_url,
				'old_relative' => str_replace( $uploads_url, '', $old_url ),
				'new_relative' => str_replace( $uploads_url, '', $new_url ),
			);
			$old_basenames[] = wp_basename( $old_url );
		}

		// Nothing actually changed (e.g. same-size, same-name re-upload): skip the scan entirely.
		if ( empty( $mappings ) ) {
			return;
		}

		// The longest common prefix of the mapped file names (main file plus every
		// sub-size) is a substring of every URL variation, so it is a safe, cheap
		// pre-filter for skipping cells that cannot reference the replaced file.
		// Deriving it from the actual mapped names (rather than the attachment URL)
		// keeps it correct for "-scaled" images, whose sub-sizes drop the suffix.
		$name_stem = $this->get_common_prefix( $old_basenames );

		// wp_json_encode() escapes non-ASCII to \uXXXX, so a raw multibyte stem would
		// not match inside JSON cells. Disable the pre-filter for such names so those
		// cells are still scanned (an empty stem means "do not pre-filter").
		if ( preg_match( '/[\x80-\xff]/', $name_stem ) ) {
			$name_stem = '';
		}

		global $wpdb;

		$search_replace = new WSRW_Search_Replace();
		$page_size      = $search_replace->get_page_size();
		$changed        = false;

		// Reuse the engine's table list, which also excludes the plugin's own tables.
		$tables = WSRW_Search_Replace::get_all_tables();

		foreach ( $tables as $table ) {
			$columns     = WSRW_Search_Replace::get_table_columns( $table )['columns'];
			$key_columns = $this->get_primary_key_columns( $table );

			if ( empty( $columns ) || empty( $key_columns ) ) {
				continue; // Skip tables we cannot read or that have no primary key.
			}

			$is_postmeta_table = ( false !== strpos( $table, 'postmeta' ) );
			// %i is unavailable on WP < 6.2, so escape the schema-derived identifiers.
			$safe_table = esc_sql( $table );
			$order_by   = '`' . implode( '`, `', array_map( 'esc_sql', $key_columns ) ) . '`';
			$offset     = 0;

			// Page through the table, ordered by the full primary key so paging stays stable
			// (even for composite keys) while rows are updated in place, and an entire (huge)
			// table is never held in memory.
			do {
				$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $safe_table ORDER BY $order_by LIMIT %d, %d", $offset, $page_size ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table and key names are schema-derived and escaped; the LIMIT values are prepared.
				$fetched = count( $rows );

				foreach ( $rows as $row ) {
					// Build the full primary-key WHERE so each update targets exactly one row.
					$where = array();
					foreach ( $key_columns as $key_column ) {
						if ( ! isset( $row->$key_column ) ) {
							continue 2;
						}
						$where[ $key_column ] = $row->$key_column;
					}

					$update_data = array();

					foreach ( $columns as $column ) {
						if ( isset( $where[ $column ] ) ) {
							continue; // Never rewrite a primary-key column.
						}

						$content = $row->$column;

						// _wp_attached_file stores an uploads-relative path, so match on the relative form.
						if ( $is_postmeta_table && 'meta_key' === $column && '_wp_attached_file' === $content ) {
							$meta_value = isset( $row->meta_value ) ? $row->meta_value : '';
							$replaced   = $meta_value;

							foreach ( $mappings as $mapping ) {
								if ( '' !== $mapping['old_relative'] && false !== strpos( $replaced, $mapping['old_relative'] ) ) {
									$replaced = str_replace( $mapping['old_relative'], $mapping['new_relative'], $replaced );
								}
							}

							if ( $meta_value !== $replaced ) {
								$update_data['meta_value'] = $replaced;
							}

							continue;
						}

						if ( ! is_string( $content ) || '' === $content ) {
							continue;
						}

						// Don't skip the GUID column for image URLs.
						if ( 'guid' === $column && ! $this->is_image_url( $this->old_file_url ) ) {
							if ( apply_filters( 'wsrw_skip_guids', true ) ) {
								continue;
							}
						}

						// Fast pre-filter: only parse/replace cells that reference the replaced file.
						if ( '' !== $name_stem && false === strpos( $content, $name_stem ) ) {
							continue;
						}

						$replaced_content = $content;

						if ( is_serialized( $content ) ) {
							foreach ( $mappings as $mapping ) {
								$replaced_content = $this->replace_in_serialized( $replaced_content, $mapping['variations'], $mapping['new_url'] );
							}
						} elseif ( $this->is_json( $content ) ) {
							foreach ( $mappings as $mapping ) {
								$replaced_content = $this->replace_in_json( $replaced_content, $mapping['variations'], $mapping['new_url'] );
							}
						} else {
							foreach ( $mappings as $mapping ) {
								foreach ( $mapping['variations'] as $variation ) {
									if ( false !== strpos( $replaced_content, $variation ) ) {
										$replaced_content = str_replace( $variation, $mapping['new_url'], $replaced_content );
									}
								}
							}
						}

						if ( $content !== $replaced_content ) {
							$update_data[ $column ] = $replaced_content;
						}
					}

					if ( ! empty( $update_data ) ) {
						$wpdb->update( $table, $update_data, $where ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Media URL rewrite; $wpdb->update() prepares the SET and WHERE values.
						$changed = true;
					}
				}

				$offset += $page_size;
			} while ( $fetched === $page_size );
		}

		// Force Elementor CSS regeneration only when a reference actually changed.
		if ( $changed && class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::instance()->files_manager->clear_cache();
		}
	}

	/**
	 * Get the primary key column names for a table.
	 *
	 * Returns every column in the primary key (a composite key returns more than
	 * one), so callers can order and target rows by the full, unique key.
	 *
	 * @param string $table The table name.
	 * @return string[] The primary key column names, or an empty array if none.
	 */
	private function get_primary_key_columns( $table ) {
		global $wpdb;

		$safe_table = esc_sql( $table );
		$columns    = $wpdb->get_results( "DESCRIBE `$safe_table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema-derived, escaped table name; DESCRIBE takes no bindable values.

		$keys = array();
		foreach ( (array) $columns as $column ) {
			if ( isset( $column->Key ) && 'PRI' === $column->Key ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$keys[] = $column->Field; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}

		return $keys;
	}

	/**
	 * Get the longest common prefix shared by a set of strings.
	 *
	 * Used to derive a substring common to the main file and every sub-size name,
	 * so the reference scan can cheaply skip cells that cannot reference the file.
	 *
	 * @param string[] $strings The strings to compare.
	 * @return string The longest common prefix, or an empty string if there is none.
	 */
	private function get_common_prefix( $strings ) {
		if ( empty( $strings ) ) {
			return '';
		}

		$prefix = (string) array_shift( $strings );

		foreach ( $strings as $string ) {
			$string = (string) $string;
			$max    = min( strlen( $prefix ), strlen( $string ) );
			$i      = 0;

			while ( $i < $max && $prefix[ $i ] === $string[ $i ] ) {
				++$i;
			}

			$prefix = substr( $prefix, 0, $i );

			if ( '' === $prefix ) {
				break;
			}
		}

		return $prefix;
	}

	/**
	 * Get source metadata before replacement (stored in a transient or option).
	 *
	 * @param int $media_id The media ID.
	 * @return array|false The source metadata or false if not found.
	 */
	protected function get_source_metadata( $media_id ) {
		// Try to get from transient first (short-term storage).
		$source_metadata = get_transient( 'wsrw_source_metadata_' . $media_id );

		if ( false === $source_metadata ) {
			// Fallback: try to get from option (longer-term storage).
			$source_metadata = get_option( 'wsrw_source_metadata_' . $media_id, false );
		}

		return $source_metadata;
	}

	/**
	 * Store source metadata before replacement.
	 *
	 * @param int   $media_id The media ID.
	 * @param array $metadata The metadata to store.
	 */
	protected function store_source_metadata( $media_id, $metadata ) {
		// Store in transient for 1 hour (short-term).
		set_transient( 'wsrw_source_metadata_' . $media_id, $metadata, HOUR_IN_SECONDS );

		// Also store in option as backup (longer-term).
		update_option( 'wsrw_source_metadata_' . $media_id, $metadata );
	}

	/**
	 * Remove the stored source metadata after processing.
	 *
	 * @param int $media_id The media ID.
	 */
	protected function delete_source_metadata( $media_id ) {
		if ( ! $media_id ) {
			return;
		}

		delete_transient( 'wsrw_source_metadata_' . $media_id );
		delete_option( 'wsrw_source_metadata_' . $media_id );
	}

	/**
	 * Get URL mappings with nearest size fallbacks.
	 *
	 * @param array $source_metadata Source image metadata.
	 * @param array $target_metadata Target image metadata.
	 * @return array Array of old_url => new_url mappings.
	 */
	protected function get_url_mappings_with_nearest_sizes( $source_metadata, $target_metadata ) {
		$mappings = array();

		if ( empty( $source_metadata ) || empty( $target_metadata ) ) {
			// Fallback to simple URL replacement.
			$mappings[ $this->old_file_url ] = $this->new_file_url;
			return $mappings;
		}

		// Let the Pro class swap the extension when extension replacement is enabled.
		$final_new_url = $this->maybe_replace_extension_in_url( $this->old_file_url, $this->new_file_url );

		// Get base URLs for both source and target.
		$source_base_url = dirname( $this->old_file_url ) . '/';
		$target_base_url = dirname( $final_new_url ) . '/';

		// Add main file mapping.
		$mappings[ $this->old_file_url ] = $final_new_url;

		// Get source sizes.
		$source_sizes = isset( $source_metadata['sizes'] ) ? $source_metadata['sizes'] : array();
		$target_sizes = isset( $target_metadata['sizes'] ) ? $target_metadata['sizes'] : array();

		// Process each source size.
		foreach ( $source_sizes as $size_name => $size_data ) {
			if ( ! isset( $size_data['file'] ) ) {
				continue;
			}

			$source_size_url = $source_base_url . $size_data['file'];

			// Check if this size exists in target.
			if ( isset( $target_sizes[ $size_name ]['file'] ) ) {
				// Direct mapping exists.
				$target_size_file = $target_sizes[ $size_name ]['file'];

				// Let the Pro class swap the extension for size files.
				$target_size_file = $this->maybe_replace_extension_in_filename( $target_size_file, $final_new_url );

				$target_size_url              = $target_base_url . $target_size_file;
				$mappings[ $source_size_url ] = $target_size_url;
			} else {
				// Find nearest size.
				$nearest_file = $this->find_nearest_size( $size_name, $source_metadata, $target_metadata );
				if ( $nearest_file ) {
					// Let the Pro class swap the extension for the nearest file.
					$nearest_file = $this->maybe_replace_extension_in_filename( $nearest_file, $final_new_url );

					$target_size_url              = $target_base_url . $nearest_file;
					$mappings[ $source_size_url ] = $target_size_url;
				} else {
					// Fallback to main image.
					$mappings[ $source_size_url ] = $final_new_url;
				}
			}
		}

		return $mappings;
	}

	/**
	 * Adjust the mapped URL when extension replacement is enabled.
	 * No-op here, the Pro class overrides this to swap the extension.
	 *
	 * @param string $old_url The old URL.
	 * @param string $new_url The new URL.
	 * @return string The URL to use in mappings.
	 */
	protected function maybe_replace_extension_in_url( $old_url, $new_url ) {
		return $new_url;
	}

	/**
	 * Adjust a size filename when extension replacement is enabled.
	 * No-op here, the Pro class overrides this to swap the extension.
	 *
	 * @param string $filename The size filename.
	 * @param string $reference_url URL to get the new extension from.
	 * @return string The filename to use in mappings.
	 */
	protected function maybe_replace_extension_in_filename( $filename, $reference_url ) {
		return $filename;
	}

	/**
	 * Find the nearest image size based on width comparison.
	 *
	 * This is based on the findNearestSize method from the Replacer class.
	 *
	 * @param string $size_name The size name to find a replacement for.
	 * @param array  $source_metadata Source image metadata.
	 * @param array  $target_metadata Target image metadata.
	 * @return string|false The filename of the nearest size or false if not found.
	 */
	protected function find_nearest_size( $size_name, $source_metadata, $target_metadata ) {
		// Check if we have the required data.
		if ( ! isset( $source_metadata['sizes'][ $size_name ] ) || ! isset( $target_metadata['width'] ) ) {
			// Check if target is SVG (SVGs don't need thumbnails).
			if ( false !== strpos( $this->new_file_url, '.svg' ) ) {
				return basename( $this->new_file_url );
			}
			return false;
		}

		$old_width = $source_metadata['sizes'][ $size_name ]['width']; // Width from size not in new image.
		$new_width = $target_metadata['width']; // Default check - width of main image.

		$diff         = abs( $old_width - $new_width );
		$closest_file = basename( $target_metadata['file'] ); // Main file as default.

		// Check target sizes for closer match.
		$target_sizes = isset( $target_metadata['sizes'] ) ? $target_metadata['sizes'] : array();

		foreach ( $target_sizes as $target_size_name => $target_size_data ) {
			if ( ! isset( $target_size_data['width'] ) || ! isset( $target_size_data['file'] ) ) {
				continue;
			}

			$this_diff = abs( $old_width - $target_size_data['width'] );

			if ( $this_diff < $diff ) {
				$closest_file = $target_size_data['file'];

				// Handle array case (some plugins might return arrays).
				if ( is_array( $closest_file ) ) {
					$closest_file = $closest_file[0];
				}

				if ( ! empty( $closest_file ) ) {
					$diff = $this_diff;
				}
			}
		}

		return empty( $closest_file ) ? false : $closest_file;
	}

	/**
	 * Check if a URL is an image URL based on its extension.
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the URL is an image URL, false otherwise.
	 */
	protected function is_image_url( $url ) {
		$image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' );
		$extension        = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
		return in_array( $extension, $image_extensions, true );
	}

	/**
	 * Get different variations of a URL that might be stored in the database.
	 *
	 * @param string $url The original URL.
	 * @return array Array of URL variations.
	 */
	protected function get_url_variations( $url ) {
		$variations = array( $url );

		// Add URL without domain (relative URL).
		global $wpdb;
		$site_url = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'siteurl'" ); // phpcs:ignore

		if ( $site_url && 0 === strpos( $url, $site_url ) ) {
			$variations[] = str_replace( $site_url, '', $url );
		}

		// Add URL with different protocols.
		if ( 0 === strpos( $url, 'https://' ) ) {
			$variations[] = str_replace( 'https://', 'http://', $url );
		} elseif ( 0 === strpos( $url, 'http://' ) ) {
			$variations[] = str_replace( 'http://', 'https://', $url );
		}

		// Add URL without protocol.
		$variations[] = preg_replace( '#^https?://#', '//', $url );

		// Add URL encoded variation.
		$variations[] = rawurlencode( $url );

		return array_unique( $variations );
	}

	/**
	 * Replace old file URL with new file URL in serialized data.
	 *
	 * @param string $serialized_data The serialized data.
	 * @param array  $old_urls Array of old URLs to replace.
	 * @param string $new_url The new URL.
	 * @return string The updated serialized data.
	 */
	protected function replace_in_serialized( $serialized_data, $old_urls, $new_url ) {
		// Require the search and replace class when needed.
		if ( ! class_exists( 'WSRW_Search_Replace' ) ) {
			require_once WSRW_PLUGIN_PATH . 'includes/class-wsrw-search-replace.php';
		}

		// Use proper error handling instead of silencing errors.
		if ( ! preg_match( '/^[aOs]:/', $serialized_data ) ) {
			return $serialized_data;
		}

		// Never instantiate objects: URL replacement only touches arrays and strings, and the
		// scanned data can be attacker-planted post content, so block object injection.
		$unserialized = unserialize( $serialized_data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		if ( false === $unserialized ) {
			return $serialized_data;
		}

		$search_replace = new WSRW_Search_Replace();

		// Use provided URLs or class properties.
		$old_urls = ( null !== $old_urls ) ? $old_urls : array( $this->old_file_url );
		$new_url  = ( null !== $new_url ) ? $new_url : $this->new_file_url;

		$replaced = $unserialized;

		foreach ( $old_urls as $old_url ) {
			$replaced = $search_replace->array_replace_recursive(
				$old_url,
				$new_url,
				$replaced
			);
		}

		// Using serialize is necessary here as we're handling serialized data.
		return serialize( $replaced ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Replace old file URL with new file URL in JSON data.
	 *
	 * @param string $json_data The JSON data.
	 * @param array  $old_urls Array of old URLs to replace.
	 * @param string $new_url The new URL.
	 * @return string The updated JSON data.
	 */
	protected function replace_in_json( $json_data, $old_urls, $new_url ) {
		// Require the search and replace class when needed.
		if ( ! class_exists( 'WSRW_Search_Replace' ) ) {
			require_once WSRW_PLUGIN_PATH . 'includes/class-wsrw-search-replace.php';
		}

		$decoded = json_decode( $json_data, true );
		if ( null === $decoded ) {
			return $json_data;
		}

		$search_replace = new WSRW_Search_Replace();

		// Use provided URLs or class properties.
		$old_urls = ( null !== $old_urls ) ? $old_urls : array( $this->old_file_url );
		$new_url  = ( null !== $new_url ) ? $new_url : $this->new_file_url;

		$replaced = $decoded;

		foreach ( $old_urls as $old_url ) {
			$replaced = $search_replace->array_replace_recursive(
				$old_url,
				$new_url,
				$replaced
			);
		}

		return wp_json_encode( $replaced );
	}

	/**
	 * Check if a string is valid JSON.
	 *
	 * @param mixed $data The data to check for JSON validity.
	 * @return bool Whether the data is valid JSON.
	 */
	private function is_json( $data ) {
		if ( ! is_string( $data ) ) {
			return false;
		}

		json_decode( $data );
		return JSON_ERROR_NONE === json_last_error();
	}

}
