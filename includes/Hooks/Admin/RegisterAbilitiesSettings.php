<?php
/**
 * Declares the settings options through the Settings API.
 *
 * @package WpMcpAbilities\Hooks\Admin
 */

namespace WpMcpAbilities\Hooks\Admin;

use WpMcpAbilities\Contracts\HookInterface;
use WpMcpAbilities\Services\SettingsSanitizer;
use WpMcpAbilities\Services\SettingsService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers both options and their validation.
 *
 * The screen itself is rendered by a script and saves through the REST route,
 * so no section or field is declared here. Registering the options still
 * matters: it is what applies their validation to every `update_option()` made
 * from the admin, whatever made it.
 */
class RegisterAbilitiesSettings implements HookInterface {
    /**
     * @param SettingsSanitizer $sanitizer Validation shared with the REST route.
     */
    public function __construct( private SettingsSanitizer $sanitizer ) {
    }

    /**
     * @inheritDoc
     */
    public function hooks(): void {
        add_action( 'admin_init', [ $this, 'registerSettings' ] );

        // Ties what options.php requires to write the option to what the screen
        // requires to be opened, so lowering one never silently leaves the other
        // behind.
        add_filter(
            'option_page_capability_' . SettingsService::PAGE_SLUG,
            static fn (): string => SettingsService::CAPABILITY
        );
    }

    /**
     * Registers the options.
     *
     * The sanitize callbacks resolve the third-party catalogue, hence the
     * abilities registry, but only when an option is actually written, never
     * while the hook runs: resolving it on every `admin_init` would freeze the
     * registry before later plugins have registered theirs.
     *
     * @return void
     */
    public function registerSettings(): void {
        register_setting(
            SettingsService::PAGE_SLUG,
            SettingsService::OPTION_KEY,
            [
                'type'              => 'object',
                'default'           => [],
                'sanitize_callback' => [ $this->sanitizer, 'sanitizeEnabled' ],
                'show_in_rest'      => false,
            ]
        );

        register_setting(
            SettingsService::PAGE_SLUG,
            SettingsService::EXPOSED_OPTION_KEY,
            [
                'type'              => 'object',
                'default'           => [],
                'sanitize_callback' => [ $this->sanitizer, 'sanitizeExposed' ],
                'show_in_rest'      => false,
            ]
        );
    }
}
