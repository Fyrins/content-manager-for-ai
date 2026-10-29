<?php
/**
 * Updates an existing term.
 *
 * @package CM4AI\Abilities\Taxonomy
 */

namespace CM4AI\Abilities\Taxonomy;

use CM4AI\Support\AbstractAbility;
use CM4AI\Support\Capabilities;
use CM4AI\Support\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Renames a term or edits its slug, description or parent.
 */
class UpdateTermAbility extends AbstractAbility {
    use TaxonomyFormatting;

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return self::qualify( 'update-term' );
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string {
        return __( 'Update a term', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __(
            'Updates the name, slug, description or parent of an existing term.',
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
                'term_id'     => [
                    'type'        => 'integer',
                    'description' => __( 'Identifier of the term to update.', 'content-manager-for-ai-agents' ),
                ],
                'taxonomy'    => [
                    'type'        => 'string',
                    'description' => __( 'Taxonomy the term belongs to.', 'content-manager-for-ai-agents' ),
                ],
                'name'        => [
                    'type'        => 'string',
                    'description' => __( 'New display name.', 'content-manager-for-ai-agents' ),
                ],
                'slug'        => [
                    'type'        => 'string',
                    'description' => __( 'New slug.', 'content-manager-for-ai-agents' ),
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => __( 'New description.', 'content-manager-for-ai-agents' ),
                ],
                'parent_id'   => [
                    'type'        => 'integer',
                    'description' => __( 'New parent term, 0 to move the term to the root.', 'content-manager-for-ai-agents' ),
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
            'properties' => [
                'success' => [ 'type' => 'boolean' ],
                'term'    => $this->termSchema(),
            ],
        ];
    }

    /**
     * Authorises against the very term being edited, not a blanket capability.
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

        if ( is_wp_error( $term ) ) {
            return $term;
        }

        $valid = $this->validateTaxonomy( $term->taxonomy );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        if ( ! Capabilities::canEditTerm( $term ) ) {
            return $this->forbidden();
        }

        $args = [];

        if ( isset( $input['name'] ) ) {
            $args['name'] = Input::string( $input, 'name' );
        }

        if ( isset( $input['slug'] ) ) {
            $args['slug'] = sanitize_title( Input::string( $input, 'slug' ) );
        }

        if ( isset( $input['description'] ) ) {
            $args['description'] = sanitize_textarea_field( (string) $input['description'] );
        }

        if ( isset( $input['parent_id'] ) ) {
            $args['parent'] = Input::int( $input, 'parent_id' );
        }

        if ( [] === $args ) {
            return new \WP_Error(
                'nothing_to_update',
                __( 'Provide at least one field to update.', 'content-manager-for-ai-agents' ),
                [ 'status' => 400 ]
            );
        }

        /**
         * Filters the `wp_update_term()` arguments used by the update-term ability.
         *
         * @param array<string, mixed> $args  Arguments passed to wp_update_term().
         * @param \WP_Term             $term  Term being updated.
         * @param array<string, mixed> $input Normalised MCP input.
         */
        $args = (array) apply_filters( 'cm4ai_update_term_args', $args, $term, $input );

        $result = wp_update_term( $term->term_id, $term->taxonomy, $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $updated = $this->resolveTerm( (int) $result['term_id'], $term->taxonomy );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        return [
            'success' => true,
            'term'    => $this->formatTerm( $updated ),
        ];
    }
}
