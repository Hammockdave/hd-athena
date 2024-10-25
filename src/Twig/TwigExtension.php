<?php

namespace Drupal\hd_athena\Twig;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\Element;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension with some useful functions and filters.
 *
 * As version 1.7 all dependencies are instantiated on demand for performance
 * reasons.
 */
class TwigExtension extends AbstractExtension  {

    /**
     * {@inheritdoc}
     */
    public function getFunctions() {
        return [
            new TwigFunction('render_block_with_config', [$this, 'renderBlockWithConfig']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName() {
        return 'hd_athena_twig';
    }

    // This has been pulled from the 2.x version of the Twig tweak module
    // until we can migrate template files to use the updated functions, we'll use this specific one
    public function renderBlockWithConfig($id, array $configuration = [], $wrapper = TRUE) {

        $configuration += ['label_display' => BlockPluginInterface::BLOCK_LABEL_VISIBLE];

        /** @var \Drupal\Core\Block\BlockPluginInterface $block_plugin */
        $block_plugin = \Drupal::service('plugin.manager.block')
            ->createInstance($id, $configuration);

        // Inject runtime contexts.
        if ($block_plugin instanceof ContextAwarePluginInterface) {
            $contexts = \Drupal::service('context.repository')->getRuntimeContexts($block_plugin->getContextMapping());
            \Drupal::service('context.handler')->applyContextMapping($block_plugin, $contexts);
        }

        if (!$block_plugin->access(\Drupal::currentUser())) {
            return FALSE;
        }

        // Title block needs special treatment.
        if ($block_plugin instanceof TitleBlockPluginInterface) {
            $request = \Drupal::request();
            $route_match = \Drupal::routeMatch();
            $title = \Drupal::service('title_resolver')->getTitle($request, $route_match->getRouteObject());
            $block_plugin->setTitle($title);
        }

        $build = [
            'content' => $block_plugin->build(),
            '#cache' => [
                'contexts' => $block_plugin->getCacheContexts(),
                'tags' => $block_plugin->getCacheTags(),
                'max-age' => $block_plugin->getCacheMaxAge(),
            ],
        ];

        if ($block_plugin instanceof TitleBlockPluginInterface) {
            $build['#cache']['contexts'][] = 'url';
        }

        if ($wrapper && !Element::isEmpty($build['content'])) {
            $build += [
                '#theme' => 'block',
                '#attributes' => [],
                '#contextual_links' => [],
                '#configuration' => $block_plugin->getConfiguration(),
                '#plugin_id' => $block_plugin->getPluginId(),
                '#base_plugin_id' => $block_plugin->getBaseId(),
                '#derivative_plugin_id' => $block_plugin->getDerivativeId(),
            ];
        }

        return $build;    }

} // End of class
