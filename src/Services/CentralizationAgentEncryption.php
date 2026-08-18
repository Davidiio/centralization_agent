<?php

namespace Drupal\centralization_agent\Services;

use Drupal\Component\Utility\Crypt;

/**
 * Encryption logic for system_status.
 */
class CentralizationAgentEncryption {

  /**
   * System Status: create a new token.
   */
  public static function getToken() {
    // $chars = array_merge(range(0, 9),
    //   range('a', 'z'),
    //   range('A', 'Z'),
    //   range(0, 99));

    // shuffle($chars);

    // $token = "";
    // for ($i = 0; $i < 8; $i++) {
    //   $token .= $chars[$i];
    // }

    $token = Crypt::randomBytesBase64(32);

    return $token;
  }

  public static function getEncryptKey() {
    $key = base64_encode(random_bytes(32)); // 32 octets pour AES-256.
    return $key;
  }

  /**
   * System Status: encrypt a plaintext message using openssl.
   */
  public static function encryptOpenssl($plaintext) {
    // $encrypt_token = \Drupal::config('centralization_agent.settings')->get('encrypt_key');
    // $key =  $encrypt_token;

    // $iv = openssl_random_pseudo_bytes(16);
    // $cyphertext = openssl_encrypt($plaintext, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    // $base64_encoded = base64_encode($iv . $cyphertext);
    // return $base64_encoded;
    $key = base64_decode(\Drupal::config('centralization_agent.settings')->get('encrypt_key'));

    $iv = random_bytes(12); // 12 octets recommandés pour GCM (pas 16).
    $tag = '';

    $ciphertext = openssl_encrypt(
      $plaintext,
      'AES-256-GCM',
      $key,
      OPENSSL_RAW_DATA,
      $iv,
      $tag,
      '', // AAD (additional authenticated data), optionnel.
      16  // longueur du tag en octets.
    );

    if ($ciphertext === FALSE) {
      throw new \RuntimeException('Échec du chiffrement.');
    }

    // On concatène iv + tag + ciphertext pour tout transmettre ensemble.
    return base64_encode($iv . $tag . $ciphertext);
  }

}
