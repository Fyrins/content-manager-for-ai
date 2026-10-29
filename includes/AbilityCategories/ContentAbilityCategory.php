<?php
/**
 * Ability category holding every content management ability of the plugin.
 *
 * @package CM4AI\AbilityCategories
 */

namespace CM4AI\AbilityCategories;

use CM4AI\Contracts\AbilityCategoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `cm4ai` ability category.
 */
class ContentAbilityCategory implements AbilityCategoryInterface {
    /**
     * Category slug, also used as the ability name namespace.
     */
    public const SLUG = 'cm4ai';

    /**
     * @inheritDoc
     */
    public function getName(): string {
        return self::SLUG;
    }

    /**
     * @inheritDoc
     */
    public function getProperties(): array {
        return [
            'label'       => __( 'Content Manager for AI Agents', 'content-manager-for-ai-agents' ),
            'description' => __(
                'Abilities for WordPress content management.',
                'content-manager-for-ai-agents'
            ),
        ];
    }
}
