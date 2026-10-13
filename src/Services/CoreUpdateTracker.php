<?php

namespace Drupal\centralization_agent\Services;

use Composer\InstalledVersions;
use Drupal\Core\State\StateInterface;

class CoreUpdateTracker {

  private const STATE_KEY = 'centralization_agent.update_history';
  private const PACKAGE = 'drupal/core-recommended';

  public function __construct(
    private readonly StateInterface $state,
  ) {}

  /** À appeler depuis hook_cron (et éventuellement hook_update_N / drush). */
  public function check(): void {
    $current = InstalledVersions::getPrettyVersion(self::PACKAGE);
    if ($current === NULL) {
      return;
    }

    $history = $this->state->get(self::STATE_KEY, []);
    $last = end($history);

    // Version inchangée : rien à faire.
    if ($last !== FALSE && $last['to'] === $current) {
      return;
    }

    $history[] = [
      'from' => $last ? $last['to'] : NULL,
      'to' => $current,
      // Première exécution : version de départ, date de mise à jour inconnue.
      'updated_at' => $last === FALSE ? NULL : date('c'),
      // Date à laquelle le module a vu cette version pour la première fois.
      'detected_at' => date('c'),
    ];

    // On garde un historique raisonnable.
    $this->state->set(self::STATE_KEY, array_slice($history, -20));
  }

  /** Données exposées à centralization. */
  public function getInfo(): array {
    $history = $this->state->get(self::STATE_KEY, []);

    // Dernière entrée dont la date de mise à jour est connue.
    $last = NULL;
    foreach (array_reverse($history) as $entry) {
      if ($entry['updated_at'] !== NULL) {
        $last = $entry;
        break;
      }
    }

    return [
      'core_version' => InstalledVersions::getPrettyVersion(self::PACKAGE),
      'last_update' => $last,
      'history' => $history,
    ];
  }

}