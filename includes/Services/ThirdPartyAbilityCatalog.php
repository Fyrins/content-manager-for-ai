<?php
/**
 * Inventory of the abilities other plugins have registered.
 *
 * @package CM4AI\Services
 */

namespace CM4AI\Services;

use CM4AI\Support\AbstractAbility;

defined( 'ABSPATH' ) || exit;

/**
 * Lists what reaches MCP without coming from this plugin.
 *
 * The settings screen used to show only what this plugin ships, while a
 * production site registers far more. Everything carrying `meta.mcp.public` is
 * reachable through `mcp-adapter/execute-ability`, so an inventory that stops at
 * our own abilities tells an administrator less than half of what an agent can
 * do on the site.
 */
class ThirdPartyAbilityCatalog {
    /**
     * Namespaces this catalogue never offers to switch off.
     *
     * Removing an ability of the adapter would cut the branch the agent sits on,
     * and the core ones are what the adapter builds upon.
     *
     * `cm4ai` is not among them on purpose: it is this plugin's prefix,
     * but another plugin could register abilities under it too, and excluding it
     * would hide those from this screen without listing them on the other one.
     * Ours are recognised by the mark they carry, not by their name.
     *
     * @var array<int, string>
     */
    private const PROTECTED_NAMESPACES = [ 'core', 'mcp-adapter' ];

    /**
     * Abilities registered by other plugins, grouped by namespace.
     *
     * @return array<string, array<string, \WP_Ability>>
     */
    public function grouped(): array {
        $grouped = [];

        foreach ( $this->all() as $name => $ability ) {
            $grouped[ $this->namespaceOf( $name ) ][ $name ] = $ability;
        }

        ksort( $grouped );

        return $grouped;
    }

    /**
     * Abilities registered by other plugins, keyed by name.
     *
     * @return array<string, \WP_Ability>
     */
    public function all(): array {
        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return [];
        }

        $abilities = [];

        foreach ( wp_get_abilities() as $ability ) {
            if ( ! $ability instanceof \WP_Ability ) {
                continue;
            }

            $name = $ability->get_name();

            if ( in_array( $this->namespaceOf( $name ), self::PROTECTED_NAMESPACES, true ) ) {
                continue;
            }

            if ( $this->isOurs( $ability ) ) {
                continue;
            }

            $abilities[ $name ] = $ability;
        }

        ksort( $abilities );

        return $abilities;
    }

    /**
     * Whether an ability is exposed to MCP clients by its own declaration.
     *
     * One that is not stays listed, so the screen says what exists rather than
     * only what is reachable, but switching it off changes nothing.
     *
     * @param \WP_Ability $ability Ability to read.
     */
    public function isExposedToMcp( \WP_Ability $ability ): bool {
        $meta = $ability->get_meta();

        return ! empty( $meta['mcp']['public'] );
    }

    /**
     * Whether an ability declares itself destructive.
     *
     * The Abilities API lets a plugin annotate what its ability does, and
     * SEOPress for one marks its writes. Read rather than guessed from the name:
     * `clear-website-cache` destroys nothing a name would betray, and
     * `update-redirection` destroys plenty.
     *
     * @param \WP_Ability $ability Ability to read.
     */
    public function isDestructive( \WP_Ability $ability ): bool {
        return $this->annotation( $ability, 'destructive' );
    }

    /**
     * Whether an ability declares that it only reads.
     *
     * Read from the same annotations as `isDestructive()`, for the same reason.
     * An ability that says nothing is taken as writing: the settings screen
     * would rather warn about a harmless ability than reassure about one that
     * changes the site.
     *
     * @param \WP_Ability $ability Ability to read.
     */
    public function isReadOnly( \WP_Ability $ability ): bool {
        return $this->annotation( $ability, 'readonly' );
    }

    /**
     * Option the settings box of an ability writes to.
     *
     * One box, two questions, never both on the same ability. An ability its
     * own plugin exposes can only be taken away from MCP, which the switches
     * hold; one it keeps closed can only be handed over, which the exposures
     * hold. The screen, the validation and the REST route all ask here, so
     * they cannot disagree.
     *
     * @param \WP_Ability $ability Ability to read.
     * @return string `SettingsService::OPTION_KEY` or `SettingsService::EXPOSED_OPTION_KEY`.
     */
    public function optionFor( \WP_Ability $ability ): string {
        return $this->isExposedToMcp( $ability )
            ? SettingsService::OPTION_KEY
            : SettingsService::EXPOSED_OPTION_KEY;
    }

    /**
     * Whether an ability sets one of its boolean annotations.
     *
     * @param \WP_Ability $ability Ability to read.
     * @param string      $key     Annotation name.
     */
    private function annotation( \WP_Ability $ability, string $key ): bool {
        $meta = $ability->get_meta();

        return ! empty( $meta['annotations'][ $key ] );
    }

    /**
     * Whether an ability was registered by this plugin.
     *
     * @param \WP_Ability $ability Ability to read.
     */
    private function isOurs( \WP_Ability $ability ): bool {
        $meta = $ability->get_meta();

        return ( $meta['cm4ai']['plugin'] ?? null ) === AbstractAbility::PLUGIN_MARK;
    }

    /**
     * Namespace part of an ability name.
     *
     * @param string $name Ability name.
     */
    private function namespaceOf( string $name ): string {
        $parts = explode( '/', $name, 2 );

        return $parts[0];
    }
}
