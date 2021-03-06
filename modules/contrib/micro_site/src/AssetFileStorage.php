<?php

namespace Drupal\micro_site;

use Drupal\micro_site\Entity\SiteInterface;

/**
 * Class AssetFileStorage.
 *
 * @package Drupal\micro_site
 *
 * This asset file storage class implements a content-addressed file system
 * where each file is stored in a location like so:
 * public://micro_site_asset/[extension]/[name]-[md5].[extension]
 * Note that the name and extension-dir are redundant and purely for DX.
 *
 * Due to the nature of the config override system, the content of any asset
 * config entity can vary on external factory beyond our control, be it
 * language, domain, settings.php overrides or anything else. In other words,
 * any asset entity can map to an arbitrary number of actual assets.
 * Thus asset files are generated in AssetFileStorage::internalFileUri()
 * with a file name that is unique by their content, and only deleted on cache
 * flush.
 *
 * Class inspired from asset_injector module.
 *
 * Also see comments on caching in @see micro_site_page_attachments().
 */
abstract class AssetFileStorage {

  /**
   * The site entity.
   *
   * @var \Drupal\micro_site\Entity\SiteInterface
   */
  protected $site;

  /**
   * AssetFileStorage constructor.
   *
   * @param SiteInterface $site
   *   The site entity.
   */
  public function __construct(SiteInterface $site) {
    $this->site = $site;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function extension();

  /**
   * Create file and return internal uri.
   *
   * @return string
   *   Internal file URI using public:// stream wrapper.
   */
  public function createFile() {
    $internal_uri = self::internalFileUri();
    if (!is_file($internal_uri)) {
      $directory = dirname($internal_uri);
      file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
      file_unmanaged_save_data($this->site->getCss(), $internal_uri, FILE_EXISTS_REPLACE);
    }
    return $internal_uri;
  }

  /**
   * Update file and return internal uri.
   *
   * @return string
   *   Internal file URI using public:// stream wrapper.
   */
  public function updateFile() {
    $internal_uri = $this->createFile();
    file_unmanaged_save_data($this->site->getCss(), $internal_uri, FILE_EXISTS_REPLACE);
    return $internal_uri;
  }

  /**
   * Delete files for an asset.
   *
   * Yes, we can have multiple files for an asset configuration, if we have
   * overrides.
   */
  public function deleteFiles() {
    $pattern = $this->internalFileUri(TRUE);
    $paths = glob($pattern);
    foreach ($paths as $path) {
      file_unmanaged_delete($path);
    }
  }

  /**
   * Create internal file URI or pattern.
   *
   * @param bool $pattern
   *   Get Pattern instead of internal file URI.
   *
   * @return string
   *   File uri.
   */
  protected function internalFileUri($pattern = FALSE) {
    $name = 'site-' . $this->site->id();
    $extension = $this->extension();
    $hash = $pattern ? '*' : md5($name);
    $all_assets_directory = self::internalDirectoryUri();
    if ($pattern) {
      // glob() does not understand stream wrappers. Sigh.
      $all_assets_directory = \Drupal::service('file_system')
        ->realpath($all_assets_directory);
    }
    $internal_uri = "$all_assets_directory/$extension/$name-$hash.$extension";
    return $internal_uri;
  }

  /**
   * Get our directory.
   *
   * @return string
   *   Directory of the assets.
   */
  protected static function internalDirectoryUri() {
    return 'public://micro_site_asset';
  }

  /**
   * Delete all asset files.
   *
   * @see asset_injector_cache_flush()
   */
  public static function deleteAllFiles() {
    file_unmanaged_delete_recursive(self::internalDirectoryUri());
  }

}
