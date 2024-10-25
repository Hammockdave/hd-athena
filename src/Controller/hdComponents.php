<?php

namespace Drupal\hd_athena\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 * Workfront Components Styleguide controller.
 *
 */
class hdComponents extends ControllerBase {

    /**
     *
     * Returns a page with styleguide elements.
     */
    public function content() {

        $data = array();

        $data['components_form'] = \Drupal::formBuilder()->getForm('Drupal\hd_athena\Form\componentsSelection');

      return [
          '#theme' => 'style_guide_components',
          '#data' => $data,
      ];
    }


}

