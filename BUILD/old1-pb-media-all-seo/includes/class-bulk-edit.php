<?php
/**
 * Bulk SEO meta editor.
 *
 * Provides a dedicated admin submenu page where the user can:
 *  - filter posts by post type and search term
 *  - select multiple posts via checkboxes
 *  - apply SEO field values in bulk (only fields with non-empty input
 *    are written; empty fields are skipped to avoid clobbering data)
 *
 * @package PB_Media_All_SEO
 */

declare( strict_types=1 );

namespace PB_Media_All_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bulk_Edit {

	public const MENU_SLUG   = 'pb-media-seo-bulk';
	private const NONCE_NAME = 'pb_seo_bulk_nonce';
	private const NONCE_ACTION = 'pb_seo_bulk_save';

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 );
		add_action( 'admin_post_pb_seo_bulk_apply', [ $this, 'handle_submit' ] );
	}

	public function add_submenu(): void {
		add_submenu_page(
			Settings::MENU_SLUG,
			esc_html__( 'Bulk Edit SEO', 'pb-media-all-seo' ),
			esc_html__( 'Bulk Edit SEO', 'pb-media-all-seo' ),
			'edit_others_posts',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$selected_pt = isset( $_GET['pt'] ) ? sanitize_key( (string) $_GET['pt'] ) : 'post';
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( (string) $_GET['s'] ) : '';
		$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$post_types = Plugin::get_supported_post_types();
		if ( ! in_array( $selected_pt, $post_types, true ) ) {
			$selected_pt = (string) reset( $post_types );
		}

		$query = new \WP_Query(
			[
				'post_type'      => $selected_pt,
				'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
				'posts_per_page' => 30,
				'paged'          => $paged,
				's'              => $search,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);
		?>
		<div class="wrap pb-seo-bulk">
			<h1><?php esc_html_e( 'Bulk Edit SEO', 'pb-media-all-seo' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Wybierz wpisy i pola, które chcesz nadpisać. Puste pola są pomijane.', 'pb-media-all-seo' ); ?>
			</p>

			<?php $this->render_admin_notices(); ?>

			<form method="get" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
				<select name="pt">
					<?php foreach ( $post_types as $pt ) :
						$obj = get_post_type_object( $pt );
						?>
						<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $selected_pt, $pt ); ?>>
							<?php echo esc_html( $obj ? $obj->labels->name : $pt ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
					placeholder="<?php esc_attr_e( 'Szukaj…', 'pb-media-all-seo' ); ?>" />
				<?php submit_button( __( 'Filtruj', 'pb-media-all-seo' ), 'secondary', '', false ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="action" value="pb_seo_bulk_apply" />
				<input type="hidden" name="pt" value="<?php echo esc_attr( $selected_pt ); ?>" />

				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th class="manage-column column-cb check-column">
								<input type="checkbox" id="pb-seo-cb-all" />
							</th>
							<th><?php esc_html_e( 'Tytuł', 'pb-media-all-seo' ); ?></th>
							<th><?php esc_html_e( 'Title SEO', 'pb-media-all-seo' ); ?></th>
							<th><?php esc_html_e( 'Description SEO', 'pb-media-all-seo' ); ?></th>
							<th><?php esc_html_e( 'Robots', 'pb-media-all-seo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $query->have_posts() ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'Brak wpisów.', 'pb-media-all-seo' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $query->posts as $p ) :
								if ( ! $p instanceof \WP_Post ) {
									continue;
								}
								$t = (string) get_post_meta( $p->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageTitleSEO', true );
								$d = (string) get_post_meta( $p->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageDescriptionSEO', true );
								$r = (string) get_post_meta( $p->ID, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageRobotsSEO', true );
								?>
								<tr>
									<th class="check-column">
										<input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( (string) $p->ID ); ?>" />
									</th>
									<td>
										<strong>
											<a href="<?php echo esc_url( (string) get_edit_post_link( $p->ID ) ); ?>">
												<?php echo esc_html( get_the_title( $p ) ); ?>
											</a>
										</strong>
										<div class="row-actions">ID: <?php echo (int) $p->ID; ?> · <?php echo esc_html( $p->post_status ); ?></div>
									</td>
									<td><?php echo esc_html( $t !== '' ? $t : '—' ); ?></td>
									<td><?php echo esc_html( $d !== '' ? wp_trim_words( $d, 8 ) : '—' ); ?></td>
									<td><?php echo esc_html( $r !== '' ? $r : '—' ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<?php $this->render_pagination( $query, $selected_pt, $search, $paged ); ?>

				<h2><?php esc_html_e( 'Wartości do nadpisania', 'pb-media-all-seo' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bulk_title"><?php esc_html_e( 'Title SEO', 'pb-media-all-seo' ); ?></label></th>
						<td><input type="text" id="bulk_title" name="bulk[pageTitleSEO]" class="large-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bulk_desc"><?php esc_html_e( 'Description SEO', 'pb-media-all-seo' ); ?></label></th>
						<td><input type="text" id="bulk_desc" name="bulk[pageDescriptionSEO]" class="large-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bulk_kw"><?php esc_html_e( 'Keywords SEO', 'pb-media-all-seo' ); ?></label></th>
						<td><input type="text" id="bulk_kw" name="bulk[pageKeywordsSEO]" class="large-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bulk_robots"><?php esc_html_e( 'Robots', 'pb-media-all-seo' ); ?></label></th>
						<td>
							<select id="bulk_robots" name="bulk[pageRobotsSEO]">
								<option value=""><?php esc_html_e( '— bez zmian —', 'pb-media-all-seo' ); ?></option>
								<?php foreach ( [ 'index', 'noindex', 'follow', 'nofollow', 'noarchive', 'nosnippet' ] as $opt ) : ?>
									<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bulk_ogtype"><?php esc_html_e( 'OG Type', 'pb-media-all-seo' ); ?></label></th>
						<td>
							<select id="bulk_ogtype" name="bulk[pageOGtype]">
								<option value=""><?php esc_html_e( '— bez zmian —', 'pb-media-all-seo' ); ?></option>
								<?php foreach ( [ 'website', 'article', 'blog', 'product' ] as $opt ) : ?>
									<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Zastosuj do zaznaczonych', 'pb-media-all-seo' ), 'primary', 'submit', true, [ 'id' => 'pb-seo-bulk-submit' ] ); ?>
			</form>

			<script>
			(function(){
				var cbAll = document.getElementById('pb-seo-cb-all');
				if (cbAll) {
					cbAll.addEventListener('change', function(){
						document.querySelectorAll('input[name="post_ids[]"]').forEach(function(cb){
							cb.checked = cbAll.checked;
						});
					});
				}
			})();
			</script>
		</div>
		<?php
		wp_reset_postdata();
	}

	private function render_pagination( \WP_Query $query, string $pt, string $search, int $paged ): void {
		if ( $query->max_num_pages <= 1 ) {
			return;
		}
		$base = add_query_arg(
			[
				'page'  => self::MENU_SLUG,
				'pt'    => $pt,
				's'     => $search,
				'paged' => '%#%',
			],
			admin_url( 'admin.php' )
		);
		$links = paginate_links(
			[
				'base'      => $base,
				'format'    => '',
				'current'   => $paged,
				'total'     => $query->max_num_pages,
				'prev_text' => '«',
				'next_text' => '»',
			]
		);
		echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( (string) $links ) . '</div></div>';
	}

	private function render_admin_notices(): void {
		$notice = get_transient( 'pb_seo_bulk_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'pb_seo_bulk_notice_' . get_current_user_id() );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( (string) $notice )
			);
		}
	}

	/**
	 * Handle the bulk-apply form submit.
	 */
	public function handle_submit(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'pb-media-all-seo' ), 403 );
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
			? array_map( 'absint', (array) $_POST['post_ids'] )
			: [];
		$ids = array_values( array_filter( $ids ) );

		$bulk = isset( $_POST['bulk'] ) && is_array( $_POST['bulk'] )
			? wp_unslash( $_POST['bulk'] )
			: [];

		$updated = 0;
		if ( ! empty( $ids ) && ! empty( $bulk ) ) {
			$allowed_robots = [ 'index', 'noindex', 'follow', 'nofollow', 'noarchive', 'nosnippet' ];
			$allowed_og     = [ 'website', 'article', 'blog', 'product' ];

			foreach ( $ids as $id ) {
				if ( ! current_user_can( 'edit_post', $id ) ) {
					continue;
				}

				if ( isset( $bulk['pageTitleSEO'] ) && '' !== trim( (string) $bulk['pageTitleSEO'] ) ) {
					update_post_meta( $id, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageTitleSEO', sanitize_text_field( (string) $bulk['pageTitleSEO'] ) );
				}
				if ( isset( $bulk['pageDescriptionSEO'] ) && '' !== trim( (string) $bulk['pageDescriptionSEO'] ) ) {
					update_post_meta( $id, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageDescriptionSEO', sanitize_text_field( (string) $bulk['pageDescriptionSEO'] ) );
				}
				if ( isset( $bulk['pageKeywordsSEO'] ) && '' !== trim( (string) $bulk['pageKeywordsSEO'] ) ) {
					update_post_meta( $id, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageKeywordsSEO', sanitize_text_field( (string) $bulk['pageKeywordsSEO'] ) );
				}
				if ( isset( $bulk['pageRobotsSEO'] ) && in_array( (string) $bulk['pageRobotsSEO'], $allowed_robots, true ) ) {
					update_post_meta( $id, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageRobotsSEO', (string) $bulk['pageRobotsSEO'] );
				}
				if ( isset( $bulk['pageOGtype'] ) && in_array( (string) $bulk['pageOGtype'], $allowed_og, true ) ) {
					update_post_meta( $id, PB_MEDIA_ALL_SEO_META_PREFIX . 'pageOGtype', (string) $bulk['pageOGtype'] );
				}
				++$updated;
			}
		}

		set_transient(
			'pb_seo_bulk_notice_' . get_current_user_id(),
			sprintf(
				/* translators: %d: number of posts updated */
				_n( 'Zaktualizowano %d wpis.', 'Zaktualizowano %d wpisów.', $updated, 'pb-media-all-seo' ),
				$updated
			),
			60
		);

		// Invalidate sitemap cache.
		delete_transient( 'pb_seo_sitemap_pages' );

		wp_safe_redirect(
			add_query_arg(
				[
					'page' => self::MENU_SLUG,
					'pt'   => isset( $_POST['pt'] ) ? sanitize_key( (string) $_POST['pt'] ) : 'post',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
