<?php
/**
 * Adds the settings page and loads the script rendering it.
 *
 * @package WpMcpAbilities\Hooks\Admin
 */

namespace WpMcpAbilities\Hooks\Admin;

use WpMcpAbilities\Contracts\HookInterface;
use WpMcpAbilities\Services\SettingsService;

defined( 'ABSPATH' ) || exit;

/**
 * Declares the page, renders its mount point and enqueues its script.
 *
 * Everything shown on the page comes from `SettingsRestRoute`, preloaded so
 * the screen renders without waiting on a first request.
 */
class RegisterAbilitiesPage implements HookInterface {
    /**
     * Handle of the script and of the stylesheet.
     *
     * @var string
     */
    private const HANDLE = 'wp-mcp-abilities-settings';

    /**
     * Hook suffix of the page, known once it is added.
     *
     * @var string
     */
    private string $hookSuffix = '';

    /**
     * @inheritDoc
     */
    public function hooks(): void {
        add_action( 'admin_menu', [ $this, 'registerPage' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
    }

    /**
     * Adds the page under the Settings menu.
     *
     * @return void
     */
    public function registerPage(): void {
        $this->hookSuffix = (string) add_options_page(
            __( 'MCP Abilities', 'wp-mcp-abilities' ),
            __( 'MCP Abilities', 'wp-mcp-abilities' ),
            SettingsService::CAPABILITY,
            SettingsService::PAGE_SLUG,
            [ $this, 'renderPage' ]
        );
    }

    /**
     * Enqueues the screen's script on the page only.
     *
     * @param string $hookSuffix Hook suffix of the current admin page.
     * @return void
     */
    public function enqueueAssets( string $hookSuffix ): void {
        if ( '' === $this->hookSuffix || $hookSuffix !== $this->hookSuffix ) {
            return;
        }

        $assetFile = WPMCPA_DIR . '/build/index.asset.php';

        if ( ! is_readable( $assetFile ) ) {
            return;
        }

        $asset = require $assetFile;
        $url   = plugins_url( 'build/', WPMCPA_FILE );

        wp_enqueue_script(
            self::HANDLE,
            $url . 'index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );
        wp_set_script_translations( self::HANDLE, 'wp-mcp-abilities', WPMCPA_DIR . '/languages' );

        wp_enqueue_style(
            self::HANDLE,
            $url . 'style-index.css',
            [ 'wp-components' ],
            $asset['version']
        );
        wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

        $path    = '/' . SettingsRestRoute::NAMESPACE . SettingsRestRoute::ROUTE;
        $preload = array_reduce( [ $path ], 'rest_preload_api_request', [] );

        wp_add_inline_script(
            'wp-api-fetch',
            sprintf(
                'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );',
                wp_json_encode( $preload, JSON_HEX_TAG | JSON_HEX_AMP )
            ),
            'after'
        );
        wp_add_inline_script(
            self::HANDLE,
            sprintf( 'window.wpMcpAbilities = %s;', wp_json_encode( [ 'path' => $path ], JSON_HEX_TAG | JSON_HEX_AMP ) ),
            'before'
        );
    }

    /**
     * Renders the mount point of the script.
     *
     * @return void
     */
    public function renderPage(): void {
        if ( ! current_user_can( SettingsService::CAPABILITY ) ) {
            return;
        }

        ?>
        <div class="wrap wpmcpa-settings-wrap">
            <h1><?php echo esc_html__( 'MCP Abilities', 'wp-mcp-abilities' ); ?></h1>
            <div id="wpmcpa-settings">
                <?php if ( ! is_readable( WPMCPA_DIR . '/build/index.asset.php' ) ) : ?>
                    <div class="notice notice-error inline">
                        <p><?php esc_html_e( 'The screen\'s script is missing. On a copy installed from the repository, run `npm ci && npm run build`.', 'wp-mcp-abilities' ); ?></p>
                    </div>
                <?php endif; ?>
                <noscript>
                    <?php esc_html_e( 'This screen needs JavaScript.', 'wp-mcp-abilities' ); ?>
                </noscript>
            </div>
        </div>
        <?php
    }
}
