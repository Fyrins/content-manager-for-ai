<?php
/**
 * Permanently deletes a term.
 *
 * @package CM4AI\Abilities\Taxonomy
 */

namespace CM4AI\Abilities\Taxonomy;

use CM4AI\Support\AbstractAbility;
use CM4AI\Support\Capabilities;
use CM4AI\Support\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes a term from any exposed taxonomy. Destructive, disabled by default.
 */
class DeleteTermAbility extends AbstractAbility {
    use TaxonomyFormatting;

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return self::qualify( 'delete-term' );
    }

    /**
     * @inheritDoc
     */
    public function isDestructive(): bool {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string {
        return __( 'Delete a term', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __( 'Permanently deletes a taxonomy term.', 'content-manager-for-ai-agents' );
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
                    'description' => __( 'Identifier of the term to delete.', 'content-manager-for-ai-agents' ),
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
        return [
            'type'       => 'object',
            'properties' => [
                'success' => [ 'type' => 'boolean' ],
            ],
        ];
    }

    /**
     * Authorises against the very term being deleted, not a blanket capability.
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

        return Capabilities::canDeleteTerm( $term );
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

        if ( ! Capabilities::canDeleteTerm( $term ) ) {
            return $this->forbidden();
        }

        $result = wp_delete_term( $term->term_id, $term->taxonomy );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( false === $result ) {
            return new \WP_Error(
                'delete_failed',
                __( 'Unable to delete the term.', 'content-manager-for-ai-agents' ),
                [ 'status' => 500 ]
            );
        }

        return [ 'success' => true ];
    }
}
