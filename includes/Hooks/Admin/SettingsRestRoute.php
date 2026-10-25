<?php
/**
 * REST route read and written by the settings screen.
 *
 * @package WpMcpAbilities\Hooks\Admin
 */

namespace WpMcpAbilities\Hooks\Admin;

use WpMcpAbilities\Contracts\HookInterface;
use WpMcpAbilities\Services\SettingsSanitizer;
use WpMcpAbilities\Services\SettingsScreenData;
use WpMcpAbilities\Services\SettingsService;

defined( 'ABSPATH' ) || exit;

/**
 * `GET` returns everything the screen shows, `POST` saves the switches.
 *
 * Only speaks REST: what the screen shows is built by `SettingsScreenData`.
 * The abilities registry is only resolved inside the callbacks, once every
 * plugin has had its turn to register, never while the route is declared.
 */
class SettingsRestRoute implements HookInterface {
    /**
     * REST namespace of the route.
     *
     * @var string
     */
    public const NAMESPACE = 'wp-mcp-abilities/v1';

    /**
     * Path of the route inside its namespace.
     *
     * @var string
     */
    public const ROUTE = '/settings';

    /**
     * @param SettingsScreenData $screenData What the screen shows.
     * @param SettingsSanitizer  $sanitizer  Validation shared with the Settings API.
     */
    public function __construct(
        private SettingsScreenData $screenData,
        private SettingsSanitizer $sanitizer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function hooks(): void {
        add_action( 'rest_api_init', [ $this, 'registerRoute' ] );
    }

    /**
     * Declares the route.
     *
     * @return void
     */
    public function registerRoute(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'read' ],
                    'permission_callback' => [ $this, 'canManage' ],
                ],
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'save' ],
                    'permission_callback' => [ $this, 'canManage' ],
                    'args'                => [
                        'enabled' => [
                            'type'                 => 'object',
                            'additionalProperties' => [ 'type' => 'boolean' ],
                        ],
                        'exposed' => [
                            'type'                 => 'object',
                            'additionalProperties' => [ 'type' => 'boolean' ],
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Same capability as the screen.
     */
    public function canManage(): bool {
        return current_user_can( SettingsService::CAPABILITY );
    }

    /**
     * Returns the screen's data.
     *
     * @return \WP_REST_Response
     */
    public function read(): \WP_REST_Response {
        return rest_ensure_response( $this->screenData->payload() );
    }

    /**
     * Saves the switches sent, then returns the screen's data as stored.
     *
     * Only what the request carries changes. The option's validation switches
     * off every listed ability its payload leaves out, the way a form treats an
     * unchecked box; a REST client sending a single switch, or none, must not
     * turn everything else off. The current state is therefore laid under the
     * request before validating. The screen always sends every switch, so it
     * sees no difference.
     *
     * @param \WP_REST_Request $request Request carrying `enabled` and `exposed`.
     * @return \WP_REST_Response
     */
    public function save( \WP_REST_Request $request ): \WP_REST_Response {
        $enabled = $request->get_param( 'enabled' );
        $exposed = $request->get_param( 'exposed' );

        if ( is_array( $enabled ) ) {
            update_option(
                SettingsService::OPTION_KEY,
                $this->sanitizer->sanitizeEnabled( array_merge( $this->screenData->currentSwitches( 'enabled' ), $enabled ) )
            );
        }

        if ( is_array( $exposed ) ) {
            update_option(
                SettingsService::EXPOSED_OPTION_KEY,
                $this->sanitizer->sanitizeExposed( array_merge( $this->screenData->currentSwitches( 'exposed' ), $exposed ) )
            );
        }

        return rest_ensure_response( $this->screenData->payload() );
    }
}
