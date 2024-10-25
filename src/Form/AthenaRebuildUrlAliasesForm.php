<?php

namespace Drupal\hd_athena\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\pathauto\AliasTypeBatchUpdateInterface;
use Drupal\pathauto\AliasTypeManager;
use Drupal\pathauto\PathautoGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure file system settings for this site.
 */
class AthenaRebuildUrlAliasesForm extends FormBase {

    /**
     * Generate URL aliases for un-aliased paths only.
     */
    const ACTION_CREATE = 'create';

    /**
     * Update URL aliases for paths that have an existing alias.
     */
    const ACTION_UPDATE = 'update';

    /**
     * Regenerate URL aliases for all paths.
     */
    const ACTION_ALL = 'all';

    /**
     * The alias type manager.
     *
     * @var \Drupal\pathauto\AliasTypeManager
     */
    protected $aliasTypeManager;

    /**
     * Constructs a PathautoBulkUpdateForm object.
     *
     * @param \Drupal\pathauto\AliasTypeManager $alias_type_manager
     *   The alias type manager.
     */
    public function __construct(AliasTypeManager $alias_type_manager) {
        $this->aliasTypeManager = $alias_type_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('plugin.manager.alias_type')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'athena_rebuild_url_aliases';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form = [];


        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Regenerate URL aliases for all Athena documentation'),
            '#prefix' => '<h3>Update Athena Documentation Aliases</h3>This will regenerate the URL aliases of all Athena documentation. You may have to do this after making menu changes to the Athena menu. This way you do not have to open and save every node.<br><br>',
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Get all Athena documentation
        $athenaExternalApi = \Drupal::service('hd_athena.athena_external_api');
        $allDocumentationNodes = $athenaExternalApi->getAllAthenaDocsNodes();

        $operations = [];
        foreach ($allDocumentationNodes as $nid => $nodeObject) {
            $operations[] = ['Drupal\hd_athena\Form\AthenaRebuildUrlAliasesForm::batchProcess', [$nodeObject]];
        }

        $batch = array(
            'title' => 'Updating Athena documentation aliases',
            'operations' => $operations,
            'init_message' => 'Intializing...',
            'error_message' => 'The migration process has encountered an error.',
            'finished' => '\Drupal\hd_athena\Form\AthenaRebuildUrlAliasesForm::finishedCallback',
        );
        batch_set($batch);
    }

    /**
     * Common batch processing callback for all operations.
     */
    public static function batchProcess($nodeObject, &$context) {
        $options = [];
        $options['force'] = TRUE;
        $result = \Drupal::service('pathauto.generator')->updateEntityAlias($nodeObject, 'bulkupdate', $options);

        $context['results'][] = $nodeObject->id();
        $context['message'] = t('Updating Url alias for @label', array('@label' => $nodeObject->label()));
    }

    public static function finishedCallback($success, $results, $operations) {
        if ($success) {
            $message = \Drupal::translation()->formatPlural(
                count($results),
                'One url aliased re-generated.', '@count Url aliases re-generated.'
            );
        }
        else {
            $message = t('Finished with an error.');
        }
        drupal_set_message($message);
    }
}
