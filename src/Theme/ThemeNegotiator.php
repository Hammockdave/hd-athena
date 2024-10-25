<?php

namespace Drupal\hd_athena\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\Core\Path\PathMatcherInterface;

/**
 * Our Utopia Theme Negotiator
 */
class ThemeNegotiator implements ThemeNegotiatorInterface {

    /**
     * Protected pathMatcher variable.
     *
     * @var Drupal\Core\Path\PathMatcherInterface
     */
    protected $pathMatcher;

    /**
     * {@inheritdoc}
     */
    public function __construct( PathMatcherInterface $pathMatcher) {
        $this->pathMatcher = $pathMatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function applies(RouteMatchInterface $route_match) {
        $route_name = $route_match->getRouteName();

        $applies = FALSE;
        // If we're on an Athena content type then return TRUE
        $node = $route_match->getParameter('node');
        if (!is_null($node) && $node instanceof \Drupal\node\Entity\Node && $route_name === 'entity.node.canonical') {
            $athenaContentTypes = getAthenaContentTypes();
            $contentType = $node->getType();

            if (array_key_exists($contentType, $athenaContentTypes)) {
                return TRUE;
            }

        }

        // Athena home page
        if (isset($route_name) && $route_name === 'hd_athena.athena_home_page') {
            return TRUE;
        }

        // Athena search results page
        $viewId = $route_match->getParameter('view_id');
        if ($viewId !== NULL && $viewId === 'athena_catalog') {
            return TRUE;
        }

        // Route tagging list view
        if ($viewId !== NULL && $viewId === 'route_tagging_list') {
            return TRUE;
        }

        // Lead settings webform submissions tracker page
        if (isset($route_name) && $route_name === 'hd_lead.webform_submissions_tracker') {
            return TRUE;
        }

        return $applies;
    }

    /**
     * {@inheritdoc}
     */
    public function determineActiveTheme(RouteMatchInterface $route_match) {
        $route_name = $route_match->getRouteName();
        $node = $route_match->getParameter('node');
        if (!is_null($node) && $node instanceof \Drupal\node\Entity\Node && $route_name === 'entity.node.canonical') {
            $athenaContentTypes = getAthenaContentTypes();
            $contentType = $node->getType();

            if (array_key_exists($contentType, $athenaContentTypes)) {
                return 'utopia';
            }

        }

        // Athena home page
        if (isset($route_name) && $route_name === 'hd_athena.athena_home_page') {
            return 'utopia';
        }

        // Athena search results page
        $viewId = $route_match->getParameter('view_id');
        if ($viewId !== NULL && $viewId === 'athena_catalog') {
            return 'utopia';
        }

        // Route tagging list view
        if ($viewId !== NULL && $viewId === 'route_tagging_list') {
            return 'seven';
        }

        // Lead settings webform submissions tracker page
        if (isset($route_name) && $route_name === 'hd_lead.webform_submissions_tracker') {
            return 'utopia';
        }
    }
}
