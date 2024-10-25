<?php

namespace Drupal\hd_athena\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\hd_athena\Services\GeneralFunctions;

/**
 * Adobe Launch Settings form.
 */
class AthenaHomePageEditForm extends ConfigFormBase {

    /**
     * The path validator.
     *
     * @var \Drupal\Core\Path\PathValidatorInterface
     */
    protected $pathValidator;

    /**
     * The ConfigFactory.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * The General Functions.
     *
     * @var \Drupal\hd_athena\Services\GeneralFunctions
     */
    protected $generalFunctions;

    /**
     * Constructs an AdobeLaunchSettingsForm object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The factory for configuration objects.
     * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
     *   The path validator.
     */
    public function __construct(ConfigFactoryInterface $config_factory, PathValidatorInterface $path_validator, GeneralFunctions $general_functions) {
        parent::__construct($config_factory);
        $this->pathValidator = $path_validator;
        $this->configFactory = $config_factory;
        $this->generalFunctions = $general_functions;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('config.factory'),
            $container->get('path.validator'),
            $container->get('hd_athena.general_functions')
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            'hd_athena.home_page_edit_form_settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'hd_athena_home_page_edit_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->configFactory->getEditable('hd_athena.home_page_edit_form_settings');

        $form['title'] = [
            '#type' => 'textfield',
            '#title' => 'Title',
            '#default_value' => ($config->get('title')) ? $config->get('title') : 'Athena',
            '#description' => 'The title of the page.',
            '#required' => TRUE,
        ];

        $form['body'] = array(
            '#type' => 'text_format',
            '#title' => 'Description',
            '#description' => '',
            '#default_value' => ($config->get('body') !== NULL) ? $config->get('body') : '',
            '#format' => 'full_html',
            '#allowed_formats' => ['full_html'],
            '#required' => FALSE,
        );

        $form['search_block_id'] = [
            '#type' => 'textfield',
            '#title' => 'Search Block ID',
            '#default_value' => ($config->get('search_block_id')) ? $config->get('search_block_id') : 'exposedformathena_catalogathena_search_results_machine_name',
            '#description' => 'The id of the search block.',
            '#required' => FALSE,
        ];

        $form['max_items_in_list'] = [
            '#type' => 'number',
            '#title' => 'Maximum Items in Lists',
            '#default_value' => ($config->get('max_items_in_list')) ? $config->get('max_items_in_list') : 10,
            '#description' => 'The maximum number of child menu items.',
            '#required' => TRUE,
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);
        $id = trim($form_state->getValue('search_block_id'));

        if (!empty($id)) {
            $entity_type_manager = \Drupal::entityTypeManager();
            $block = $entity_type_manager->getStorage('block')->load($id);

            if ($block === NULL) {
                $form_state->setErrorByName('search_block_id', 'The block id is not valid.');
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        parent::submitForm($form, $form_state);

        $config = $this->configFactory->getEditable('hd_athena.home_page_edit_form_settings');

        $body = $form_state->getValue('body');
        $body = $body['value'];

        $config
            ->set('title', $form_state->getValue('title'))
            ->set('body', $body)
            ->set('search_block_id', $form_state->getValue('search_block_id'))
            ->set('max_items_in_list', $form_state->getValue('max_items_in_list'))
            ->save();
    }
} // End of class
