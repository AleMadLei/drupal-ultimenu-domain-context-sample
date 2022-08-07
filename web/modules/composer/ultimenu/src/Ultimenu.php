<?php

namespace Drupal\ultimenu;

/**
 * Provides common Ultimenu utility methods.
 */
class Ultimenu {

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function service($service) {
    return \Drupal::hasService($service) ? \Drupal::service($service) : NULL;
  }

  /**
   * Returns available regions disabled by Context.
   */
  public static function contextBlocks($region, array $build): array {
    if ($context_manager = self::service('context.manager')) {
      foreach ($context_manager->getActiveReactions('blocks') as $reaction) {
        $check = $reaction->execute($build);
        return $check[$region] ?? [];
      }
    }
    return [];
  }

  /**
   * Returns available regions disabled by Context.
   */
  public static function contextDisabledRegions($theme): array {
    if ($context_manager = self::service('context.manager')) {
      foreach ($context_manager->getActiveReactions('regions') as $reaction) {
        $check = $reaction->getConfiguration();
        if (isset($check['regions'])
          && $regions = ($check['regions'][$theme] ?? [])) {
          return array_combine($regions, $regions);
        }
      }
    }
    return [];
  }

}
