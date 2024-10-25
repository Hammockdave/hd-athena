<?php
namespace Drupal\hd_athena\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
class AthenaExternalApi {

    /**
     * The Athena Core Api service.
     *
     * @var \Drupal\hd_athena\Services\AthenaCoreApi
     */
    protected $athenaCoreApi;

    /**
     * The Messenger service.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

  /**
   * Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

    /**
     * The current route match.
     *
     * @var \Drupal\Core\Routing\RouteMatchInterface
     */
    protected $routeMatch;

    /**
     * The route provider.
     *
     * @var \Drupal\Core\Routing\RouteProviderInterface
     */
    protected $routeProvider;

    /**
     * The Athena Route Docs.
     *
     * @var \Drupal\hd_athena\Services\AthenaRouteDocs
     */
    protected $athenaRouteDocs;

    public function __construct(AthenaCoreApi $athena_core_api, MessengerInterface $messenger, RouteMatchInterface $route_match, RouteProviderInterface $route_provider, AthenaRouteDocs $athena_route_docs, EntityTypeManagerInterface $entityTypeManager) {
        // Services
        $this->athenaCoreApi = $athena_core_api;
        $this->messenger = $messenger;
        $this->routeMatch = $route_match;
        $this->routeProvider = $route_provider;
        $this->athenaRouteDocs = $athena_route_docs;
        $this->entityTypeManager = $entityTypeManager;
    }

    public function getAthenaDocs($field, $value, $entityById = []) {
      $query = $this->entityTypeManager->getStorage('athena_ent')->getQuery();

      if (!empty($entityById)) {
        // Create or condition for entityById
        $orGroup = $query->orConditionGroup();
        $orGroup->condition($entityById[0], intval($entityById[1]));
        $orGroup->condition($field, $value);
        $query->condition($orGroup);
      } else {
        $query->condition($field, $value);
      }
      $query->accessCheck(FALSE);
      $aids = $query->execute();
      if(empty($aids)) {
        return [];
      }
      // Load the entities.
      return $this->entityTypeManager->getStorage('athena_ent')->loadMultiple($aids);
    }










//    // Returns all published documentation nodes
//    public function getAllAthenaDocsNodes() {
//        return $this->athenaCoreApi->getDocumentationNodes([], [], []);
//    }
//
//    public function getAllAthenaDocumentationForRoute(string $route_name, array $parameters) {
//        return $this->athenaRouteDocs->getAllAthenaDocumentationForRoute($route_name, $parameters, $this);
//    }
//
//    // Returns all published documentation for a single route
//    public function getAthenaDocsForRoute(string $route_name) {
//        $route_name = strtolower(trim($route_name));
//        $searchResults = $this->athenaCoreApi->getDocumentationNodes([], ['custom_route_ids' => $route_name], []);
//        if (empty($searchResults)) {
//            return [];
//        }
//        foreach ($searchResults as $nid => $node) {
//            // Get the value of field_plain_long_single which holds the routes assigned to documentation
//            $routesAssignedDocumentation = $node->get('field_plain_long_single')->getValue();
//            $routesAssignedDocumentation = explode(PHP_EOL, $routesAssignedDocumentation['0']['value']) ;
//
//            $exactRouteFound = FALSE;
//            foreach ($routesAssignedDocumentation as $key => $routeNameValue) {
//                $routeNameValue = strtolower(trim($routeNameValue));
//                if ($route_name === $routeNameValue) {
//                    $exactRouteFound = TRUE;
//                    break;
//                }
//            }
//
//            // If the route is found then we'll keep this node in the search results,
//            // otherwise we'll remove the node from the search results
//            if ($exactRouteFound === FALSE) {
//                unset($searchResults[$nid]);
//            }
//            unset($routesAssignedDocumentation, $exactRouteFound);
//        }
//
//        return $searchResults;
//    }
//
//    // Returns all published documentation for a single route with route parameters
//    public function getAthenaDocsForRouteWithParameters(string $route_name, array $route_parameters) {
//        $string = $route_name;
//        if (!empty($route_parameters)) {
//            $route_parameters = implode(':', $route_parameters);
//            $string .= ':' . $route_parameters;
//        }
//        return $this->getAthenaDocsForRoute($string);
//    }
//
//    // Returns all published documentation for multiple routes
//    // I'm not going to support this method because there's too much post processing to do with string/array comparisons to get non-duplicates
////    public function getAthenaDocsForMultipleRoutes(array $routes) {
////
////    }
//
//    public function getAthenaDocsForRouteTags(string $route_name) {
//        return $this->athenaCoreApi->getDocumentationForRouteTags($route_name);
//    }
//
//    // @param = A single node id
//    // Returns all published documentation nodes for a give node.
//    public function getAthenaDocsForNode(string $nid) {
//        return $this->getAthenaDocsForMultipleNodes([$nid]);
//    }
//
//    // @param = An array of node ids
//    // Returns all published documentation nodes for a given the nodes ids.
//    public function getAthenaDocsForMultipleNodes(array $nodeIds) {
//        $nodeIds = implode(',', $nodeIds);
//        return $this->athenaCoreApi->getDocumentationNodes([], ['node_ids' => $nodeIds], []);
//    }
//
//    // @param = A single Athena Tags taxonomy id
//    // Returns all published documentation nodes for a single taxonomy id.
//    public function getAthenaDocsForAthenaTag(string $tid) {
//        return $this->getAthenaDocsForMultipleAthenaTags([$tid]);
//    }
//
//    // @param = An array of Athena Tags taxonomy ids
//    // Returns all published documentation nodes for the array of Athena Tags taxonomy ids.
//    public function getAthenaDocsForMultipleAthenaTags(array $tids) {
//        $tids = implode(',', $tids);
//        return $this->athenaCoreApi->getDocumentationNodes([], ['athena_tags_taxonomy_ids' => $tids], []);
//    }
//
//    // Returns all of the Athena Tags taxonomy terms: tid => name
//    public function getAllAthenaTags() {
//        return $this->athenaCoreApi->taxonomyCoreHelper()->getTaxonomyTermsForVocabulary('athena_tags');
//    }
//
//    public function getAthenaDocsForParagraphType(string $paragraphType) {
//        return $this->getAthenaDocsForMultipleParagraphTypes([$paragraphType]);
//    }
//
//    public function getAthenaDocsForMultipleParagraphTypes(array $paragraphTypes) {
//        $paragraphTypes = implode(',', $paragraphTypes);
//        return $this->athenaCoreApi->getDocumentationNodes([], ['paragraph_type_machine_names' => $paragraphTypes], []);
//    }
//
//    public function getAthenaDocsForContentType(string $contentType) {
//        return $this->getAthenaDocsForMultipleContentTypes([$contentType]);
//    }
//    public function getAthenaDocsForMultipleContentTypes(array $contentTypes) {
//        $contentTypes = implode(',', $contentTypes);
//        return $this->athenaCoreApi->getDocumentationNodes([], ['content_type_machine_names' => $contentTypes], []);
//    }
//    public function sendParagraphUpdatedMessage(string $paragraphType): void {
//        $getParagraphTypeDocs = $this->getAthenaDocsForParagraphType($paragraphType);
//        $message = '';
//        if (empty($getParagraphTypeDocs)) {
//            $link = '<a href="/node/add/athena_general" target="_blank">create</a>';
//            $message .= 'Please remember to ' . $link . ' the documentation for this Paragraph.';
//        } else {
//            $getParagraphTypeDocs = reset($getParagraphTypeDocs);
//            if ($getParagraphTypeDocs instanceof \Drupal\node\Entity\Node) {
//                $link = '<a href="/node/' . $getParagraphTypeDocs->id() . '/edit" target="_blank">edit</a>';
//                $message .= 'Please remember to ' . $link . ' the documentation for this Paragraph since it may have changed.';
//            }
//        }
//        if (!empty(trim($message))) {
//            $this->messenger->addWarning(\Drupal\Core\Render\Markup::create($message));
//        }
//    }
//
//    public function sendContentTypeUpdatedMessage(string $contentType): void {
//        $getContentTypeDocs = $this->getAthenaDocsForContentType($contentType);
//
//        $message = '';
//        if (empty($getContentTypeDocs)) {
//            $link = '<a href="/node/add/athena_general" target="_blank">create</a>';
//            $message .= 'Please remember to ' . $link . ' the documentation for this content type.';
//        } else {
//            $message .= 'Please remember to edit the following documentation for this content type since it may have changed:<br>';
//            foreach ($getContentTypeDocs as $nid => $node) {
//                $message .= '<a href="/node/' . $nid . '/edit" target="_blank">' . $node->label() . '</a>';
//            }
//        }
//        if (!empty(trim($message))) {
//            $this->messenger->addWarning(\Drupal\Core\Render\Markup::create($message));
//        }
//    }
//
//    public function doesRouteTaggingComputedValueAlreadyExist($computed_route_name) {
//        return $this->athenaCoreApi->doesRouteTaggingComputedValueAlreadyExist($computed_route_name);
//    }

} // End of class
