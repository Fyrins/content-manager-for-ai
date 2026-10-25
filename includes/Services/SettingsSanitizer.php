<?php
/**
 * Validates what is written to the two settings options.
 *
 * @package WpMcpAbilities\Services
 */

namespace WpMcpAbilities\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a submitted payload into the stored shape of each option.
 *
 * Shared by the Settings API, which runs it on every `update_option()` of the
 * options, and by the REST route behind the settings screen, which runs it
 * explicitly since `admin_init` never fires on a REST request. Running it twice
 * on the same payload yields the same result.
 */
class SettingsSanitizer {
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
     * Casts the submitted switches to booleans, leaving the rest alone.
     *
     * Starts from the stored option rather than from an empty array: an ability
     * whose dependency is momentarily inactive is absent from the catalogue,
     * and rebuilding from scratch would drop its entry. It would then fall back to
     * its own default on the next read, quietly turning back on an ability an
     * administrator had deliberately switched off.
     *
     * @param mixed $value Raw value submitted by the form.
     * @return array<string, bool>
     */
    public function sanitizeEnabled( mixed $value ): array {
        $submitted = is_array( $value ) ? $value : [];
        $sanitized = array_map( 'boolval', $this->settings->all() );

        $listed = array_merge(
            array_keys( $this->catalog->all() ),
            array_keys( $this->thirdParty->all() )
        );

        foreach ( $listed as $name ) {
            $sanitized[ $name ] = ! empty( $submitted[ $name ] );
        }

        return $sanitized;
    }

    /**
     * Casts the submitted exposures to booleans, leaving the rest alone.
     *
     * Starts from the stored option for the same reason as the switches: an
     * ability whose plugin is momentarily inactive is absent from the catalogue,
     * and rebuilding from scratch would drop its entry.
     *
     * @param mixed $value Raw value submitted by the form.
     * @return array<string, bool>
     */
    public function sanitizeExposed( mixed $value ): array {
        $submitted = is_array( $value ) ? $value : [];
        $sanitized = array_map( 'boolval', $this->settings->allExposed() );

        /*
         * Only the abilities the screen actually offered are rewritten, and only
         * those it offered an exposure box for. An entry left by a plugin that is
         * momentarily inactive stays, rather than quietly closing again on the
         * next read.
         */
        foreach ( $this->thirdParty->all() as $name => $ability ) {
            if ( $this->thirdParty->isExposedToMcp( $ability ) ) {
                continue;
            }

            $sanitized[ $name ] = ! empty( $submitted[ $name ] );
        }

        return $sanitized;
    }
}
