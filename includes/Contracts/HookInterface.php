<?php
/**
 * Contract of the classes that attach WordPress hooks.
 *
 * @package CM4AI\Contracts
 */

namespace CM4AI\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Anything that registers actions or filters when the plugin boots.
 */
interface HookInterface {
    /**
     * Attaches the actions and filters.
     *
     * @return void
     */
    public function hooks(): void;
}
