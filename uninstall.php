<?php
/**
 * Removes the plugin's options.
 *
 * @package CM4AI
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cm4ai_enabled' );
delete_option( 'cm4ai_exposed' );
