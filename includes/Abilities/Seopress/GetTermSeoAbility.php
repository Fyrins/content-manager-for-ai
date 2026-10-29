<?php
/**
 * Reads the SEOpress meta of a taxonomy term.
 *
 * @package CM4AI\Abilities\Seopress
 */

namespace CM4AI\Abilities\Seopress;

use CM4AI\Support\AbstractAbility;
use CM4AI\Support\Capabilities;
use CM4AI\Support\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only access to the SEOpress meta of a taxonomy term.
 */
class GetTermSeoAbility extends AbstractAbility {
    use SeopressMetaMap;

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return self::qualify( 'get-term-seopress' );
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
        return __( 'Get term SEOpress meta', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __( 'Reads the SEOpress meta of a taxonomy term.', 'content-manager-for-ai-agents' );
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
                'term_id'  => [
                    'type'        => 'integer',
                    'description' => __( 'The term ID.', 'content-manager-for-ai-agents' ),
                ],
                'taxonomy' => [
                    'type'        => 'string',
                    'description' => __( 'The taxonomy slug. Optional if term_id is unambiguous.', 'content-manager-for-ai-agents' ),
                ],
            ],
            'required'   => [ 'term_id' ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutputSchema(): array {
        return [
            'type'       => 'object',
            'properties' => $this->seoReadSchemaProperties(),
        ];
    }

    /**
     * Authorises against the very term being read.
     *
     * Same rule as `get-term-meta`, whose data this is: a blanket `edit_posts`
     * would let a contributor read the SEO fields of any term, private
     * taxonomies included.
     *
     * @inheritDoc
     * @param mixed $input Raw input coming from the MCP adapter.
     */
    public function checkPermission( mixed $input = null ): bool {
        $input = Input::normalize( $input );
        $term  = $this->resolveTerm( Input::int( $input, 'term_id' ), Input::string( $input, 'taxonomy' ) );

        if ( is_wp_error( $term ) ) {
            return false;
        }

        return Capabilities::canEditTerm( $term );
    }

    /**
     * @inheritDoc
     * @param mixed $input Raw input coming from the MCP adapter.
     */
    public function execute( mixed $input = null ): array|\WP_Error {
        $input = Input::normalize( $input );
        $term  = $this->resolveTerm( Input::int( $input, 'term_id' ), Input::string( $input, 'taxonomy' ) );

        // A missing term and a forbidden one answer the same, so that calling
        // the ability without its permission check cannot tell which exist.
        if ( is_wp_error( $term ) || ! Capabilities::canEditTerm( $term ) ) {
            return $this->forbidden();
        }

        $result = $this->readSeoData(
            $term->term_id,
            $this->termSeoFields(),
            static fn ( int $objectId, string $metaKey ): mixed => get_term_meta( $objectId, $metaKey, true )
        );

        /**
         * Filters the SEOpress payload returned by the get-term-seopress ability.
         *
         * @param array<string, mixed> $result Formatted payload.
         * @param \WP_Term              $term   Term the meta was read from.
         */
        return (array) apply_filters( 'cm4ai_get_term_seo_result', $result, $term );
    }
}
