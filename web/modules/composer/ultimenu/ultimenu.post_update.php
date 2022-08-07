<?php

/**
 * @file
 * Post update hooks for Ultimenu.
 */

/**
 * Clear cache to add new arguments for deprecated drupal_get_path().
 */
function ultimenu_post_update_for_drupal10() {
  // Empty hook to clear caches.
}

/**
 * Clear cache to remove new arguments for @extension.path.resolver.
 */
function ultimenu_post_update_path_resolver_service_pre_d9_3() {
  // Empty hook to clear caches.
}
