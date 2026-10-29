<?php
/**
 * Retrieves a single term.
 *
 * @package CM4AI\Abilities\Taxonomy
 */

namespace CM4AI\Abilities\Taxonomy;

use CM4AI\Support\AbstractAbility;
use CM4AI\Support\Capabilities;
use CM4AI\Support\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Read access to a single term of any exposed taxonomy.
 */
class GetTermAbility extends AbstractAbility {
    use TaxonomyFormatting;

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return self::qualify( 'get-term' );
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
        return __( 'Get a term', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __(
            'Retrieves a taxonomy term by ID (name, slug, description, parent, count).',
            'content-manager-for-ai-agents'
        );
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): string {
        return __( 'Taxonomies', 'content-manager-for-ai-agents' );
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
                    'description' => __( 'Identifier of the term to retrieve.', 'content-manager-for-ai-agents' ),
                ],
                'taxonomy' => [
                    'type'        => 'string',
                    'description' => __(
                        'Taxonomy the term belongs to. Optional if the ID is unambiguous.',
                        'content-manager-for-ai-agents'
                    ),
                ],
            ],
            'required'   => [ 'term_id' ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutputSchema(): array {
        return $this->termSchema();
    }

    /**
     * @inheritDoc
     * @param mixed $input Raw input coming from the MCP adapter.
     */
    public function checkPermission( mixed $input = null ): bool {
        return Capabilities::canRead();
    }

    /**
     * @inheritDoc
     * @param mixed $input Raw input coming from the MCP adapter.
     */
    public function execute( mixed $input = null ): array|\WP_Error {
        $input = Input::normalize( $input );
        $term  = $this->resolveTerm( Input::int( $input, 'term_id' ), Input::string( $input, 'taxonomy' ) );

        if ( is_wp_error( $term ) ) {
            return $term;
        }

        $valid = $this->validateTaxonomy( $term->taxonomy );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        if ( ! Capabilities::canRead() ) {
            return $this->forbidden();
        }

        /**
         * Filters the payload returned by the get-term ability.
         *
         * @param array<string, mixed> $result Formatted term payload.
         * @param \WP_Term              $term   Raw term object.
         */
        return (array) apply_filters( 'cm4ai_get_term_result', $this->formatTerm( $term ), $term );
    }
}
