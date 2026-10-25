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
 * which validates explicitly: no section or field is declared here, and no
 * form posts to options.php any more. Registering the options still matters,
 * because the options are a documented way to drive the plugin from code: it
 * applies the same validation to every `update_option()` made from the admin.
 * Running it twice on a REST save is harmless, the validation being idempotent.
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
