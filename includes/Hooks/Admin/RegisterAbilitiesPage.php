<?php
/**
 * Adds the settings page and loads the script rendering it.
 *
 * @package CM4AI\Hooks\Admin
 */

namespace CM4AI\Hooks\Admin;

use CM4AI\Contracts\HookInterface;
use CM4AI\Services\SettingsService;

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
    private const HANDLE = 'cm4ai-settings';

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
            __( 'Content Manager for AI Agents', 'content-manager-for-ai-agents' ),
            __( 'Content Manager for AI Agents', 'content-manager-for-ai-agents' ),
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

        $assetFile = $this->assetFile();

        if ( null === $assetFile ) {
            return;
        }

        $asset = require $assetFile;
        $url   = plugins_url( 'build/', CM4AI_FILE );

        wp_enqueue_script(
            self::HANDLE,
            $url . 'index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );
        wp_set_script_translations( self::HANDLE, 'content-manager-for-ai-agents', CM4AI_DIR . '/languages' );

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
            sprintf( 'window.cm4ai = %s;', wp_json_encode( [ 'path' => $path ], JSON_HEX_TAG | JSON_HEX_AMP ) ),
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
        <div class="wrap cm4ai-settings-wrap">
            <h1><?php echo esc_html__( 'Content Manager for AI Agents', 'content-manager-for-ai-agents' ); ?></h1>
            <div id="cm4ai-settings">
                <?php if ( null === $this->assetFile() ) : ?>
                    <div class="notice notice-error inline">
                        <p><?php esc_html_e( 'The screen\'s script is missing. On a copy installed from the repository, run `npm ci && npm run build`.', 'content-manager-for-ai-agents' ); ?></p>
                    </div>
                <?php endif; ?>
                <noscript>
                    <?php esc_html_e( 'This screen needs JavaScript.', 'content-manager-for-ai-agents' ); ?>
                </noscript>
            </div>
        </div>
        <?php
    }

    /**
     * The asset file `npm run build` writes, if the script has been built.
     *
     * @return string|null Path of the file, or null when it is missing.
     */
    private function assetFile(): ?string {
        $file = CM4AI_DIR . '/build/index.asset.php';

        return is_readable( $file ) ? $file : null;
    }
}
