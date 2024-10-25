<?php
namespace Drupal\hd_athena\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\Image;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

class EntityCoreHelper {
    /**
     * The entity type manager service.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    public $entityTypeManager;

    protected $entityTypeBundleInfo;

    public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
        $this->entityTypeManager = $entity_type_manager;
        $this->entityTypeBundleInfo = $entity_type_bundle_info;
    }

    public function entityTypeManager() {
        return $this->entityTypeManager;
    }

    public function getEntityViewBuilder($entityType) {
        return $this->entityTypeManager->getViewBuilder($entityType);
    }

    public function getAllEntityFieldValues($entity) {
        $fieldValues = array();
        // Get all the fields
        $fields = $entity->getFields();

        // Get the value of each of the fields
        foreach ($fields as $name => $fieldObject) {
            $fieldValues[$name] = array('string_value' => $fields[$name]->getString(), 'array_value' => $fields[$name]->getValue());
        }

        return $fieldValues;
    }

    public function isEntity($entity) {
        if (!is_object($entity)) {
            return FALSE;
        }
        try {
            // Validate the entity
            $isEntity = $entity->validate();
        } catch (\Exception $e) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param object $entity
     * @param string $fieldName
     *
     * @return array|uri and url
     */
    public function getDefaultImageInfoForField($entity = FALSE, $fieldName = FALSE) {
        $isEntity = $this->isEntity($entity);
        if ($entity === FALSE || $fieldName === FALSE || $isEntity === FALSE) {
            return FALSE;
        }

        // This will return tye type of entity it is (i.e. paragraph, node, file, etc.)
        $entityType = $this->getEntityTypeId($entity);

        // Make sure the entity has the field
        $fieldName = trim($fieldName);
        $hasField = $this->doesEntityHaveField($entity, $fieldName);

        if ($hasField === FALSE) {
            return FALSE;
        }

        // Get the entity bundle
        $bundle = $entity->bundle();

        // Get the default image
        $field_config = FieldConfig::loadByName($entityType, $bundle, $fieldName);
        $file_uuid = $field_config->getSetting('default_image')['uuid'];
        if (!$file_uuid) {
            /* var $field_storage_config FieldStorageConfig */
            $field_storage_config = FieldStorageConfig::loadByName('node', 'field_hero_image');
            $file_uuid = $field_storage_config->getSetting('default_image')['uuid'];

        }
        $file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $file_uuid);

        $defaultImageArray = [];
        $defaultImageArray['uri'] = $file->getFileUri();
        $defaultImageArray['absolute_url'] = file_create_url($file->getFileUri());

        return $defaultImageArray;
    }

    /**
     * @param $entity
     *
     * @return string (paragraph, node, etc.)
     */
    public function getEntityTypeId($entity) {
        return $entity->getEntityTypeId();
    }

    /**
     * @param $entity
     * @param $fieldName
     *
     * @return boolean
     */
    public function doesEntityHaveField($entity, $fieldName) {
        $hasField = $entity->hasField($fieldName);

        return $hasField;
    }

    /**
     * @param $entity
     * @param $fieldName
     *
     * @return boolean
     */
    public function doesEntityHaveFieldAndNotEmpty($entity, $fieldName) {
        $hasField = $entity->hasField($fieldName);

        if ($hasField === FALSE) {
            return FALSE;
        }

        // See if there's a value
        $value = $this->getFieldValue($entity, $fieldName);

        if (is_array($value) && empty($value)) {
            return FALSE;
        }

        if (is_object($value) && empty($value)) {
            return FALSE;
        }

        if (is_string($value) && strlen($value) < 1) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $entityType
     * @param $id
     *
     * @return string The type of entity
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function getEntityBundleType($entityType, $id) {
        // Load Entity
        $entity = $this->loadEntity($entityType, $id);
        if ($entity === NULL) {
            return FALSE;
        }
        return $entity->getType();
    }

    /**
     * @param $entityType
     * @param $id
     * @param $getTranslatedEntity = this will determine if a translated entity gets returned or not
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function loadEntity($entityType, $id, $getTranslatedEntity = FALSE) {
        $entity = $this->entityTypeManager->getStorage($entityType)->load($id);

        // If $getTranslatedEntity is TRUE then return the translated entity if there is one
        if ($entity !== NULL && $getTranslatedEntity === TRUE) {
            // Get the current page language
            $langCode = \Drupal::service('language_manager')->getCurrentLanguage()->getId();

            if ($entity->hasTranslation($langCode)) {
                $entity = $entity->getTranslation($langCode);
            } elseif ($entity instanceof \Drupal\paragraphs\Entity\Paragraph && $entity->getEntityTypeId() === 'paragraph') {
                // In some cases a loaded Paragraph entity does not include the right entity for the given language,
                // you need to get the latest revision id for the given language and then you "should" be able to pull out the correct translated entity
                // I'm only adding this code for Paragraphs for now.
//                $paragraphStatus = TRUE;
//                try {
//                    $latest_revision_id = $this->entityTypeManager->getStorage('paragraph')->getLatestTranslationAffectedRevisionId($entity->id(), $langCode);
//                    $paragraphEntity = $this->entityTypeManager->getStorage('paragraph')->loadRevision($latest_revision_id);
//                } catch (\Exception $e) {
//                    $paragraphStatus = FALSE;
//                }
//
//                if ($paragraphStatus === TRUE && $paragraphEntity->hasTranslation($langCode)) {
//                    $tempEntity = $paragraphEntity->getTranslation($langCode);
//                    if ($tempEntity !== NULL && $tempEntity instanceof \Drupal\paragraphs\Entity\Paragraph) {
//                        $entity = $tempEntity;
//                    }
//                }
            }
        }

        return $entity;
    }

    public function loadEntityRevision($entityType, $revision_id) {
        $langCode = \Drupal::service('language_manager')->getCurrentLanguage()->getId();
        $entity = $this->entityTypeManager->getStorage($entityType)->loadRevision($revision_id);
        if ($entity !== NULL && $entity->hasTranslation($langCode)) {
            $tempEntity = $entity->getTranslation($langCode);
            if ($tempEntity !== NULL) {
                unset($entity);
                $entity = $tempEntity;
            }
        }

        return $entity;
    }

    public function loadMultipleEntities($entityType, array $ids) {
        try {
            $entities = $this->entityTypeManager->getStorage($entityType)->loadMultiple($ids);
        } catch (\Exception $e) {
            $entities = FALSE;
        }

        return $entities;
    }

    public function loadEntityByIdAndBundleType($entityType, $id, $bundle) {
        $load = $this->entityTypeManager->getStorage($entityType)->loadByProperties(['id' => $id, 'type' => [$bundle]]);

        // $load will be an array
        if (empty($load)) {
            return NULL;
        }

        $entity = reset($load);

        return $entity;
    }

    public function deleteEntity($entityType, $id) {
        $entity = $this->entityTypeManager->getStorage($entityType)->load($id);

        if ($entity === NULL) {
            return FALSE;
        }

        try {
            $entity->delete();
        } catch (\Exception $e) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $entity
     * @param $fieldMachineName
     *
     * @return bool
     */
    public function getFirstFieldValue($entity, $fieldMachineName) {
        // Make sure the field exists
        $fieldExists = $this->doesEntityHaveField($entity, $fieldMachineName);
        if ($fieldExists) {
            if($entity->get($fieldMachineName)->first()) {
                $value = $entity->get($fieldMachineName)->first()->getValue();
                return $value;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    public function getFieldStringValue($entity, $fieldMachineName) {
        // Make sure the field exists
        $fieldExists = $this->doesEntityHaveField($entity, $fieldMachineName);
        if ($fieldExists) {
            $value = $entity->get($fieldMachineName)->getString();
            return $value;
        } else {
            return FALSE;
        }
    }

    public function getFieldValue($entity, $fieldMachineName) {
        // Make sure the field exists
        $fieldExists = $this->doesEntityHaveField($entity, $fieldMachineName);
        if ($fieldExists) {
            $value = $entity->get($fieldMachineName)->getValue();
            return $value;
        } else {
            return FALSE;
        }
    }

    public function buildFileUrlFromTargetId($targetId) {
        // Load the File entity
        $file = $this->loadEntity('file', $targetId);
        if ($file === NULL || $file === FALSE) {
            return FALSE;
        }
        $uri = $file->getFileUri();

        $url = $this->buildUrlFromUri($uri);

        return $url;
    }

    public function buildUrlFromUri($uri) {
        return file_create_url($uri);
    }

    /**
     * @param $entity
     * @param $field_name
     * @param $langCode
     * @return bool (array, if the value is available)
     *
     * Returning a field with current translated value.
     */
    public function getTranslationFieldValue($entity, $field_name, $langCode = '') {
      if(!$langCode) {
        $langCode = \Drupal::service('language_manager')->getCurrentLanguage()->getId();
      }
      if ($entity->hasTranslation($langCode)) {
        $entity = $entity->getTranslation($langCode);
      }
      $fieldValue = $this->getFieldValue($entity, $field_name);
      return $fieldValue;
    }

    public function getAllBundlesForEntity($entityTypeId) {
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);
        return $bundles;
    }
}
