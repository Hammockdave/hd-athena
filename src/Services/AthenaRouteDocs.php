<?php
namespace Drupal\hd_athena\Services;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\hd_athena\Services\AthenaCoreApi;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\RouterInterface;
use RecursiveIteratorIterator;
use RecursiveArrayIterator;

class AthenaRouteDocs {

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
     * The router service.
     *
     * @var \Symfony\Component\Routing\RouterInterface
     */
    protected $router;

    // The Athena External Api service
    protected $athenaExternalApi;

    // The node if there is one in the route
    protected $node = FALSE;

    // The actual Route object if there is one
    protected $routeObject = FALSE;

    // The route name
    protected $routeName;

    // The route parameters
    protected $routeParameters;

    // The route name combinations for searching
    protected $routeNameCombinations = [];

    // The standard return array
    protected $standardReturnArray = [];

    // All the node ids of the searched documentation
    protected $foundNodeIds = [];

    public function __construct(AthenaCoreApi $athena_core_api, MessengerInterface $messenger, RouteMatchInterface $route_match, RouteProviderInterface $route_provider, RouterInterface $router) {
        // Services
        $this->athenaCoreApi = $athena_core_api;
        $this->messenger = $messenger;
        $this->routeMatch = $route_match;
        $this->routeProvider = $route_provider;
        $this->router = $router;

        // Class specifics
    }


    // Returns all published documentation for the current route, or an empty array if there's a problem or no documentation
    public function getAllAthenaDocumentationForRoute($route_name = FALSE, array $route_parameters = [], $athena_external_api) {
        $this->athenaExternalApi = $athena_external_api;
        // Do basic error checking
        if ($route_name === FALSE) {
            return [];
        } else {
            // Make sure the route exists
            try {
                $test = $this->routeProvider->getRouteByName($route_name); // getRouteByName will throw an exception if the route doesn't exist
            } catch (\Exception $e) {
                return [];
            }

        }

        // Set some of the defaults
        $this->routeName = $route_name;
        $this->routeParameters = $route_parameters;

        // Get and set the Route object and other properties such as node if there is one = \Symfony\Component\Routing\Route
        $routeObjectStatus = $this->_getRouteObject();
        if ($routeObjectStatus === FALSE) { // An exception was thrown so we don't have the data we need to continue
            return [];
        }

        // Set the Route Name Combinations
        $this->_setRouteNameCombinations();

        // Set the standard return array of documentation
        $this->_setStandardReturnArray();

        // Get the node documentation if there is any
        $nodeDocumentation = $this->_getNodeDocumentation();

        // Get the route documentation if there is any
        $routeDocumentation = $this->_getRouteDocumentation();

        // Get the documentation for any Athena Tags that have been assigned to the route
        $routeTagDocumentation = $this->_getRouteTagDocumentation();

        // Lastly, remove any duplicate found nodes
        $this->_removeDuplicateNodesFromReturnArray();

        return $this->standardReturnArray;
    }

    protected function _getRouteTagDocumentation() {
        $documentation = [];

        // Loop through $this->routeNameCombinations and get the documentation for each one.
        // The most specific will be first
        foreach ($this->routeNameCombinations as $key => $value) {
            $tempDocs = $this->athenaExternalApi->getAthenaDocsForRouteTags($value);
            if (!empty($tempDocs)) {
                $documentation[] = $tempDocs;
            }
            unset($tempDocs);
        }

        $this->standardReturnArray['route_tags'] = $documentation;
    }

    // This is the documentation if someone put the route in the route field of the Athena content type
    protected function _getRouteDocumentation() {
        $documentation = [];

        // Loop through $this->routeNameCombinations and get the documentation for each one.
        // The most specific will be first
        foreach ($this->routeNameCombinations as $key => $value) {
            $tempDocs = $this->athenaExternalApi->getAthenaDocsForRoute($value);
            if (!empty($tempDocs)) {
                $documentation[] = $tempDocs;
            }
            unset($tempDocs);
        }
        $this->standardReturnArray['route'] = $documentation;
    }

    protected function _getNodeDocumentation() {
        if ($this->node === FALSE) {
            return FALSE;
        }
        $this->standardReturnArray['node']['id'] = $this->athenaExternalApi->getAthenaDocsForNode($this->node->id());
        $this->standardReturnArray['node']['content_type'] = $this->athenaExternalApi->getAthenaDocsForContentType($this->node->bundle());
    }

    // This is the standard return array of documentation that features (i.e. slideout window) can count on
    protected function _setStandardReturnArray() {
        $data = [];

        // Node documentation
        $data['node'] = [];
        $data['node']['id'] = [];
        $data['node']['content_type'] = [];

        // Route documentation
        $data['route'] = [];

        // Route Tags documentation
        $data['route_tags'] = [];

        $this->standardReturnArray = $data;
    }

    // This will remove any duplicate nodes from the $this->standardReturnArray before it gets returned
    protected function listArrayRecursive($someArray) {

    }

    /**
     * http://uk1.php.net/array_walk_recursive implementation that is used to remove nodes from the array.
     *
     * @param array The input array.
     * @param callable $callback Function must return boolean value indicating whether to remove the node.
     * @return array
     */
    public function walk_recursive_remove(array $array, callable $callback) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $array[$k] = $this->walk_recursive_remove($v, $callback);
            } else {
                if ($callback($v, $k)) {
                    unset($array[$k]);
                }
            }
        }

        return $array;
    }

    private function __shouldItemBeRemoved($v, $k) {
        if ($v instanceof \Drupal\node\NodeInterface ) {
            $nid = $v->id();
            if (in_array($nid, $this->foundNodeIds)) {
                return TRUE;
            } else {
                $this->foundNodeIds[] = $v->id();
                return FALSE;
            }
        } else {
            $this->foundNodeIds[] = $v->id();
            return FALSE;
        }
    }

    protected function _removeDuplicateNodesFromReturnArray() {
        // https://stackoverflow.com/questions/6235901/how-to-unset-elements-using-array-walk-recursive
        $removedDuplicates = $this->walk_recursive_remove($this->standardReturnArray, [$this, '__shouldItemBeRemoved']);
        unset($this->standardReturnArray);
        $this->standardReturnArray = $removedDuplicates;
    }

    // This will formulate and set the route name combinations for documentation searching.
    // For example, if the route name is entity.node.edit_form and the array of parameters has node => 539,
    // Then the $this->routeNameCombinations will be set to the following with the most specific first:
    // entity.node.edit_form:536
    // entity.node.edit_form
    // This way we'll get the most specific documentation first to the least specific last
    protected function _setRouteNameCombinations() {
        $data = [];
        $route_name = $this->routeName;

        // Get rid of the route parameter array keys
        $route_parameters = array_values($this->routeParameters);
        $count = count($route_parameters) - 1; // arrays are zero based

        for ($i = $count; $i <= $count && $i >= 0; $i--) {
            if (isset($route_parameters[$i])) {
                $data[] = $route_name . ':' . implode(':', $route_parameters);
                unset($route_parameters[$i]);
            }

        }
        $data[] = $route_name;

        $this->routeNameCombinations = $data;
    }

    protected function _getRouteObject() {
        $status = TRUE;

        // Try getting the URL object
        try {
            $urlObject = \Drupal\Core\Url::fromRoute($this->routeName, $this->routeParameters);
        } catch (\Exception $e) {
            return FALSE;
        }

        // Try getting the Request object
        try {
            $request = \Symfony\Component\HttpFoundation\Request::create($urlObject->toString());
        } catch (\Exception $e) {
            return FALSE;
        }

        // Try getting a match for the request
        try {
            $match = $this->router->matchRequest($request);
        } catch (\Exception $e) {
            return FALSE;
        }

        // At this point we should have an array of data about the Route,
        // so set some of the variables
        if (isset($match['node']) && $match['node'] instanceof \Drupal\node\NodeInterface) {
            $this->node = $match['node'];
        }

        if (isset($match['_route_object']) && $match['_route_object'] instanceof \Symfony\Component\Routing\Route) {
            $this->routeObject = $match['_route_object'];
        }

        return $status;
    }



} // End of class
