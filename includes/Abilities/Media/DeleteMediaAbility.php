<?php
/**
 * Permanently deletes an attachment.
 *
 * @package CM4AI\Abilities\Media
 */

namespace CM4AI\Abilities\Media;

use CM4AI\Support\AbstractAbility;
use CM4AI\Support\Capabilities;
use CM4AI\Support\Input;

defined( 'ABSPATH' ) || exit;

/**
 * Permanently deletes a media from the library.
 *
 * Destructive: disabled by default, sites must opt in explicitly.
 */
class DeleteMediaAbility extends AbstractAbility {
    use MediaFormatting;

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return self::qualify( 'delete-media' );
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
        return __( 'Delete a media', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string {
        return __(
            'Permanently deletes a media from the library.',
            'content-manager-for-ai-agents'
        );
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): string {
        return __( 'Media', 'content-manager-for-ai-agents' );
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'attachment_id' => [
                    'type'        => 'integer',
                    'description' => __( 'Identifier of the attachment to delete.', 'content-manager-for-ai-agents' ),
                ],
            ],
            'required'   => [ 'attachment_id' ],
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
     * Authorises against the very attachment being deleted, not a blanket capability.
     *
     * @inheritDoc
     * @param mixed $input Raw input coming from the MCP adapter.
     */
    public function checkPermission( mixed $input = null ): bool {
        $input      = Input::normalize( $input );
        $attachment = $this->resolveAttachment( Input::int( $input, 'attachment_id' ) );

        if ( is_wp_error( $attachment ) ) {
            return false;
        }

        return Capabilities::canDeletePost( $attachment );
    }

    /**
     * @inheritDoc
     * @param mixed $input Raw input coming from the MCP adapter.
     */
    public function execute( mixed $input = null ): array|\WP_Error {
        $input      = Input::normalize( $input );
        $attachment = $this->resolveAttachment( Input::int( $input, 'attachment_id' ) );

        if ( is_wp_error( $attachment ) ) {
            return $attachment;
        }

        if ( ! Capabilities::canDeletePost( $attachment ) ) {
            return $this->forbidden();
        }

        $result = wp_delete_attachment( $attachment->ID, true );

        if ( ! $result ) {
            return new \WP_Error(
                'delete_failed',
                __( 'Unable to delete the attachment.', 'content-manager-for-ai-agents' ),
                [ 'status' => 500 ]
            );
        }

        return [
            'success' => true,
        ];
    }
}
