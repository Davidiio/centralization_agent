<?php

namespace Drupal\centralization_agent\Services;

/**
 * Encryption logic for system_status.
 */
class CentralizationAgentEncryption {

  /**
   * System Status: create a new token.
   */
  public static function getToken() {
    $chars = array_merge(range(0, 9),
      range('a', 'z'),
      range('A', 'Z'),
      range(0, 99));

    shuffle($chars);

    $token = "";
    for ($i = 0; $i < 8; $i++) {
      $token .= $chars[$i];
    }

    return $token;
  }

  /**
   * System Status: encrypt a plaintext message using openssl.
   */
  public static function encryptOpenssl($plaintext) {
    $encrypt_token = \Drupal::config('centralization_agent.settings')->get('centralization_agent_encrypt_token');
    $key =  $encrypt_token;

    // utf8_encode is deprecated
    // $plaintext_utf8 = utf8_encode($plaintext);

    $iv = openssl_random_pseudo_bytes(16);
    $cyphertext = openssl_encrypt($plaintext, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    $base64_encoded = base64_encode($iv . $cyphertext);
    return $base64_encoded;
  }

}
