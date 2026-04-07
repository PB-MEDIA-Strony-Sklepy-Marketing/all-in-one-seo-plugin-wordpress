<?php
/**
 * Automatic OpenGraph image generator.
 *
 * Generates a 1200×630 PNG by:
 *  1. Loading the featured image (or a solid background if missing)
 *  2. Cropping/scaling it to 1200×630
 *  3. Drawing a semi-transparent dark gradient overlay
 *  4. Rendering the post title (or custom text) as wrapped text
 *  5. Saving the result to the media library + setting it as og:image
 *
 * Requires the GD extension (almost always available on WP hosts).
 * Triggered manually from the OG meta box via an admin-post action.
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OG_Image {

	public const WIDTH        = 1200;
	public const HEIGHT       = 630;
	public const META_KEY     = PB_MEDIA_ALL_SEO_META_PREFIX . 'pageOGimage';
	public const META_GEN_ID  = PB_MEDIA_ALL_SEO_META_PREFIX . 'og_generated_id';
	private const NONCE_ACTION = 'pb_seo_og_generate';

	public function register_hooks(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'admin_post_pb_seo_og_generate', [ $this, 'handle_generate' ] );
	}

	public function add_meta_box(): void {
		foreach ( Plugin::get_supported_post_types() as $pt ) {
			add_meta_box(
				'pb_seo_og_image_gen',
				esc_html__( 'PB MEDIA — Auto OG Image', 'pb-media-all-seo' ),
				[ $this, 'render_meta_box' ],
				$pt,
				'side',
				'low'
			);
		}
	}

	public function render_meta_box( \WP_Post $post ): void {
		$gen_id = (int) get_post_meta( $post->ID, self::META_GEN_ID, true );
		$preview_url = '';
		if ( $gen_id > 0 ) {
			$url = wp_get_attachment_url( $gen_id );
			if ( is_string( $url ) ) {
				$preview_url = $url;
			}
		}
		$has_thumb = has_post_thumbnail( $post->ID );
		$has_gd    = extension_loaded( 'gd' );
		?>
		<div class="pb-seo-og-gen">
			<?php if ( '' !== $preview_url ) : ?>
				<p>
					<img src="<?php echo esc_url( $preview_url ); ?>" alt="" style="max-width:100%;height:auto;border:1px solid #ddd;" />
				</p>
			<?php endif; ?>

			<?php if ( ! $has_gd ) : ?>
				<p style="color:#a00;">
					<?php esc_html_e( 'Wymagane rozszerzenie PHP GD nie jest dostępne na tym serwerze.', 'pb-media-all-seo' ); ?>
				</p>
			<?php elseif ( ! $has_thumb ) : ?>
				<p>
					<?php esc_html_e( 'Ustaw obrazek wyróżniający (Featured Image), aby wygenerować OG image.', 'pb-media-all-seo' ); ?>
				</p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<input type="hidden" name="action" value="pb_seo_og_generate" />
					<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post->ID ); ?>" />
					<p>
						<label for="pb_seo_og_overlay"><?php esc_html_e( 'Tekst nakładki:', 'pb-media-all-seo' ); ?></label>
						<input type="text" id="pb_seo_og_overlay" name="overlay" class="widefat"
							value="<?php echo esc_attr( get_the_title( $post ) ); ?>" />
					</p>
					<?php submit_button( __( 'Generuj OG Image', 'pb-media-all-seo' ), 'secondary', 'submit', false ); ?>
				</form>
				<p class="description">
					<?php esc_html_e( 'Wygeneruje obraz 1200×630 z featured image + nakładką tekstową i ustawi go jako og:image.', 'pb-media-all-seo' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_generate(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'pb-media-all-seo' ), 403 );
		}
		check_admin_referer( self::NONCE_ACTION );

		$overlay = isset( $_POST['overlay'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['overlay'] ) ) : '';
		if ( '' === $overlay ) {
			$overlay = (string) get_the_title( $post_id );
		}

		$attachment_id = $this->generate( $post_id, $overlay );

		$redirect = get_edit_post_link( $post_id, 'url' );
		if ( $attachment_id > 0 ) {
			$redirect = add_query_arg( 'pb_seo_og_generated', '1', (string) $redirect );
		} else {
			$redirect = add_query_arg( 'pb_seo_og_generated', '0', (string) $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Generate the OG image and attach it to the post.
	 *
	 * @return int Attachment ID, or 0 on failure.
	 */
	public function generate( int $post_id, string $overlay_text ): int {
		if ( ! extension_loaded( 'gd' ) ) {
			return 0;
		}
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb_id <= 0 ) {
			return 0;
		}
		$src_path = (string) get_attached_file( $thumb_id );
		if ( '' === $src_path || ! file_exists( $src_path ) ) {
			return 0;
		}

		$src = $this->load_image( $src_path );
		if ( null === $src ) {
			return 0;
		}

		// Create canvas 1200x630.
		$canvas = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
		if ( false === $canvas ) {
			imagedestroy( $src );
			return 0;
		}
		imagealphablending( $canvas, true );
		imagesavealpha( $canvas, true );

		// Cover-fit (scale + center crop).
		[ $sw, $sh ] = [ imagesx( $src ), imagesy( $src ) ];
		$src_ratio   = $sw / max( 1, $sh );
		$dst_ratio   = self::WIDTH / self::HEIGHT;

		if ( $src_ratio > $dst_ratio ) {
			// Source is wider — crop sides.
			$new_h = $sh;
			$new_w = (int) ( $sh * $dst_ratio );
			$src_x = (int) ( ( $sw - $new_w ) / 2 );
			$src_y = 0;
		} else {
			$new_w = $sw;
			$new_h = (int) ( $sw / $dst_ratio );
			$src_x = 0;
			$src_y = (int) ( ( $sh - $new_h ) / 2 );
		}
		imagecopyresampled( $canvas, $src, 0, 0, $src_x, $src_y, self::WIDTH, self::HEIGHT, $new_w, $new_h );
		imagedestroy( $src );

		// Dark gradient overlay (bottom-heavy) for text legibility.
		for ( $y = 0; $y < self::HEIGHT; $y++ ) {
			$alpha = (int) round( ( $y / self::HEIGHT ) * 110 ); // 0..110 (out of 127)
			$color = imagecolorallocatealpha( $canvas, 0, 0, 0, 127 - $alpha );
			imageline( $canvas, 0, $y, self::WIDTH, $y, $color );
		}

		// Draw the text overlay.
		$this->draw_text( $canvas, $overlay_text );

		// Save to uploads.
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			imagedestroy( $canvas );
			return 0;
		}
		$filename = 'pb-seo-og-' . $post_id . '-' . time() . '.png';
		$filepath = trailingslashit( $uploads['path'] ) . $filename;

		if ( ! imagepng( $canvas, $filepath, 6 ) ) {
			imagedestroy( $canvas );
			return 0;
		}
		imagedestroy( $canvas );

		// Insert as attachment.
		$filetype = wp_check_filetype( $filename, null );
		$att      = [
			'guid'           => trailingslashit( $uploads['url'] ) . $filename,
			'post_mime_type' => $filetype['type'] ?? 'image/png',
			'post_title'     => sanitize_text_field( $overlay_text ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];
		$attachment_id = wp_insert_attachment( $att, $filepath, $post_id );
		if ( is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Save references.
		update_post_meta( $post_id, self::META_GEN_ID, (string) $attachment_id );
		$url = (string) wp_get_attachment_url( $attachment_id );
		if ( '' !== $url ) {
			update_post_meta( $post_id, self::META_KEY, $url );
		}

		return (int) $attachment_id;
	}

	/**
	 * Load image into a GD resource regardless of format.
	 *
	 * @return \GdImage|null
	 */
	private function load_image( string $path ): ?\GdImage {
		$info = @getimagesize( $path );
		if ( false === $info ) {
			return null;
		}
		switch ( $info[2] ) {
			case IMAGETYPE_JPEG:
				$im = @imagecreatefromjpeg( $path );
				break;
			case IMAGETYPE_PNG:
				$im = @imagecreatefrompng( $path );
				break;
			case IMAGETYPE_GIF:
				$im = @imagecreatefromgif( $path );
				break;
			case IMAGETYPE_WEBP:
				$im = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false;
				break;
			default:
				return null;
		}
		return $im instanceof \GdImage ? $im : null;
	}

	/**
	 * Draw multi-line wrapped text near the bottom-left of the canvas.
	 */
	private function draw_text( \GdImage $canvas, string $text ): void {
		$text  = trim( $text );
		if ( '' === $text ) {
			return;
		}

		$white = imagecolorallocate( $canvas, 255, 255, 255 );
		$shadow = imagecolorallocatealpha( $canvas, 0, 0, 0, 60 );

		$font_path = $this->find_font();
		$padding   = 60;
		$max_width = self::WIDTH - ( 2 * $padding );

		if ( null !== $font_path && function_exists( 'imagettftext' ) ) {
			$font_size = 56;
			$lines     = $this->wrap_text_ttf( $font_path, $font_size, $max_width, $text );

			// Limit to 4 lines.
			if ( count( $lines ) > 4 ) {
				$lines    = array_slice( $lines, 0, 4 );
				$lines[3] = rtrim( $lines[3] ) . '…';
			}

			$line_h = (int) ( $font_size * 1.35 );
			$total  = $line_h * count( $lines );
			$y      = self::HEIGHT - $padding - $total + $font_size;

			foreach ( $lines as $line ) {
				// Soft shadow.
				imagettftext( $canvas, $font_size, 0, $padding + 2, $y + 2, $shadow, $font_path, $line );
				imagettftext( $canvas, $font_size, 0, $padding,     $y,     $white,  $font_path, $line );
				$y += $line_h;
			}
		} else {
			// Fallback: built-in bitmap font (no Unicode, no anti-aliasing).
			$font  = 5;
			$cw    = imagefontwidth( $font );
			$lines = $this->wrap_text_bitmap( $cw, $max_width, $text );
			if ( count( $lines ) > 6 ) {
				$lines = array_slice( $lines, 0, 6 );
			}
			$line_h = imagefontheight( $font ) + 6;
			$y      = self::HEIGHT - $padding - ( count( $lines ) * $line_h );
			foreach ( $lines as $line ) {
				imagestring( $canvas, $font, $padding, $y, $line, $white );
				$y += $line_h;
			}
		}
	}

	private function find_font(): ?string {
		// Try common font paths bundled with WP/Linux.
		$candidates = [
			ABSPATH . WPINC . '/fonts/dashicons.ttf', // Always shipped with WP, but limited glyphs.
			'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
			'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
			'/Library/Fonts/Arial.ttf',
			'C:\\Windows\\Fonts\\arial.ttf',
		];
		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) && false !== stripos( $path, 'sans' ) ) {
				return $path;
			}
		}
		// Last resort: any file that exists from the candidate list.
		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}
		return null;
	}

	/**
	 * Wrap text to fit within $max_width pixels using TTF metrics.
	 *
	 * @return array<int,string>
	 */
	private function wrap_text_ttf( string $font, int $size, int $max_width, string $text ): array {
		$words = preg_split( '/\s+/u', $text ) ?: [];
		$lines = [];
		$line  = '';
		foreach ( $words as $word ) {
			$test = '' === $line ? $word : ( $line . ' ' . $word );
			$bbox = @imagettfbbox( $size, 0, $font, $test );
			if ( false === $bbox ) {
				$lines[] = $test;
				$line    = '';
				continue;
			}
			$w = abs( $bbox[2] - $bbox[0] );
			if ( $w > $max_width && '' !== $line ) {
				$lines[] = $line;
				$line    = $word;
			} else {
				$line = $test;
			}
		}
		if ( '' !== $line ) {
			$lines[] = $line;
		}
		return $lines;
	}

	/**
	 * @return array<int,string>
	 */
	private function wrap_text_bitmap( int $char_width, int $max_width, string $text ): array {
		$max_chars = max( 10, (int) floor( $max_width / max( 1, $char_width ) ) );
		return explode( "\n", wordwrap( $text, $max_chars, "\n", true ) );
	}
}
