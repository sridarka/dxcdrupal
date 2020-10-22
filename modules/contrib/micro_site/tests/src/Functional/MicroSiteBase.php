<?php

namespace Drupal\Tests\micro_site\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\micro_site\Traits\MicroSiteTestTrait;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group micro_site
 */
abstract class MicroSiteBase extends BrowserTestBase {

  use MicroSiteTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'micro_site'
  ];

  /**
   * We use the standard profile for testing.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * A user with permission to administer micro site.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $globalAdminUser;

  /**
   * A user with permission to administer own microsite.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $microSiteAdminUser;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\micro_site\SiteStorageInterface definition.
   *
   * @var \Drupal\micro_site\SiteStorageInterface
   */
  protected $siteStorage;

  /**
   * The base URL used for micro site based on a sub domain.
   *
   * @var string
   */
  protected $baseUrl;

  /**
   * The base Scheme of the micro site master host.
   *
   * @var string
   */
  protected $baseScheme;

  /**
   * The public URL of the micro site master host.
   *
   * @var string
   */
  protected $publicUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->configFactory = $this->container->get('config.factory');
    $this->siteStorage = $this->entityTypeManager->getStorage('site');
    $this->globalAdminUser = $this->drupalCreateUser($this->getGlobalAdminPermissions());
    $this->microSiteAdminUser = $this->drupalCreateUser($this->getMicroSiteAdminPermissions());
    $this->baseUrl = 'microsite.local';
    $this->baseScheme = 'http';
    $this->publicUrl = 'www.microsite.local';
  }

  /**
   * Generates site entities for testing.
   *
   * In my environment, I use the microsite.local hostname as a base. Then I name
   * hostnames one.* two.* up to five.
   *
   * The script may also add test6.local, test7.local, test8.local up to any
   * number to test a large number of micro sites.
   *
   * @param string $type
   *   The micro site type.
   * @param int $count
   *   The number of micro site to create.
   * @param string $type_url
   *   The type of the URL.
   * @param string|null $base_url
   *   The base url to use for micro site creation (e.g. microsite.local).
   * @param array $list
   *   An optional list of sub-domains to apply instead of the default set.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createMicroSites($type, $count = 1, $type_url = 'domain', $base_url = NULL, array $list = []) {
    if (empty($base_url)) {
      $base_url = $this->baseUrl;
    }
    // Note: these micro sites are rigged to work on my test server.
    // For proper testing, yours should be set up similarly, but you can pass a
    // $list array to change the default.
    if (empty($list)) {
      $list = [
        'one',
        'two',
        'three',
        'four',
        'five',
      ];
    }
    for ($i = 0; $i < $count; $i++) {
      if (!empty($list[$i])) {
        // Currently we only support the domain option. So we build the same URL
        // whatever the type url is set. We could later test also sub-domain VS
        // domain (and maybe VS path) based micro sites.
        if ($type_url == 'subdomain') {
          $site_url = $list[$i] . '.' . $base_url;
        }
        elseif ($type_url == 'domain') {
          $site_url = $list[$i] . '.' . $base_url;
        }
        else {
          $site_url = $list[$i] . '.' . $base_url;
        }
        $site_mail = $list[$i] . '@' . $base_url;
        $name = 'Micro Site ' . ucfirst($list[$i]);
      }
      else {
        $site_url = 'test' . $i . '.local';
        $site_mail = 'mail@test' . $i . '.local';
        $name = 'Micro Site Test ' . $i;
      }

      $values = [
        'type' => $type,
        'site_url' => $site_url,
        'mail' => $site_mail,
        'site_scheme' => FALSE,
        'name' => $name,
      ];
      $micro_site = \Drupal::entityTypeManager()->getStorage('site')->create($values);
      $micro_site->save();
    }
  }

  /**
   * Gets the permissions for the global administrator.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getGlobalAdminPermissions() {
    return [
      'administer site entities',
      'administer micro site settings',
    ];
  }

  /**
   * Gets the permissions for a micro site administrator.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getMicroSiteAdminPermissions() {
    return [
      'administer own site entity',
    ];
  }

  /**
   * Finds link with specified locator.
   *
   * @param string $locator
   *   Link id, title, text or image alt.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The link node element.
   */
  public function findLink($locator) {
    return $this->getSession()->getPage()->findLink($locator);
  }

  /**
   * Confirms absence of link with specified locator.
   *
   * @param string $locator
   *   Link id, title, text or image alt.
   *
   * @return bool
   *   TRUE if link is absent, or FALSE.
   */
  public function findNoLink($locator) {
    return empty($this->getSession()->getPage()->hasLink($locator));
  }

  /**
   * Finds field (input, textarea, select) with specified locator.
   *
   * @param string $locator
   *   Input id, name or label.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The input field element.
   */
  public function findField($locator) {
    return $this->getSession()->getPage()->findField($locator);
  }

  /**
   * Finds button with specified locator.
   *
   * @param string $locator
   *   Button id, value or alt.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The button node element.
   */
  public function findButton($locator) {
    return $this->getSession()->getPage()->findButton($locator);
  }

  /**
   * Presses button with specified locator.
   *
   * @param string $locator
   *   Button id, value or alt.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function pressButton($locator) {
    $this->getSession()->getPage()->pressButton($locator);
  }

  /**
   * Fills in field (input, textarea, select) with specified locator.
   *
   * @param string $locator
   *   Input id, name or label.
   * @param string $value
   *   Value.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *
   * @see \Behat\Mink\Element\NodeElement::setValue
   */
  public function fillField($locator, $value) {
    $this->getSession()->getPage()->fillField($locator, $value);
  }

  /**
   * Checks checkbox with specified locator.
   *
   * @param string $locator
   *   An input id, name or label.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function checkField($locator) {
    $this->getSession()->getPage()->checkField($locator);
  }

  /**
   * Unchecks checkbox with specified locator.
   *
   * @param string $locator
   *   An input id, name or label.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function uncheckField($locator) {
    $this->getSession()->getPage()->uncheckField($locator);
  }

  /**
   * Selects option from select field with specified locator.
   *
   * @param string $locator
   *   An input id, name or label.
   * @param string $value
   *   The option value.
   * @param bool $multiple
   *   Whether to select multiple options.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *
   * @see NodeElement::selectOption
   */
  public function selectFieldOption($locator, $value, $multiple = FALSE) {
    $this->getSession()->getPage()->selectFieldOption($locator, $value, $multiple);
  }

}
