<?php
/**
 * Carries the switches over from the plugin's former name.
 *
 * @package CM4AI\Services
 */

namespace CM4AI\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Copies the settings saved under WP MCP Abilities, the plugin's name up to 1.3.
 *
 * The rename changed the plugin folder, so WordPress sees Content Manager for AI Agents as a new
 * plugin: nothing is migrated in place, the copy runs on activation. The old
 * options are left alone, since the old plugin may still be installed and its
 * own uninstall removes them. Ability names are rewritten with the new prefix;
 * exposed third-party abilities keep theirs, they belong to other plugins.
 */
class LegacySettingsMigration {
    /**
     * Option holding the switches under the former name.
     *
     * @var string
     */
    public const LEGACY_OPTION_KEY = 'wpmcpa_enabled';

    /**
     * Option holding the third-party exposure under the former name.
     *
     * @var string
     */
    public const LEGACY_EXPOSED_OPTION_KEY = 'wpmcpa_exposed';

    /**
     * Ability prefix under the former name.
     *
     * @var string
     */
    private const LEGACY_PREFIX = 'wp-mcp-abilities/';

    /**
     * Copies the legacy options when the new ones were never saved.
     *
     * @return void
     */
    public static function run(): void {
        $legacy = get_option( self::LEGACY_OPTION_KEY );
        if ( is_array( $legacy ) && false === get_option( SettingsService::OPTION_KEY ) ) {
            $settings = [];
            foreach ( $legacy as $ability => $enabled ) {
                $name = (string) $ability;
                if ( str_starts_with( $name, self::LEGACY_PREFIX ) ) {
                    $name = 'cm4ai/' . substr( $name, strlen( self::LEGACY_PREFIX ) );
                }
                $settings[ $name ] = (bool) $enabled;
            }
            update_option( SettingsService::OPTION_KEY, $settings );
        }

        $exposed = get_option( self::LEGACY_EXPOSED_OPTION_KEY );
        if ( is_array( $exposed ) && false === get_option( SettingsService::EXPOSED_OPTION_KEY ) ) {
            update_option( SettingsService::EXPOSED_OPTION_KEY, array_map( 'boolval', $exposed ) );
        }
    }
}
