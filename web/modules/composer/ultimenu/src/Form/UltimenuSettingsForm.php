<?php

namespace Drupal\ultimenu\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines ultimenu admin settings form.
 */
class UltimenuSettingsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Block\BlockManagerInterface.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The Plugin service.
   *
   * @var \Drupal\ultimenu\UltimenuManagerInterface
   */
  protected $ultimenuManager;

  /**
   * The Ultimenu skin service.
   *
   * @var \Drupal\ultimenu\UltimenuSkinInterface
   */
  protected $ultimenuSkin;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ultimenu_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ultimenu.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->blockManager = $container->get('plugin.manager.block');
    $instance->ultimenuManager = $container->get('ultimenu.manager');
    $instance->ultimenuSkin = $container->get('ultimenu.skin');
    return $instance;
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $manager          = $this->ultimenuManager;
    $menus            = $manager->getMenus();
    $ultimenu_regions = $manager->getRegions();
    $regions_enabled  = $manager->getEnabledRegions();
    $theme_default    = $manager->getConfig('system.theme')->get('default');
    $config           = $this->config('ultimenu.settings');
    $blocks           = $config->get('blocks');
    $regions          = $config->get('regions');
    $path             = \ultimenu_get_path('module', 'ultimenu');
    $is_help          = $manager->getModuleHandler()->moduleExists('help');
    $route_name       = ['name' => 'ultimenu'];
    $readme           = $is_help ? Url::fromRoute('help.page', $route_name)->toString() : Url::fromUri('base:' . $path . '/docs/README.md')->toString();

    $form['#attached']['library'][] = 'ultimenu/admin';

    $form['ultimenu'] = [
      '#type' => 'vertical_tabs',
      '#weight' => -200,
      '#prefix' => '<p>' . $this->t('An Ultimenu <strong>block</strong> is based on a <strong>menu</strong>. Ultimenu <strong>regions</strong> are based on the <strong>menu items</strong>. The result is a block contains regions containing blocks, as opposed to: a region contains blocks.<br><br>Save it one at a time to reveal the regions. Be sure to read the <a href=":url">documentation</a> before proceeding.', [':url' => $readme]) . '</p>',
    ];

    $form['ultimenu_blocks'] = [
      '#type'  => 'details',
      '#title' => $this->t('Ultimenu blocks'),
      '#group' => 'ultimenu',
    ];

    $form['ultimenu_blocks']['blocks'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Toggle Ultimenu blocks'),
      '#options' => $menus,
      '#default_value' => $blocks ?: [],
      '#description' => $this->t('Check one to create an Ultimenu <code>block</code>. The block will be available at <a href=":block_admin">block admin</a> under Ultimenu set. And the relevant <code>regions</code> based on its enabled menu items will be available below after saving. Ultimenu will only care for the first top level menus. If you are willing to use submenus, please adjust the settings under <code>Menu levels</code> relevant to the particular Menu block assigned within an Ultimenu region (analog to D7 <a href=":menu_block" target="_blank">Menu block</a> module), and embed them inside an Ultimenu region. Or use the provided <b>Render submenu</b> option when editing the Ultimenu block.', [
        ':block_admin' => Url::fromRoute('block.admin_display')->toString(),
        ':menu_block' => '//drupal.org/project/menu_block',
      ]),
    ];

    $form['ultimenu_regions'] = [
      '#type' => 'details',
      '#title' => $this->t('Ultimenu regions'),
      '#group' => 'ultimenu',
    ];

    // All available Ultimenu regions.
    $form['ultimenu_regions']['regions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Toggle Ultimenu regions'),
      '#options' => $ultimenu_regions,
      '#default_value' => $regions ?: [],
      '#description' => $this->t('Check one to enable an Ultimenu <code>region</code>. The regions will be available at <a href="@block_admin">block admin</a>.', ['@block_admin' => Url::fromRoute('block.admin_display')->toString()]),
    ];

    // Compare settings against Ultimenu regions stored in theme .info.yml.
    if ($theme_regions = $manager->getTool()->parseThemeInfo($manager->getRegions())) {
      foreach ($theme_regions as $key => $region) {
        $form['ultimenu_regions']['regions'][$key]['#attributes']['class'][] = 'stored-in-theme';
        // Option disabled, but region stored in theme .info.yml, provide hint.
        if (empty($regions[$key])) {
          // Ultimenu region is stored in .info.yml, force remove is enabled.
          if ($manager->removeRegions()) {
            $form['ultimenu_regions']['regions'][$key]['#field_suffix'] = $this->t('&#8592; Stored, but force removed');
          }
          // Ultimenu region is stored in .info.yml, force remove is disabled.
          else {
            $form['ultimenu_regions']['regions'][$key]['#attributes']['class'][] = 'error';
            $form['ultimenu_regions']['regions'][$key]['#field_suffix'] = $this->t('&#8592; Stored, but disabled?');
          }
        }
      }
    }

    $form['ultimenu_goodies'] = [
      '#type' => 'details',
      '#title' => $this->t('Ultimenu goodies'),
      '#group' => 'ultimenu',
    ];

    $goodies = $config->get('goodies');
    // Menu title tooltip: http://webdesign.about.com/od/htmltags/a/aa101005.htm
    $form['ultimenu_goodies']['goodies'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Toggle Ultimenu goodies'),
      '#default_value' => !empty($goodies) ? array_values((array) $goodies) : [],
      '#options' => [
        'menu-desc' => $this->t('Render menu description'),
        'desc-top' => $this->t('Menu description above menu title'),
        'title-class' => $this->t('Add TITLE class to menu item'),
        'mlid-class' => $this->t('Add shortened UUID class (deprecated, will be removed at 3.x)'),
        'mlid-hash-class' => $this->t('Add shortened HASH class'),
        'counter-class' => $this->t('Add menu counter class'),
        'no-tooltip' => $this->t('Remove browser tooltip'),
        'ultimenu-mlid' => $this->t('Use shortened UUID, not TITLE, for Ultimenu region key (deprecated, will be removed at 3.x)'),
        'ultimenu-mlid-hash' => $this->t('Use shortened HASH, not TITLE, for Ultimenu region key (takes precedence over previous option)'),
        'force-remove-region' => $this->t('Force remove Ultimenu region stored in <b>@theme_default.info.yml</b>', ['@theme_default' => $theme_default]),
        'no-extras' => $this->t('Disable <b>ultimenu.extras.css</b>, various overrides and fixes, if you can bear headaches. Be sure to read the file.'),
        'off-canvas-all' => $this->t('Enable off-canvas for both mobile and desktop. Be sure to clear cache.'),
        'vanilla' => $this->t('Use vanilla JavaScript, requires Blazy 2.5+. Be sure to clear cache.'),
        'fe-themes' => $this->t('Make regions available for all front-end/ non-admin themes to avoid headaches during theme switchings. Otherwise only the default one.'),
      ],
      '#description' => $this->t("Using ugly UUID or HASH as region key is useful for a multilingual site, or to avoid accidental removal of regions due to changing menu item titles.<br /> Keep the UUID option if you're already using it, otherwise choose HASH.<br /> You can force remove unwanted Ultimenu regions stored in <code>@theme_default.info.yml</code>. Otherwise <code>@theme_default.info.yml</code> will always win, ignoring the Ultimenu region checkboxes above. <br>By default off-canvas is for mobile (<= 944px), enabled for desktop as needed.",
        ['@theme_default' => $theme_default]),
    ];

    $form['ultimenu_goodies']['skins'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to custom Ultimenu skins library'),
      '#default_value' => $config->get('skins'),
      '#description' => $this->t('The path to Ultimenu skins folder, e.g.: <code>libraries/skins/ultimenu</code> containing CSS files that will be used as options for each Ultimenu block. Please be specific with <code>ultimenu</code> directory to limit the scan. Or place skins in your theme default: <code>@theme_default/css/ultimenu</code> for auto discovery.',
      ['@theme_default' => \ultimenu_get_path('theme', $theme_default)]),
    ];

    $form['ultimenu_goodies']['fallback_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AJAX fallback text'),
      '#default_value' => $config->get('fallback_text'),
      '#description' => $this->t('The fallback text as link for when the AJAX fails. Default value: <b>@fallback_text</b>', ['@fallback_text' => 'Loading... Click here if it takes longer.']),
    ];

    $form['ultimenu_goodies']['ajaxmw'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AJAX mobile max-width'),
      '#default_value' => $config->get('ajaxmw'),
      '#description' => $this->t('Provides valid CSS max-width to disable AJAX, and auto trigger the AJAX instead, including units, e.g.: 481px. Meaning for device 480px below, AJAX will be auto-loaded rather than triggered by click.'),
    ];

    // Provide region definitions for copy/paste.
    if (!empty($regions)) {
      $copies = [];
      foreach ($regions_enabled as $key => $region) {
        $copies[] = "$key: '$region'";
      }

      if ($copies) {
        $copies = "regions:\n  " . implode("\n  ", $copies);
        $form['ultimenu_goodies']['markups'] = [
          '#type' => 'item',
          '#markup' => '<textarea class="getfocus" spellcheck="false">' . $copies . '</textarea>',
          '#allowed_tags' => ['textarea'],
        ];

        $form['ultimenu_goodies']['markups']['#suffix'] = $this->t("<ol><li>Changing menu item title will remove its region, unless HASH is enabled.</li><li>If a menu item is deleted or disabled, the related Ultimenu region is deleted.</li><li>Changing region key from TITLE to HASH will reset relevant regions and blocks. Simply re-assign blocks to get them back.</li><li>Optionally copy the provided regions into your <code>@theme_default</code> to permanently store Ultimenu regions.</li><li>Don't forget to clear cache whenever you update <code>@theme_default</code> file.</li><li>If you disable a region here, but stored in <code>@theme_default</code>, you can force remove <code>@theme_default</code> regions by checking <code>Force remove Ultimenu region stored in @theme_default</code> above. Otherwise <code>@theme_default</code> always wins ignoring the above settings.</li><li>If you copy/paste the above regions, be sure to exclude <code>regions: key</code> if the working theme has already regions key in place.</li></ol><br><div class='messages messages--warning'><strong>Warning!</strong> If you edit <code>@theme_default</code>, be careful with file indentation, otherwise .yml parser fatal error.</div>",
          ['@theme_default' => $theme_default . '.info.yml']);
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isValueEmpty('skins') && !is_dir($form_state->getValue('skins'))) {
      $form_state->setErrorByName('skins', $this->t('<strong>@skins</strong> do not exists. Please create the directory first.', ['@skins' => $form_state->getValue('skins')]));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data = [];
    // Do not litter the ultimenu.settings.yml.
    $data['blocks'] = array_filter($form_state->getValue('blocks'));
    $data['regions'] = array_filter($form_state->getValue('regions'));
    $data['goodies'] = array_filter($form_state->getValue('goodies'));

    $config = $this->configFactory->getEditable('ultimenu.settings');

    if ($form_state->hasValue('blocks')) {
      $config->set('blocks', $data['blocks']);
    }
    if ($form_state->hasValue('regions')) {
      $config->set('regions', $data['regions']);
    }
    if ($form_state->hasValue('goodies')) {
      $config->set('goodies', $data['goodies']);
    }
    foreach (['skins', 'fallback_text', 'ajaxmw'] as $key) {
      if ($form_state->hasValue($key)) {
        $config->set($key, $form_state->getValue($key));
      }
    }

    $config->save();

    // Reset static and theme info to get the new blocks and regions fetched.
    $this->ultimenuSkin->clearCachedDefinitions();

    // Invalidate the block cache to update ultimenu-based derivatives.
    $this->blockManager->clearCachedDefinitions();
    $this->configFactory->clearStaticCache();

    // If anything fails, notice to clear the cache.
    $this->messenger()->addMessage($this->t('Be sure to <a href=":clear_cache">clear the cache</a> <strong>ONLY IF</strong> trouble to see the updated regions at <a href=":block_admin">block admin</a>, or when changing off-canvas.', [
      ':clear_cache' => Url::fromRoute('system.performance_settings')->toString(),
      ':block_admin' => Url::fromRoute('block.admin_display')->toString(),
    ]));

    parent::submitForm($form, $form_state);
  }

}
