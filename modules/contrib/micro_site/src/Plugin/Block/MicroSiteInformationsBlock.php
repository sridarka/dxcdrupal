<?php

namespace Drupal\micro_site\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Template\Attribute;
use Drupal\micro_site\Entity\SiteInterface;
use Drupal\micro_site\SiteNegotiatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides a 'Micro Site Informations' block.
 *
 * @Block(
 *  id = "micro_site_informations",
 *  admin_label = @Translation("Micro Site Informations block"),
 *  category = @Translation("Micro Site"),
 * )
 */
class MicroSiteInformationsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\micro_site\SiteNegotiatorInterface definition.
   *
   * @var \Drupal\micro_site\SiteNegotiatorInterface
   */
  protected $siteNegotiator;

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new SubscribeOnBlock object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param $plugin_id
   *   The plugin id.
   * @param $plugin_definition
   *   The plugin definition.
   * @param \Drupal\micro_site\SiteNegotiatorInterface $site_negotiator
   *   The site negotiator.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SiteNegotiatorInterface $site_negotiator, CurrentRouteMatch $current_route_match, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->siteNegotiator = $site_negotiator;
    $this->currentRouteMatch = $current_route_match;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('micro_site.negotiator'),
      $container->get('current_route_match'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'display_active_site' => '',
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['display_active_site'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display active site'),
      '#description' => $this->t('Check this option to display the active site.'),
      '#default_value' => $this->configuration['display_active_site'],
      '#weight' => 10,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['display_active_site'] = $form_state->getValue('display_active_site');
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermissions($account, ['view micro site informations'], 'OR');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $entity = $this->getCurrentEntity();
    if (!$entity instanceof ContentEntityInterface) {
      return $build;
    }
    $display_active_site = $this->configuration['display_active_site'];
    $active_site = $this->siteNegotiator->getActiveSite();
    $entity_site = $this->getMainSite($entity);
    $entity_sites = $this->getOthersSite($entity);
    $entity_sites_all = $this->isOnAllSites($entity);
    $entity_sites_all_label = $entity_sites_all ? $this->t('Yes (master included)') : '';

    $attributes = new Attribute([
      'class' => [
        'micro-site-informations',
      ]
    ]);

    $build['micro_site_informations'] = [
      '#theme' => 'micro_site_informations',
      '#attributes' => $attributes,
      '#entity' => $entity,
      '#display_active_site' => $display_active_site,
      '#active_site' => $active_site,
      '#entity_site' => $entity_site,
      '#entity_sites' => $entity_sites,
      '#entity_sites_all' => $entity_sites_all,
      '#entity_sites_all_label' => $entity_sites_all_label,
    ];

    $entity_include_master = $this->isIncludedOnMaster($entity);
    if (!is_null($entity_include_master) && $entity_sites_all) {
      $build['micro_site_informations']['#entity_include_master'] = $entity_include_master;
      $entity_sites_all_label = $entity_include_master ? $this->t('Yes (master included)') : $this->t('Yes (master excluded)');
      $build['micro_site_informations']['#entity_sites_all_label'] = $entity_sites_all_label;
    }

    return $build;
  }

  /**
   * Get the current entity from the route.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   */
  protected function getCurrentEntity() {
    $entity = NULL;
    $keys = $this->currentRouteMatch->getParameters()->keys();
    $entity_type_id = isset($keys[0]) ? $keys[0] : '';
    if (empty($entity_type_id)) {
      return $entity;
    }

    $entity = $this->currentRouteMatch->getParameter($entity_type_id);
    return $entity;
  }

  /**
   * Get the main site of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return \Drupal\micro_site\Entity\SiteInterface|null
   */
  protected function getMainSite(ContentEntityInterface $entity) {
    $site = NULL;
    if ($entity->hasField('site_id') && !$entity->get('site_id')->isEmpty()) {
      $sites = $entity->get('site_id')->referencedEntities();
      $site = reset($sites);
    }
    return $site;
  }

  /**
   * Get the secondary sites of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return \Drupal\micro_site\Entity\SiteInterface[]|[]
   */
  protected function getOthersSite(ContentEntityInterface $entity) {
    $sites = [];
    if ($entity->hasField('field_sites') && !$entity->get('field_sites')->isEmpty()) {
      $sites = $entity->get('field_sites')->referencedEntities();
    }
    return $sites;
  }

  /**
   * Get the sites all option of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return bool
   *   TRUE if the site is published on all micro sites. Otherwise FALSE.
   */
  protected function isOnAllSites(ContentEntityInterface $entity) {
    $all = FALSE;
    if ($entity->hasField('field_sites_all') && !$entity->get('field_sites_all')->isEmpty()) {
      $all = (bool) $entity->field_sites_all->value;
    }
    elseif ($entity->hasField('site_all') && !$entity->get('site_all')->isEmpty()) {
      $all = (bool) $entity->site_all->value;
    }
    return $all;
  }

  /**
   * Get the include master option of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return bool|null
   *   TRUE|FALSE if the entity has the option. Otherwise NULL.
   */
  protected function isIncludedOnMaster(ContentEntityInterface $entity) {
    $include_master = NULL;
    if (!$entity->hasField('include_master')) {
      return $include_master;
    }
    $include_master = (bool) $entity->include_master->value;
    return $include_master;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    $entity = $this->getCurrentEntity();
    if (!$entity instanceof ContentEntityInterface) {
      return $tags;
    }
    $tags = Cache::mergeTags($tags, $entity->getCacheTags());

    $entity_site = $this->getMainSite($entity);
    if ($entity_site instanceof SiteInterface) {
      $tags = Cache::mergeTags($tags, $entity_site->getCacheTags());
    }

    $entity_sites = $this->getOthersSite($entity);
    foreach ($entity_sites as $site) {
      if ($site instanceof SiteInterface) {
        $tags = Cache::mergeTags($tags, $site->getCacheTags());
      }
    }
    return $tags;
  }

  public function getCacheContexts() {
    $cache_contexts = parent::getCacheContexts();
    $cache_contexts = Cache::mergeContexts($cache_contexts, ['url.site', 'url']);
    return $cache_contexts;
  }

}
