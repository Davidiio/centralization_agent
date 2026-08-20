<?php

namespace Drupal\centralization_agent\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class TokenAccessCheck {

  protected ConfigFactoryInterface $configFactory;

  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  public function access(Request $request): AccessResultInterface {

    // https obligatoire
    if ($this->configFactory->get('centralization_agent.settings')->get('https_required') && !$request->isSecure()) {
      return AccessResult::forbidden('HTTPS requis.');
    }

    \Drupal::logger('centralization_agent')->notice('HTTPS OK');

    // Vérification de l'adresse IP
    $allowedIps = $this->configFactory->get('centralization_agent.settings')->get('allowed_ips') ?? [];
    $clientIp = $request->getClientIp();

    \Drupal::logger('centralization_agent')->notice('Allowed IPs: ' . implode(', ', $allowedIps));
    \Drupal::logger('centralization_agent')->notice('Client IP: ' . $clientIp);
    if (!empty($allowedIps) && !in_array($clientIp, $allowedIps, TRUE)) {
      return AccessResult::forbidden('IP non autorisée.');
    }

    \Drupal::logger('centralization_agent')->notice('Allowed IP OK');

    // Vérification du token
    $token = $request->headers->get('X-Api-Token');

    if ($token === NULL) {
      return AccessResult::forbidden('Token manquant.');
    }

    \Drupal::logger('centralization_agent')->notice('Token not null OK');

    $secret = $this->configFactory->get('centralization_agent.settings')->get('shared_token');;

    if (!hash_equals((string) $secret, $token)) {
      return AccessResult::forbidden('Token invalide.');
    }

    \Drupal::logger('centralization_agent')->notice('Valid Token OK');

    return AccessResult::allowed();
  }

}