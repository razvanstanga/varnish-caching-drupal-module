<?php

/**
 * @file
 * This file contains drush integration for the vcaching module.
 */

/**
 * Implements hook_drush_command().
 *
 * @return array
 */
function vcaching_drush_command()
{
  $items = array();
  $items['vcaching-purge-all'] = array(
    'description' => 'Purge all pages in varnish.',
    'drupal dependencies' => array('vcaching'),
    'aliases' => array('vpa'),
    'callback' => 'vcaching_purge_all',
    );
  $items['vcaching-purge-urls'] = array(
    'description' => "Purge a list of URLs in varnish.",
    'arguments' => array(
      'paths' => 'A list of paths to purge separated by ;. Regular expressions allowed.',
      ),
    'aliases' => array('vpu'),
    'drupal dependencies' => array('vcaching'),
    'callback' => 'vcaching_purge_urls',
    );
  return $items;
}

/**
 * Callback for vcaching-purge-all drush command.
 *
 * @return void
 */
function vcaching_purge_all()
{
  $vcaching = new VCaching('vcaching_');
  drush_log(t('Purging all Varnish cache'), 'ok');
  $vcaching->purgeUrl(base_path() . '?vc-regex');
  drush_log($vcaching->getNoticeMessage(true), 'ok');
}

/**
 * Callback for vcaching-purge-urls drush command.
 *
 * @param string $paths A space separated list of paths.
 * @return void
 */
function vcaching_purge_urls($paths)
{
  $vcaching = new VCaching('vcaching_');
  $paths = explode(";", $paths);
  foreach ($paths as $path) {
    drush_log("Purging Varnish cache path " . $path);
    $vcaching->purgeUrl($path);
  }
  drush_log($vcaching->getNoticeMessage(true), 'ok');
}

/**
 * Implements hook_drush_cache_clear().
 *
 * @param array $types
 * @return void
 */
function vcaching_drush_cache_clear(&$types)
{
  $types['vcaching'] = 'vcaching_purge_all';
}
