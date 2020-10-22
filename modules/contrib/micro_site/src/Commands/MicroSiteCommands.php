<?php

namespace Drupal\micro_site\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Site\Settings;
use Drush\Commands\DrushCommands;

/**
 * Micro Site drush commands.
 */
class MicroSiteCommands extends DrushCommands {

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Key-value store service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValue;

  /**
   * MicroSiteCommands constructor.
   *
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   *   Date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue
   *   Key-value store service.
   */
  public function __construct(DateFormatter $dateFormatter, EntityTypeManagerInterface $entityTypeManager, KeyValueFactoryInterface $keyValue) {
    parent::__construct();
    $this->dateFormatter = $dateFormatter;
    $this->entityTypeManager = $entityTypeManager;
    $this->keyValue = $keyValue;
  }

  /**
   * List all micro sites which can be updated according the settings.
   *
   * @param string $types
   *   Restrict to a comma-separated list of micro site types (Optional).
   * @param array $options
   *   Additional options for the command.
   *
   * @command micro-site:status
   *
   * @default $options []
   *
   * @usage micro-site:status
   *   Retrieve micro sites entites eligibles to be updated.
   *
   * @validate-module-enabled micro_site
   *
   * @aliases misis, micro-site-status
   *
   * @field-labels
   *   id: Micro Site ID
   *   type: Type
   *   site_url: Site URL
   *   https: HTTPS
   *   site_url_new: Site URL (new)
   *   https_new: HTTPS (new)
   * @default-fields id,type,site_url,https,site_url_new,https_new
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Micro sites eligibles as table.
   */
  public function status($types = '', array $options = []) {
    $settings = $this->getSettings();
    if(empty($settings)) {
      $this->logger()->error(
        dt('No settings "override_micro_site_url" found to override micro site URL')
      );
      return NULL;
    }

    $sites = $this->getMicroSites($types);
    if(empty($sites)) {
      $this->logger()->error(
        dt('No micro sites found to be updated.')
      );
      return NULL;
    }

    $micro_sites_processed = $this->keyValue->get('micro_site_override_url');
    $table = [];
    foreach ($sites as $site_id => $site) {
      $site_url_new = '--';
      $https_new = '--';
      $site_url = $site->getSiteUrl();
      $site_type = $site->bundle();
      if ($micro_sites_processed->get($site->id(), FALSE)) {
        $site_url_new = '--processed--';
        $https_new = '--processed--';
      }
      // Per site ID.
      elseif (isset($settings[$site->id()])) {
        $site_url_new = isset($settings[$site->id()]['site_url']) ? $settings[$site->id()]['site_url'] : '--';
        if (isset($settings[$site->id()]['https'])) {
          $https_new = $settings[$site->id()]['https'] ? t('Yes') : t('No');
        }
      }
      // Per site URL.
      elseif (isset($settings[$site_url])) {
        $site_url_new = isset($settings[$site_url]['site_url']) ? $settings[$site_url]['site_url'] : '--';
        if (isset($settings[$site_url]['https'])) {
          $https_new = $settings[$site_url]['https'] ? t('Yes') : t('No');
        }
      }
      // Per site type.
      elseif (isset($settings[$site_type])) {
        $url = $this->getSiteUrlNew($settings[$site_type], $site_type, $site_url);
        if (!empty($url)) {
          $site_url_new = $url;
        }
        if (isset($settings[$site_type]['https'])) {
          $https_new = $settings[$site_type]['https'] ? t('Yes') : t('No');
        }
      }
      // Global override.
      elseif (isset($settings['global'])) {
        $url = $this->getSiteUrlNew($settings['global'], $site_type, $site_url, TRUE);
        if (!empty($url)) {
          $site_url_new = $url;
        }
        if (isset($settings['global']['https'])) {
          $https_new = $settings['global']['https'] ? t('Yes') : t('No');
        }
      }

      $table[] = [
        'id' => $site->id(),
        'type' => $site->bundle(),
        'site_url' => $site->getSiteUrl(),
        'https' => $site->getSiteScheme() === 'https' ? t('Yes') : t('No'),
        'site_url_new' => $site_url_new,
        'https_new' => $https_new,
      ];
    }

    return new RowsOfFields($table);
  }

  /**
   * Perform a micro site update process.
   *
   * @param string $types
   *   site types to process. Delimit multiple using commas.
   * @param array $options
   *   Additional options for the command.
   *
   * @command micro-site:update
   *
   * @default $options []
   *
   * @usage micro-site:update
   *   Perform all micro sites. Please check before which micro sites will
   *   be processed.
   * @usage micro-site:update generic,one_page
   *   Process Micro Site of type generic and one_page.
   *
   * @validate-module-enabled micro_site
   *
   * @aliases misiup, micro-site-update
   *
   * @throws \Exception
   *   If there are not enough parameters to the command.
   */
  public function update($types = '', array $options = []) {
    $settings = $this->getSettings();
    if(empty($settings)) {
      $this->logger()->error(
        dt('No settings found to override the micro sites URL')
      );
      return NULL;
    }

    $sites = $this->getMicroSites($types);
    if(empty($sites)) {
      $this->logger()->error(
        dt('No micro sites found to be updated.')
      );
      return NULL;
    }
    $count = 0;
    $micro_sites_processed = $this->keyValue->get('micro_site_override_url');
    foreach ($sites as $site_id => $site) {
      $site_url_new = NULL;
      $https_new = NULL;
      $site_url = $site->getSiteUrl();
      $site_type = $site->bundle();
      if ($micro_sites_processed->get($site->id(), FALSE)) {
        $this->logger()->notice(
          dt('Micro Site @name (@site_url) already processed. Skipping.', ['@name' => $site->label(), '@site_url' => $site_url])
        );
        continue;
      }

      // Per site ID.
      if (isset($settings[$site->id()])) {
        $site_url_new = isset($settings[$site->id()]['site_url']) ? $settings[$site->id()]['site_url'] : NULL;
        if (isset($settings[$site->id()]['https'])) {
          $https_new = (bool) $settings[$site->id()]['https'] ;
        }
      }
      // Per site URL.
      if (isset($settings[$site_url])) {
        $site_url_new = isset($settings[$site_url]['site_url']) ? $settings[$site_url]['site_url'] : NULL;
        if (isset($settings[$site_url]['https'])) {
          $https_new = (bool) $settings[$site_url]['https'] ;
        }
      }
      // Per site type.
      elseif (isset($settings[$site_type])) {
        $url = $this->getSiteUrlNew($settings[$site_type], $site_type, $site_url);
        if (!empty($url)) {
          $site_url_new = $url;
        }
        if (isset($settings[$site_type]['https'])) {
          $https_new = (bool) $settings[$site_type]['https'];
        }
      }
      // Global override.
      elseif (isset($settings['global'])) {
        $url = $this->getSiteUrlNew($settings['global'], $site_type, $site_url, TRUE);
        if (!empty($url)) {
          $site_url_new = $url;
        }
        if (isset($settings['global']['https'])) {
          $https_new = (bool) $settings['global']['https'];
        }
      }

      if ($site_url_new || $https_new !== NULL) {
        try {
          if ($https_new !== NULL) {
            $site->set('site_scheme', $https_new);
          }
          if ($site_url_new) {
            $site->set('site_url', $site_url_new);
          }
          $site->save();
          $count++;
          $micro_sites_processed->set($site->id(), TRUE);
          $this->logger()->notice(
            dt(
              'Processing micro site @name (ID:@id, URL:@site_url) with new URL @site_url_new',
              ['@name' => $site->label(), '@id' => $site->id(), '@site_url' => $site_url, '@site_url_new' => $site_url_new]
            )
          );
        }
        catch (\Exception $e) {
          $this->logger()->error(
            dt(
              'Failure when saving micro site @id (@site_url) with message: @message',
              ['@id' => $site->id(), '@site_url' => $site_url, '@message' => $e->getMessage()]
            )
          );
          continue;
        }
      }
    }
    return dt('@count micro sites processed.', ['@count' => $count]);
  }

  /**
   * Perform a micro site reset status.
   *
   * @param string $types
   *   Site types to process. Delimit multiple using commas.
   * @param array $options
   *   Additional options for the command.
   *
   * @command micro-site:reset
   *
   * @option ids Site IDs to process. Delimit multiple using commas.
   *
   * @default $options []
   *
   * @usage micro-site:reset
   *   Reset status of all micro sites.
   * @usage micro-site:reset generic,one_page
   *   Reset status of all micro sites of type generic and one_page.
   * @usage micro-site:reset --ids=1,2,3
   *   Reset status of all micro sites with IDs 1, 2 or 3.
   *
   * @validate-module-enabled micro_site
   *
   * @aliases misire, micro-site-reset
   *
   * @throws \Exception
   *   If there are not enough parameters to the command.
   */
  public function reset($types = '', array $options = ['ids' => NULL]) {
    $ids = [];
    if (!empty($options['ids'])) {
      $ids = explode(',', $options['ids']);
    }

    $micro_sites_processed = $this->keyValue->get('micro_site_override_url');
    if (empty($types) && empty($ids)) {
      $micro_sites_processed->deleteAll();
      return dt('All micro sites status reset.');
    }

    $sites = $this->getMicroSites($types, $ids);
    if(empty($sites)) {
      $this->logger()->error(
        dt('No micro sites found to be reset.')
      );
      return NULL;
    }
    $count = 0;
    foreach ($sites as $site_id => $site) {
      if (!$micro_sites_processed->get($site->id())) {
        $this->logger()->notice(
          dt(
            'No processed status found for Micro Site @name (ID:@id, URL:@site_url). Skipping',
            ['@name' => $site->label(), '@id' => $site->id(), '@site_url' => $site->getSiteUrl()]
          )
        );
      }
      else {
        $micro_sites_processed->delete($site->id());
        $this->logger()->notice(
          dt(
            'Resetting status of micro site @name (ID:@id, URL:@site_url)',
            ['@name' => $site->label(), '@id' => $site->id(), '@site_url' => $site->getSiteUrl()]
          )
        );
        $count++;
      }
    }
    return dt('@count micro sites status reset.', ['@count' => $count]);
  }

  /**
   * Get the micro site settings.
   *
   * @return array
   *   An array keyed with a micro site URL and with values the new site URL, and
   *   if the micro site must use HTTPS.
   */
  protected function getSettings() {
    return Settings::get('micro_site_override_url');
  }

  /**
   * Get all the micro sites.
   *
   * @param string $types
   *   A comma separated list of site type.
   * @param array $ids
   *   An array of micro site ID.
   *
   * @return \Drupal\micro_site\Entity\SiteInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getMicroSites($types = '', array $ids = []) {
    if (empty($types)) {
      if (!empty($ids)) {
        $sites = $this->entityTypeManager->getStorage('site')->loadMultiple($ids);
      }
      else {
        $sites = $this->entityTypeManager->getStorage('site')->loadMultiple();
      }
    }
    else {
      $types = explode(',', $types);
      $values = [];
      $values['type'] = $types;
      if (!empty($ids)) {
        $values['id'] = $ids;
      }
      $sites = $this->entityTypeManager->getStorage('site')->loadByProperties($values);
    }
    return $sites;
  }

  protected function getSiteUrlNew(array $settings, $site_type, $site_url, $global = FALSE) {
    $site_url_new = NULL;
    $available_methods = [
      'prefix',
      'subdomain',
      'tld',
    ];
    $valid_parameters = TRUE;
    if ($global) {
      $site_type = 'global';
    }
    if (empty($settings['method'])) {
      $this->logger()->error(
        dt('No method entry found in @setting[\'@site_type\']', ['@site_type' => $site_type])
      );
      $valid_parameters = FALSE;
    }
    if (empty($settings['pattern'])) {
      $this->logger()->error(
        dt('No pattern entry found in $setting[\'@site_type\']', ['@site_type' => $site_type])
      );
      $valid_parameters = FALSE;
    }
    if (!empty($settings['method'])) {
      $method = $settings['method'];
      if (!in_array($method, $available_methods)) {
        $this->logger()->error(
          dt('Method entry found in $setting[\'@site_type\'] not valid. Method must be "prefix", "subdomain" or "tld"', ['@site_type' => $site_type])
        );
        $valid_parameters = FALSE;
      }
    }
    if ($valid_parameters) {
      $method = $settings['method'];
      switch ($method) {
        case 'prefix':
          $site_url_new = $settings['pattern'] . '-' . $site_url;
          break;
        case 'subdomain':
          $site_url_parts = explode('.', $site_url, 3);
          $site_url_parts = array_reverse($site_url_parts);
          $site_url_parts[2] = $settings['pattern'];
          $site_url_parts = array_reverse($site_url_parts);
          $site_url_new =implode('.', $site_url_parts);
          break;
        case 'tld':
          $site_url_parts = explode('.', $site_url);
          $site_url_parts = array_reverse($site_url_parts);
          $site_url_parts[0] = $settings['pattern'];
          $site_url_parts = array_reverse($site_url_parts);
          $site_url_new =implode('.', $site_url_parts);
          break;
      }
    }
    return $site_url_new;
  }

}
