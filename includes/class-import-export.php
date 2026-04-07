<?php
/**
 * JSON import / export of plugin settings.
 *
 * Exports:
 *  - All `pb_seo_*` global options (fb:app_id, robots.txt, llms.txt)
 *  - All `_pb_seo_*` post meta for every post
 *
 * Imports:
 *  - Validates the JSON envelope (version + payload)
 *  - Restores global options
 *  - Restores per-post meta keyed by post ID + slug fallback
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Import_Export {

	public const MENU_SLUG     = 'pb-media-seo-io';
	private const NONCE_ACTION = 'pb_seo_io';
	public const VERSION       = '1.0';

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 30 );
		add_action( 'admin_post_pb_seo_export', [ $this, 'handle_export' ] );
		add_action( 'admin_post_pb_seo_import', [ $this, 'handle_import' ] );
	}

	public function add_submenu(): void {
		add_submenu_page(
			Settings::MENU_SLUG,
			esc_html__( 'Import / Export SEO', 'pb-media-all-seo' ),
			esc_html__( 'Import / Export', 'pb-media-all-seo' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_transient( 'pb_seo_io_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'pb_seo_io_notice_' . get_current_user_id() );
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( (string) $notice['type'] ),
				esc_html( (string) $notice['msg'] )
			);
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import / Export SEO', 'pb-media-all-seo' ); ?></h1>

			<h2><?php esc_html_e( 'Eksport', 'pb-media-all-seo' ); ?></h2>
			<p><?php esc_html_e( 'Pobierz plik JSON ze wszystkimi ustawieniami pluginu i meta SEO wszystkich wpisów.', 'pb-media-all-seo' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="pb_seo_export" />
				<?php submit_button( __( 'Pobierz plik JSON', 'pb-media-all-seo' ), 'primary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Import', 'pb-media-all-seo' ); ?></h2>
			<p><?php esc_html_e( 'Wczytaj plik JSON wyeksportowany z innej instalacji.', 'pb-media-all-seo' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="pb_seo_import" />
				<input type="file" name="pb_seo_import_file" accept="application/json,.json" required />
				<p>
					<label>
						<input type="checkbox" name="overwrite_meta" value="1" checked />
						<?php esc_html_e( 'Nadpisz istniejące meta SEO wpisów', 'pb-media-all-seo' ); ?>
					</label>
				</p>
				<?php submit_button( __( 'Importuj', 'pb-media-all-seo' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Export
	 * ------------------------------------------------------------------ */

	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'pb-media-all-seo' ), 403 );
		}
		check_admin_referer( self::NONCE_ACTION );

		global $wpdb;

		$options_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( PB_MEDIA_ALL_SEO_OPTION_PREFIX ) . '%'
			),
			ARRAY_A
		);
		$options = [];
		foreach ( (array) $options_raw as $row ) {
			$options[ $row['option_name'] ] = $row['option_value'];
		}

		$meta_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_name, p.post_type
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key LIKE %s",
				$wpdb->esc_like( PB_MEDIA_ALL_SEO_META_PREFIX ) . '%'
			),
			ARRAY_A
		);

		$post_meta = [];
		foreach ( (array) $meta_raw as $row ) {
			$pid = (int) $row['post_id'];
			if ( ! isset( $post_meta[ $pid ] ) ) {
				$post_meta[ $pid ] = [
					'post_id'   => $pid,
					'post_name' => (string) $row['post_name'],
					'post_type' => (string) $row['post_type'],
					'meta'      => [],
				];
			}
			$post_meta[ $pid ]['meta'][ (string) $row['meta_key'] ] = (string) $row['meta_value'];
		}

		$envelope = [
			'plugin'      => 'pb-media-all-seo',
			'version'     => self::VERSION,
			'exported_at' => gmdate( 'c' ),
			'site_url'    => home_url(),
			'options'     => $options,
			'post_meta'   => array_values( $post_meta ),
		];

		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
		}
		if ( ! headers_sent() ) {
			@header_remove( 'Content-Type' );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="pb-media-all-seo-export-' . gmdate( 'Y-m-d-His' ) . '.json"' );

		echo wp_json_encode( $envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Import
	 * ------------------------------------------------------------------ */

	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'pb-media-all-seo' ), 403 );
		}
		check_admin_referer( self::NONCE_ACTION );

		if ( ! isset( $_FILES['pb_seo_import_file'] ) || ! is_array( $_FILES['pb_seo_import_file'] ) ) {
			$this->notice( 'error', __( 'Brak pliku.', 'pb-media-all-seo' ) );
			$this->redirect();
		}

		$file = $_FILES['pb_seo_import_file'];
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$this->notice( 'error', __( 'Błąd uploadu.', 'pb-media-all-seo' ) );
			$this->redirect();
		}
		$tmp = (string) ( $file['tmp_name'] ?? '' );
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			$this->notice( 'error', __( 'Nieprawidłowy plik.', 'pb-media-all-seo' ) );
			$this->redirect();
		}

		$contents = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			$this->notice( 'error', __( 'Nie udało się odczytać pliku.', 'pb-media-all-seo' ) );
			$this->redirect();
		}

		$decoded = json_decode( $contents, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			$this->notice( 'error', __( 'Plik nie zawiera prawidłowego JSON.', 'pb-media-all-seo' ) );
			$this->redirect();
		}

		if ( ( $decoded['plugin'] ?? '' ) !== 'pb-media-all-seo' ) {
			$this->notice( 'error', __( 'Plik nie pochodzi z PB MEDIA ALL SEO.', 'pb-media-all-seo' ) );
			$this->redirect();
		}

		$overwrite = isset( $_POST['overwrite_meta'] );

		// 1. Restore global options.
		$opt_count = 0;
		if ( isset( $decoded['options'] ) && is_array( $decoded['options'] ) ) {
			$allowed_opts = [
				Settings::OPT_FB_APP_ID,
				Settings::OPT_ROBOTS_TXT,
				Settings::OPT_LLMS_TXT,
			];
			foreach ( $decoded['options'] as $name => $value ) {
				if ( ! in_array( (string) $name, $allowed_opts, true ) ) {
					continue;
				}
				update_option( (string) $name, (string) $value );
				++$opt_count;
			}
		}

		// 2. Restore post meta.
		$meta_count = 0;
		$skipped    = 0;
		if ( isset( $decoded['post_meta'] ) && is_array( $decoded['post_meta'] ) ) {
			foreach ( $decoded['post_meta'] as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$pid       = (int) ( $entry['post_id'] ?? 0 );
				$post_name = (string) ( $entry['post_name'] ?? '' );
				$post_type = (string) ( $entry['post_type'] ?? '' );
				$meta      = (array) ( $entry['meta'] ?? [] );

				$target_id = $this->resolve_post_id( $pid, $post_name, $post_type );
				if ( $target_id <= 0 ) {
					++$skipped;
					continue;
				}
				if ( ! current_user_can( 'edit_post', $target_id ) ) {
					++$skipped;
					continue;
				}

				foreach ( $meta as $key => $value ) {
					$key = (string) $key;
					if ( strpos( $key, PB_MEDIA_ALL_SEO_META_PREFIX ) !== 0 ) {
						continue;
					}
					if ( ! $overwrite && '' !== (string) get_post_meta( $target_id, $key, true ) ) {
						continue;
					}
					update_post_meta( $target_id, $key, (string) $value );
					++$meta_count;
				}
			}
		}

		delete_transient( 'pb_seo_sitemap_pages' );
		delete_transient( 'pb_seo_sitemap_images' );

		$this->notice(
			'success',
			sprintf(
				/* translators: 1: options, 2: meta entries, 3: skipped */
				__( 'Import zakończony. Opcji: %1$d, meta: %2$d, pominiętych: %3$d.', 'pb-media-all-seo' ),
				$opt_count,
				$meta_count,
				$skipped
			)
		);
		$this->redirect();
	}

	private function resolve_post_id( int $pid, string $slug, string $post_type ): int {
		if ( $pid > 0 && get_post( $pid ) instanceof \WP_Post ) {
			return $pid;
		}
		if ( '' !== $slug && '' !== $post_type ) {
			$found = get_page_by_path( $slug, OBJECT, $post_type );
			if ( $found instanceof \WP_Post ) {
				return (int) $found->ID;
			}
		}
		return 0;
	}

	private function notice( string $type, string $msg ): void {
		set_transient(
			'pb_seo_io_notice_' . get_current_user_id(),
			[ 'type' => $type, 'msg' => $msg ],
			60
		);
	}

	private function redirect(): void {
		wp_safe_redirect(
			add_query_arg( 'page', self::MENU_SLUG, admin_url( 'admin.php' ) )
		);
		exit;
	}
}
