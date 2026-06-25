<?php
/**
 * Product admin UI (Black Bean table, no CPT editor).
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level Blackbean Shop admin menu.
 */
function blackbean_shop_register_admin_menu() : void {
	add_menu_page(
		__( 'Blackbean Shop', 'blackbean' ),
		__( 'Shop', 'blackbean' ),
		'edit_posts',
		'blackbean-shop-manager',
		'blackbean_shop_render_manager_page',
		'dashicons-cart',
		56
	);

	add_submenu_page(
		'blackbean-shop-manager',
		__( 'Products', 'blackbean' ),
		__( 'Products', 'blackbean' ),
		'edit_posts',
		'blackbean-shop-manager',
		'blackbean_shop_render_manager_page'
	);

	add_submenu_page(
		'blackbean-shop-manager',
		__( 'Edit product', 'blackbean' ),
		__( 'Edit product', 'blackbean' ),
		'edit_posts',
		'blackbean-shop-product',
		'blackbean_shop_render_product_edit_page'
	);
}
add_action( 'admin_menu', 'blackbean_shop_register_admin_menu', 4 );

/**
 * Product edit is linked from the product list; keep it out of the sidebar.
 */
function blackbean_shop_hide_product_edit_menu() : void {
	remove_submenu_page( 'blackbean-shop-manager', 'blackbean-shop-product' );
}
add_action( 'admin_menu', 'blackbean_shop_hide_product_edit_menu', 99 );

/**
 * Hidden Shop subpages must keep the Shop parent for WP admin access checks.
 *
 * @param string|null $parent_file Parent file.
 * @return string|null
 */
function blackbean_shop_hidden_admin_parent_file( $parent_file ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
	if ( in_array( $page, array( 'blackbean-shop-product', 'blackbean-doc-edit' ), true ) ) {
		return 'blackbean-shop-manager';
	}
	return $parent_file;
}
add_filter( 'parent_file', 'blackbean_shop_hidden_admin_parent_file' );

/**
 * Highlight the correct Shop submenu while on hidden edit screens.
 *
 * @param string|null $submenu_file Submenu file.
 * @param string|null $parent_file  Parent file.
 * @return string|null
 */
function blackbean_shop_hidden_admin_submenu_file( $submenu_file, $parent_file = null ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
	if ( 'blackbean-shop-product' === $page ) {
		return 'blackbean-shop-manager';
	}
	if ( 'blackbean-doc-edit' === $page ) {
		return 'blackbean-docs';
	}
	return $submenu_file;
}
add_filter( 'submenu_file', 'blackbean_shop_hidden_admin_submenu_file', 10, 2 );

/**
 * Product edit / create screen.
 */
function blackbean_shop_render_product_edit_page() : void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit products.', 'blackbean' ) );
	}

	$product_id = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;
	$row        = $product_id > 0 ? blackbean_product_get_row( $product_id ) : null;

	if ( isset( $_POST['blackbean_shop_product_save'] ) ) {
		check_admin_referer( 'blackbean_shop_product_save', 'blackbean_shop_product_nonce' );
		$result = blackbean_shop_handle_product_save( $product_id );
		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		} else {
			$product_id = (int) $result;
			$row        = blackbean_product_get_row( $product_id );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product saved.', 'blackbean' ) . '</p></div>';
		}
	}

	$ui = blackbean_shop_admin_ui_classes();
	$is_new = ! $row;
	$title  = $is_new ? '' : (string) $row['title'];
	$slug   = $is_new ? '' : (string) $row['slug'];
	$status = $is_new ? 'draft' : (string) $row['status'];
	$content = $is_new ? '' : (string) $row['content'];
	$excerpt = $is_new ? '' : (string) $row['excerpt'];
	$price   = $is_new ? 0 : (float) $row['price'];
	$sku     = $is_new ? '' : (string) $row['sku'];
	$stock   = $is_new ? -1 : (int) $row['stock'];
	$is_digital = $is_new ? 1 : (int) $row['is_digital'];
	$download_url = $is_new ? '' : (string) $row['download_url'];
	$download_file_id = $is_new ? 0 : (int) $row['download_file_id'];
	$license_prefix = $is_new ? '' : (string) $row['license_prefix'];
	$license_max = $is_new ? 1 : (int) $row['license_max_sites'];
	$featured_image_id = $is_new ? 0 : (int) $row['featured_image_id'];
	?>
	<div class="<?php echo esc_attr( $ui['manager_wrap'] ); ?>">
		<header class="bb-admin-page-header">
			<div>
				<h1 class="bb-admin-page-title"><?php echo $is_new ? esc_html__( 'Add product', 'blackbean' ) : esc_html__( 'Edit product', 'blackbean' ); ?></h1>
			</div>
			<div class="<?php echo esc_attr( $ui['toolbar'] ); ?>">
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-shop-manager' ) ); ?>"><?php esc_html_e( 'Back to products', 'blackbean' ); ?></a>
			</div>
		</header>

		<form method="post" class="<?php echo esc_attr( $ui['card'] ); ?>" style="max-width:48rem;">
			<?php wp_nonce_field( 'blackbean_shop_product_save', 'blackbean_shop_product_nonce' ); ?>
			<input type="hidden" name="blackbean_shop_product_save" value="1" />

			<div class="<?php echo esc_attr( $ui['fields'] ); ?>">
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_product_title"><?php esc_html_e( 'Title', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="text" id="bb_product_title" name="bb_product_title" value="<?php echo esc_attr( $title ); ?>" required />
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_product_slug"><?php esc_html_e( 'Slug', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="text" id="bb_product_slug" name="bb_product_slug" value="<?php echo esc_attr( $slug ); ?>" />
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_product_status"><?php esc_html_e( 'Status', 'blackbean' ); ?></label>
					<select class="<?php echo esc_attr( $ui['input'] ); ?>" id="bb_product_status" name="bb_product_status">
						<?php foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $st ) : ?>
							<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status, $st ); ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_product_excerpt"><?php esc_html_e( 'Excerpt', 'blackbean' ); ?></label>
					<textarea class="<?php echo esc_attr( $ui['input'] ); ?>" id="bb_product_excerpt" name="bb_product_excerpt" rows="2"><?php echo esc_textarea( $excerpt ); ?></textarea>
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_product_content"><?php esc_html_e( 'Description', 'blackbean' ); ?></label>
					<textarea class="<?php echo esc_attr( $ui['input'] ); ?>" id="bb_product_content" name="bb_product_content" rows="8"><?php echo esc_textarea( $content ); ?></textarea>
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_price"><?php esc_html_e( 'Price', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="number" step="0.01" min="0" id="bb_price" name="bb_price" value="<?php echo esc_attr( (string) $price ); ?>" />
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_sku"><?php esc_html_e( 'SKU', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="text" id="bb_sku" name="bb_sku" value="<?php echo esc_attr( $sku ); ?>" />
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_stock"><?php esc_html_e( 'Stock (-1 = unlimited)', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="number" id="bb_stock" name="bb_stock" value="<?php echo esc_attr( (string) $stock ); ?>" />
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_featured_image_id"><?php esc_html_e( 'Featured image (attachment ID)', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="number" min="0" id="bb_featured_image_id" name="bb_featured_image_id" value="<?php echo esc_attr( (string) $featured_image_id ); ?>" />
				</div>
				<label class="<?php echo esc_attr( $ui['check_row'] ); ?>">
					<input type="checkbox" name="bb_is_digital" value="1" <?php checked( $is_digital ); ?> />
					<?php esc_html_e( 'Digital product', 'blackbean' ); ?>
				</label>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_download_url"><?php esc_html_e( 'Download URL', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="url" id="bb_download_url" name="bb_download_url" value="<?php echo esc_attr( $download_url ); ?>" />
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_download_file_id"><?php esc_html_e( 'Download file (media ID)', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="number" min="0" id="bb_download_file_id" name="bb_download_file_id" value="<?php echo esc_attr( (string) $download_file_id ); ?>" />
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_license_prefix"><?php esc_html_e( 'License prefix', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="text" id="bb_license_prefix" name="bb_license_prefix" value="<?php echo esc_attr( $license_prefix ); ?>" />
				</div>
				<div class="<?php echo esc_attr( $ui['field'] ); ?>">
					<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="bb_license_max_sites"><?php esc_html_e( 'Activations allowed', 'blackbean' ); ?></label>
					<input class="<?php echo esc_attr( $ui['input'] ); ?>" type="number" min="1" max="99" id="bb_license_max_sites" name="bb_license_max_sites" value="<?php echo esc_attr( (string) $license_max ); ?>" />
				</div>
			</div>

			<?php submit_button( $is_new ? __( 'Create product', 'blackbean' ) : __( 'Save product', 'blackbean' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * @param int $product_id Existing ID or 0 for new.
 * @return int|WP_Error
 */
function blackbean_shop_handle_product_save( int $product_id ) {
	$data = array(
		'title'             => isset( $_POST['bb_product_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['bb_product_title'] ) ) : '',
		'slug'              => isset( $_POST['bb_product_slug'] ) ? sanitize_title( wp_unslash( (string) $_POST['bb_product_slug'] ) ) : '',
		'status'            => isset( $_POST['bb_product_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['bb_product_status'] ) ) : 'draft',
		'excerpt'           => isset( $_POST['bb_product_excerpt'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['bb_product_excerpt'] ) ) : '',
		'content'           => isset( $_POST['bb_product_content'] ) ? wp_kses_post( wp_unslash( (string) $_POST['bb_product_content'] ) ) : '',
		'price'             => isset( $_POST['bb_price'] ) ? (float) wp_unslash( $_POST['bb_price'] ) : 0,
		'sku'               => isset( $_POST['bb_sku'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['bb_sku'] ) ) : '',
		'stock'             => isset( $_POST['bb_stock'] ) ? (int) wp_unslash( $_POST['bb_stock'] ) : -1,
		'is_digital'        => ! empty( $_POST['bb_is_digital'] ) ? 1 : 0,
		'download_url'      => isset( $_POST['bb_download_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['bb_download_url'] ) ) : '',
		'download_file_id'  => isset( $_POST['bb_download_file_id'] ) ? max( 0, (int) wp_unslash( $_POST['bb_download_file_id'] ) ) : 0,
		'license_prefix'    => isset( $_POST['bb_license_prefix'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['bb_license_prefix'] ) ) : '',
		'license_max_sites' => isset( $_POST['bb_license_max_sites'] ) ? max( 1, min( 99, (int) wp_unslash( $_POST['bb_license_max_sites'] ) ) ) : 1,
		'featured_image_id' => isset( $_POST['bb_featured_image_id'] ) ? max( 0, (int) wp_unslash( $_POST['bb_featured_image_id'] ) ) : 0,
	);

	if ( $product_id > 0 ) {
		blackbean_product_update( $product_id, $data );
		return $product_id;
	}

	return blackbean_product_insert( $data );
}

/**
 * @param int $product_id Product ID.
 * @return string
 */
function blackbean_shop_product_edit_url( int $product_id = 0 ) : string {
	if ( $product_id > 0 ) {
		return admin_url( 'admin.php?page=blackbean-shop-product&product_id=' . $product_id );
	}
	return admin_url( 'admin.php?page=blackbean-shop-product' );
}
