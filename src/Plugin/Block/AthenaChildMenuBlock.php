<?php

namespace Drupal\hd_athena\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hd_athena\Services\GeneralFunctions;
use Drupal\hd_athena\Services\AthenaMenuCoreApi;
use Drupal\node\Entity\Node;


/**
 * Provides a Featured Resources Block.
 *
 * @Block(
 *   id = "hd_athena_athena_child_menu_block",
 *   admin_label = @Translation("Athena Child Menu Block"),
 *   category = @Translation("Workfront"),
 * )
 */
class AthenaChildMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {
    protected $generalFunctions;
    protected $athenaMenuCoreApi;
    protected $athenaContentTypes;

    public function __construct(array $configuration, $plugin_id, $plugin_definition, GeneralFunctions $general_functions, AthenaMenuCoreApi $athena_menu_core_api) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->generalFunctions = $general_functions;
        $this->athenaMenuCoreApi = $athena_menu_core_api;
        $this->athenaContentTypes = getAthenaContentTypes();
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration, $plugin_id, $plugin_definition,
            $container->get('hd_athena.general_functions'),
            $container->get('hd_athena.athena_menu_core_api')
        );
    }

    public function build() {
        $cacheTags = $this->getCacheTags();
        $data = [];
        $data['menu'] = $this->_getChildMenu();
        return array(
            '#theme' => 'athena_child_menu_block',
            '#data' => $data,
            '#cache' => array('tags' => $cacheTags),
        );
    }

    protected function _getChildMenu() {
        // Get the node if there is one
        $node = $this->generalFunctions::getRouteMatchNode();
        if (!$node instanceof \Drupal\node\Entity\Node || !array_key_exists($node->bundle(), $this->athenaContentTypes)) {
            return FALSE;
        }

        // Get the menu link id for the node if there is one
        $menuLinkId = $this->athenaMenuCoreApi->getMenuLinkIdForNode($node);
        if ($menuLinkId === FALSE) {
            return FALSE;
        }

        // Get the menu for the menu link id
        $menu = $this->athenaMenuCoreApi->getMenuForLinkId($menuLinkId);

        // Make sure it has children
        if (!isset($menu['child']['submenu-0']['child']) || empty($menu['child']['submenu-0']['child'])) {
            return FALSE;
        }

        // Return only the children
        return $menu['child']['submenu-0']['child'];
    }
    // Build a form to let the user set the footer text for Desktop & Mobile
    public function blockForm($form, FormStateInterface $form_state) {
        return $form;
    }

    public function blockSubmit($form, FormStateInterface $form_state) {
        parent::blockSubmit($form, $form_state);
    }

    public function getCacheTags() {
        $cacheTags = parent::getCacheTags();

        //With this when your node change your block will rebuild
        if ($node = \Drupal::service('hd_athena.general_functions')::getRouteMatchNode()) {
            $cacheTags[] = 'node:' . $node->id();
        }

        return $cacheTags;
    }

} // End of class
