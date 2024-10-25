<?php
namespace Drupal\hd_athena\Services;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\hd_athena\Services\EntityCoreHelper;
use Drupal\menu_item_extras\Entity\MenuItemExtrasMenuLinkContent;
use Drupal\node\Entity\Node;

class AthenaMenuCoreApi {

    /**
     * The Entity Core Helper service.
     *
     * @var \Drupal\hd_athena\Services\EntityCoreHelper
     */
    protected $entityCoreHelper;

    protected $athenaMenuMachineName = 'athena-main-menu';
    protected $athenaMenuEntity;

    public function __construct(EntityCoreHelper $entity_core_helper) {
        // Services
        $this->entityCoreHelper = $entity_core_helper;

        $this->_setAthenaMenuEntity();
    }

    public function getMenuLinkContentFieldValue($uuid, $field_name = '') {
        $returnValue = FALSE;
        $entity = \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $uuid);
        if ($entity !== NULL && $entity instanceof MenuItemExtrasMenuLinkContent) {
            if ($entity->hasField($field_name)) {
                $returnValue = $entity->get($field_name)->getValue();
            }
        }

        return $returnValue;
    }

    public function stripMenuLinkContentStringFromUuid($string) {
        if (strtolower(substr($string, 0, 18)) === 'menu_link_content:') {
            $string = substr($string, 18);
        }

        return $string;
    }

    public function getTopLevelMenuItemsAndDirectDescendants() {
        //Get drupal menu
        $parameters = new MenuTreeParameters();
        //$parameters->setRoot('menu_link_content:e8266b6b-6ae8-467c-a8e6-d2191094a56c');
        $parameters->setRoot('');
        $parameters->onlyEnabledLinks();
        $parameters->setMaxDepth(3);
        $sub_nav = \Drupal::menuTree()->load('athena-main-menu', $parameters);

        //Generate array
        $this->__generateSubMenuTree($menu_tree, $sub_nav);

        return $menu_tree;
    }

    // Pass it a Menu Link Content Entity id.
    public function getMenuForLinkId($menuLinkId) {
        //$menu_link = MenuLinkContent::load($menuLinkId);
        $menuLinkContentEntity = $this->entityCoreHelper->loadEntity('menu_link_content', $menuLinkId);
        if ($menuLinkContentEntity === NULL || $menuLinkContentEntity === FALSE) {
            return FALSE;
        }

        $pluginId = $menuLinkContentEntity->getPluginId();

        $parameters = new MenuTreeParameters();
        $parameters->setRoot($pluginId);
        $parameters->onlyEnabledLinks();
        $sub_nav = \Drupal::menuTree()->load('athena-main-menu', $parameters);
        $this->__generateSubMenuTree($menu_tree, $sub_nav);

        return $menu_tree;
    }

    // This returns an id for MenuLinkContent::load($menu_link_id)
    public function getMenuLinkIdForNode(Node $node) {
        $menuLinkId = FALSE;
        $systemUri = 'entity:node/' . $node->id();
        $query = \Drupal::entityQuery('menu_link_content')
            ->condition('link.uri', $systemUri)
            ->condition('menu_name', $this->athenaMenuMachineName);

        $result = $query->execute();

        if (!empty($result)) {
            $menuLinkId = reset($result);
        }

        return $menuLinkId;
    }

    protected function _setAthenaMenuEntity() {
        $this->athenaMenuEntity = $this->entityCoreHelper->loadEntity('menu', $this->athenaMenuMachineName);
    }

    private function __generateSubMenuTree(&$output, &$input, $parent = FALSE) {
        $menuService = \Drupal::service('menu_item_extras.menu_link_tree_handler');
        $input = array_values($input);
        foreach($input as $key => $item) {
            $key = 'submenu-' . $key;
            $name = $item->link->getTitle();
            $description = FALSE;
            $url = $item->link->getUrlObject();
            $url_string = $url->toString();
            $plugin_id = $item->link->getPluginId();
            $parentMenuLinkId = $item->link->getParent();
            $test = $menuService->getMenuLinkItemEntity($item->link);
            $uuid = $item->link->getDerivativeId();

            if (!empty($uuid)) {
                $entity = \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $uuid);
                if ($entity !== NULL && $entity->field_plain_long->value && !empty($entity->field_plain_long->value)) {
                    $description = $entity->field_plain_long->value;
                }
            }

            //If not root element, add as child
            if ($parent === FALSE) {
                $output[$key] = [
                    'name' => $name,
                    'description' => $description,
                    'tid' => $key,
                    'url_str' => $url_string,
                    'plugin_id' => $plugin_id,
                    'uuid' => $uuid,
                    'parent_menu_link_id' => $parentMenuLinkId
                ];
            }
            else {
                $parent = 'submenu-' . $parent;
                $output['child'][$key] = [
                    'name' => $name,
                    'description' => $description,
                    'tid' => $key,
                    'url_str' => $url_string,
                    'plugin_id' => $plugin_id,
                    'uuid' => $uuid,
                    'parent_menu_link_id' => $parentMenuLinkId
                ];
            }

            if ($item->hasChildren) {

                if ($item->depth == 1) {
                    $this->__generateSubMenuTree($output[$key], $item->subtree, $key);
                }
                else {

                    $this->__generateSubMenuTree($output['child'][$key], $item->subtree, $key);
                }
            }
        }
    }
} // End of class
