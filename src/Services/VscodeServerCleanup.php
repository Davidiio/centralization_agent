<?php

namespace Drupal\centralization_agent\Services;

use Drupal\Core\File\FileSystemInterface;
use RuntimeException;

class VscodeServerCleanup {

  protected FileSystemInterface $fileSystem;

  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * Removes the .vscode-server directory from the current user's home.
   *
   * @return bool
   *   TRUE when the directory was removed or did not exist.
   */
  public function remove(): bool {
    $home = getenv('HOME');

    if (!is_string($home) || $home === '' || $home === DIRECTORY_SEPARATOR) {
      throw new RuntimeException('Le répertoire personnel est introuvable.');
    }

    $directory = rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.vscode-server';

    if (!file_exists($directory) && !is_link($directory)) {
      return TRUE;
    }

    dsm($directory);
    return $this->fileSystem->deleteRecursive($directory);
  }

}