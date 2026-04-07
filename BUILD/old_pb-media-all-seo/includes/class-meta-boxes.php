<?php
/**
 * Meta Boxes for post edit screen.
 *
 * Provides three meta boxes:
 *  - Meta Tagi SEO
 *  - Tagi OpenGraph
 *  - Schema JSON SEO
 *
 * Uses standard add_meta_box() API which is fully compatible with both
 * the Classic Editor and Gutenberg (block editor).
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Meta_Boxes {

	private const NONCE_ACTION = 'pb_seo_save_meta';
	private const NONCE_NAME   = 'pb_seo_meta_nonce';

	/**
	 * Allowed values for the robots meta dropdown.
	 *
	 * @var array<int,string>
	 */
	private const ROBOTS_OPTIONS = [
		'index',
		'noindex',
		'follow',
		'nofollow',
		'noarchive',
		'nosnippet',
	];

	/**
	 * Allowed values for og:type dropdown.
	 *
	 * @var array<int,string>
	 */
	private const OG_TYPE_OPTIONS = [
		'website',
		'article',
		'blog',
		'product',
	];

	/**
	 * Field keys (without prefix) used as post-meta and form names.
	 *
	 * @var array<int,string>
	 */
	private const SEO_FIELDS = [
		'pageTitleSEO',
		'pageDescriptionSEO',
		'pageKeywordsSEO',
		'pageAuthorSEO',
		'pageCanonicalURLSEO',
		'pageRobotsSEO',
		'pageCustomMetaTagSEO',
	];

	/**
	 * @var array<int,string>
	 */
	private const OG_FIELDS = [
		'pageOGtitle',
		'pageOGdescription',
		'pageOGimage',
		'pageOGimagealt',
		'pageOGtype',
		'pageOGurl',
		'pageOGsitename',
		'pageOGCustomTag',
	];

	private const SCHEMA_FIELD = 'pageSchemaJSON';

	public function register_hooks(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_boxes' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
	}

	/**
	 * Register meta boxes for every supported post type.
	 */
	public function add_meta_boxes(): void {
		$post_types = Plugin::get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'pb_seo_meta_tags',
				esc_html__( 'PB MEDIA — Meta Tagi SEO', 'pb-media-all-seo' ),
				[ $this, 'render_seo_meta_box' ],
				$post_type,
				'normal',
				'high'
			);

			add_meta_box(
				'pb_seo_opengraph',
				esc_html__( 'PB MEDIA — Tagi OpenGraph', 'pb-media-all-seo' ),
				[ $this, 'render_og_meta_box' ],
				$post_type,
				'normal',
				'high'
			);

			add_meta_box(
				'pb_seo_schema_json',
				esc_html__( 'PB MEDIA — Schema JSON SEO (JSON-LD)', 'pb-media-all-seo' ),
				[ $this, 'render_schema_meta_box' ],
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Enqueue admin styles only on the post edit screens.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		wp_enqueue_style(
			'pb-media-all-seo-admin',
			PB_MEDIA_ALL_SEO_URL . 'admin/css/admin.css',
			[],
			PB_MEDIA_ALL_SEO_VERSION
		);
	}

	/* ---------------------------------------------------------------------
	 * Meta box renderers
	 * ------------------------------------------------------------------ */

	public function render_seo_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$values = $this->get_field_values( $post->ID, self::SEO_FIELDS );
		$users  = get_users(
			[
				'fields'  => [ 'ID', 'display_name' ],
				'orderby' => 'display_name',
				'order'   => 'ASC',
			]
		);
		?>
		<div class="pb-seo-wrap">
			<p class="pb-seo-row">
				<label for="pb_seo_pageTitleSEO"><strong><?php esc_html_e( 'Title SEO', 'pb-media-all-seo' ); ?></strong></label>
				<input type="text" id="pb_seo_pageTitleSEO" name="pb_seo[pageTitleSEO]"
					value="<?php echo esc_attr( $values['pageTitleSEO'] ); ?>"
					placeholder="<?php esc_attr_e( 'Wpisz tytuł dla tej strony SEO', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageDescriptionSEO"><strong><?php esc_html_e( 'Meta Description SEO', 'pb-media-all-seo' ); ?></strong></label>
				<input type="text" id="pb_seo_pageDescriptionSEO" name="pb_seo[pageDescriptionSEO]"
					value="<?php echo esc_attr( $values['pageDescriptionSEO'] ); ?>"
					placeholder="<?php esc_attr_e( 'Wpisz opis dla tej strony SEO', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageKeywordsSEO"><strong><?php esc_html_e( 'Meta Keywords SEO', 'pb-media-all-seo' ); ?></strong></label>
				<input type="text" id="pb_seo_pageKeywordsSEO" name="pb_seo[pageKeywordsSEO]"
					value="<?php echo esc_attr( $values['pageKeywordsSEO'] ); ?>"
					placeholder="<?php esc_attr_e( 'Wpisz słowa kluczowe po przecinku i ze spacją dla tej strony SEO', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageAuthorSEO"><strong><?php esc_html_e( 'Meta Author SEO', 'pb-media-all-seo' ); ?></strong></label>
				<select id="pb_seo_pageAuthorSEO" name="pb_seo[pageAuthorSEO]" class="widefat">
					<option value=""><?php esc_html_e( '— Domyślny autor wpisu —', 'pb-media-all-seo' ); ?></option>
					<?php foreach ( $users as $user ) : ?>
						<option value="<?php echo esc_attr( (string) $user->ID ); ?>"
							<?php selected( $values['pageAuthorSEO'], (string) $user->ID ); ?>>
							<?php echo esc_html( $user->display_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageCanonicalURLSEO"><strong><?php esc_html_e( 'Meta Canonical URL SEO', 'pb-media-all-seo' ); ?></strong></label>
				<input type="url" id="pb_seo_pageCanonicalURLSEO" name="pb_seo[pageCanonicalURLSEO]"
					value="<?php echo esc_attr( $values['pageCanonicalURLSEO'] ); ?>"
					placeholder="<?php esc_attr_e( 'Podaj cały kanoniczny adres URL strony lub pozostaw puste to zostanie użyty domyślny URL tej strony', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageRobotsSEO"><strong><?php esc_html_e( 'Meta Tag Robots SEO', 'pb-media-all-seo' ); ?></strong></label>
				<select id="pb_seo_pageRobotsSEO" name="pb_seo[pageRobotsSEO]" class="widefat">
					<option value=""><?php esc_html_e( '— Domyślne (brak) —', 'pb-media-all-seo' ); ?></option>
					<?php foreach ( self::ROBOTS_OPTIONS as $opt ) : ?>
						<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $values['pageRobotsSEO'], $opt ); ?>>
							<?php echo esc_html( $opt ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageCustomMetaTagSEO"><strong><?php esc_html_e( 'Add Custom Meta Tags SEO', 'pb-media-all-seo' ); ?></strong></label>
				<textarea id="pb_seo_pageCustomMetaTagSEO" name="pb_seo[pageCustomMetaTagSEO]"
					rows="6" class="widefat code"
					placeholder="<?php esc_attr_e( 'Wpisz kodem HTML dodatkowe niestandardowe Meta Tagi HTML SEO dla tej konkretnej strony. Stosuj przycisk Enter jako separator między znacznikami. Jeden Meta Tag per line.', 'pb-media-all-seo' ); ?>"><?php
					echo esc_textarea( $values['pageCustomMetaTagSEO'] );
				?></textarea>
				<span class="description"><?php esc_html_e( 'Dozwolone są tylko tagi <meta> oraz <link>.', 'pb-media-all-seo' ); ?></span>
			</p>
		</div>
		<?php
	}

	public function render_og_meta_box( \WP_Post $post ): void {
		// Nonce already printed by SEO meta box on the same screen, but we
		// re-print under a unique field name in case meta boxes are reordered/hidden.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME . '_og' );

		$values  = $this->get_field_values( $post->ID, self::OG_FIELDS );
		$locale  = get_locale();
		?>
		<div class="pb-seo-wrap">
			<p class="pb-seo-row">
				<label for="pb_seo_pageOGtitle"><strong>og:title</strong></label>
				<input type="text" id="pb_seo_pageOGtitle" name="pb_seo[pageOGtitle]"
					value="<?php echo esc_attr( $values['pageOGtitle'] ); ?>"
					placeholder="<?php esc_attr_e( 'Tytuł OpenGraph (puste = tytuł posta)', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageOGdescription"><strong>og:description</strong></label>
				<input type="text" id="pb_seo_pageOGdescription" name="pb_seo[pageOGdescription]"
					value="<?php echo esc_attr( $values['pageOGdescription'] ); ?>"
					placeholder="<?php esc_attr_e( 'Opis OpenGraph (puste = excerpt)', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageOGimage"><strong>og:image</strong></label>
				<input type="url" id="pb_seo_pageOGimage" name="pb_seo[pageOGimage]"
					value="<?php echo esc_attr( $values['pageOGimage'] ); ?>"
					placeholder="<?php esc_attr_e( 'Pełny URL obrazka (puste = featured image)', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageOGimagealt"><strong>og:image:alt</strong></label>
				<input type="text" id="pb_seo_pageOGimagealt" name="pb_seo[pageOGimagealt]"
					value="<?php echo esc_attr( $values['pageOGimagealt'] ); ?>"
					placeholder="<?php esc_attr_e( 'Opis alternatywny obrazka (puste = alt z galerii)', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label><strong>og:locale</strong></label>
				<input type="text" value="<?php echo esc_attr( $locale ); ?>" class="widefat" readonly />
				<span class="description"><?php esc_html_e( 'Automatycznie z ustawień WordPressa.', 'pb-media-all-seo' ); ?></span>
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageOGtype"><strong>og:type</strong></label>
				<select id="pb_seo_pageOGtype" name="pb_seo[pageOGtype]" class="widefat">
					<?php foreach ( self::OG_TYPE_OPTIONS as $opt ) : ?>
						<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $values['pageOGtype'] ?: 'article', $opt ); ?>>
							<?php echo esc_html( $opt ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageOGurl"><strong>og:url</strong></label>
				<input type="url" id="pb_seo_pageOGurl" name="pb_seo[pageOGurl]"
					value="<?php echo esc_attr( $values['pageOGurl'] ); ?>"
					placeholder="<?php esc_attr_e( 'Pełny URL strony (puste = permalink)', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageOGsitename"><strong>og:site_name</strong></label>
				<input type="text" id="pb_seo_pageOGsitename" name="pb_seo[pageOGsitename]"
					value="<?php echo esc_attr( $values['pageOGsitename'] ); ?>"
					placeholder="<?php esc_attr_e( 'Nazwa witryny (puste = nazwa bloga)', 'pb-media-all-seo' ); ?>"
					class="widefat" />
			</p>

			<p class="pb-seo-row">
				<label for="pb_seo_pageOGCustomTag"><strong><?php esc_html_e( 'Add Custom OpenGraph SEO', 'pb-media-all-seo' ); ?></strong></label>
				<textarea id="pb_seo_pageOGCustomTag" name="pb_seo[pageOGCustomTag]"
					rows="5" class="widefat code"
					placeholder="<?php esc_attr_e( 'Jeden tag <meta property="og:..."/> per linia.', 'pb-media-all-seo' ); ?>"><?php
					echo esc_textarea( $values['pageOGCustomTag'] );
				?></textarea>
			</p>
		</div>
		<?php
	}

	public function render_schema_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME . '_schema' );
		$value = (string) get_post_meta( $post->ID, PB_MEDIA_ALL_SEO_META_PREFIX . self::SCHEMA_FIELD, true );
		?>
		<div class="pb-seo-wrap">
			<p class="pb-seo-row">
				<label for="pb_seo_pageSchemaJSON">
					<strong><?php esc_html_e( 'Schema JSON-LD (Schema.org)', 'pb-media-all-seo' ); ?></strong>
				</label>
				<textarea id="pb_seo_pageSchemaJSON" name="pb_seo[pageSchemaJSON]"
					rows="14" class="widefat code"
					placeholder='{"@context":"https://schema.org","@type":"Article","headline":"..."}'><?php
					echo esc_textarea( $value );
				?></textarea>
				<span class="description">
					<?php esc_html_e( 'Wklej kompletny obiekt JSON-LD. Plugin zwaliduje strukturę przed zapisem.', 'pb-media-all-seo' ); ?>
				</span>
			</p>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Save handler
	 * ------------------------------------------------------------------ */

	/**
	 * Save all meta-box data on post save.
	 */
	public function save_meta_boxes( int $post_id, \WP_Post $post ): void {
		// Bail on autosaves / revisions / quick edits.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return; // Our meta box was not on the screen.
		}
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ),
			self::NONCE_ACTION
		) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, Plugin::get_supported_post_types(), true ) ) {
			return;
		}

		$raw = isset( $_POST['pb_seo'] ) && is_array( $_POST['pb_seo'] )
			? wp_unslash( $_POST['pb_seo'] )
			: [];

		// --- SEO meta tags ---------------------------------------------------
		$this->save_text_field( $post_id, 'pageTitleSEO', $raw );
		$this->save_text_field( $post_id, 'pageDescriptionSEO', $raw );
		$this->save_text_field( $post_id, 'pageKeywordsSEO', $raw );
		$this->save_int_field( $post_id, 'pageAuthorSEO', $raw );
		$this->save_url_field( $post_id, 'pageCanonicalURLSEO', $raw );
		$this->save_enum_field( $post_id, 'pageRobotsSEO', $raw, self::ROBOTS_OPTIONS );
		$this->save_html_meta_tags( $post_id, 'pageCustomMetaTagSEO', $raw );

		// --- OpenGraph -------------------------------------------------------
		$this->save_text_field( $post_id, 'pageOGtitle', $raw );
		$this->save_text_field( $post_id, 'pageOGdescription', $raw );
		$this->save_url_field( $post_id, 'pageOGimage', $raw );
		$this->save_text_field( $post_id, 'pageOGimagealt', $raw );
		$this->save_enum_field( $post_id, 'pageOGtype', $raw, self::OG_TYPE_OPTIONS );
		$this->save_url_field( $post_id, 'pageOGurl', $raw );
		$this->save_text_field( $post_id, 'pageOGsitename', $raw );
		$this->save_html_meta_tags( $post_id, 'pageOGCustomTag', $raw );

		// --- Schema JSON-LD --------------------------------------------------
		$this->save_schema_json( $post_id, $raw );
	}

	/**
	 * @param array<string,mixed> $raw
	 */
	private function save_text_field( int $post_id, string $key, array $raw ): void {
		$value = isset( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
		update_post_meta( $post_id, PB_MEDIA_ALL_SEO_META_PREFIX . $key, $value );
	}

	/**
	 * @param array<string,mixed> $raw
	 */
	private function save_url_field( int $post_id, string $key, array $raw ): void {
		$value = isset( $raw[ $key ] ) ? esc_url_raw( (string) $raw[ $key ] ) : '';
		update_post_meta( $post_id, PB_MEDIA_ALL_SEO_META_PREFIX . $key, $value );
	}

	/**
	 * @param array<string,mixed> $raw
	 */
	private function save_int_field( int $post_id, string $key, array $raw ): void {
		$value = isset( $raw[ $key ] ) ? absint( $raw[ $key ] ) : 0;
		update_post_meta( $post_id, PB_MEDIA_ALL_SEO_META_PREFIX . $key, (string) $value );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @param array<int,string>   $allowed
	 */
	private function save_enum_field( int $post_id, string $key, array $raw, array $allowed ): void {
		$value = isset( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
		if ( '' !== $value && ! in_array( $value, $allowed, true ) ) {
			$value = '';
		}
		update_post_meta( $post_id, PB_MEDIA_ALL_SEO_META_PREFIX . $key, $value );
	}

	/**
	 * Save raw HTML restricted to <meta> and <link> tags.
	 *
	 * @param array<string,mixed> $raw
	 */
	private function save_html_meta_tags( int $post_id, string $key, array $raw ): void {
		$value = isset( $raw[ $key ] ) ? (string) $raw[ $key ] : '';

		$allowed = [
			'meta' => [
				'name'       => true,
				'property'   => true,
				'content'    => true,
				'http-equiv' => true,
				'charset'    => true,
				'scheme'     => true,
			],
			'link' => [
				'rel'    => true,
				'href'   => true,
				'type'   => true,
				'sizes'  => true,
				'media'  => true,
				'title'  => true,
				'hreflang' => true,
			],
		];

		$clean = wp_kses( $value, $allowed );
		update_post_meta( $post_id, PB_MEDIA_ALL_SEO_META_PREFIX . $key, $clean );
	}

	/**
	 * Validate JSON before saving Schema field.
	 *
	 * @param array<string,mixed> $raw
	 */
	private function save_schema_json( int $post_id, array $raw ): void {
		$value = isset( $raw[ self::SCHEMA_FIELD ] ) ? trim( (string) $raw[ self::SCHEMA_FIELD ] ) : '';

		if ( '' === $value ) {
			delete_post_meta( $post_id, PB_MEDIA_ALL_SEO_META_PREFIX . self::SCHEMA_FIELD );
			return;
		}

		// Validate JSON.
		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			set_transient(
				'pb_seo_schema_error_' . get_current_user_id(),
				sprintf(
					/* translators: %s: JSON error message */
					__( 'Plugin PB MEDIA ALL SEO: nieprawidłowy JSON w polu Schema (%s). Wartość nie została zapisana.', 'pb-media-all-seo' ),
					json_last_error_msg()
				),
				60
			);
			return;
		}

		// Re-encode to a clean, normalized form (no escaped slashes, unicode kept).
		$normalized = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $normalized ) {
			return;
		}
		update_post_meta( $post_id, PB_MEDIA_ALL_SEO_META_PREFIX . self::SCHEMA_FIELD, wp_slash( $normalized ) );
	}

	/**
	 * Display admin notice if JSON validation failed.
	 */
	public function render_admin_notices(): void {
		$key   = 'pb_seo_schema_error_' . get_current_user_id();
		$error = get_transient( $key );
		if ( $error ) {
			delete_transient( $key );
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( (string) $error )
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch all field values for a given post ID.
	 *
	 * @param array<int,string> $fields
	 * @return array<string,string>
	 */
	private function get_field_values( int $post_id, array $fields ): array {
		$out = [];
		foreach ( $fields as $field ) {
			$out[ $field ] = (string) get_post_meta( $post_id, PB_MEDIA_ALL_SEO_META_PREFIX . $field, true );
		}
		return $out;
	}
}
