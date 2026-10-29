<?php
/**
 * Plugin Name:       Content Manager for AI Agents
 * Description:       Content management abilities for AI agents, exposed through the WordPress Abilities API and the MCP Adapter.
 * Version:           2.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Alexandre Revire
 * Author URI:        https://alexandre-revire.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       content-manager-for-ai-agents
 * Domain Path:       /languages
 *
 * @package CM4AI
 */

namespace CM4AI;

defined( 'ABSPATH' ) || exit;

define( 'CM4AI_VERSION', '2.0.0' );
define( 'CM4AI_FILE', __FILE__ );
define( 'CM4AI_DIR', __DIR__ );

require_once __DIR__ . '/includes/Autoloader.php';

Autoloader::register();
register_activation_hook( __FILE__, [ Services\LegacySettingsMigration::class, 'run' ] );
Plugin::boot();
