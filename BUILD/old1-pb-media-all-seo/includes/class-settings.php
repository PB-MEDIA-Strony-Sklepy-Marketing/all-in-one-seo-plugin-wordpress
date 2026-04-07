<?php
/**
 * Plugin settings page (left sidebar in wp-admin).
 *
 * Sections:
 *  - Facebook App ID
 *  - Sitemap XML — Strony       (link to /sitemapa.xml)
 *  - Sitemap XML — Obrazy       (link to /sitemapa-image.xml)
 *  - Robots.txt content
 *  - LLM.txt content
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const MENU_SLUG  = 'pb-media-seo';
	public const OPTION_GROUP = 'pb_media_all_seo_group';

	public const OPT_FB_APP_ID = PB_MEDIA_ALL_SEO_OPTION_PREFIX . 'fb_app_id';
	public const OPT_ROBOTS_TXT = PB_MEDIA_ALL_SEO_OPTION_PREFIX . 'robots_txt';
	public const OPT_LLMS_TXT  = PB_MEDIA_ALL_SEO_OPTION_PREFIX . 'llms_txt';

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
		// fb:app_id is numeric.
		return preg_replace( '/[^0-9]/', '', $value ) ?? '';
	}

	public function sanitize_textarea_plain( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		// Preserve plain text including newlines, just normalize line endings.
		$value = str_replace( [ "\r\n", "\r" ], "\n", $value );
		// Strip control chars except \n and \t.
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
		return (string) $value;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Schedule a rewrite-rules flush after option update (for robots/llms slugs).
		if ( isset( $_GET['settings-updated'] ) ) {
			flush_rewrite_rules( false );
			delete_transient( 'pb_seo_sitemap_pages' );
			delete_transient( 'pb_seo_sitemap_images' );
		}

		$site_url    = trailingslashit( home_url() );
		$fb_app_id   = (string) get_option( self::OPT_FB_APP_ID, '' );
		$robots_txt  = (string) get_option( self::OPT_ROBOTS_TXT, $this->default_robots_txt() );
		$llms_txt    = (string) get_option( self::OPT_LLMS_TXT, $this->default_llms_txt() );
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

				<h2><?php esc_html_e( 'Sitemap XML — Strony', 'pb-media-all-seo' ); ?></h2>
				<p>
					<?php esc_html_e( 'Automatycznie generowana sitemapa wszystkich publicznych post_type. Współistnieje obok wbudowanej wp-sitemap.xml.', 'pb-media-all-seo' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( $site_url . 'sitemapa.xml' ); ?>" target="_blank" class="button button-secondary">
						<?php esc_html_e( 'Przejdź do podglądu sitemapa.xml', 'pb-media-all-seo' ); ?>
					</a>
					<code><?php echo esc_html( $site_url . 'sitemapa.xml' ); ?></code>
				</p>

				<h2><?php esc_html_e( 'Sitemap XML — Obrazy', 'pb-media-all-seo' ); ?></h2>
				<p>
					<?php esc_html_e( 'Automatycznie generowana sitemapa wszystkich załączników (obrazów) z biblioteki mediów.', 'pb-media-all-seo' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( $site_url . 'sitemapa-image.xml' ); ?>" target="_blank" class="button button-secondary">
						<?php esc_html_e( 'Przejdź do podglądu sitemapa-image.xml', 'pb-media-all-seo' ); ?>
					</a>
					<code><?php echo esc_html( $site_url . 'sitemapa-image.xml' ); ?></code>
				</p>

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
