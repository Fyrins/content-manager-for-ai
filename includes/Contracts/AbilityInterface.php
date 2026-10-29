<?php
/**
 * Contract of an ability registered by the plugin.
 *
 * @package CM4AI\Contracts
 */

namespace CM4AI\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * An ability handed to `wp_register_ability()`.
 */
interface AbilityInterface {
    /**
     * Fully qualified ability name, `namespace/name`.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Arguments passed to `wp_register_ability()`.
     *
     * @return array<string, mixed>
     */
    public function getProperties(): array;
}
