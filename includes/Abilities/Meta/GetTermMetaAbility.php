<?php
/**
 * Reads a term meta field.
 *
 * @package CM4AI\Abilities\Meta
 */

namespace CM4AI\Abilities\Meta;

use CM4AI\Support\AbstractAbility;
use CM4AI\Support\Capabilities;
use CM4AI\Support\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Reads a single term meta field, guarded by the protected-meta allowlist.
 */
class GetTermMetaAbility extends AbstractAbility {
    use MetaAccess;

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return self::qualify( 'get-term-meta' );
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
        return __( 'Get term meta', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __(
            'Reads a term meta field. Protected meta keys are refused unless explicitly allowlisted.',
            'content-manager-for-ai-agents'
        );
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): string {
        return __( 'Meta', 'content-manager-for-ai-agents' );
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
                    'description' => __( 'Identifier of the term to read.', 'content-manager-for-ai-agents' ),
                ],
                'taxonomy' => [
                    'type'        => 'string',
                    'description' => __( 'Taxonomy the term belongs to, used to disambiguate the lookup.', 'content-manager-for-ai-agents' ),
                ],
                'meta_key' => [
                    'type'        => 'string',
                    'description' => __(
                        'Meta key to read. Omit it to list every readable meta of the term.',
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
                'success'    => [ 'type' => 'boolean' ],
                'term_id'    => [ 'type' => 'integer' ],
                'meta_key'   => [ 'type' => 'string' ],
                'meta_value' => $this->metaValueSchema(),
                'meta'       => [
                    'type'        => 'object',
                    'description' => __(
                        'Every readable meta of the term, returned when no meta key was given.',
                        'content-manager-for-ai-agents'
                    ),
                ],
            ],
        ];
    }

    /**
     * Authorises against the very term being read.
     *
     * Term meta is only exposed by the REST API in the `edit` context, and its
     * write siblings check `edit_term`: a blanket `edit_posts` would let a
     * contributor read the meta of any term, private taxonomies included.
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

        $rawMetaKey = $input['meta_key'] ?? null;

        if ( null === $rawMetaKey || '' === $rawMetaKey ) {
            return [
                'success' => true,
                'term_id' => $term->term_id,
                'meta'    => $this->filterReadableMeta( 'term', (array) get_term_meta( $term->term_id ) ),
            ];
        }

        $metaKey = $this->resolveMetaKey( 'term', $rawMetaKey );
        if ( is_wp_error( $metaKey ) ) {
            return $metaKey;
        }

        return [
            'success'    => true,
            'term_id'    => $term->term_id,
            'meta_key'   => $metaKey,
            'meta_value' => get_term_meta( $term->term_id, $metaKey, true ),
        ];
    }
}
