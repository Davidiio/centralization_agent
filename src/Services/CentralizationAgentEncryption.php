<?php

namespace Drupal\centralization_agent\Services;

use Drupal\Component\Utility\Crypt;

class CentralizationAgentEncryption {

  public static function getToken() {
    $token = Crypt::randomBytesBase64(32);

    return $token;
  }

  public static function getEncryptKey() {
    $key = base64_encode(random_bytes(32)); // 32 octets pour AES-256.
    return $key;
  }

  public static function encryptOpenssl($plaintext) {

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
