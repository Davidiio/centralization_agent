<?php

namespace Drupal\centralization_agent\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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

    $form['centralization_agent_service'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Centralization Agent ID'),
      '#description' => $config->get('centralization_agent_token') . "-" . $config->get('centralization_agent_encrypt_token'),
      '#default_value' => $config->get('centralization_agent_token') . "-" . $config->get('centralization_agent_encrypt_token'),
      '#attributes' => ['style' => ['display:none;']],
      '#size' => 60,
      '#maxlength' => 60,
      '#disabled' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['centralization_agent.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

}
