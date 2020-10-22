<?php

namespace Drupal\Tests\micro_site\Traits;

/**
 * Contains helper classes for tests to set up various configuration.
 */
trait MicroSiteTestTrait {

  /**
   * Adds a test micro site to an entity.
   *
   * @param string $entity_type
   *   The entity type being acted upon.
   * @param int $entity_id
   *   The entity id.
   * @param array|string $ids
   *   An id or array of ids of micro site to add.
   * @param string $field
   *   The name of the micro site field used to attach to the entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addMicroSiteToEntity($entity_type, $entity_id, $ids, $field) {
    if ($entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
      $entity->set($field, $ids);
      $entity->save();
    }
  }

  /**
   * Returns a list of all micro sites.
   *
   * @return \Drupal\micro_site\Entity\SiteInterface[]
   *   An array of micro site entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMicroSites() {
    /** @var \Drupal\micro_site\SiteStorageInterface $site_storage */
    $site_storage = \Drupal::entityTypeManager()->getStorage('site');
    $site_storage->resetCache();
    return $site_storage->loadMultiple();
  }


}
