<?php
namespace Drupal\hd_athena\Services;

use \Drupal\Core\Language\LanguageManagerInterface;
use \Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Link;
use Drupal\Core\Url;
use \Drupal\hd_athena\Services\RenderCoreHelper;
use \Drupal\taxonomy\TermStorage;
use Drupal\Core\Language\LanguageInterface;
use \Drupal\taxonomy\VocabularyForm;




class TaxonomyCoreHelper {

    /**
     * The Entity Core Helper service.
     *
     * @var \Drupal\hd_athena\Services\EntityCoreHelper
     */
    protected $entityCoreHelper;

    /**
     * The Language Manager service.
     *
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected $languageManager;

    /**
     * The Entity Repository service.
     *
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected $entityRepository;

    /**
     * The Render Core Helper service.
     *
     * @var \Drupal\hd_athena\Services\RenderCoreHelper
     */
    protected $renderCoreHelper;

    public function __construct(EntityCoreHelper $entity_core_helper, LanguageManagerInterface $language_manager, EntityRepository $entity_repository, RenderCoreHelper $render_core_helper) {
        $this->entityCoreHelper = $entity_core_helper;
        $this->languageManager = $language_manager;
        $this->entityRepository = $entity_repository;
        $this->renderCoreHelper = $render_core_helper;
    }

    /**
     * @return \Drupal\hd_athena\Services\EntityCoreHelper
     */
    public function entityCoreHelper() {
        return $this->entityCoreHelper;
    }

    /**
     * @param $tid
     * @return bool|\Drupal\Core\Entity\EntityInterface|null
     */
    public function getTaxonomyTermData($tid) {
        $language = $this->languageManager->getCurrentLanguage()->getId();
        try {
            $termData = $this->entityCoreHelper()->loadEntity('taxonomy_term', $tid);
            if($termData !== NULL && $termData->hasTranslation($language)){
                $translatedTerm = $this->entityRepository->getTranslationFromContext($termData, $language);
                $termData = $translatedTerm;
            }
        } catch (\Exception $exception) {
            $termData = FALSE;
        }

        if ($termData == NULL) {
            $termData = FALSE;
        }

        return $termData;
    }

    public function getTaxonomyTermNameFromTid($tid) {
        $term = $this->entityCoreHelper()->loadEntity('taxonomy_term', $tid);
        if ($term === NULL) {
            return FALSE;
        }
        $label = $term->label();

        return $label;
    }

    public function getTaxonomyTermFieldValue($tid, $fieldName) {

        $termData = $this->getTaxonomyTermData($tid);

        if ($termData == FALSE) {
            return FALSE;
        }

        $value = $termData->get($fieldName)->getValue();

        if ($value == NULL) {
            return FALSE;
        }

        return $value;
    }

    public function getTaxonomyTermFieldValueFirst($tid, $fieldName) {

        $termData = $this->getTaxonomyTermData($tid);

        if ($termData == FALSE) {
            return FALSE;
        }

        $value = $termData->get($fieldName)->getValue();

        if ($value == NULL) {
            return FALSE;
        }

        if (isset($value['0']['value'])) {
            return $value['0']['value'];
        }

        if (isset($value['0']['target_id'])) {
            return $value['0']['target_id'];
        }

        return $value;
    }

    public function getTestimonialsData($tid) {
        $taxonomyEntity = $this->getTaxonomyTermData($tid);
        if ($taxonomyEntity === FALSE) {
            return FALSE;
        }
        $data = [];

        // Person name
        $data['name'] = $taxonomyEntity->label();
        // Full Width - Image: field_media_image/field_webdam_single
        if(!empty($taxonomyEntity->field_media_image->getValue())) {
            $fullWidthImageEntityId = $taxonomyEntity->get('field_media_image')->getValue();
            $fullWidthImageEntityId = $fullWidthImageEntityId['0']['target_id'];
            $data['full_width_image_url'] = $this->renderCoreHelper->getMediaImageUrls($fullWidthImageEntityId)['relative'];
        }
        elseif(!empty($taxonomyEntity->field_webdam_single->getValue())) {
            $fullWidthImageEntityId = $taxonomyEntity->get('field_webdam_single')->getValue();
            $fullWidthImageEntityId = $fullWidthImageEntityId['0']['target_id'];
            $data['full_width_image_url'] = $this->renderCoreHelper->getWebDamImageUrl($fullWidthImageEntityId);
        }

        // Half Width - Image: field_media_image_2/field_webdam_single_two
        if(!empty($taxonomyEntity->field_media_image_2->getValue())) {
            $halfWidthImageEntityId = $taxonomyEntity->get('field_media_image_2')->getValue();
            $halfWidthImageEntityId = $halfWidthImageEntityId['0']['target_id'];
            $data['half_width_image_url'] = $this->renderCoreHelper->getMediaImageUrls($halfWidthImageEntityId)['relative'];
        }
        elseif(!empty($taxonomyEntity->field_webdam_single_two->getValue())) {
            $halfWidthImageEntityId = $taxonomyEntity->get('field_webdam_single_two')->getValue();
            $halfWidthImageEntityId = $halfWidthImageEntityId['0']['target_id'];
            $data['half_width_image_url'] = $this->renderCoreHelper->getWebDamImageUrl($halfWidthImageEntityId);
        }

        // Portrait - Image: field_media_image_3/field_webdam_portrait
        if($taxonomyEntity->hasField('field_media_image_3')) {
            if (!empty($taxonomyEntity->field_media_image_3->getValue())) {
                $portraitImageEntityId = $taxonomyEntity->get('field_media_image_3')->getValue();
                $portraitImageEntityId = $portraitImageEntityId['0']['target_id'];
                $data['portrait_image_url'] = $this->renderCoreHelper->renderMediaImageUsingImageStyle($portraitImageEntityId, 'portrait');
            }
        }
        elseif($taxonomyEntity->hasField('field_webdam_portrait')) {
            if (!empty($taxonomyEntity->field_webdam_portrait->getValue())) {
                $portraitImageEntityId = $taxonomyEntity->get('field_webdam_portrait')->getValue();
                $portraitImageEntityId = $portraitImageEntityId['0']['target_id'];
                $data['portrait_image_url'] = $this->renderCoreHelper->renderWebdamAssetUsingImageStyle($portraitImageEntityId, 'portrait');
            }
        }

        // Organization
        $data['organization_image_url'] = FALSE;
        $organizationNodeId = $taxonomyEntity->get('field_content_ref_organization')->getValue();
        if(isset($organizationNodeId['0']['target_id'])) {
            $organizationNodeId = $organizationNodeId['0']['target_id'];
            $organizationEntity = $this->entityCoreHelper->loadEntity('node', $organizationNodeId);
            $data['organization_name'] = $organizationEntity->getTitle();
            if ($organizationEntity !== NULL) {
                // Get the logo
                $logoId = $this->entityCoreHelper->getFieldValue($organizationEntity, 'field_image');
                if (isset($logoId['0']['target_id'])) {
                  $logoId = $logoId['0']['target_id'];
                  $logoUrls = $this->renderCoreHelper->getImageUrls($logoId);
                  $data['organization_image_url'] = $logoUrls['relative'];
                }
                // Get the small logo (black and white)
                $smallLogoId = $this->entityCoreHelper->getFieldValue($organizationEntity, 'field_image_two');
                if (isset($smallLogoId['0']['target_id'])) {
                    $smallLogoId = $smallLogoId['0']['target_id'];
                    $smallLogoUrls = $this->renderCoreHelper->getImageUrls($smallLogoId);
                    $data['organization_small_image_url'] = $smallLogoUrls['relative'];
                }
            }
        }

        // Link field - field_link
        if($taxonomyEntity->hasField('field_link')) {
            if (!empty($taxonomyEntity->field_link->getValue())) {
                $linkObject = $taxonomyEntity->get('field_link')->getValue();
                $link_options = array(
                    'attributes' => array(
                        'class' => array(
                            'arrow-link',
                        ),
                    ),
                );
                $testimonial_link = $this->renderCoreHelper->createLinkArrayFromTextUrl($linkObject[0]['title'], Url::fromUri($linkObject[0]['uri'], $link_options));
                $data['testimonial_link'] = $testimonial_link;
            }
        }

        // Role
        $roleTid = $taxonomyEntity->get('field_taxo_job_title')->getValue();
        $roleTid = $roleTid['0']['target_id'];
        $roleTermData = $this->getTaxonomyTermData($roleTid);
        if($roleTermData) {
            $data['role'] = $roleTermData->label();
        }

        // Testimonial
        $testimonial = $taxonomyEntity->get('field_text_formatted_long_single')->getValue();
        $data['testimonial'] = $testimonial['0']['value'];

        return $data;
    }

    public function getTaxonomyTermsForVocabulary($vocabularyId) {
        $terms = [];
        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', $vocabularyId);
        $query->sort('weight');
        $tids = $query->execute();

        foreach ($tids as $tid) {
            $terms[$tid] = $this->getTaxonomyTermNameFromTid($tid);
        }

        return $terms;
    }
    public function getTaxonomyTermTreeForVocabulary($vocabularyId) {
        $term_tree_term_array = [];
        $curr_lang_code = \Drupal::service('language_manager')->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
        $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term');
        $term_tree = $terms->loadTree($vocabularyId);
        foreach ($term_tree as $term) {
            // Get the last level children
            $child_array = [];
            if($term->depth > 0) {
                $children = $terms->loadChildren($term->tid);
                foreach($children as $child) {
                    $child = \Drupal::service('entity.repository')->getTranslationFromContext($child, $curr_langcode);
                    $child_array[$child->id()] = array(
                        'name' => $child->getName(),
                        'tid' => $child->id(),
                    );
                }
            }
            // Get the top level items to start the array
            if($term->depth == 0) {
                $term_object = $terms->load($term->tid);
                $term_object = \Drupal::service('entity.repository')->getTranslationFromContext($term_object, $curr_lang_code);
                $term_tree_term_array[$term->tid] = array(
                    'name' => $term_object->getName(),
                    'tid' => $term->tid,
                    'depth' => $term->depth,
                    'parent' => $term->parents[0],
                    'children' => array(),
                );
            }
            // After the top level items have been added to the array, add the children
            if($term->depth > 0) {
                $tax_object = $terms->load($term->tid);
                $tax_object = \Drupal::service('entity.repository')->getTranslationFromContext($tax_object, $curr_lang_code);
                $term_tree_term_array[$term->parents[0]]['children'][$term->tid] = array(
                    'name' => $tax_object->getName(),
                    'tid' => $term->tid,
                    'depth' => $term->depth,
                    'parent' => $term->parents[0],
                    'children' => $child_array,
                );
            }
        }
        return $term_tree_term_array;
    }

    // This will return an array of full terms $tid => $term object
    public function getFullTaxonomyTermsForVocabulary($vocabularyId) {
        $terms = [];
        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', $vocabularyId);
        $query->sort('weight');
        $tids = $query->execute();

        foreach ($tids as $tid) {
            $term = $this->getTaxonomyTermData($tid);

            if ($term !== FALSE) {
                $terms[$tid] = $term;
            }

            unset($term);
        }

        return $terms;
    }

    public function getTidFromTermName($termName, $vocabularyId = FALSE) {
        // Get taxonomy term storage.
        $taxonomyStorage = $this->entityCoreHelper->entityTypeManager->getStorage('taxonomy_term');
        // Set name properties.
        $properties['name'] = $termName;
        // Set vocabulary if not false.
        if ($vocabularyId !== FALSE)
            $properties['vid'] = $vocabularyId;

        // Load taxonomy term by properties.
        $terms = $taxonomyStorage->loadByProperties($properties);
        if (empty($terms)) {
            return FALSE;
        }

        $term = reset($terms);
        if ($term instanceof \Drupal\taxonomy\Entity\Term) {
            $termId = (int) $term->id();
            return $termId;
        } else {
            return FALSE;
        }
    }

    // This will return a Term entity
    public function addTaxonomyTermIfItDoesNotExist($termName = FALSE, $vocabularyId = FALSE) {
        if ($termName === FALSE || $vocabularyId === FALSE) {
            return FALSE;
        }

        // Make sure the vocabulary exits
        $vocabularyExists = $this->doesTaxonomyVocabularyExist($vocabularyId);
        if ($vocabularyExists === NULL) {
            return FALSE;
        }

        $vocabularTerms = $this->getTaxonomyTermsForVocabulary($vocabularyId);
        $term = FALSE;

        if (!empty($vocabularTerms)) {
            $tempTermName = strtolower(trim($termName));
            foreach ($vocabularTerms as $key => $value) {
                $tempValue = strtolower(trim($value));
                if ($tempTermName === $tempValue) {
                    $term = $this->getTaxonomyTermData($key);
                    break;
                }
                unset($tempValue);
            }
        }

        // See if we need to add the term
        if ($term === FALSE) {
            $termEntity = $this->addTaxonomyTerm($termName, $vocabularyId);
            if ($termEntity !== FALSE) {
                $term = $termEntity;
            }
        }

        return $term;
    }

    public function addTaxonomyTerm($termName = FALSE, $vocabularyId = FALSE, $langcode = 'en') {
        if ($termName === FALSE || $vocabularyId === FALSE) {
            return FALSE;
        }

        $vocabularyExists = $this->doesTaxonomyVocabularyExist($vocabularyId);
        if ($vocabularyExists === FALSE) {
            return FALSE;
        }

        $term = [
            'name'     => $termName,
            'vid'      => $vocabularyId,
            'langcode' => $langcode,
        ];

        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->create($term);
        $term->save();

        return $term;
    }

    public function doesTaxonomyVocabularyExist($vocabularyId) {
        $vocabularyExists = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vocabularyId);
        if ($vocabularyExists === NULL) {
            return FALSE;
        }

        return TRUE;
    }
}
