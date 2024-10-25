<?php
namespace Drupal\hd_athena\Controller\World;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hd_athena\Services\AthenaMenuCoreApi;
use Drupal\user\Entity\User;
use Drupal\node\Entity\NodeType;

class AthenaHomePage extends ControllerBase {

    protected $athenaMenuCoreApi;
    protected $config;

    public function __construct(AthenaMenuCoreApi $athena_menu_core_api) {
        // Dependancy Injection
        $this->athenaMenuCoreApi = $athena_menu_core_api;
        $this->config = \Drupal::config('hd_athena.home_page_edit_form_settings')->get();

    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *
     * @return static
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('hd_athena.athena_menu_core_api')
        );
    }


    public function content() {
        $data = [];

        $data['title'] = (isset($this->config['title'])) ? $this->config['title'] : 'Athena';
        $data['body'] = (isset($this->config['body'])) ? $this->config['body'] : '';

        // Get the logged in user info
        if ($user = User::load(\Drupal::currentUser()->id())) {
            $data['user_name'] = $user->getDisplayName();
            if (!$user->user_picture->isEmpty()) {
                $data['picture'] = $user->user_picture->view('small');
            }
            else {
                $data['picture'] = NULL;// get default picture
            }
        }

        // Get the top level menu links and direct reports
        $data['menu'] = $this->athenaMenuCoreApi->getTopLevelMenuItemsAndDirectDescendants();

        // get All the content types
        $data['content_types'] = [];
        $node_types = NodeType::loadMultiple();
        // If you need to display them in a drop down:
        foreach ($node_types as $node_type) {
            $data['content_types'][$node_type->id()] = ['name' => $node_type->label(), 'description' => $node_type->getDescription()];
        }

        // Get the search block
        $data['search_block_id'] = (isset($this->config['search_block_id'])) ? $this->config['search_block_id'] : FALSE;
        $data['search_block'] = $this->_getSearchBlock();

        // Max number of items in menu list
        $data['max_items_menu_list'] = (isset($this->config['max_items_in_list'])) ? $this->config['max_items_in_list'] : 10;

        // Return the response
        return array(
            '#theme' => 'athena-home-page',
            '#data' => $data,
            //'#cache' => array('tags' => $cacheTags),
        );

    }


    protected function _getSearchBlock() {
        if (!isset($this->config['search_block_id'])) {
            return FALSE;
        }
        $id = trim($this->config['search_block_id']);
        if (empty($id)) {
            return FALSE;
        }
        $entity_type_manager = \Drupal::entityTypeManager();
        $block = $entity_type_manager->getStorage('block')->load($id);

        if ($block === NULL || $block === FALSE) {
            return FALSE;
        }

        return $entity_type_manager->getViewBuilder('block')->view($block);
    }
} // End of class
