<?php
/**
 * REST route read and written by the settings screen.
 *
 * @package WpMcpAbilities\Hooks\Admin
 */

namespace WpMcpAbilities\Hooks\Admin;

use WpMcpAbilities\Contracts\HookInterface;
use WpMcpAbilities\Services\AbilityCatalogService;
use WpMcpAbilities\Services\SettingsSanitizer;
use WpMcpAbilities\Services\SettingsService;
use WpMcpAbilities\Services\ThirdPartyAbilityCatalog;
use WpMcpAbilities\Support\AbstractAbility;
use WpMcpAbilities\Support\Requirements;

defined( 'ABSPATH' ) || exit;

/**
 * `GET` returns everything the screen shows, `POST` saves the switches.
 *
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
     * Route of the MCP Adapter's default server, under its `mcp` namespace.
     *
     * @var string
     */
    private const DEFAULT_SERVER_ROUTE = 'mcp/mcp-adapter-default-server';

    /**
     * @param AbilityCatalogService    $catalog    Inventory of the abilities of this plugin.
     * @param ThirdPartyAbilityCatalog $thirdParty Inventory of the abilities of other plugins.
     * @param SettingsService          $settings   Reader of the stored options.
     * @param SettingsSanitizer        $sanitizer  Validation shared with the Settings API.
     */
    public function __construct(
        private AbilityCatalogService $catalog,
        private ThirdPartyAbilityCatalog $thirdParty,
        private SettingsService $settings,
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
     * Same capability as the screen and as options.php.
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
        return rest_ensure_response( $this->payload() );
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
                $this->sanitizer->sanitizeEnabled( array_merge( $this->currentSwitches( 'enabled' ), $enabled ) )
            );
        }

        if ( is_array( $exposed ) ) {
            update_option(
                SettingsService::EXPOSED_OPTION_KEY,
                $this->sanitizer->sanitizeExposed( array_merge( $this->currentSwitches( 'exposed' ), $exposed ) )
            );
        }

        return rest_ensure_response( $this->payload() );
    }

    /**
     * Current state of the switches one option holds, as the screen sends them.
     *
     * Read from the same rows the screen shows, so a request merged over it
     * changes exactly what it names.
     *
     * @param string $option `enabled` for the switches, `exposed` for the exposures.
     * @return array<string, bool>
     */
    private function currentSwitches( string $option ): array {
        $switches = [];

        foreach ( $this->ownAbilities() as $row ) {
            if ( 'enabled' === $option ) {
                $switches[ $row['name'] ] = $row['enabled'];
            }
        }

        foreach ( $this->thirdPartyAbilities() as $row ) {
            if ( ( 'enabled' === $option ) === $row['joinable'] ) {
                $switches[ $row['name'] ] = $row['enabled'];
            }
        }

        return $switches;
    }

    /**
     * Everything the screen needs, in one document.
     *
     * @return array<string, mixed>
     */
    private function payload(): array {
        return [
            'abilities'  => $this->ownAbilities(),
            'thirdParty' => $this->thirdPartyAbilities(),
            'connection' => $this->connection(),
        ];
    }

    /**
     * The plugin's abilities, sorted by group then label.
     *
     * @return list<array<string, mixed>>
     */
    private function ownAbilities(): array {
        $rows = [];

        foreach ( $this->catalog->grouped() as $group => $abilities ) {
            foreach ( $abilities as $name => $ability ) {
                /** @var AbstractAbility $ability */
                $rows[] = [
                    'name'        => $name,
                    'label'       => $ability->getLabel(),
                    'description' => $ability->getDescription(),
                    'group'       => (string) $group,
                    'readonly'    => $ability->isReadOnly(),
                    'destructive' => $ability->isDestructive(),
                    'enabled'     => $this->settings->isEnabled( $name, $ability->isEnabledByDefault() ),
                ];
            }
        }

        return $rows;
    }

    /**
     * Abilities of other plugins, sorted by namespace then name.
     *
     * `joinable` tells which of the two options the box writes to. An ability its
     * own plugin exposes can only be taken away from MCP, and lands in the
     * switches; one it keeps closed can only be handed over, and lands in the
     * exposures.
     *
     * @return list<array<string, mixed>>
     */
    private function thirdPartyAbilities(): array {
        $rows = [];

        foreach ( $this->thirdParty->grouped() as $namespace => $abilities ) {
            foreach ( $abilities as $name => $ability ) {
                $joinable = $this->thirdParty->isExposedToMcp( $ability );

                $rows[] = [
                    'name'        => $name,
                    'label'       => (string) $ability->get_label(),
                    'description' => (string) $ability->get_description(),
                    'group'       => (string) $namespace,
                    'readonly'    => $this->thirdParty->isReadOnly( $ability ),
                    'destructive' => $this->thirdParty->isDestructive( $ability ),
                    'joinable'    => $joinable,
                    'enabled'     => $joinable
                        ? $this->settings->isEnabled( $name, true )
                        : $this->settings->isExposed( $name ),
                ];
            }
        }

        return $rows;
    }

    /**
     * What a client needs to connect, and what would keep it from doing so.
     *
     * @return array<string, mixed>
     */
    private function connection(): array {
        $user       = wp_get_current_user();
        $serverName = sanitize_title( get_bloginfo( 'name' ) );

        return [
            'endpoint'              => rest_url( self::DEFAULT_SERVER_ROUTE ),
            'serverName'            => '' !== $serverName ? $serverName : 'wordpress',
            'userLogin'             => $user->user_login,
            'mcpAdapter'            => Requirements::hasMcpAdapter(),
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The MCP Adapter's filter, read here but not declared by this plugin.
            'defaultServer'         => (bool) apply_filters( 'mcp_adapter_create_default_server', true ),
            'appPasswordsAvailable' => wp_is_application_passwords_available_for_user( $user ),
            'profileUrl'            => admin_url( 'profile.php#application-passwords-section' ),
            'adapterUrl'            => Requirements::MCP_ADAPTER_URL,
        ];
    }
}
