<?php

namespace Drupal\ultimenu;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Ultimenu Manager implementation.
 */
class UltimenuManager extends UltimenuBase implements UltimenuManagerInterface, TrustedCallbackInterface {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Static cache for the menu blocks.
   *
   * @var array
   */
  protected $menuBlocks;

  /**
   * Static cache for the blocks.
   *
   * @var array
   */
  protected $blocks;

  /**
   * Static cache for the regions.
   *
   * @var array
   */
  protected $regions;

  /**
   * Static cache for the enabled regions.
   *
   * @var array
   */
  protected $enabledRegions;

  /**
   * Static cache for the enabled regions filtered by menu.
   *
   * @var array
   */
  protected $regionsByMenu;

  /**
   * Static cache for the menu options.
   *
   * @var array
   */
  protected $menuOptions;

  /**
   * Static cache for the offcanvas block.
   *
   * @var array
   */
  protected $block;

  /**
   * The Ultimenu tree service.
   *
   * @var \Drupal\ultimenu\UltimenuTree
   */
  protected $tree;

  /**
   * The Ultimenu tool service.
   *
   * @var \Drupal\ultimenu\UltimenuTool
   */
  protected $tool;

  /**
   * Constructs a Ultimenu object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, BlockManagerInterface $block_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer, UltimenuTreeInterface $tree, UltimenuToolInterface $tool) {
    parent::__construct($config_factory, $entity_type_manager, $block_manager);
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->tree = $tree;
    $this->tool = $tool;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->renderer = $container->get('renderer');
    $instance->tree = $container->get('ultimenu.tree');
    $instance->tool = $container->get('ultimenu.tool');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderBuild'];
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleHandler() {
    return $this->moduleHandler;
  }

  /**
   * Returns the renderer.
   */
  public function getRenderer() {
    return $this->renderer;
  }

  /**
   * Returns the tool service.
   */
  public function getTool() {
    return $this->tool;
  }

  /**
   * Returns the tool service.
   */
  public function getTree() {
    return $this->tree;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenus() {
    if (!isset($this->menuOptions)) {
      $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple(NULL);
      $this->menuOptions = $this->tree->getMenus($menus);
    }
    return $this->menuOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function getUltimenuBlocks() {
    if (!isset($this->menuBlocks)) {
      $this->menuBlocks = [];
      $blocks = $this->getSetting('blocks');
      foreach ($this->getMenus() as $delta => $nice_name) {
        if (!empty($blocks[$delta])) {
          $this->menuBlocks[$delta] = $this->t('@name', ['@name' => $nice_name]);
        }
      }
      asort($this->menuBlocks);
    }
    return $this->menuBlocks;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build = []) {
    $build = [
      '#theme'      => 'ultimenu',
      '#items'      => [],
      '#build'      => $build,
      '#pre_render' => [[$this, 'preRenderBuild']],
    ];

    $this->moduleHandler->alter('ultimenu_build', $build);
    return $build;
  }

  /**
   * Builds the Ultimenu outputs as a structured array ready for ::renderer().
   */
  public function preRenderBuild(array $element) {
    $build = $element['#build'];
    $config = $build['config'];

    unset($build, $element['#build']);

    $config['current_path'] = Url::fromRoute('<current>')->toString();
    $tree_access_cacheability = new CacheableMetadata();
    $tree_link_cacheability = new CacheableMetadata();
    $items = $this->buildMenuTree($config, $tree_access_cacheability, $tree_link_cacheability);

    // Apply the tree-wide gathered access cacheability metadata and link
    // cacheability metadata to the render array. This ensures that the
    // rendered menu is varied by the cache contexts that the access results
    // and (dynamic) links depended upon, and invalidated by the cache tags
    // that may change the values of the access results and links.
    $tree_cacheability = $tree_access_cacheability->merge($tree_link_cacheability);
    $tree_cacheability->applyTo($element);

    // Build the elements.
    $element['#config'] = $config;
    $element['#items'] = $items;
    $element['#attached'] = $this->attach($config);
    $element['#cache']['tags'][] = 'config:ultimenu.' . $config['menu_name'];

    // Build the hamburger button, only for the main navigation.
    // @todo provide non-main ultimenus a toggler local to their containers.
    if ($config['bid'] == 'ultimenu-main') {
      $label = $this->t('Menu');
      $button = '<button data-ultimenu-button="#ultimenu-main" class="button button--ultimenu"
        aria-label="' . $label . '" value="' . $label . '"><span class="bars">' . $label . '</span></button>';
      $element['#suffix'] = Markup::create($button);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function attach(array $config = []) {
    $goodies = $this->getSetting('goodies');
    $load = [];

    $load['library'][] = 'ultimenu/ultimenu';
    if (!empty($config['skin_basename'])) {
      $load['library'][] = 'ultimenu/skin.' . $config['skin_basename'];
    }
    if (!empty($config['orientation']) && strpos($config['orientation'], 'v') !== FALSE) {
      $load['library'][] = 'ultimenu/vertical';
    }
    if (!empty($config['ajaxify'])) {
      $load['library'][] = 'ultimenu/ajax';
    }
    if (empty($goodies['no-extras'])) {
      $load['library'][] = 'ultimenu/extras';
    }

    // Specific for main navigation.
    if ($config['menu_name'] == 'main') {
      $canvas_skin = empty($config['canvas_skin']) ? 'scalein' : $config['canvas_skin'];

      $load['library'][] = 'ultimenu/hamburger';
      $load['library'][] = 'ultimenu/offcanvas.' . $canvas_skin;

      // Optional if using the provided configuration.
      if (!empty($config['canvas_off']) && !empty($config['canvas_on'])) {
        $js_config = [
          'canvasOff' => trim(strip_tags($config['canvas_off'])),
          'canvasOn' => trim(strip_tags($config['canvas_on'])),
        ];
        $load['drupalSettings']['ultimenu'] = $js_config;
      }
    }

    if ($mw = $this->getSetting('ajaxmw')) {
      $load['drupalSettings']['ultimenu']['ajaxmw'] = $mw;
    }

    $this->moduleHandler->alter('ultimenu_attach', $load, $attach);
    return $load;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMenuTree(array $config, CacheableMetadata &$tree_access_cacheability, CacheableMetadata &$tree_link_cacheability) {
    $menu_name = $config['menu_name'];
    $active_trails = $this->tree->getMenuActiveTrail()->getActiveTrailIds($menu_name);
    $tree = $this->tree->loadMenuTree($menu_name);

    if (empty($tree)) {
      return [];
    }

    $ultimenu = [];
    $theme = $this->getThemeDefault();
    $config['context_disabled_regions'] = $disabled_regions = Ultimenu::contextDisabledRegions($theme);

    foreach ($tree as $data) {
      $link = $data->link;
      // Generally we only deal with visible links, but just in case.
      if (!$link->isEnabled()) {
        continue;
      }

      if ($data->access !== NULL && !$data->access instanceof AccessResultInterface) {
        throw new \DomainException('MenuLinkTreeElement::access must be either NULL or an AccessResultInterface object.');
      }

      // Gather the access cacheability of every item in the menu link tree,
      // including inaccessible items. This allows us to render cache the menu
      // tree, yet still automatically vary the rendered menu by the same cache
      // contexts that the access results vary by.
      // However, if $data->access is not an AccessResultInterface object, this
      // will still render the menu link, because this method does not want to
      // require access checking to be able to render a menu tree.
      if ($data->access instanceof AccessResultInterface) {
        $tree_access_cacheability = $tree_access_cacheability->merge(CacheableMetadata::createFromObject($data->access));
      }

      // Gather the cacheability of every item in the menu link tree. Some links
      // may be dynamic: they may have a dynamic text (e.g. a "Hi, <user>" link
      // text, which would vary by 'user' cache context), or a dynamic route
      // name or route parameters.
      $tree_link_cacheability = $tree_link_cacheability->merge(CacheableMetadata::createFromObject($link));

      // Only render accessible links.
      if ($data->access instanceof AccessResultInterface && !$data->access->isAllowed()) {
        continue;
      }

      $config['region'] = $region = $this->tool->getRegionKey($link);
      // Exclude regions disabled by Context.
      if (isset($disabled_regions[$region])) {
        continue;
      }

      $ultimenu[$link->getPluginId()] = $this->buildMenuItem($data, $active_trails, $config);
    }
    return $ultimenu;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMenuItem($data, array $active_trails, array $config) {
    $goodies    = $this->getSetting('goodies');
    $link       = $data->link;
    $url        = $link->getUrlObject();
    $mlid       = $link->getPluginId();
    $titles     = $this->tool->extractTitleHtml($link);
    $title      = $titles['title'];
    $title_html = $titles['title_html'];
    $li_classes = $li_attributes = $li_options = [];
    $region     = $config['region'];
    $flyout     = '';

    // Must run after the title, modified, or not, the region depends on it.
    $config['has_submenu'] = !empty($config['submenu']) && $link->isExpanded() && $data->hasChildren;
    $config['is_ajax_region'] = FALSE;
    $config['is_active'] = array_key_exists($mlid, $active_trails);
    $config['title'] = $title;
    $config['mlid'] = $mlid;
    $li_options['title-class'] = $title;
    $li_options['mlid-hash-class'] = $this->tool->getShortenedHash($mlid);

    if (!empty($goodies['mlid-class'])) {
      $li_options['mlid-class'] = $link->getRouteName() == '<front>' ? 'front_page' : $this->tool->getShortenedUuid($mlid);
    }

    $link_options = $link->getOptions();
    if ($url->isRouted()) {
      if ($config['is_active']) {
        $li_classes[] = 'is-active-trail';
      }

      // Front page has no active trail.
      if ($link->getRouteName() == '<front>') {
        // Intentionally on the second line to not hit it till required.
        if ($this->tool->getPathMatcher()->isFrontPage()) {
          $li_classes[] = 'is-active-trail';
        }
      }

      // Also enable set_active_class for the contained link.
      $link_options['set_active_class'] = TRUE;

      // Add a "data-drupal-link-system-path" attribute to let the
      // drupal.active-link library know the path in a standardized manner.
      $system_path = $url->getInternalPath();

      // Special case for the front page.
      if ($url->getRouteName() === '<front>') {
        $system_path = '<front>';
      }

      // @todo System path is deprecated - use the route name and parameters.
      $link_options['attributes']['data-drupal-link-system-path'] = $system_path;
      $config['system_path'] = $system_path;
    }

    // Remove browser tooltip if so configured.
    if (!empty($goodies['no-tooltip'])) {
      $link_options['attributes']['title'] = '';
    }

    // Add LI title class based on title if so configured.
    foreach ($li_options as $li_key => $li_value) {
      if (!empty($goodies[$li_key])) {
        $li_classes[] = Html::cleanCssIdentifier(mb_strtolower('uitem--' . str_replace('_', '-', $li_value)));
      }
    }

    // Add hint for external link.
    if ($url->isExternal()) {
      $link_options['attributes']['class'][] = 'is-external';
    }

    // Add LI counter class based on counter if so configured.
    if (!empty($goodies['counter-class'])) {
      static $item_id = 0;
      $li_classes[] = 'uitem--' . (++$item_id);
    }

    // Handle list item class attributes.
    $li_attributes['class'] = array_merge(['ultimenu__item', 'uitem'], $li_classes);

    // Flyout.
    $flyout = $this->getFlyout($region, $config);

    // Provides hints for AJAX.
    $orientation = $config['orientation'] ?: '';
    $orientation = 'is-' . str_replace('ultimenu--', '', $orientation);
    $flyout_attributes['class'] = ['ultimenu__flyout', $orientation];
    if (!empty($flyout)) {
      if ($config['is_ajax_region']) {
        $flyout_attributes['data-ultiajax-region'] = $region;
        $link_options['attributes']['data-ultiajax-trigger'] = TRUE;
      }
      $title_html .= '<span class="caret" aria-hidden="true"></span>';
    }

    $extra_classes = isset($link_options['attributes']['class']) ? $link_options['attributes']['class'] : [];
    if (!is_array($extra_classes)) {
      $extra_classes = [$extra_classes];
    }
    $link_options['attributes']['class'] = $extra_classes ? array_merge(['ultimenu__link'], $extra_classes) : ['ultimenu__link'];

    $link_element = [
      '#type' => 'link',
      '#options' => $link_options,
      '#url' => $url,
      '#title' => [
        '#markup' => $title_html,
        '#allowed_tags' => ['b', 'em', 'i', 'small', 'span', 'strong'],
      ],
    ];

    // Pass link to template.
    return [
      'link' => $link_element,
      'flyout' => $flyout,
      'attributes' => new Attribute($li_attributes),
      'flyout_attributes' => new Attribute($flyout_attributes),
      'config' => $config,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildAjaxLink(array $config = []) {
    return [
      '#type' => 'link',
      '#title' => strip_tags($this->getFallbackText()),
      '#attributes' => [
        'class' => [
          'ultimenu__ajax',
          'use-ajax',
        ],
        'rel' => 'nofollow',
        'id' => Html::getUniqueId('ultiajax-' . $this->tool->getShortenedHash($config['mlid'])),
      ],
      '#url' => Url::fromRoute('ultimenu.ajax', [
        'mlid' => $config['mlid'],
        // @todo revert if any issue: 'cur' => $config['current_path'],
        'sub' => $config['has_submenu'] ? 1 : 0,
      ]),
    ];
  }

  /**
   * Return the fallback text.
   */
  public function getFallbackText() {
    return $this->t('@text', ['@text' => $this->getSetting('fallback_text') ?: 'Loading... Click here if it takes longer.']);
  }

  /**
   * Returns the flyout if available.
   */
  public function getFlyout($region, array &$config) {
    $flyout = [];
    if ($regions = $this->getSetting('regions')) {
      if (!empty($regions[$region])) {

        // Simply display the flyout, if AJAX is disabled.
        if (empty($config['ajaxify'])) {
          $flyout = $this->buildFlyout($region, $config);
        }
        else {
          // We have a mix of (non-)ajaxified regions here.
          // Provides an AJAX link as a fallback and also the trigger.
          // No need to check whether the region is empty, or not, as otherwise
          // defeating the purpose of ajaxified regions, to gain performance.
          // The site builder should at least provide one accessible block
          // regardless of complex visibility by paths or roles. A trade off.
          $ajax_regions = isset($config['regions']) ? array_filter($config['regions']) : [];
          $config['is_ajax_region'] = $ajax_regions && in_array($region, $ajax_regions);
          $flyout = $config['is_ajax_region'] ? $this->buildAjaxLink($config) : $this->buildFlyout($region, $config);
        }
      }
    }
    return $flyout;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFlyout($region, array $config) {
    $build   = $content = [];
    $reverse = FALSE;
    $count   = 0;

    if (!empty($config['has_submenu'])) {
      $reverse = !empty($config['submenu_position']) && $config['submenu_position'] == 'bottom';
      $content[] = $this->tree->loadSubMenuTree($config['menu_name'], $config['mlid'], $config['title']);
    }

    if ($blocks = $this->getBlocksByRegion($region, $config)) {
      $content[] = $blocks;
      $count = count($blocks);
    }

    if ($content = array_filter($content)) {
      $config['count']  = $count;
      $build['content'] = $reverse ? array_reverse($content, TRUE) : $content;
      $build['#config'] = $config;
      $build['#region'] = $region;
      $build['#sorted'] = TRUE;

      $attributes['class'][] = 'ultimenu__region';

      // Useful to calculate grids.
      if ($count) {
        $attributes['class'][] = 'region';
        $attributes['class'][] = 'region--count-' . $count;
      }

      // Add the region theme wrapper for the flyout.
      $build['#attributes'] = $attributes;
      $build['#theme_wrappers'][] = 'region';
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlocksByRegion($region, array $config) {
    if (!isset($this->blocks[$region])) {
      $build = [];
      $blocks = $this->entityTypeManager->getStorage('block')->loadByProperties([
        'theme' => $this->getThemeDefault(),
        'region' => $region,
      ]);

      if ($blocks) {
        uasort($blocks, 'Drupal\block\Entity\Block::sort');

        // Only provides extra access checks if the region is ajaxified.
        if (empty($config['ajaxify'])) {
          foreach ($blocks as $key => $block) {
            if ($block->access('view')) {
              $build[$key] = $this->entityTypeManager->getViewBuilder($block->getEntityTypeId())->view($block, 'block');
            }
          }
        }
        else {
          foreach ($blocks as $key => $block) {
            if ($this->tool->isAllowedBlock($block, $config)) {
              $build[$key] = $this->entityTypeManager->getViewBuilder($block->getEntityTypeId())->view($block, 'block');
            }
          }
        }
      }

      // Merges with blocks provided by Context.
      if ($context_blocks = Ultimenu::contextBlocks($region, $build)) {
        $build = array_merge($build, $context_blocks);
      }

      $this->blocks[$region] = $build;
    }
    return $this->blocks[$region];
  }

  /**
   * {@inheritdoc}
   */
  public function getRegions() {
    if (!isset($this->regions)) {
      $blocks      = $this->getSetting('blocks');
      $menu_blocks = is_array($blocks) ? array_filter($blocks) : [$blocks];
      $menus       = [];

      foreach ($menu_blocks as $delta => $title) {
        $menus[$delta] = $this->tree->loadMenuTree($delta);
      }

      $regions = [];
      foreach ($menus as $menu_name => $tree) {
        foreach ($tree as $item) {
          $name_id = $this->tool->truncateRegionKey($menu_name);
          $name_id_nice = str_replace("_", " ", $name_id);
          $link = $item->link;

          $menu_title = $this->tool->getTitle($link);
          $region_key = $this->tool->getRegionKey($link);
          $regions[$region_key] = "Ultimenu:$name_id_nice: $menu_title";
        }
      }
      $this->regions = $regions;
    }
    return $this->regions;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledRegions() {
    if (!isset($this->enabledRegions)) {
      $this->enabledRegions = [];
      $regions_all = $this->getRegions();

      // First limit to enabled regions from the settings.
      if (($regions_enabled = $this->getSetting('regions')) !== NULL) {
        foreach (array_filter($regions_enabled) as $enabled) {
          // We must depend on enabled menu items as always.
          // A disabled menu item will automatically drop its region.
          if (array_key_exists($enabled, $regions_all)) {
            $this->enabledRegions[$enabled] = $regions_all[$enabled];
          }
        }
      }
    }
    return $this->enabledRegions;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegionsByMenu($menu_name) {
    if (!isset($this->regionsByMenu[$menu_name])) {
      $regions = [];
      foreach ($this->getEnabledRegions() as $key => $region_name) {
        if (strpos($key, 'ultimenu_' . $menu_name . '_') === FALSE) {
          continue;
        }
        $regions[$key] = $region_name;
      }
      $this->regionsByMenu[$menu_name] = $regions;
    }
    return $this->regionsByMenu[$menu_name];
  }

  /**
   * {@inheritdoc}
   */
  public function removeRegions() {
    $goodies = $this->getSetting('goodies');
    if (empty($goodies['force-remove-region'])) {
      return FALSE;
    }
    return $this->tool->parseThemeInfo($this->getRegions());
  }

  /**
   * Returns the block content idenfied by its entity ID.
   */
  public function getBlock($bid) {
    if (!isset($this->block[$bid])) {
      $this->block[$bid] = [];
      if ($block = $this->entityTypeManager->getStorage('block')->load($bid)) {
        $this->block[$bid] = $block->getPlugin()->build();
      }
    }
    return $this->block[$bid];
  }

  /**
   * Implements hook_library_info_alter().
   */
  public function libraryInfoAlter(&$libraries, $extension) {
    if ($extension === 'ultimenu') {
      if ($this->moduleHandler->moduleExists('blazy')) {
        $libraries['base']['dependencies'][] = 'blazy/dblazy';
        if ($this->getSetting('goodies.vanilla')) {
          $deps = ['core/drupal', 'core/drupalSettings', 'ultimenu/base'];
          $libraries['ultimenu']['dependencies'] = $deps;
          $libraries['ultimenu']['js'] = ['js/ultimenu.vanilla.min.js' => []];
          $libraries['ajax']['js'] = ['js/ultimenu.ajax.vanilla.min.js' => []];
        }
      }
    }
  }

  /**
   * Implements hook_system_info_alter().
   */
  public function systemInfoAlter(&$info, Extension $file, $type) {
    $ok = $file->getName() == $this->getThemeDefault();
    $goodies = $this->getSetting('goodies');

    // Make regions available for all themes, except admin to avoid headaches
    // during theme switching like at most devs.
    if (!empty($goodies['fe-themes'])) {
      $name = $info['name'] ?? '';
      $hidden = $info['hidden'] ?? FALSE;
      $desc = $info['description'] ?? 'blah';
      // Drupal has no keyword/ grouping to distinguish [front|back]-end themes.
      $admin = stripos($desc, 'admin') !== FALSE;
      $ok = !$hidden && !$admin && !in_array($name, ['Stark']);
    }

    if ($type == 'theme' && isset($info['regions']) && $ok) {
      if ($regions = $this->getEnabledRegions()) {

        // Append the Ultimenu regions into the theme defined regions.
        foreach ($regions as $key => $region) {
          $info['regions'] += [$key => $region];
        }

        // Remove unwanted Ultimenu regions from theme .info if so configured.
        if (($remove_regions = $this->removeRegions()) !== FALSE) {
          foreach ($remove_regions as $key => $region) {
            unset($info['regions'][$key]);
          }
        }
      }
    }
  }

}
