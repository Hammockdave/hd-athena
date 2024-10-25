<?php
namespace Drupal\hd_athena\Services;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Views;
use Drupal\image\Entity\ImageStyle;
use Exception;
use Drupal\Component\Utility\Html;
use Drupal\hd_athena\Services\EntityCoreHelper;
use Drupal\field\Entity\FieldConfig;
use \Drupal\Core\Entity\EntityRepository;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class RenderCoreHelper {
    /**
     * The Image Factory service.
     *
     * @var \Drupal\Core\Image\ImageFactory
     */
    protected $imageFactory;

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
     * The Entity Repository service.
     *
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected $entityRepository;


    public function __construct(ImageFactory $image_factory, RendererInterface $renderer, EntityCoreHelper $entity_core_helper, EntityRepository $entity_repository) {
        $this->imageFactory = $image_factory;
        $this->renderer = $renderer;
        $this->entityCoreHelper = $entity_core_helper;
        $this->entityRepository = $entity_repository;
    }

    /**
     * @return \Drupal\hd_athena\Services\EntityCoreHelper
     */
    public function entityCoreHelper() {
        return $this->entityCoreHelper;
    }

    // Given a relative URL (i.e. /some-relative-url), this will return an array of data that can be used for translation purposes
    public function getTranslatedUrlDataFromRelativeUrl($url) {
        $url = trim(strtolower($url));

        // Return Values
        $returnValues = [];
        $returnValues['translated_url_of_current_language'] = FALSE;
        $returnValues['original_url'] = $url;
        $returnValues['url_object'] = FALSE;
        $returnValues['current_language_object'] = FALSE;
        $returnValues['url_page_type'] = FALSE;
        $returnValues['nid'] = FALSE;

        // Basic pre-error checking
        // Make sure we're NOT dealing with an external url
        $isUrlExternal = UrlHelper::isExternal($url);
        if (empty($url) || $isUrlExternal === TRUE) {
            return $returnValues;
        } else {
            // Make sure the URL begins with a slash
            if (strpos($url, '/') !== 0) {
                $url = '/' . $url;
            }
        }

        //
        $currentLanguageId = strtolower(\Drupal::languageManager()->getCurrentLanguage()->getId());

        // We need to determine the language of the url
        $languageDataUrl = $this->getLangaugeDataOfRelativeUrl($url);
        $returnValues['current_language_object'] = $languageDataUrl['installed_languages'][$currentLanguageId];

        // We first need to check if the url is a node
        $pathAlias = \Drupal::service('path.alias_manager')->getPathByAlias($languageDataUrl['url_no_language_prefix'], $languageDataUrl['lang_id']);

        // If the path alias is not a node then we need to check if the path alias is a redirect,
        // if it is a redirect then try and get the redirect node if there is one.
        if (substr($pathAlias, 0, 6) !== '/node/') {
            $tempPathAlias = $pathAlias;
            if (strpos($pathAlias, '/') === 0) {
                $tempPathAlias = substr($pathAlias, 1);
            }
            // If a node wasn't found, then there could be a redirect
            $repository = \Drupal::service('redirect.repository');
            $redirectEntities = $repository->findBySourcePath($tempPathAlias);

            if (!empty($redirectEntities)) {
                $redirectEntityPathAlias = $this->getRedirectEntityPathAlias($redirectEntities, $currentLanguageId);

                // We found a redirect path alias
                if ($redirectEntityPathAlias !== FALSE) {
                    if (substr($redirectEntityPathAlias, 0, 6) === '/node/') {
                        $pathAlias = $redirectEntityPathAlias;
                    }
                }
            }
        }

        $pattern = '/^\/node\/\d+$/';
        // We found a node....hopefully
        if (preg_match($pattern, $pathAlias, $match)) {
            // Drop the /node/ from the string and it should give you the node id
            $nid = str_replace('/node/', '', $pathAlias);
            $nodeHelper = \Drupal::service('hd_athena.node_helper');

            // We're going to test to make sure the node id is valid since
            // the rest of the code assumes that there's a valid node in the system
            $nidValid = $nodeHelper->isNodeIdValid($nid);
            if ($nidValid === FALSE) {
                return  $returnValues;
            }

            $options = [];
            $options['language'] = $languageDataUrl['installed_languages'][$currentLanguageId];
            $nodeUri = 'entity:node/' . $nid;
            $nodeUrlObject = \Drupal\Core\Url::fromUri($nodeUri, $options);

            // Test the new url object to make sure that it works,
            // if it doesn't work then an exception will be thrown
            $status = TRUE;
            try {
                $translatedUrlOfCurrentLanguage = $nodeUrlObject->toString();
            }
            catch (RouteNotFoundException $e) {
                $status = FALSE;
            }
            catch (InvalidParameterException $e) {
                $status = FALSE;
            }
            catch (MissingMandatoryParametersException $e) {
                $status = FALSE;
            }
            catch (\Exception $e) {
                $status = FALSE;
            }
            // If there's a problem with the URL object then obviously something horrible went wrong
            if ($status === FALSE) {
                return  $returnValues;
            }
            $returnValues['translated_url_of_current_language'] = $translatedUrlOfCurrentLanguage;
            $returnValues['url_object'] = $nodeUrlObject;
            $returnValues['url_page_type'] = 'node';
            $returnValues['nid'] = $nid;

            return $returnValues;
        }

        // At this point we know that we are NOT on a node, so we may be on a View or a Custom route
        // Any other internal url we are simply not going to deal with
        $pathValidationUrlObject = \Drupal::service('path.validator')->getUrlIfValidWithoutAccessCheck($url);
        if ($pathValidationUrlObject !== FALSE && $pathValidationUrlObject->isRouted() === TRUE) {
            // Get the route name
            $routeName = $pathValidationUrlObject->getRouteName();
            if (substr($routeName, 0, 4) === 'view') {
                $returnValues['url_page_type'] = 'view';
            } else {
                $returnValues['url_page_type'] = 'custom'; // We're going to assume it's custom
            }
            $pathValidationUrlObject->setOption('language', $languageDataUrl['installed_languages'][$currentLanguageId]);
            $returnValues['translated_url_of_current_language'] = $pathValidationUrlObject->toString();
            $returnValues['url_object'] = $pathValidationUrlObject;

            return $returnValues;
        }

        return $returnValues;
    }

    public function getLangaugeDataOfRelativeUrl($url) {
        // Get all installed language
        $installedLanguages = \Drupal::languageManager()->getLanguages();

        // Return Values
        $returnValues = [];
        $returnValues['url_no_language_prefix'] = $url;
        $returnValues['original_url'] = $url;
        $returnValues['lang_id'] = FALSE;
        $returnValues['lang_object'] = FALSE;
        $returnValues['installed_languages'] = $installedLanguages;


        foreach ($installedLanguages as $lid => $languageObject) {
            $languagePrefixStringLength = strlen($lid) + 2; // We add 2 for the two slashes /<lang id>/
            $langaguePrefix = '/' . $lid . '/';
            if (substr($url, 0, $languagePrefixStringLength) === $langaguePrefix) {
                $returnValues['lang_id'] = $lid;
                $returnValues['lang_object'] = $languageObject;
                $returnValues['url_no_language_prefix'] = substr($url, ($languagePrefixStringLength - 1));
                break;
            }
        }

        if ($returnValues['lang_id'] === FALSE) {
            $returnValues['lang_id'] = 'en';
            $returnValues['lang_object'] = $installedLanguages['en'];
        }

        return $returnValues;
    }

    public function getRedirectEntityPathAlias(array $redirectEntities, $currentLanguageId) {
        $pathAlias = FALSE;
        $redirectEntityPathAlias = FALSE;
        $undRedirectEntityPathAlias = FALSE;

        // Loop through the Redirect entities and try and pull out the redirect entity path alias,
        // for the current language if possible, otherwise try and get the generic one (i.e. und).
        foreach ($redirectEntities as $entityId => $redirectEntity) {
            $languageId = $redirectEntity->get('language')->getValue();
            if (isset($languageId['0']['value']) && strtolower($languageId['0']['value']) === $currentLanguageId) {
                $redirectEntityPathAlias = $redirectEntity->getRedirect();
                if (isset($redirectEntityPathAlias['uri'])) {
                    $redirectEntityPathAlias = strtolower($redirectEntityPathAlias['uri']);
                }
                break;
            } elseif (isset($languageId['0']['value']) && strtolower($languageId['0']['value']) === 'und') { // No language specified
                $undRedirectEntityPathAlias = $redirectEntity->getRedirect();
                if (isset($undRedirectEntityPathAlias['uri'])) {
                    $undRedirectEntityPathAlias = strtolower($undRedirectEntityPathAlias['uri']);
                }
            }
        }


        // If there's neither one then return false
        if ($redirectEntityPathAlias === FALSE && $undRedirectEntityPathAlias === FALSE) {
            return FALSE;
        }

        // Figure out which redirect entity path alias we're going to use
        // It defaults to the current language one other we'll use the generic/no language one
        if ($redirectEntityPathAlias !== FALSE) {
            $pathAlias = $redirectEntityPathAlias;
        } else {
            $pathAlias = $undRedirectEntityPathAlias;
        }

        // We're only going to deal with internal path aliases
        if (substr($pathAlias, 0, 9) === 'internal:') {
            $pathAlias = substr($pathAlias, 9);

            // We're going to query the database directly since the AliasManager service
            // takes into account the language of the current page. We don't want that.
            // We want to get ALL the path aliases for every language.
            $connection = \Drupal::database();
            $query = $connection->select('path_alias', 'pa');
            $query->condition('pa.alias', $pathAlias, '=');
            $query->fields('pa', ['path', 'langcode']);
            $results = $query->execute()->fetchAll();
            if (empty($results)) {
                // If there are no results then there is no path alias
                $pathAlias = FALSE;
            } else {
                // There could be multiple path aliases found, so we
                // need to first try and get the path alias for the current language,
                // otherwise we will default to the English language
                $englishPathAlias = FALSE;
                $currentLanguagePathAlias = FALSE;
                foreach ($results as $key => $object) {
                    if (isset($object->langcode) && $object->langcode === $currentLanguageId) {
                        $currentLanguagePathAlias = $object->path;
                        break;
                    } elseif (isset($object->langcode) && $object->langcode === 'en') {
                        $englishPathAlias = $object->path;
                    }
                }

                // If there's neither a path alias for the current language or english then,
                // we better not use some other language path alias just to be safe
                if ($englishPathAlias === FALSE && $currentLanguagePathAlias === FALSE) {
                    $pathAlias = FALSE;
                } else {
                    // If there's a path alias for the current language then use it
                    if ($currentLanguagePathAlias !== FALSE) {
                        $pathAlias = $currentLanguagePathAlias;
                    } elseif ($englishPathAlias !== FALSE) { // If there's a path for English then use it
                        $pathAlias = $englishPathAlias;
                    } else { // It should never get to here but just in case
                        $pathAlias = FALSE;
                    }
                }
            }
        }

        return $pathAlias;
    }

  /**
   * Video element render
   *
   * @param $url
   * @return array
   */
    public function renderVideoFromUrl($url) {
        $embedVideoURL = $this->getEmbedVideoURL($url);
        $renderedArray = [];
        $renderedArray[0] = [
          '#type' => 'video_embed_iframe',
          '#provider' => 'wistia',
          '#url' => $embedVideoURL,
          '#query' => [
            'autoplay' => 0
          ],
          '#attributes' => [
            'width' => 854,
            'height' => 480,
            'frameborder' => '0',
            'allowfullscreen' => 'allowfullscreen',
          ],
        ];
        $renderedArray[0]['#cache']['contexts'][] = 'user.permissions';

        $renderedArray[0] = [
          '#type' => 'container',
          '#attributes' => ['class' => [Html::cleanCssIdentifier(sprintf('video-embed-field-provider-wistia'))]],
          'children' => $renderedArray[0],
        ];

        // For responsive videos, wrap each field item in it's own container.
        $renderedArray[0]['#attached']['library'][] = 'video_embed_field/responsive-video';
        $renderedArray[0]['#attributes']['class'][] = 'video-embed-field-responsive-video';

        return $renderedArray;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmbedVideoURL($input, $id_only = false) {
      // Wistia
      if(strpos($input, "wistia.com") !== false ) {
        preg_match('/^https?:\/\/(.+)?(wistia.com|wi.st)\/(medias|embed)\/(?<id>[0-9A-Za-z]+)$/', $input, $matches);
        if (isset($matches['id'])) {
            if($id_only) {
                return $matches['id'];
            }
          return sprintf('https://fast.wistia.net/embed/iframe/%s', $matches['id']);
        }
      }

      // Youtube
      else if(strpos($input, "youtube.com") !== false ) {
        preg_match('/^https?:\/\/(www\.)?((?!.*list=)youtube\.com\/watch\?.*v=|youtu\.be\/)(?<id>[0-9A-Za-z_-]*)/', $input, $matches);
        if (isset($matches['id'])) {
            if($id_only) {
                return $matches['id'];
            }
          return sprintf('https://www.youtube.com/embed/%s', $matches['id']);
        }
      }

      // If given input (URL) isn't supported or Invalid URL is given, returns false.
      return FALSE;

    }

    public function buildImageStyleUrl($uri, $imageStyle, $relative = FALSE) {
        $style = \Drupal::entityTypeManager()->getStorage('image_style')->load($imageStyle);

        if ($style === NULL || $style === FALSE) {
            return FALSE;
        }
        $url = $style->buildUrl($uri);

        if($relative) {
            // TODO: Will use \Drupal::service('file_url_generator')->transformRelative($file_url) in the future
            // See https://www.drupal.org/node/2940031
            return \Drupal::service('file_url_generator')->transformRelative($url);
        } else {
            return $url;
        }
    }

    public function buildFileUrlFromFid($fid, $relative = FALSE) {
        $file = File::load($fid);
        $uri = $file->getFileUri();
        $absolute_url = file_create_url($uri);

        if($relative) {
            // TODO: Will use \Drupal::service('file_url_generator')->transformRelative($file_url) in the future
            // See https://www.drupal.org/node/2940031
            return \Drupal::service('file_url_generator')->transformRelative($absolute_url);
        } else {
            return $absolute_url;
        }

    }

    public function buildBlazyResponsiveImageRenderObject($entity, $field, $responsive_image_style_id, $attributes = []) {
        if($file = $this->entityCoreHelper()->getFieldValue($entity, $field)) {
            $renderObjects = [];
            foreach ($file as $f) {
                $uri = $this->getImageUrls($f['target_id']);
                $uri = $uri['uri'];

                $settings = [
                    'uri' => $uri,
                    'lazy' => 'blazy',
                    'responsive_image_style_id' => $responsive_image_style_id,
                ];
                $item_attributes = $attributes;
                $item_attributes['alt'] = $f['alt'];
                $item_attributes['title'] = $f['title'];
                $renderObject = [
                    '#theme'    => 'blazy',
                    '#settings' => $settings,
                    '#item_attributes' => $item_attributes,
                    '#attached' => ['library' => ['blazy/load']],
                ];
                $renderObjects[] = $renderObject;
            }
            if(count($renderObjects) === 1) {
                return $renderObjects[0];
            }
            else {
                return $renderObjects;
            }
        }
        else {
            return FALSE;
        }
    }

    public function buildImageStyleUrlFromFileId($fid, $imageStyle) {
        // Get the uri
        $uri = $this->getImageUrls($fid);
        $uri = $uri['uri'];

        // Build the url
        return $this->buildImageStyleUrl($uri, $imageStyle);
    }

    public function getImageUrls($imageFileId) {
        $file = \Drupal\file\Entity\File::load($imageFileId);
        $imageUri = $file->getFileUri();
        $mimeType = $file->getMimeType();
        $imageAbsoluteUrl = file_create_url($imageUri);
        $imageRelativeUrl = \Drupal::service('file_url_generator')->transformRelative($imageAbsoluteUrl);

        return array('absolute' => $imageAbsoluteUrl, 'relative' => $imageRelativeUrl, 'uri' => $imageUri, 'fid' => $imageFileId, 'mime_type' => $mimeType);
    }
    public function getMediaImageUrls($mediaFileId) {
        $mediaEntity = $this->entityCoreHelper->loadEntity('media', $mediaFileId);
        if ($mediaEntity === NULL or $mediaEntity === FALSE) {
            return FALSE;
        }

        if($mediaImage = $this->entityCoreHelper->getFieldValue($mediaEntity, 'field_media_image')) {
            $file = \Drupal\file\Entity\File::load($mediaImage[0]['target_id']);
            $imageUri = $file->getFileUri();
            $mimeType = $file->getMimeType();
            $imageAbsoluteUrl = file_create_url($imageUri);
            $imageRelativeUrl = \Drupal::service('file_url_generator')->transformRelative($imageAbsoluteUrl);

            return array('absolute' => $imageAbsoluteUrl, 'relative' => $imageRelativeUrl, 'uri' => $imageUri, 'fid' => $mediaImage[0]['target_id'], 'mime_type' => $mimeType, 'alt' => $mediaImage[0]['alt']);
        }
        return FALSE;
    }

    public function buildImageStyleRenderArray($imageFileId = FALSE, $imageStyleId = FALSE) {
        if ($imageFileId == FALSE || $imageStyleId == FALSE) {
            return FALSE;
        }

        // Load the file
        $file = $this->entityCoreHelper()->loadEntity('file', $imageFileId);

        if ($file == NULL) {
            return FALSE;
        }

        // Make sure the Image Style Id is valid
        $imageEntity = $this->entityCoreHelper()->loadEntity('image_style', $imageStyleId);
        if ($imageEntity == NULL) {
            return FALSE;
        }

        $variables = array(
            'image_style_id' => $imageStyleId,
            'uri' => $file->getFileUri(),
        );

        // The image.factory service will check if our image is valid.
        $image = $this->imageFactory->get($file->getFileUri());
        if ($image->isValid()) {
            $variables['width'] = $image->getWidth();
            $variables['height'] = $image->getHeight();
        } else {
            return FALSE;
        }

        $imageRenderArray = [
            '#theme' => 'image_style',
            '#width' => $variables['width'],
            '#height' => $variables['height'],
            '#style_name' => $variables['image_style_id'],
            '#uri' => $variables['uri'],
        ];

        // Add the file entity to the cache dependencies.
        // This will clear our cache when this entity updates.
        $this->renderer->addCacheableDependency($imageRenderArray, $file);

        return $imageRenderArray;
    }

    public function renderParagraphById($type, $id, $viewMode = 'default') {
        $entity = $this->entityCoreHelper->loadEntity($type, $id);
        if ($entity === NULL) {
            return FALSE;
        }
        $viewBuilder = $this->entityCoreHelper->getEntityViewBuilder($type);

        $pre_render = $viewBuilder->view($entity, $viewMode);
        // Drupal renderer service
      $renderer = \Drupal::service('renderer');
        $render_output = $renderer->render($pre_render);

        return $render_output;
    }

    public function renderEntityById($type, $id, $viewMode = 'default') {
        $entity = $this->entityCoreHelper->loadEntity($type, $id);
        if ($entity === NULL) {
            return FALSE;
        }
        $viewBuilder = $this->entityCoreHelper->getEntityViewBuilder($type);

        $pre_render = $viewBuilder->view($entity, $viewMode);
      $renderer = \Drupal::service('renderer');
        $render_output = $renderer->render($pre_render);

        return $render_output;
    }

    public function renderNodeById($id, $viewMode = 'default') {
        $type = 'node';
        $entity = $this->entityCoreHelper->loadEntity($type, $id);
        if ($entity === NULL) {
            return FALSE;
        }
        $viewBuilder = $this->entityCoreHelper->getEntityViewBuilder($type);

        $pre_render = $viewBuilder->view($entity, $viewMode);
      $renderer = \Drupal::service('renderer');
        $render_output = $renderer->render($pre_render);
        return $render_output;
    }

    public function renderTaxonomyById($id, $viewMode = 'default') {
        $type = 'taxonomy_term';
        $entity = $this->entityCoreHelper->loadEntity($type, $id);
        if ($entity === NULL) {
            return FALSE;
        }
        $viewBuilder = $this->entityCoreHelper->getEntityViewBuilder($type);

        $pre_render = $viewBuilder->view($entity, $viewMode);
      $renderer = \Drupal::service('renderer');
        $render_output = $renderer->render($pre_render);
        return $render_output;
    }

    public function renderWebdamAssetUsingImageStyle($id, $imageStyle) {
        // Try loading the entity
        $mediaEntity = $this->entityCoreHelper->loadEntity('media', $id);
        if ($mediaEntity === NULL or $mediaEntity === FALSE) {
            return FALSE;
        }

        // Get the image file id
        $fid = $this->entityCoreHelper->getFieldValue($mediaEntity, 'field_media_file');
        if (isset($fid['0']['target_id'])) {
            $fid = $fid['0']['target_id'];
            // Load the file
            $file = $this->entityCoreHelper->loadEntity('file', $fid);
            $uri = $file->getFileUri();
        } else {
            return FALSE;
        }

        // Get the alt text if it's set
        $altText = $this->entityCoreHelper->getFieldValue($mediaEntity, 'field_hd_alt_text');
        if (isset($altText['0']['value'])) {
            $altText = $altText['0']['value'];
        } else {
            $altText = '';
        }

        // Build the image style url
        $imageStyleUrl = $this->buildImageStyleUrl($uri, $imageStyle);
        if ($imageStyleUrl === FALSE) {
            return FALSE;
        }

        // Build html using the alt text
        $htmlVersion = '<img class="webdam-image" src="' . $imageStyleUrl . '" alt="' . $altText . '">';

        $return['url'] = $imageStyleUrl;
        $return['url_html'] = $htmlVersion;

        return $return;
    }

    public function getWebDamImageUrl($id) {
        // Try loading the entity
        $mediaEntity = $this->entityCoreHelper->loadEntity('media', $id);
        if ($mediaEntity === NULL or $mediaEntity === FALSE) {
            return FALSE;
        }

        // Get the image file id
        $fid = $this->entityCoreHelper->getFieldValue($mediaEntity, 'field_media_file');
        if (isset($fid['0']['target_id'])) {
            $fid = $fid['0']['target_id'];
            // Load the file
            $file = $this->entityCoreHelper->loadEntity('file', $fid);
            $uri = $file->getFileUri();
        } else {
            return FALSE;
        }

        $url = file_create_url($uri);

        return $url;
    }

    public function getWebDamImageProperties($id) {
        $data = [];
        $data['media_entity_id'] = $id;
        $data['width'] = FALSE;
        $data['height'] = FALSE;
        $data['absolute_url'] = FALSE;
        $data['relative_url'] = FALSE;
        $data['url_html'] = FALSE;
        $data['uri'] = FALSE;
        $data['fid'] = FALSE;
        // Try loading the entity
        $mediaEntity = $this->entityCoreHelper->loadEntity('media', $id);
        if ($mediaEntity === NULL or $mediaEntity === FALSE) {
            return FALSE;
        }

        // Get the image file id
        $fid = $this->entityCoreHelper->getFieldValue($mediaEntity, 'field_media_file');

        if (isset($fid['0']['target_id'])) {
            $fid = $fid['0']['target_id'];
            // Load the file
            $file = $this->entityCoreHelper->loadEntity('file', $fid);
            $uri = $file->getFileUri();
            $data['uri'] = $uri;
            $data['fid'] = $fid;
            $image = $this->imageFactory->get($file->getFileUri());
            if ($image->isValid()) {
                $data['width'] = $image->getWidth();
                $data['height'] = $image->getHeight();
            }
            $data['absolute_url'] = file_create_url($uri);
            $data['relative_url'] = \Drupal::service('file_url_generator')->transformRelative($data['absolute_url']);

            // Get the alt text if it's set
            $altText = $this->entityCoreHelper->getFieldValue($mediaEntity, 'field_hd_alt_text');
            if (isset($altText['0']['value'])) {
                $altText = $altText['0']['value'];
            } else {
                $altText = '';
            }
            $htmlVersion = '<img class="webdam-image" src="' . $data['relative_url'] . '" alt="' . $altText . '">';
            $data['url_html'] = $htmlVersion;
        } else {
            return FALSE;
        }

        return $data;
    }

    // Get raw string from html
    public function getRawStringFromHtml($string) {
        // Strip the html tags
        $string = strip_tags(trim($string));

        // Run the string through html_entity_decode, this is for things such as &nbsp;
        $string = html_entity_decode($string);

        // Remove any newline carriage characters
        $string = str_replace("\n", '', $string);
        $string = str_replace("\r", '', $string);
        $string = str_replace("\r\n", '', $string);

        return $string;
    }

    // This gets the default image set to an image field if there is one
    public function getDefaultImageFromField($entityType, $entityBundle, $fieldName) {
        $fieldEntity = $this->entityCoreHelper->loadEntity('field_config', $entityType . '.' . $entityBundle . '.' . $fieldName);
        if ($fieldEntity === NULL) {
            return FALSE;
        }
        $imageUuid = $fieldEntity->getSetting('default_image')['uuid'];
        $image = $this->entityRepository->loadEntityByUuid('file', $imageUuid);
        $imageUri = $image->getFileUri();

        $imageAbsoluteUrl = file_create_url($imageUri);
        $imageRelativeUrl = \Drupal::service('file_url_generator')->transformRelative($imageAbsoluteUrl);

        return array('absolute' => $imageAbsoluteUrl, 'relative' => $imageRelativeUrl, 'uri' => $imageUri);
    }

    // This will output a link from Text, URL and optional attributes
    public function createLinkArrayFromTextUrl($text, $url, $attributes = array()) {

        return Link::fromTextAndUrl($text,$url,$attributes);

    }

    // Generates html for speakers grid output
    public function getSpeakersGrid($speakers) {

        $node_helper = \Drupal::service('hd_athena.node_helper');

        $speaker_markup = array();

        foreach($speakers as $speaker) {

            $speakerEntity = $this->entityCoreHelper->loadEntity('node', $speaker['target_id']);
            if($speakerEntity) {
                $speaker_data = $node_helper->getSpeakerData($speakerEntity);

                // Get image of image style
                if(isset($speaker_data['headshot']['fid'])) {
                    $image_info = $this->buildImageStyleUrlFromFileId($speaker_data['headshot']['fid'], 'speaker_grid');
                } else {
                    $image_info = '';
                }
                $speaker_markup[$speaker['target_id']]['item_container'] = array(
                    '#type' => 'container',
                    '#attributes' => array(
                        'class' => ['speaker-card','col','col-sm-6','col-md-3','pb-5','w-100'],
                    ),
                );

                $speaker_markup[$speaker['target_id']]['item_container']['image'] = array(
                    '#type' => 'html_tag',
                    '#tag' => 'img',
                    '#attributes' => array(
                        'class' => ['speaker_image','b-lazy','w-100'],
                        'src' => 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==',
                        'data-src' => $image_info,
                    ),
                );

                $speaker_markup[$speaker['target_id']]['item_container']['info'] = array(
                    '#type' => 'container',
                    '#attributes' => array(
                        'class' => ['speaker-info', 'no-gutters', 'px-0'],
                    ),
                );

                $speaker_markup[$speaker['target_id']]['item_container']['info']['name'] = array(
                    '#type' => 'html_tag',
                    '#tag' => 'div',
                    '#value' => $speaker_data['name'],
                    '#attributes' => array(
                        'class' => ['speaker-name', 'font-size-xl', 'pt-2'],
                    ),
                );

                $speaker_markup[$speaker['target_id']]['item_container']['info']['job_title'] = array(
                    '#type' => 'html_tag',
                    '#tag' => 'div',
                    '#value' => $speaker_data['job_title'],
                    '#attributes' => array(
                        'class' => ['speaker-title'],
                    ),
                );

                $speaker_markup[$speaker['target_id']]['item_container']['info']['org_name'] = array(
                    '#type' => 'html_tag',
                    '#tag' => 'div',
                    '#value' => $speaker_data['organization']['name'],
                    '#attributes' => array(
                        'class' => ['speaker-org'],
                    ),
                );
            }

        }

        return $speaker_markup;

    }

    public function renderMediaImageUsingImageStyle($id, $imageStyle) {
        if($media_image = $this->getMediaImageUrls($id)) {
            // Build the image style url
            $imageStyleUrl = $this->buildImageStyleUrl($media_image['uri'], $imageStyle);
            if ($imageStyleUrl === FALSE) {
                $htmlVersion = '<img class="media-image b-lazy" data-src="' . $media_image['relative'] . '" alt="' . $media_image['alt'] . '">';
                $return['url'] = $media_image['relative'];

            }
            else {
                $htmlVersion = '<img class="media-image b-lazy" data-src="' . $imageStyleUrl . '" alt="' . $media_image['alt'] . '">';
                $return['url'] = $imageStyleUrl;
            }
            $return['url_html'] = $htmlVersion;

            return $return;
        }
        else {
            return FALSE;
        }
        return FALSE;
    }

    public function getMediaImageProperties($id) {
        $data = [];
        $data['media_entity_id'] = $id;
        $data['width'] = FALSE;
        $data['height'] = FALSE;
        $data['absolute_url'] = FALSE;
        $data['relative_url'] = FALSE;
        $data['url_html'] = FALSE;
        $data['uri'] = FALSE;
        $data['fid'] = FALSE;
        // Try loading the entity
        $mediaEntity = $this->entityCoreHelper->loadEntity('media', $id);
        if ($mediaEntity === NULL or $mediaEntity === FALSE) {
            return FALSE;
        }

        // Get the image file id
        $fid = $this->entityCoreHelper->getFieldValue($mediaEntity, 'field_media_image');

        if (isset($fid['0']['target_id'])) {
            $fid = $fid['0']['target_id'];
            // Load the file
            $file = $this->entityCoreHelper->loadEntity('file', $fid);
            $uri = $file->getFileUri();
            $data['uri'] = $uri;
            $data['fid'] = $fid;
            $image = $this->imageFactory->get($file->getFileUri());
            if ($image->isValid()) {
                $data['width'] = $image->getWidth();
                $data['height'] = $image->getHeight();
            }
            $data['absolute_url'] = file_create_url($uri);
            $data['relative_url'] = \Drupal::service('file_url_generator')->transformRelative($data['absolute_url']);

            // Get the alt text if it's set
            $altText = $this->entityCoreHelper->getFieldValue($mediaEntity, 'field_hd_alt_text');
            if (isset($altText['0']['value'])) {
                $altText = $altText['0']['value'];
            } else {
                $altText = '';
            }
            $htmlVersion = '<img class="media-image b-lazy" data-src="' . $data['relative'] . '" alt="' . $altText . '">';
            $data['url_html'] = $htmlVersion;
        } else {
            return FALSE;
        }

        return $data;
    }
}
