<?php
/**
 * Shop admin UI class map and notices (self-contained; no theme Tailwind required).
 *
 * @package Blackbean_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Semantic UI classes for Black Bean Shop wp-admin screens.
 *
 * @return array<string, string>
 */
function blackbean_shop_admin_ui_classes() : array {
	return array(
		'admin_shell'         => 'bb-shop-settings-wrap',
		'root'                => 'blackbean-admin-shell',
		'settings_wrap'       => 'wrap blackbean-admin-shell blackbean-admin-settings bb-shop-settings-wrap',
		'manager_wrap'        => 'wrap blackbean-admin-shell blackbean-shop-manager bb-shop-manager-wrap',
		'card'                => 'bb-admin-card',
		'card_head'           => 'bb-admin-card-head',
		'card_title'          => 'bb-admin-card-title',
		'card_sub'            => 'bb-admin-card-sub',
		'grid'                => 'bb-admin-grid',
		'stat'                => 'bb-admin-stat',
		'label'               => 'bb-admin-label',
		'value'               => 'bb-admin-value',
		'hint'                => 'bb-admin-hint',
		'badge_in'            => 'bb-admin-badge bb-admin-badge--in',
		'badge_out'           => 'bb-admin-badge bb-admin-badge--out',
		'badge_neutral'       => 'bb-admin-badge bb-admin-badge--neutral',
		'badge_warn'          => 'bb-admin-badge bb-admin-badge--warn',
		'actions'             => 'bb-admin-actions',
		'toolbar'             => 'bb-admin-toolbar-actions',
		'btn_pri'             => 'bb-admin-btn bb-admin-btn-primary',
		'btn_sec'             => 'bb-admin-btn bb-admin-btn-secondary',
		'table_wrap'          => 'bb-admin-card bb-admin-table-wrap',
		'table'               => 'bb-admin-table',
		'schema_table_wrap'   => 'bb-admin-table-wrap',
		'section'             => 'bb-admin-section',
		'section_title'       => 'bb-admin-section-title',
		'fields'              => 'bb-admin-fields',
		'field'               => 'bb-admin-field',
		'form_label'          => 'bb-admin-form-label',
		'input'               => 'bb-admin-input',
		'check_row'           => 'bb-admin-check-row',
		'check'               => 'bb-admin-check',
		'panel'               => 'bb-admin-panel',
		'note_ok'             => 'bb-admin-note-ok',
		'notice_ok'           => 'bb-admin-notice bb-admin-notice--ok',
		'notice_warn'         => 'bb-admin-notice bb-admin-notice--warn',
		'notice_error'        => 'bb-admin-notice bb-admin-notice--error',
	);
}

/**
 * Render a styled admin notice on shop screens.
 *
 * @param string $message Message (escaped by caller).
 * @param string $type    ok|warn|error.
 */
function blackbean_shop_admin_render_notice( string $message, string $type = 'ok' ) : void {
	$ui    = blackbean_shop_admin_ui_classes();
	$class = $ui['notice_ok'];
	if ( 'warn' === $type ) {
		$class = $ui['notice_warn'];
	} elseif ( 'error' === $type ) {
		$class = $ui['notice_error'];
	}

	$role = 'error' === $type ? 'alert' : 'status';
	echo '<div class="' . esc_attr( $class ) . '" role="' . esc_attr( $role ) . '">';
	echo '<span class="bb-admin-notice__text">' . wp_kses_post( $message ) . '</span>';
	echo '</div>';
}
