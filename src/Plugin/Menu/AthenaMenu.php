<?php

namespace Drupal\hd_athena\Plugin\Menu;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class AthenaMenu extends MenuLinkBase implements ContainerFactoryPluginInterface {

    /**
     * The current route match.
     *
     * @var \Drupal\Core\Routing\RouteMatchInterface
     */
    protected $currentRouteMatch;

    /**
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
     */
    public function __construct($configuration, $plugin_id,$plugin_definition, RouteMatchInterface $current_route_match) {
        parent::__construct($configuration,$plugin_id,$plugin_definition);
        $this->currentRouteMatch = $current_route_match;
    }

    /**
     * {@inheritDoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        // TODO: Implement create() method.
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('current_route_match')

        );
    }


    public function getTitle() {
        return 'Athena';

    }

    /**
     * {@inheritdoc}
     */
    public function getCacheMaxAge() {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions() {
        // This is where we can overwrite the class of the link
        // If we want to have an indicator of documentation we can add logic here.
        $this->pluginDefinition['options']['attributes']['class'] = ['use-ajax conditional-class'];

        // Adding parameters to the link
        $route_name = $this->currentRouteMatch->getRouteName();
        if ($route_name !== NULL) {
            $this->pluginDefinition['options']['query']['route'] = $route_name;
        }

        $raw_params = $this->currentRouteMatch->getRawParameters()->all();
        if($raw_params) {
            foreach ($raw_params as $key => $param) {
                $this->pluginDefinition['options']['query'][$key] = $param;
            }
        }

        return $this->pluginDefinition['options'] ?: [];
    }

    public function getDescription() {
        // TODO: Implement getDescription() method.
    }


    public function updateLink(array $new_definition_values, $persist) {
        // TODO: Implement updateLink() method.
    }


    public function getRouteParameters() {
      return [];
    }

}
