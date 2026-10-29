<?php
/**
 * Holds the plugin's abilities and hands them to the Abilities API.
 *
 * @package CM4AI\Registry
 */

namespace CM4AI\Registry;

use CM4AI\Contracts\AbilityCategoryInterface;
use CM4AI\Contracts\AbilityInterface;
use CM4AI\Contracts\HookInterface;
use CM4AI\Support\AbstractAbility;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of the static abilities and categories.
 *
 * Post type abilities are not stored here: `PostTypeAbilityProvider` builds and
 * registers them itself.
 */
final class AbilityRegistry implements HookInterface {
    /**
     * Abilities keyed by name.
     *
     * @var array<string, AbilityInterface>
     */
    private array $abilities = [];

    /**
     * Categories keyed by slug.
     *
     * @var array<string, AbilityCategoryInterface>
     */
    private array $categories = [];

    /**
     * Whether `cm4ai_register_abilities` has already been fired.
     *
     * @var bool
     */
    private bool $extended = false;

    /**
     * Whether `cm4ai_register_abilities` is running right now.
     *
     * @var bool
     */
    private bool $extending = false;

    /**
     * Adds an ability; a later one with the same name replaces the earlier.
     *
     * Abilities added from `cm4ai_register_abilities` must extend
     * `AbstractAbility`: the settings screen, the switches and the execution
     * hooks all rely on it, and an ability registered without them could be
     * neither listed nor switched off. Such an ability is refused.
     *
     * @param AbilityInterface $ability Ability to register.
     * @return void
     */
    public function addAbility( AbilityInterface $ability ): void {
        if ( $this->extending && ! $ability instanceof AbstractAbility ) {
            _doing_it_wrong(
                __METHOD__,
                esc_html(
                    sprintf(
                        /* translators: 1: ability name, 2: class name. */
                        __( 'The ability "%1$s" was not added: abilities added on cm4ai_register_abilities must extend %2$s.', 'content-manager-for-ai-agents' ),
                        $ability->getName(),
                        AbstractAbility::class
                    )
                ),
                '1.0.0'
            );

            return;
        }

        $this->abilities[ $ability->getName() ] = $ability;
    }

    /**
     * Adds a category.
     *
     * @param AbilityCategoryInterface $category Category to register.
     * @return void
     */
    public function addCategory( AbilityCategoryInterface $category ): void {
        $this->categories[ $category->getName() ] = $category;
    }

    /**
     * Every ability, keyed by name.
     *
     * @return array<string, AbilityInterface>
     */
    public function getAbilities(): array {
        $this->extend();

        return $this->abilities;
    }

    /**
     * One ability by name.
     *
     * @param string $name Ability name.
     * @return AbilityInterface|null
     */
    public function getAbilityByName( string $name ): ?AbilityInterface {
        $this->extend();

        return $this->abilities[ $name ] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function hooks(): void {
        add_action( 'wp_abilities_api_categories_init', [ $this, 'registerCategories' ] );
        add_action( 'wp_abilities_api_init', [ $this, 'registerAbilities' ] );
    }

    /**
     * Registers the categories.
     *
     * @return void
     */
    public function registerCategories(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        foreach ( $this->categories as $name => $category ) {
            wp_register_ability_category( $name, $category->getProperties() );
        }
    }

    /**
     * Registers the abilities left after the `cm4ai_abilities` filter.
     *
     * @return void
     */
    public function registerAbilities(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        $this->extend();

        /**
         * Filters the abilities about to be registered.
         *
         * @param array<string, AbilityInterface> $abilities Abilities keyed by name.
         */
        $abilities = apply_filters( 'cm4ai_abilities', $this->abilities );

        foreach ( (array) $abilities as $ability ) {
            if ( $ability instanceof AbilityInterface ) {
                wp_register_ability( $ability->getName(), $ability->getProperties() );
            }
        }
    }

    /**
     * Lets other code add abilities, once, on first use of the registry.
     *
     * Not fired from `Plugin::boot()`: the plugin boots while its own file
     * loads, before plugins loaded after it and before the theme, which would
     * miss the action. The registry is first read when the abilities are
     * registered or the settings screen is built, so at the earliest during
     * `init`. A read made before `after_setup_theme`, by a `cm4ai_loaded`
     * callback for instance, gets the built-in abilities only and leaves the
     * action for later, so that every plugin and the theme have hooked it by
     * the time it fires. The flag is raised before the action so that a
     * callback reading the registry does not fire it again.
     *
     * @return void
     */
    private function extend(): void {
        if ( $this->extended || ! did_action( 'after_setup_theme' ) ) {
            return;
        }
        $this->extended  = true;
        $this->extending = true;

        try {
            /**
             * Fires once, on first use of the registry, to add custom abilities.
             *
             * Call `$registry->addAbility()` with an instance of a class
             * extending `CM4AI\Support\AbstractAbility`. A later ability
             * with the same name replaces the earlier one, including the
             * plugin's own: use a namespace of your own.
             *
             * @param AbilityRegistry $registry The plugin's ability registry.
             */
            do_action( 'cm4ai_register_abilities', $this );
        } finally {
            $this->extending = false;
        }
    }
}
