<?php
/**
 * What the settings screen shows, read from the catalogues and the options.
 *
 * @package WpMcpAbilities\Services
 */

namespace WpMcpAbilities\Services;

use WpMcpAbilities\Support\AbstractAbility;
use WpMcpAbilities\Support\Requirements;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the document the REST route serves, and the state a save starts from.
 *
 * Kept out of the route so that the route only speaks REST, and so that the
 * rows and the switches a save merges over are read the same way.
 */
class SettingsScreenData {
    /**
     * Route of the MCP Adapter's default server, under its `mcp` namespace.
     *
     * @var string
     */
    private const DEFAULT_SERVER_ROUTE = 'mcp/mcp-adapter-default-server';

    /**
     * REST parameter carrying each option, as rows name it in `option`.
     *
     * @var array<string, string>
     */
    private const PARAMS = [
        SettingsService::OPTION_KEY         => 'enabled',
        SettingsService::EXPOSED_OPTION_KEY => 'exposed',
    ];

    /**
     * @param AbilityCatalogService    $catalog    Inventory of the abilities of this plugin.
     * @param ThirdPartyAbilityCatalog $thirdParty Inventory of the abilities of other plugins.
     * @param SettingsService          $settings   Reader of the stored options.
     */
    public function __construct(
        private AbilityCatalogService $catalog,
        private ThirdPartyAbilityCatalog $thirdParty,
        private SettingsService $settings
    ) {
    }

    /**
     * Everything the screen needs, in one document.
     *
     * @return array<string, mixed>
     */
    public function payload(): array {
        return [
            'abilities'  => $this->ownAbilities(),
            'thirdParty' => $this->thirdPartyAbilities(),
            'connection' => $this->connection(),
        ];
    }

    /**
     * Current state of the switches one option holds, as the screen sends them.
     *
     * Read from the same rows the screen shows, so a request merged over it
     * changes exactly what it names.
     *
     * @param string $param `enabled` for the switches, `exposed` for the exposures.
     * @return array<string, bool>
     */
    public function currentSwitches( string $param ): array {
        $switches = [];

        foreach ( array_merge( $this->ownAbilities(), $this->thirdPartyAbilities() ) as $row ) {
            if ( $param === $row['option'] ) {
                $switches[ $row['name'] ] = $row['enabled'];
            }
        }

        return $switches;
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
                    'option'      => self::PARAMS[ SettingsService::OPTION_KEY ],
                    'enabled'     => $this->settings->isEnabled( $name, $ability->isEnabledByDefault() ),
                ];
            }
        }

        return $rows;
    }

    /**
     * Abilities of other plugins, sorted by namespace then name.
     *
     * `option` names the REST parameter, hence the option, the box writes to,
     * as decided by `ThirdPartyAbilityCatalog::optionFor()`: the screen sends
     * each box back under it without having to know the rule.
     *
     * @return list<array<string, mixed>>
     */
    private function thirdPartyAbilities(): array {
        $rows = [];

        foreach ( $this->thirdParty->grouped() as $namespace => $abilities ) {
            foreach ( $abilities as $name => $ability ) {
                $option = $this->thirdParty->optionFor( $ability );

                $rows[] = [
                    'name'        => $name,
                    'label'       => (string) $ability->get_label(),
                    'description' => (string) $ability->get_description(),
                    'group'       => (string) $namespace,
                    'readonly'    => $this->thirdParty->isReadOnly( $ability ),
                    'destructive' => $this->thirdParty->isDestructive( $ability ),
                    'option'      => self::PARAMS[ $option ],
                    'enabled'     => SettingsService::OPTION_KEY === $option
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

        /**
         * Filters the MCP server URL the settings screen hands out.
         *
         * The screen points at the MCP Adapter's default server. A site
         * declaring its own server, or an adapter version that moves the
         * default one, can point it elsewhere.
         *
         * @param string $url URL of the MCP server.
         */
        $endpoint = (string) apply_filters(
            'wpmcpa_server_url',
            rest_url( self::DEFAULT_SERVER_ROUTE )
        );

        return [
            'endpoint'              => $endpoint,
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
