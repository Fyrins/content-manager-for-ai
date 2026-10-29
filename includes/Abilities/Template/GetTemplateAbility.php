<?php
/**
 * Retrieves a single template or template part.
 *
 * @package CM4AI\Abilities\Template
 */

namespace CM4AI\Abilities\Template;

use CM4AI\Support\AbstractAbility;
use CM4AI\Support\Capabilities;
use CM4AI\Support\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Read access to a single block template or template part, by id or slug.
 */
class GetTemplateAbility extends AbstractAbility {
    use TemplateAccess;

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return self::qualify( 'get-template' );
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
        return __( 'Get a template', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __(
            'Retrieves a block template or template part, whether it comes from a theme file or a customised post.',
            'content-manager-for-ai-agents'
        );
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): string {
        return __( 'Templates', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'type' => $this->typeProperty(),
                'id'   => [
                    'type'        => 'string',
                    'description' => __( 'Composite identifier, formatted as "{theme}//{slug}".', 'content-manager-for-ai-agents' ),
                ],
                'slug' => [
                    'type'        => 'string',
                    'description' => __(
                        'Template slug. Used instead of "id", completed with the active theme.',
                        'content-manager-for-ai-agents'
                    ),
                ],
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutputSchema(): array {
        return $this->templateSchema();
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
        if ( ! Capabilities::canRead() ) {
            return $this->forbidden();
        }

        $input = Input::normalize( $input );
        $type  = $this->resolveType( $input );

        $valid = $this->validateType( $type );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        $id = $this->resolveTemplateId( $input );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        $template = $this->resolveTemplate( $id, $type );
        if ( is_wp_error( $template ) ) {
            return $template;
        }

        /**
         * Filters the payload returned by the get-template ability.
         *
         * @param array<string, mixed> $result   Formatted template payload.
         * @param \WP_Block_Template    $template Raw template object.
         */
        return (array) apply_filters(
            'cm4ai_get_template_result',
            $this->formatTemplate( $template ),
            $template
        );
    }
}
