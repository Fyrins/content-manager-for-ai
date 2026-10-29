<?php
/**
 * Reads the SEOpress meta of a post.
 *
 * @package CM4AI\Abilities\Seopress
 */

namespace CM4AI\Abilities\Seopress;

use CM4AI\Support\AbstractAbility;
use CM4AI\Support\Capabilities;
use CM4AI\Support\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only access to the SEOpress meta of a post.
 */
class GetPostSeoAbility extends AbstractAbility {
    use SeopressMetaMap;

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return self::qualify( 'get-post-seopress' );
    }

    /**
     * @inheritDoc
     */
    public function isReadOnly(): bool {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string {
        return __( 'Get post SEOpress meta', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __( 'Reads the SEOpress meta of a post.', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): string {
        return __( 'SEOpress', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool {
        return $this->isSeopressActive();
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => __( 'The post ID.', 'content-manager-for-ai-agents' ),
                ],
            ],
            'required'   => [ 'post_id' ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutputSchema(): array {
        return [
            'type'       => 'object',
            'properties' => array_merge(
                [
                    'focus_keyword'    => [ 'type' => [ 'string', 'null' ] ],
                    'primary_category' => [ 'type' => [ 'string', 'null' ] ],
                    'breadcrumb_title' => [ 'type' => [ 'string', 'null' ] ],
                    'nosnippet'        => [ 'type' => 'boolean' ],
                    'noimageindex'     => [ 'type' => 'boolean' ],
                    'redirect_enabled' => [ 'type' => 'boolean' ],
                    'redirect_type'    => [ 'type' => [ 'integer', 'null' ] ],
                    'redirect_url'     => [ 'type' => [ 'string', 'null' ] ],
                ],
                $this->seoReadSchemaProperties()
            ),
        ];
    }

    /**
     * @inheritDoc
     * @param mixed $input Raw input coming from the MCP adapter.
     */
    public function checkPermission( mixed $input = null ): bool {
        $input = Input::normalize( $input );
        $post  = $this->resolvePost( Input::int( $input, 'post_id' ) );

        if ( is_wp_error( $post ) ) {
            return false;
        }

        return Capabilities::canEditPost( $post );
    }

    /**
     * @inheritDoc
     * @param mixed $input Raw input coming from the MCP adapter.
     */
    public function execute( mixed $input = null ): array|\WP_Error {
        $input = Input::normalize( $input );
        $post  = $this->resolvePost( Input::int( $input, 'post_id' ) );

        if ( is_wp_error( $post ) ) {
            return $post;
        }

        if ( ! Capabilities::canEditPost( $post ) ) {
            return $this->forbidden();
        }

        $result = $this->readSeoData(
            $post->ID,
            $this->postSeoFields(),
            static fn ( int $objectId, string $metaKey ): mixed => get_post_meta( $objectId, $metaKey, true )
        );

        /**
         * Filters the SEOpress payload returned by the get-post-seopress ability.
         *
         * @param array<string, mixed> $result Formatted payload.
         * @param \WP_Post              $post   Post the meta was read from.
         */
        return (array) apply_filters( 'cm4ai_get_post_seo_result', $result, $post );
    }
}
