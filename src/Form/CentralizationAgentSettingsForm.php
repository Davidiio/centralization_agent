<?php

namespace Drupal\centralization_agent\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Configure pants settings for this site.
 */
class CentralizationAgentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'centralization_agent_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('centralization_agent.settings');

    $form['shared_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Centralization Agent shared token'),
      '#description' => $config->get('shared_token'),
      '#default_value' => $config->get('shared_token'),
      '#attributes' => ['style' => ['display:none;']],
      '#size' => 128,
      '#maxlength' => 128,
      '#disabled' => TRUE,
    ];
    
    $form['encrypt_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Centralization Agent Encrypt Key'),
      '#description' => $config->get('encrypt_key'),
      '#default_value' => $config->get('encrypt_key'),
      '#attributes' => ['style' => ['display:none;']],
      '#size' => 128,
      '#maxlength' => 128,
      '#disabled' => TRUE,
    ];

    $form['https_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('HTTPS obligatoire'),
      '#description' => $this->t('Si activé, toutes les requêtes vers l\'API doivent être effectuées via HTTPS.'),
      '#default_value' => $config->get('https_required') ?? FALSE,
    ];

    $form['allowed_ips'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Adresses IP autorisées'),
      '#description' => $this->t('Une adresse IP ou une plage CIDR par ligne (ex: 203.0.113.5 ou 198.51.100.0/24). Laissez vide pour désactiver le filtrage IP.'),
      '#default_value' => implode("\n", $this->config('centralization_agent.settings')->get('allowed_ips') ?? []),
      '#rows' => 6,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['centralization_agent.settings'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $raw = $form_state->getValue('allowed_ips');
    $lines = array_filter(array_map('trim', explode("\n", $raw)));

    foreach ($lines as $line) {
      if (!$this->isValidIpOrCidr($line)) {
        $form_state->setErrorByName('allowed_ips', $this->t('L\'entrée "@ip" n\'est pas une adresse IP valide.', ['@ip' => $line]));
      }
    }
  }

  protected function isValidIpOrCidr(string $value): bool {
    // Plage CIDR (ex: 198.51.100.0/24).
    if (str_contains($value, '/')) {
      [$ip, $mask] = explode('/', $value, 2);
      return filter_var($ip, FILTER_VALIDATE_IP) !== FALSE && is_numeric($mask);
    }
    // IP simple (v4 ou v6).
    return filter_var($value, FILTER_VALIDATE_IP) !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $raw = $form_state->getValue('allowed_ips');
    $ips = array_values(array_filter(array_map('trim', explode("\n", $raw))));

    $this->config('centralization_agent.settings')
      ->set('allowed_ips', $ips)
      ->set('https_required', $form_state->getValue('https_required'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
