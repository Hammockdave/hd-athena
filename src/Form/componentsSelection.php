<?php

namespace Drupal\hd_athena\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\hd_athena\Services\EntityCoreHelper;
use Drupal\hd_athena\Services\RenderCoreHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Implements a form to populate components used on the site
 */
class componentsSelection extends FormBase {

    /**
     * The RenderInterface service.
     *
     * @var \Drupal\Core\Render\RendererInterface
     */
    protected $renderer;

    /**
     * The Entity Core Helper service.
     *
     * @var \Drupal\hd_athena\Services\EntityCoreHelper
     */
    protected $entityCoreHelper;

    /**
     * The Render Core Helper service.
     *
     * @var \Drupal\hd_athena\Services\RenderCoreHelper
     */
    protected $renderCoreHelper;

    /**
     * The entity type manager service.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;


    public function __construct(RendererInterface $renderer, EntityCoreHelper $entityCoreHelper,RenderCoreHelper $renderCoreHelper, EntityTypeManagerInterface $entityTypeManager) {
        $this->renderer = $renderer;
        $this->entityCoreHelper = $entityCoreHelper;
        $this->renderCoreHelper = $renderCoreHelper;
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('renderer'),
            $container->get('hd_athena.entity_core_helper'),
            $container->get('hd_athena.render_core_helper'),
            $container->get('entity_type.manager')
        );
    }


    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'hd_styleguide_components_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $event = NULL) {

        // Get all the paragraphs info
//        $entityManager = \Drupal::service('entity_type.manager');
//        $bundles = $entityManager->getBundleInfo('paragraph');

      $paragraphBundles = [];
      $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo('paragraph');
      foreach ($bundle_info as $bundle => $info) {
        $paragraphBundles[$bundle] = $info['label'];
      }

//        $paragraph_types = array(
//            '_none' => '- Select a component to render -',
//        );

//        foreach($bundles as $key => $bundle) {
//            $paragraph_types[$key] = $bundle['label'];
//        }

        $params = \Drupal::request()->query->all();

        $form['select'] = array(
            '#type' => 'select',
            '#title' => t('Select the Component you wish to render.'),
            '#options' => $paragraphBundles,
            '#description' => t('This will render up to 10 published versions of the component.'),
            '#default_value' => array_key_exists('component',$params) ? $params['component'] : '_none',
            '#ajax'    => array(
                'callback' => [$this, 'viewModeCallback'],
                'wrapper'  => 'view_mode',
            ),
        );

        // Hiding the view modes sine they all render anyways
//        $form['view_mode'] = array(
//            '#type'      => 'select',
//            '#title'     => $this->t('View Mode'),
//            '#options'   => ['_none' => $this->t('- Select a component to see view modes -')],
//            '#default_value' => array_key_exists('view_mode',$params) ? $params['view_mode'] : '_none',
//            '#prefix'    => '<div class="dropdown bootstrap-select form-select form-control" id="view_mode">',
//            '#suffix'    => '</div>',
//            '#validated' => TRUE,
//        );

        if(array_key_exists('component',$params)) {
            $view_modes = $this->configuredParagraphViewModes($params['component']);
            if (!empty($view_modes)) {
                foreach ($view_modes as $key => $mode) {
                    $options[$key] = $mode;
                }
            }
            else {
                $options[0] = 'No configured view modes';
            }
            $form['view_mode']['#options'] = $options;
        }

        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Show rendered component(s).'),
            '#ajax' => [
                'callback' => '::loadComponent',
            ],
        ];

        return $form;
    }

    /**
     *
     */
    public function loadComponent(array $form, FormStateInterface $form_state) {

        if($form_state->getValue('view_mode') && $form_state->getValue('view_mode') != 'default') {
            $view_mode = $form_state->getValue('view_mode');
        } else {
            $view_mode = FALSE;
        }

        $paragraphs_ids = $this->loadIDsForBundles($form_state->getValue('select'),$view_mode);

        $response = new AjaxResponse();

        $rendered = '';

        $i = 0;
        foreach($paragraphs_ids as $id) {
            $paragraph_object = $this->entityCoreHelper->loadEntity('paragraph',$id);

            if($rendered_paragraph = $this->renderCoreHelper->renderParagraphById('paragraph',$id,$view_mode)) {
                $parent = $paragraph_object->getParentEntity();
                if($parent) {
                    $parent_type = $parent->getEntityType()->id();
                    if ($parent_type == 'node') {
                        $parent_id = $parent->id();
                        $options = ['absolute' => TRUE, 'target' => '_blank'];
                        $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $parent_id], $options);
                        $parent_link = Link::fromTextAndUrl(t('View this component on it\'s parent node'), $url);
                        $parent_link = $parent_link->toRenderable();
                        $parent_link['#attributes'] = [
                            'class' => ['arrow-link'],
                            'target' => '_blank'
                        ];
                        $rendered .= '<div class="marker divs text-center bg-light">Divider above - ' . $this->renderer->render($parent_link) . '</div>' . $rendered_paragraph . '<div class="marker divs text-center bg-light mb-2">Divider below</div>';
                    }

                    if ($parent_type == 'paragraph') {
                        $rendered .= '<div class="container text-center bg-gray-100 p-2">This component is rendered within <b>' . $parent->getParagraphType()
                                ->label() . '</b></div>' . $rendered_paragraph . '<div class="marker divs text-center bg-light mb-2">Divider below</div>';
                    }
                }


            }

            if ($i++ == 10) break;
        }

        $response->addCommand(
            new HtmlCommand('.result_message', $rendered)
        );

        // Add the selected items to the URL
        $response->addCommand(
            new InvokeCommand(NULL, 'component', ['?component=' . $form_state->getValue('select') . '&view_mode=' . $view_mode])
        );


        return $response;

    }

    /**
     * Get all the IDs of a paragraph bundle
     */
    public function loadIDsForBundles($bundle,$view_mode = 'default') {

        $entities = $this->entityTypeManager->getStorage('paragraph')->loadByProperties(['type' => $bundle,'status' => 1]);

        $ids = [];

        foreach($entities as $key => $entity) {
            $ids[] = $key;
            if($view_mode && $entity->hasField('field_wf_view_mode')) {
                if($entity->get('field_wf_view_mode')->getValue()[0]['value'] == $view_mode) {
                    $ids[] = $key;
                }
            } else {
                $ids[] = $key;
            }
        }

        return $ids;

    }

    public function viewModeCallback(array &$form, FormStateInterface $form_state) {
        $options = ['default' => 'Default'];

        $selected_bundle = $form_state->getValue('select');

        $view_modes = $this->configuredParagraphViewModes($selected_bundle);

        if(!empty($view_modes)) {
            foreach($view_modes as $key => $mode) {
                $options[$key] = $mode;
            }
        } else {
            $options['default'] = 'Default - No configured view modes';
        }

        $form['view_mode']['#options'] = $options;

        return $form['view_mode'];
    }

    public function configuredParagraphViewModes($bundle) {
        $query = $this->entityTypeManager->getStorage('entity_view_display')->getQuery();
        $ids = $query->condition('targetEntityType', 'paragraph')
            ->condition('bundle', $bundle)
            ->execute();
        $view_modes = [];
        foreach ($ids as $id) {
            $parts = explode('.', $id);
            $view_mode = $parts[0] . '.' . $parts[2];
            $view_modes[$view_mode] = $view_mode;
        }
        $labeled_view_modes = [];
        if (!empty($view_modes)) {
            /** @var \Drupal\Core\Entity\EntityViewModeInterface[] $entity_view_modes */
            $entity_view_modes = EntityViewMode::loadMultiple($view_modes);
            foreach ($entity_view_modes as $entity) {
                $labeled_view_modes[explode('.', $entity->id())[1]] = $entity->label();
            }
        }
        return $labeled_view_modes;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

    }

}
