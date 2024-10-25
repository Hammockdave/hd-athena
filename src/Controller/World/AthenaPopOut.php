<?php

namespace Drupal\hd_athena\Controller\World;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Renderer;
use Drupal\hd_athena\Services\RenderCoreHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\hd_athena\Services\AthenaExternalApi;
use Drupal\Core\Url;

class AthenaPopOut extends ControllerBase {

    /**
     * @var \Drupal\Core\Render\Renderer
     */
    protected $renderer;

    /**
     * Athena External API.
     *
     * @param \Drupal\hd_athena\Services\AthenaExternalApi $externalApi
     */
    protected $AthenaExternalApi;

    /**
     * The Render Core Helper service.
     *
     * @var \Drupal\hd_athena\Services\RenderCoreHelper
     */
    protected $renderCoreHelper;

    public function __construct(Renderer $renderer, AthenaExternalApi $athena_external_api, RenderCoreHelper $renderCoreHelper) {
        $this->renderer = $renderer;
        $this->AthenaExternalApi = $athena_external_api;
        $this->renderCoreHelper = $renderCoreHelper;
    }

    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('renderer'),
            $container->get('hd_athena.athena_external_api'),
            $container->get('hd_athena.render_core_helper')
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param $mode
     */
    public function content(Request $request, $mode = 'teaser') {

        $rendered = '';
        $docs = [];
        $route = FALSE;
        $user = \Drupal::currentUser()->getRoles();

        $query_params = $request->query->all();

        // Getting documentation based on a route
        if(array_key_exists('route',$query_params)) {
            $route = $query_params['route'];
            if (array_key_exists('node', $query_params)) {
              ksm('node');
                $docs = $this->AthenaExternalApi->getAthenaDocs(field: 'field_computed_route_name', value: $route, entityById: ['node_reference', $query_params['node']]);
            } else {
              $docs = $this->AthenaExternalApi->getAthenaDocs(field: 'field_computed_route_name', value: $route);
            }
        }

        // Getting documentation for specific components
        if(array_key_exists('component',$query_params)) {
            if($query_params['component'] == 'paragraph') {
                $docs = $this->AthenaExternalApi->getAthenaDocs(field: 'paragraph_bundle', value: [$query_params['bundle']]);
                $mode = 'teaser2';
            }
        }

        if (!empty($docs)) {
            foreach ($docs as $doc) {
                // Get the body field
                $body = !$doc->get('description')->isEmpty() ? $doc->get('description')->getValue()[0]['value'] : '';

                // Get the image field
                $images = !$doc->get('field_image')->isEmpty() ? $doc->get('field_image')->view() : '';

                // Generate the URL with the title of the doc
                $athena_url = Url::fromRoute('entity.athena_ent.canonical', ['athena_ent' => $doc->id()]);
                $athena_link = [
                    '#type' => 'link',
                    '#url' => $athena_url,
                    '#title' => $doc->label(),
                    '#attributes' => [
                        'target' => '_blank'
                    ]
                ];

                // Add some stuff and send to the template
                $content_list = [
                    '#theme' => 'athena_dialog_content_view',
                    '#data' => [
                        'mode' => $mode,
                        'title' => $this->renderer->render($athena_link),
                        'body' => $body,
                        'images' => $images
                    ],
                ];

                // Render the template with the data
                $rendered .= $this->renderer->render($content_list) . '<hr><br>';

            }
        } else {
            // We can add a link or some other logic to have a 'create documentation' link
            $rendered = 'Sorry, there is no documentation available at this time.<hr>';
        }

        // Add the route name to help admins create specific documentation
        if($route && in_array('administrator', $user)) {
            $rendered .= '<div class="admin-info">Admins: route - ' . $route . '</div>';
        }

        // Athena dashboard link
        $athena_url = Url::fromRoute('hd_athena.athena_home_page');
        $link = [
            '#type' => 'link',
            '#url' => $athena_url,
            '#title' => t('Athena Dashboard'),
            '#attributes' => [
                'class' => [
                    'btn',
                    'btn-primary'
                ],
                'target' => '_blank'
            ]
        ];

        return [
            '#prefix' => $this->renderer->render($link) . '<br><br>',
            '#markup' => $rendered
        ];

    }
}
