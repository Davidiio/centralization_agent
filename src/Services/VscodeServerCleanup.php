<?php

namespace Drupal\centralization_agent\Services;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use RuntimeException;

class VscodeServerCleanup {

  protected FileSystemInterface $fileSystem;
  private string $appRoot;
  private string $vscodeServerRelativePath;

  public function __construct(FileSystemInterface $file_system, ConfigFactoryInterface $config_factory, string $appRoot) {
    $this->fileSystem = $file_system;
    $this->vscodeServerRelativePath = $config_factory->get('centralization_agent.settings')->get('vscode_server_relative_path') ?? '';
    $this->appRoot = $appRoot;
  }

  /**
   * Removes the .vscode-server directory from the current user's home.
   *
   * @return bool
   *   TRUE when the directory was removed or did not exist.
   */
  public function remove(): bool {
    $directory = rtrim($this->appRoot, DIRECTORY_SEPARATOR)
      . DIRECTORY_SEPARATOR
      . trim($this->vscodeServerRelativePath, DIRECTORY_SEPARATOR)
      . DIRECTORY_SEPARATOR
      . '.vscode-server';
    
    if (!file_exists($directory) && !is_link($directory)) {
      return TRUE;
    }

    if(\Drupal::config('centralization_agent.settings')->get('show_debug_info')) {
      \Drupal::logger('centralization_agent')->notice('Removing VS Code Server directory: @directory', ['@directory' => $directory]);
    }
    return $this->fileSystem->deleteRecursive($directory);
  }

}