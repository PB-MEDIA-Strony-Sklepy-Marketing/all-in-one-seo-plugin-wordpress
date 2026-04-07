<?php
/**
 * Plugin settings page (left sidebar in wp-admin).
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const MENU_SLUG    = 'pb-media-seo';
	public const OPTION_GROUP = 'pb_media_all_seo_group';

	public const OPT_FB_APP_ID  = PB_MEDIA_ALL_SEO_OPTION_PREFIX . 'fb_app_id';
	public const OPT_ROBOTS_TXT = PB_MEDIA_ALL_SEO_OPTION_PREFIX . 'robots_txt';
	public const OPT_LLMS_TXT   = PB_MEDIA_ALL_SEO_OPTION_PREFIX . 'llms_txt';

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu(): void {
		add_menu_page(
			esc_html__( 'PB MEDIA SEO', 'pb-media-all-seo' ),
			esc_html__( 'PB MEDIA SEO', 'pb-media-all-seo' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ],
			'dashicons-search',
			80
		);
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPT_FB_APP_ID,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_fb_app_id' ],
				'default'           => '',
			]
		);
		register_setting(
			self::OPTION_GROUP,
			self::OPT_ROBOTS_TXT,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_textarea_plain' ],
				'default'           => '',
			]
		);
		register_setting(
			self::OPTION_GROUP,
			self::OPT_LLMS_TXT,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_textarea_plain' ],
				'default'           => '',
			]
		);
	}

	public function sanitize_fb_app_id( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_replace( '/[^0-9]/', '', $value ) ?? '';
	}

	public function sanitize_textarea_plain( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = str_replace( [ "\r\n", "\r" ], "\n", $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
		return (string) $value;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			flush_rewrite_rules( false );
		}

		$site_url   = trailingslashit( home_url() );
		$fb_app_id  = (string) get_option( self::OPT_FB_APP_ID, '' );
		$robots_txt = (string) get_option( self::OPT_ROBOTS_TXT, $this->default_robots_txt() );
		$llms_txt   = (string) get_option( self::OPT_LLMS_TXT, $this->default_llms_txt() );

		// Sitemap regeneration notice.
		$notice = get_transient( 'pb_seo_sitemap_notice_' . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( 'pb_seo_sitemap_notice_' . get_current_user_id() );
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( (string) ( $notice['type'] ?? 'info' ) ),
				esc_html( (string) ( $notice['msg'] ?? '' ) )
			);
		}

		// Physical file status.
		$pages_path  = Sitemap::get_file_path( Sitemap::FILE_PAGES );
		$images_path = Sitemap::get_file_path( Sitemap::FILE_IMAGES );
		$pages_ok    = Sitemap::file_exists( Sitemap::FILE_PAGES );
		$images_ok   = Sitemap::file_exists( Sitemap::FILE_IMAGES );
		$doc_root_writable = is_writable( ABSPATH );
		?>
		<div class="wrap pb-seo-settings">
			<h1><?php esc_html_e( 'PB MEDIA SEO — Ustawienia', 'pb-media-all-seo' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Kompletne ustawienia SEO: Facebook App ID, sitemapy XML, robots.txt, llm.txt.', 'pb-media-all-seo' ); ?>
			</p>

			<form action="options.php" method="post">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2><?php esc_html_e( 'Facebook App ID', 'pb-media-all-seo' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( self::OPT_FB_APP_ID ); ?>">fb:app_id</label>
						</th>
						<td>
							<input type="text"
								id="<?php echo esc_attr( self::OPT_FB_APP_ID ); ?>"
								name="<?php echo esc_attr( self::OPT_FB_APP_ID ); ?>"
								value="<?php echo esc_attr( $fb_app_id ); ?>"
								class="regular-text"
								placeholder="123456789012345" />
							<p class="description">
								<?php esc_html_e( 'Globalne dla całej instalacji. Renderowane jako <meta property="fb:app_id"> w sekcji <head>.', 'pb-media-all-seo' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sitemap XML — Strony i Obrazy', 'pb-media-all-seo' ); ?></h2>
				<p>
					<?php esc_html_e( 'Sitemapy są generowane jako fizyczne pliki XML w document root i serwowane bezpośrednio przez serwer www (z prawidłowym Content-Type). Współistnieją obok wbudowanej wp-sitemap.xml.', 'pb-media-all-seo' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Status plików', 'pb-media-all-seo' ); ?></th>
						<td>
							<p>
								<strong><?php echo esc_html( Sitemap::FILE_PAGES ); ?>:</strong>
								<?php if ( $pages_ok ) : ?>
									<span style="color:#00a32a;">✓ <?php esc_html_e( 'istnieje', 'pb-media-all-seo' ); ?></span>
									<a href="<?php echo esc_url( Sitemap::get_file_url( Sitemap::FILE_PAGES ) ); ?>" target="_blank">
										<?php esc_html_e( 'Otwórz', 'pb-media-all-seo' ); ?>
									</a>
								<?php else : ?>
									<span style="color:#d63638;">✗ <?php esc_html_e( 'nie istnieje', 'pb-media-all-seo' ); ?></span>
								<?php endif; ?>
								<br />
								<code><?php echo esc_html( $pages_path ); ?></code>
							</p>
							<p>
								<strong><?php echo esc_html( Sitemap::FILE_IMAGES ); ?>:</strong>
								<?php if ( $images_ok ) : ?>
									<span style="color:#00a32a;">✓ <?php esc_html_e( 'istnieje', 'pb-media-all-seo' ); ?></span>
									<a href="<?php echo esc_url( Sitemap::get_file_url( Sitemap::FILE_IMAGES ) ); ?>" target="_blank">
										<?php esc_html_e( 'Otwórz', 'pb-media-all-seo' ); ?>
									</a>
								<?php else : ?>
									<span style="color:#d63638;">✗ <?php esc_html_e( 'nie istnieje', 'pb-media-all-seo' ); ?></span>
								<?php endif; ?>
								<br />
								<code><?php echo esc_html( $images_path ); ?></code>
							</p>
							<p>
								<strong><?php esc_html_e( 'Katalog document root zapisywalny:', 'pb-media-all-seo' ); ?></strong>
								<?php if ( $doc_root_writable ) : ?>
									<span style="color:#00a32a;">✓ <?php esc_html_e( 'tak', 'pb-media-all-seo' ); ?></span>
								<?php else : ?>
									<span style="color:#d63638;">✗ <?php esc_html_e( 'nie — sprawdź uprawnienia', 'pb-media-all-seo' ); ?></span>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'robots.txt', 'pb-media-all-seo' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( self::OPT_ROBOTS_TXT ); ?>">robots.txt</label>
						</th>
						<td>
							<textarea id="<?php echo esc_attr( self::OPT_ROBOTS_TXT ); ?>"
								name="<?php echo esc_attr( self::OPT_ROBOTS_TXT ); ?>"
								rows="12"
								class="large-text code"><?php echo esc_textarea( $robots_txt ); ?></textarea>
							<p class="description">
								<?php
								printf(
									/* translators: %s: full robots.txt URL */
									esc_html__( 'Serwowane pod adresem: %s', 'pb-media-all-seo' ),
									'<code>' . esc_html( $site_url . 'robots.txt' ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'llm.txt', 'pb-media-all-seo' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( self::OPT_LLMS_TXT ); ?>">llm.txt</label>
						</th>
						<td>
							<textarea id="<?php echo esc_attr( self::OPT_LLMS_TXT ); ?>"
								name="<?php echo esc_attr( self::OPT_LLMS_TXT ); ?>"
								rows="14"
								class="large-text code"><?php echo esc_textarea( $llms_txt ); ?></textarea>
							<p class="description">
								<?php
								printf(
									/* translators: %s: full llm.txt URL */
									esc_html__( 'Serwowane pod adresem: %s — format Markdown zgodny z llmstxt.org.', 'pb-media-all-seo' ),
									'<code>' . esc_html( $site_url . 'llm.txt' ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Ręczna regeneracja sitemap', 'pb-media-all-seo' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Klikając poniższy przycisk wymusisz natychmiastowe wygenerowanie obu plików sitemap. Pliki są też regenerowane automatycznie po każdej publikacji/edycji wpisu lub załącznika.', 'pb-media-all-seo' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pb_seo_regenerate_sitemap' ); ?>
				<input type="hidden" name="action" value="pb_seo_regenerate_sitemap" />
				<?php submit_button( __( 'Wygeneruj sitemapy teraz', 'pb-media-all-seo' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	private function default_robots_txt(): string {
		$site_url = trailingslashit( home_url() );
		return "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: " . $site_url . "wp-sitemap.xml\nSitemap: " . $site_url . "sitemapa.xml\nSitemap: " . $site_url . "sitemapa-image.xml\n";
	}

	private function default_llms_txt(): string {
		$site_name = (string) get_bloginfo( 'name' );
		$site_desc = (string) get_bloginfo( 'description' );
		$site_url  = trailingslashit( home_url() );
		return "# {$site_name}\n\n> {$site_desc}\n\n## Resources\n\n- [Strona główna]({$site_url})\n- [Sitemapa stron]({$site_url}sitemapa.xml)\n- [Sitemapa obrazów]({$site_url}sitemapa-image.xml)\n";
	}
}
