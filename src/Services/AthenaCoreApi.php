<?php
namespace Drupal\hd_athena\Services;

use Drupal\hd_athena\Services\EntityCoreHelper;
use Drupal\hd_athena\Services\TaxonomyCoreHelper;
use Drupal\Core\Database\Connection;

class AthenaCoreApi {

    /**
     * The Entity Core Helper service.
     *
     * @var \Drupal\hd_athena\Services\EntityCoreHelper
     */
    protected $entityCoreHelper;

    /**
     * The Taxonomy Core Helper service.
     *
     * @var \Drupal\hd_athena\Services\TaxonomyCoreHelper
     */
    protected $taxonomyCoreHelper;

    /**
     * The Drupal Database service.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $connection;

    // An array of Athena content types machine_name => label
    protected $athenaContentTypes;

    // An array of Athena content types where the value of each element is the machine name of the content type
    protected $athenaContentTypesMachineName;

    public function __construct(EntityCoreHelper $entity_core_helper, TaxonomyCoreHelper $taxonomy_core_helper, Connection $connection) {
        // Services
        $this->entityCoreHelper = $entity_core_helper;
        $this->taxonomyCoreHelper = $taxonomy_core_helper;
        $this->connection = $connection;

        // Defaults
        $this->_setAthenaContentTypes();
    }

    /**
     * @return \Drupal\hd_athena\Services\EntityCoreHelper
     */
    public function entityCoreHelper() {
        return $this->entityCoreHelper;
    }

    /**
     * @return \Drupal\hd_athena\Services\TaxonomyCoreHelper
     */
    public function taxonomyCoreHelper() {
        return $this->taxonomyCoreHelper;
    }

    /**
     * @param array $contentTypeRestricted = An array of Athena content types you want to restrict the search to. Leave empty to search all of the Athena content types
     * @param array $filters = supported filters include athena_tags_taxonomy_ids, node_ids, paragraph_type_machine_names, content_type_machine_names,custom_route_ids.
     *                         The value of each filter can either be a single value or a comma delimited list of values
     * @param array $sortOptions = An array that includes the keys field_name and direction. Direction must be set to either ASC or DESC
     *
     * @return array|\Drupal\Core\Entity\EntityInterface[] = An array of nodes or an empty array if none are found.
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function getDocumentationNodes(array $contentTypeRestricted = [], array $filters = [], array $sortOptions = []) {
        $query = $this->entityCoreHelper->entityTypeManager()->getStorage('node')->getQuery();

        // Get Published nodes only
        $query->condition('status', '1');

        // See if we need to restrict the content types. The expected construct of the array should be a simple array of machine names of content types,
        // (i.e. ['athena_general', 'athena_paragraph'] and the content types MUST be a supported Athena content type
        if (empty($contentTypeRestricted)) {
            // Query all the Athena content types
            $query->condition('type', $this->athenaContentTypesMachineName, 'IN');
        } else {
            // Verify that each of the restricted content types passed in is a valid Athena content type
            foreach ($contentTypeRestricted as $key => $contentTypeMachineName) {
                if (!array_key_exists($contentTypeMachineName, $this->athenaContentTypes)) {
                    unset($contentTypeRestricted[$key]);
                }
            }
            $query->condition('type', $contentTypeRestricted, 'IN');
        }

        // Take care of any filters
        //$filters = [];
        //$filters['athena_tags_taxonomy_ids'] = '5216';
        //$filters['node_ids'] = '45196,45096';
        //$filters['paragraph_type_machine_names'] = 'advantages,banner';
        //$filters['content_type_machine_names'] = 'blog,datasheet';
        //$filters['custom_route_ids'] = 'hd_athena.thank_you_pages_settings,hd_athena.csv_bulk_upload_redirects_overview_page';

        // Cleanup the $filters array
        foreach ($filters as $k => $v) {
            $filters[$k] = trim($v);
        }
        if ($this->__checkArrayForKey($filters, 'athena_tags_taxonomy_ids') === TRUE) {
            $this->__addGroupQueryCondition($query, 'field_taxo_athena_tags', $filters['athena_tags_taxonomy_ids']);
        }
        if ($this->__checkArrayForKey($filters, 'node_ids') === TRUE) {
            $this->__addGroupQueryCondition($query, 'field_content_reference_multiple', $filters['node_ids']);
        }
        if ($this->__checkArrayForKey($filters, 'paragraph_type_machine_names') === TRUE) {
            $this->__addGroupQueryCondition($query, 'field_ref_paragraph_type_single', $filters['paragraph_type_machine_names']);
        }
        if ($this->__checkArrayForKey($filters, 'content_type_machine_names') === TRUE) {
            $this->__addGroupQueryCondition($query, 'field_content_type_ref_multi', $filters['content_type_machine_names']);
        }
        if ($this->__checkArrayForKey($filters, 'custom_route_ids') === TRUE) {
            $this->__addGroupQueryCondition($query, 'field_plain_long_single', $filters['custom_route_ids'], 'CONTAINS');
        }

        // Set the sort options
        $query->sort('promote', 'DESC');
        $query->sort('sticky', 'DESC');
        if (!empty($sortOptions)) {
            if (
                isset($sortOptions['field_name']) && !empty(trim($sortOptions['field_name'])) &&
                isset($sortOptions['direction']) && !empty(trim($sortOptions['direction'])) &&
                strtoupper($sortOptions['direction']) === 'ASC' || strtoupper($sortOptions['direction']) === 'DESC'
            ) {
                $query->sort($sortOptions['field_name'], $sortOptions['direction']);
            }
        } else {
            $query->sort('created', 'DESC');
        }

        // Exceute the query
        try {
            $nids = $query->execute();
        } catch (\Exception $e) {
            $nids = [];
        }

        $nodes = [];
        if (!empty($nids)) {
            $nodes = $this->entityCoreHelper->entityTypeManager()->getStorage('node')->loadMultiple($nids);
        }

        return $nodes;
    }

    public function doesRouteTaggingComputedValueAlreadyExist($computed_route_name) {
        $query = $this->entityCoreHelper->entityTypeManager()->getStorage('athena_eck')->getQuery();

        // Get Route Tagging bundle content only
        $query->condition('type', 'route_tagging');
        $query->condition('field_computed_route_name', $computed_route_name, '=');
        $query->range(0, 1);

        $results = $query->execute();

        if (empty($results)) {
            return FALSE;
        }

        $results = reset($results);
        //$destination = \Drupal::destination()->get();
        $options = [];
        //$options['query'] = ['destination' => $destination];
        $url = \Drupal\Core\Url::fromRoute('entity.athena_eck.edit_form', ['athena_eck' => $results], $options)->toString();

        return $url;
    }

    public function getDocumentationForRouteTags(string $route_name) {
        $query = $this->entityCoreHelper->entityTypeManager()->getStorage('athena_ent')->getQuery();

        // Get Route Tagging bundle content only
        $query->condition('field_computed_route_name', $route_name, '=');
        $query->range(0, 1);
        $query->accessCheck();

        $results = $query->execute();

        if (empty($results)) {
            return [];
        }

        $routeTaggingId = reset($results);

        // Try loading the entity
        $routeTaggingEntity = $this->entityCoreHelper->loadEntity('athena_ent', $routeTaggingId);
        if ($routeTaggingEntity === NULL || $routeTaggingEntity === FALSE) {
            return [];
        }

        // Get the Athena Tags assigned to the entity
        $athenaTags = $this->entityCoreHelper->getFieldValue($routeTaggingEntity, 'field_athena_tags');
        if (empty($athenaTags)) {
            return [];
        }

        // Build up an array of Athena Tag ids
        $tags = [];
        foreach ($athenaTags as $key => $array) {
            if (isset($array['target_id'])) {
                $tags[] = $array['target_id'];
            }
        }
        $tags = implode(',', $tags);

        // Perform the document search
        $searchResults = $this->getDocumentationNodes([], ['athena_tags_taxonomy_ids' => $tags], []);

        return $searchResults;
    }

    public function getAthenaContentTypes() {
        return $this->athenaContentTypes;
    }

    protected function _setAthenaContentTypes() {
        $this->athenaContentTypes = getAthenaContentTypes();
        $this->_setAthenaContentTypesMachineName();
    }

    protected function _setAthenaContentTypesMachineName() {
        $array = [];
        foreach ($this->athenaContentTypes as $machineName => $label) {
            $array[] = $machineName;
        }

        $this->athenaContentTypesMachineName = $array;
    }

    private function __checkArrayForKey(array $array, $key) {
        $status = FALSE;
        if (!empty($array[$key])) {
            $status = TRUE;
        }

        return $status;
    }

    private function __addGroupQueryCondition(&$query, $fieldName, $commaDelimitedListValues, $operator = '=') {
        $explodedValues = explode(',', $commaDelimitedListValues);
        $group = $query->orConditionGroup();
        foreach ($explodedValues as $key => $name) {
            $name = trim($name);
            if (!empty($name)) {
                $group->condition($fieldName, $name, $operator);
            }
        }
        $query->condition($group);
    }
} // End of class
