<?php

declare(strict_types=1);

namespace Drupal\hd_athena\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\hd_athena\AthenaInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the athena entity class.
 *
 * @ContentEntityType(
 *   id = "athena_ent",
 *   label = @Translation("Athena"),
 *   label_collection = @Translation("Athenas"),
 *   label_singular = @Translation("athena"),
 *   label_plural = @Translation("athenas"),
 *   label_count = @PluralTranslation(
 *     singular = "@count athenas",
 *     plural = "@count athenas",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\hd_athena\AthenaListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\hd_athena\AthenaAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\hd_athena\Form\AthenaForm",
 *       "edit" = "Drupal\hd_athena\Form\AthenaForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "athena_ent",
 *   admin_permission = "administer athena_ent",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/athena-ent",
 *     "add-form" = "/athena/add",
 *     "canonical" = "/athena/{athena_ent}",
 *     "edit-form" = "/athena/{athena_ent}/edit",
 *     "delete-form" = "/athena/{athena_ent}/delete",
 *     "delete-multiple-form" = "/admin/content/athena-ent/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.athena_ent.settings",
 * )
 */
final class Athena extends ContentEntityBase implements AthenaInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  public function isPublished(): bool {
    return (bool) $this->get('status')->value;
  }

  public function isNew(): bool {
    return !$this->get('id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_computed_route_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Route Name'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['paragraph_bundle'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Paragraph Bundle'))
      ->setRequired(FALSE)
      ->setSetting('allowed_values_function', 'hd_athena_get_paragraph_bundles')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -8,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['node_bundle'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Node Bundle'))
      ->setRequired(FALSE)
      ->setSetting('allowed_values_function', 'hd_athena_get_node_bundles')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -7,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Reference to a specific node.
    $fields['node_reference'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Node Reference'))
      ->setSetting('target_type', 'node')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Add an image field.
    $fields['field_image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setDescription(t('Screenshot of the athena.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'image',
        'weight' => -5,
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the athena was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the athena was last edited.'));

    return $fields;
  }

}
