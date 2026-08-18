<?php

namespace Drupal\centralization_agent\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\centralization_agent\Services\CentralizationAgentEncryption;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for Sensei's Pants routes.
 */
class CentralizationAgentController extends ControllerBase {

  /**
   * Project is missing security update(s).
   */
  const NOT_SECURE = 1;

  /**
   * Current release has been unpublished and is no longer available.
   */
  const REVOKED = 2;

  /**
   * Current release is no longer supported by the project maintainer.
   */
  const NOT_SUPPORTED = 3;

  /**
   * Project has a new release available, but it is not a security release.
   */
  const NOT_CURRENT = 4;

  /**
   * Project is up to date.
   */
  const CURRENT = 5;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The system status encrypt service.
   *
   * @var \Drupal\centralization_agent\Services\CentralizationAgentEncryption
   */
  protected $encrypt;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $container->get('centralization_agent.encrypt')
    );
  }

  /**
   * SystemStatusController constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\centralization_agent\Services\CentralizationAgentEncryption $encrypt
   *   The System Status encrypt.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, CentralizationAgentEncryption $encrypt) {
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->encrypt = $encrypt;
  }

  public function load(Request $request) {

    \Drupal::logger('centralization_agent')->notice('Processing request');

    $available = update_get_available(TRUE);

    $this->moduleHandler()->loadInclude('update', 'compare.inc');
    $available_updates = update_calculate_project_data($available);
    $general_update_status = 5;

    foreach ($available_updates as $key => $available_update) {
      if($key == "drupal") {
        $drupal_core_update_status = $available_update['status'];
        continue;
      }
      if($available_update['status'] < $general_update_status) {
        $general_update_status = $available_update['status'];
      }
    }
    
    $requirements = \Drupal::service('system.manager')->listRequirements();

    $php_version = phpversion();
    $drupal_version = \Drupal::VERSION;
    $admin_theme = \Drupal::config('system.theme')->get('admin');
    $default_theme = \Drupal::config('system.theme')->get('default');
    $site_name = \Drupal::config('system.site')->get('name');
    $database_system = $requirements['database_system']['value']->render();
    $database_system_version = $requirements['database_system_version']['value'];
    $database_name = \Drupal::database()->getConnectionOptions()['database'];
    $database_size_query = \Drupal::database()->query(
      "SELECT
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'size'
      FROM
        information_schema.tables
      WHERE
        table_schema = '" . $database_name . "';"
      )->fetchAll();
    $database_size = $database_size_query[0]->size;

    $response = [
      "site_name" => $site_name,
      "drupal_version" => $drupal_version,
      "php_version" => $php_version,
      "admin_theme" => $admin_theme,
      "default_theme" => $default_theme,
      "database_size" => $database_size,
      "database_system" => $database_system,
      "database_system_version" => $database_system_version,
      "modules" => [],
      "themes" => [],
      "available_updates" => $available_updates,
      "general_update_status" => $general_update_status,
      "drupal_core_update_status" => $drupal_core_update_status,
    ];

    $drupal_modules = $this->moduleHandler->getModuleList();
    $drupal_themes = $this->themeHandler->listInfo();

    foreach ($drupal_modules as $name => $module) {
      // Skip core modules
      if (strpos($module->getPath(), 'core/') !== false) {
        continue;
      }
      $filename = $module->getPath() . '/' . $module->getFilename();
      $module_info = Yaml::decode(file_get_contents($filename));

      // This can happen when you install using composer.
      if (isset($module_info['version']) && $module_info['version'] == "VERSION") {
        $module_info['version'] = \Drupal::VERSION;
      }

      if (!isset($module_info['version'])) {
        $module_info['version'] = NULL;
      }

      $module_type = 'custom';
      
      if (strpos($module->getPath(), 'contrib/') !== false) {
        $module_type = 'contrib';
      }

      $response['modules'][$name] = [
        "title" => $module_info['name'],
        "version" => $module_info['version'],
        "type" => $module_type,
      ];
    }

    foreach ($drupal_themes as $name => $theme) {
      $filename = $theme->getPath() . '/' . $theme->getFilename();
      $theme_info = Yaml::decode(file_get_contents($filename));

      if (!isset($theme_info['version'])) {
        $theme_info['version'] = NULL;
      }

      // This can happen when you install using composer.
      if ($theme_info['version'] == "VERSION") {
        $theme_info['version'] = \Drupal::VERSION;
      }

      if (isset($theme_info['project']) && $theme_info['project'] == 'drupal') {
        continue;
      }

      if (isset($theme_info['project'])) {
        $response['themes'][$theme_info['project']] = ["version" => $theme_info['version']];
      }
      else {
        $response['themes'][$name] = ["version" => $theme_info['version']];
      }
    }

    $encrypted_response = CentralizationAgentEncryption::encryptOpenssl(json_encode($response));
    return new JsonResponse([
      "data" => $encrypted_response
    ]);
  }

  public function cache_clear($centralization_agent_id) {
    drupal_flush_all_caches();
    return new JsonResponse([
      "data" => "cache flushed"
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($centralization_agent_id) {
    $token = $this->config('centralization_agent.settings')->get('shared_token');
    if ($token == $centralization_agent_id) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

}
