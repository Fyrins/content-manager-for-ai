<?php
/**
 * Builds the plugin's object graph and attaches its hooks.
 *
 * @package CM4AI
 */

namespace CM4AI;

use CM4AI\AbilityCategories\ContentAbilityCategory;
use CM4AI\Contracts\HookInterface;
use CM4AI\Hooks\AbilityAvailability;
use CM4AI\Hooks\Admin\RegisterAbilitiesPage;
use CM4AI\Hooks\Admin\RegisterAbilitiesSettings;
use CM4AI\Hooks\Admin\SettingsRestRoute;
use CM4AI\Hooks\PostTypeAbilityProvider;
use CM4AI\Hooks\RequirementsNotice;
use CM4AI\Hooks\ThirdPartyAbilityAvailability;
use CM4AI\Hooks\ThirdPartyAbilityExposure;
use CM4AI\Hooks\WooCommerceProductTypes;
use CM4AI\Registry\AbilityRegistry;
use CM4AI\Services\AbilityCatalogService;
use CM4AI\Services\SettingsSanitizer;
use CM4AI\Services\SettingsScreenData;
use CM4AI\Services\SettingsService;
use CM4AI\Services\ThirdPartyAbilityCatalog;

defined( 'ABSPATH' ) || exit;

/**
 * Explicit wiring: every service and hook is built here, once.
 */
final class Plugin {
    /**
     * Static abilities. Post type abilities are built by `AbilityCatalogService`.
     *
     * @var list<class-string<Contracts\AbilityInterface>>
     */
    public const ABILITIES = [
        Abilities\Content\ReplaceInPostContentAbility::class,
        Abilities\GridBuilder\ClearCacheAbility::class,
        Abilities\GridBuilder\IndexFacetsAbility::class,
        Abilities\GridBuilder\ListFacetsAbility::class,
        Abilities\Media\DeleteMediaAbility::class,
        Abilities\Media\ListMediaAbility::class,
        Abilities\Media\SetFeaturedImageAbility::class,
        Abilities\Media\UpdateMediaAbility::class,
        Abilities\Media\UploadMediaAbility::class,
        Abilities\Meta\DeleteTermMetaAbility::class,
        Abilities\Meta\GetPostMetaAbility::class,
        Abilities\Meta\GetTermMetaAbility::class,
        Abilities\Meta\UpdatePostMetaAbility::class,
        Abilities\Meta\UpdateTermMetaAbility::class,
        Abilities\Seopress\GetPostSeoAbility::class,
        Abilities\Seopress\GetTermSeoAbility::class,
        Abilities\Seopress\UpdatePostSchemasAbility::class,
        Abilities\Seopress\UpdatePostSeoAbility::class,
        Abilities\Seopress\UpdateTermSeoAbility::class,
        Abilities\Taxonomy\CreateTermAbility::class,
        Abilities\Taxonomy\DeleteTermAbility::class,
        Abilities\Taxonomy\GetTermAbility::class,
        Abilities\Taxonomy\ListTaxonomiesAbility::class,
        Abilities\Taxonomy\ListTermsAbility::class,
        Abilities\Taxonomy\UpdateTermAbility::class,
        Abilities\Template\CreateTemplateAbility::class,
        Abilities\Template\DeleteTemplateAbility::class,
        Abilities\Template\GetTemplateAbility::class,
        Abilities\Template\ListTemplatesAbility::class,
        Abilities\Template\UpdateTemplateAbility::class,
        Abilities\WooCommerce\CreateProductAbility::class,
        Abilities\WooCommerce\CreateProductAttributeAbility::class,
        Abilities\WooCommerce\CreateProductVariationAbility::class,
        Abilities\WooCommerce\DeleteProductVariationAbility::class,
        Abilities\WooCommerce\GetProductVariationAbility::class,
        Abilities\WooCommerce\ListProductAttributesAbility::class,
        Abilities\WooCommerce\ListProductVariationsAbility::class,
        Abilities\WooCommerce\UpdateProductAbility::class,
        Abilities\WooCommerce\UpdateProductVariationAbility::class,
    ];

    /**
     * Guards against a second boot.
     *
     * @var bool
     */
    private static bool $booted = false;

    /**
     * Builds the graph and attaches the hooks.
     *
     * @return void
     */
    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;

        $registry = new AbilityRegistry();
        $registry->addCategory( new ContentAbilityCategory() );
        foreach ( self::ABILITIES as $class ) {
            $registry->addAbility( new $class() );
        }

        $settings   = new SettingsService();
        $catalog    = new AbilityCatalogService( $registry );
        $thirdParty = new ThirdPartyAbilityCatalog();
        $sanitizer  = new SettingsSanitizer( $catalog, $thirdParty, $settings );
        $screenData = new SettingsScreenData( $catalog, $thirdParty, $settings );

        $hooks = [
            $registry,
            new AbilityAvailability( $settings ),
            new PostTypeAbilityProvider( $catalog, $settings ),
            new ThirdPartyAbilityAvailability( $thirdParty, $settings ),
            new ThirdPartyAbilityExposure( $thirdParty, $settings ),
            new RequirementsNotice(),
            new WooCommerceProductTypes(),
            new RegisterAbilitiesPage(),
            new RegisterAbilitiesSettings( $sanitizer ),
            new SettingsRestRoute( $screenData, $sanitizer ),
        ];

        foreach ( $hooks as $hook ) {
            /** @var HookInterface $hook */
            $hook->hooks();
        }

        /**
         * Fires once the plugin has built its services and attached its hooks.
         *
         * Custom abilities are better added on `cm4ai_register_abilities`,
         * which also reaches code loaded after this plugin. Until
         * `after_setup_theme`, the registry lists the built-in abilities only.
         *
         * @param AbilityRegistry $registry The plugin's ability registry.
         */
        do_action( 'cm4ai_loaded', $registry );
    }
}
