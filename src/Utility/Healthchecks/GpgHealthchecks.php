<?php
/**
 * Passbolt ~ Open source password manager for teams
 * Copyright (c) Passbolt SA (https://www.passbolt.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Passbolt SA (https://www.passbolt.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.passbolt.com Passbolt(tm)
 * @since         2.1.2
 */
namespace App\Utility\Healthchecks;

use App\Utility\Gpg;
use Cake\Core\Configure;
use Cake\Core\Exception\Exception;
use Cake\ORM\TableRegistry;

class GpgHealthchecks
{
    /**
     * Run all healthchecks
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function all($checks = [])
    {
        if (empty($checks)) {
            $checks = [];
        }
        $checks = self::gpgLib($checks);
        $checks = self::gpgNotDefault($checks);
        $checks = self::gpgHome($checks);
        $checks = self::gpgKeyFile($checks);
        $checks = self::gpgFingerprint($checks);
        $checks = self::gpgKeyInKeyring($checks);
        $checks = self::gpgCanSign($checks);
        $checks = self::gpgCanEncrypt($checks);
        $checks = self::gpgCanEncryptSign($checks);
        $checks = self::gpgCanDecrypt($checks);
        $checks = self::gpgCanDecryptVerify($checks);
        $checks = self::gpgCanVerify($checks);

        return $checks;
    }

    /**
     * Check gpg php module is installed and enabled
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgLib($checks = [])
    {
        $checks['gpg']['lib'] = (class_exists('gnupg'));

        return $checks;
    }

    /**
     * Check fingerprint is set
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgNotDefault($checks = [])
    {
        $checks['gpg']['gpgKey'] = (Configure::read('passbolt.gpg.serverKey.fingerprint') != null);
        $checks['gpg']['gpgKeyNotDefault'] = (Configure::read('passbolt.gpg.serverKey.fingerprint') != '2FC8945833C51946E937F9FED47B0811573EE67E');

        return $checks;
    }

    /**
     * Check gnupg home is set and usable
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgHome($checks = [])
    {
        // If no keyring location has been set, use the default one ~/.gnupg.
        $gnupgHome = getenv('GNUPGHOME');
        if (empty($gnupgHome)) {
            $uid = posix_getuid();
            $user = posix_getpwuid($uid);
            $gnupgHome = $user['dir'] . '/.gnupg';
        }

        $checks['gpg']['info']['gpgHome'] = $gnupgHome;
        $checks['gpg']['gpgHome'] = file_exists($checks['gpg']['info']['gpgHome']);
        $checks['gpg']['gpgHomeWritable'] = is_writable($checks['gpg']['info']['gpgHome']);

        return $checks;
    }

    /**
     * Check key file exist and are readable
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgKeyFile($checks = [])
    {
        $checks['gpg']['gpgKeyPublic'] = (Configure::read('passbolt.gpg.serverKey.public') != null);
        $checks['gpg']['gpgKeyPublicReadable'] = is_readable(Configure::read('passbolt.gpg.serverKey.public'));
        if ($checks['gpg']['gpgKeyPublicReadable']) {
            $publicKeydata = file_get_contents(Configure::read('passbolt.gpg.serverKey.public'));
            $checks['gpg']['gpgKeyPublicBlock'] = (strpos($publicKeydata, '-----BEGIN PGP PUBLIC KEY BLOCK-----') === 0);
        }
        $checks['gpg']['gpgKeyPrivate'] = (Configure::read('passbolt.gpg.serverKey.private') != null);
        $checks['gpg']['info']['gpgKeyPrivate'] = Configure::read('passbolt.gpg.serverKey.private');
        $checks['gpg']['gpgKeyPrivateReadable'] = is_readable(Configure::read('passbolt.gpg.serverKey.private'));
        if ($checks['gpg']['gpgKeyPrivateReadable']) {
            $privateKeydata = file_get_contents(Configure::read('passbolt.gpg.serverKey.private'));
            $checks['gpg']['gpgKeyPrivateBlock'] = (strpos($privateKeydata, '-----BEGIN PGP PRIVATE KEY BLOCK-----') === 0);
        }

        return $checks;
    }

    /**
     * Check that the private key match the fingerprint
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgFingerprint($checks = [])
    {
        $checks['gpg']['gpgKeyPrivateFingerprint'] = false;
        $checks['gpg']['gpgKeyPublicFingerprint'] = false;
        $checks['gpg']['gpgKeyPublicEmail'] = false;
        if ($checks['gpg']['gpgKeyPublicReadable'] && $checks['gpg']['gpgKeyPrivateReadable'] && $checks['gpg']['gpgKey']) {
            $gpg = new Gpg();
            $privateKeydata = file_get_contents(Configure::read('passbolt.gpg.serverKey.private'));
            $privateKeyInfo = $gpg->getKeyInfo($privateKeydata);
            if ($privateKeyInfo['fingerprint'] === Configure::read('passbolt.gpg.serverKey.fingerprint')) {
                $checks['gpg']['gpgKeyPrivateFingerprint'] = true;
            }
            $publicKeydata = file_get_contents(Configure::read('passbolt.gpg.serverKey.public'));
            $publicKeyInfo = $gpg->getPublicKeyInfo($publicKeydata);
            if ($publicKeyInfo['fingerprint'] === Configure::read('passbolt.gpg.serverKey.fingerprint')) {
                $checks['gpg']['gpgKeyPublicFingerprint'] = true;
            }
            $Gpgkeys = TableRegistry::getTableLocator()->get('Gpgkeys');
            $checks['gpg']['gpgKeyPublicEmail'] = $Gpgkeys->uidContainValidEmailRule($publicKeyInfo['uid']);
        }

        return $checks;
    }

    /**
     * Check that the server public/private keys are present in the keyring.
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgKeyInKeyring($checks = [])
    {
        $checks['gpg']['gpgKeyPublicInKeyring'] = false;
        if ($checks['gpg']['gpgHome'] && Configure::read('passbolt.gpg.serverKey.fingerprint')) {
            $gpg = new Gpg();
            $keyInfo = $gpg->getKeyInfoFromKeyring(Configure::read('passbolt.gpg.serverKey.fingerprint'));
            if (!empty($keyInfo)) {
                if ($keyInfo[0]['can_sign'] && $keyInfo[0]['can_encrypt']) {
                    $checks['gpg']['gpgKeyPublicInKeyring'] = true;
                }
            }
        }

        return $checks;
    }

    /**
     * Check if it can encrypt
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgCanEncrypt($checks = [])
    {
        $checks['gpg']['canEncrypt'] = false;
        if ($checks['gpg']['gpgKeyPublicInKeyring']) {
            $_gpg = new \gnupg();
            $_gpg->seterrormode(\gnupg::ERROR_EXCEPTION);
            $_gpg->addencryptkey(Configure::read('passbolt.gpg.serverKey.fingerprint'));
            $messageToEncrypt = 'test message';
            try {
                $encryptedMessage = $_gpg->encrypt($messageToEncrypt);
                if ($encryptedMessage !== false) {
                    $checks['gpg']['canEncrypt'] = true;
                }
            } catch (Exception $e) {
            }
        }

        return $checks;
    }

    /**
     * Check if it can encrypt and sign
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgCanEncryptSign($checks = [])
    {
        $checks['gpg']['canEncryptSign'] = false;
        if ($checks['gpg']['gpgKeyPublicInKeyring']) {
            $_gpg = new \gnupg();
            $_gpg->seterrormode(\gnupg::ERROR_EXCEPTION);
            $_gpg->addencryptkey(Configure::read('passbolt.gpg.serverKey.fingerprint'));
            $_gpg->addsignkey(Configure::read('passbolt.gpg.serverKey.fingerprint'), Configure::read('passbolt.gpg.serverKey.passphrase'));
            $messageToEncrypt = 'test message';
            try {
                $encryptedMessage2 = $_gpg->encryptsign($messageToEncrypt);
                if ($encryptedMessage2 !== false) {
                    $checks['gpg']['canEncryptSign'] = true;
                }
            } catch (Exception $e) {
            }
        }

        return $checks;
    }

    /**
     * Check if it can decrypt
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgCanDecrypt($checks = [])
    {
        $checks['gpg']['canDecrypt'] = false;
        if ($checks['gpg']['gpgKeyPublicInKeyring']) {
            if ($checks['gpg']['canEncrypt']) {
                $_gpg = new \gnupg();
                $_gpg->seterrormode(\gnupg::ERROR_EXCEPTION);
                $_gpg->adddecryptkey(Configure::read('passbolt.gpg.serverKey.fingerprint'), Configure::read('passbolt.gpg.serverKey.passphrase'));
                $_gpg->addencryptkey(Configure::read('passbolt.gpg.serverKey.fingerprint'));
                $messageToEncrypt = 'test message';
                try {
                    $encryptedMessage = $_gpg->encrypt($messageToEncrypt);
                    $decryptedMessage = $_gpg->decrypt($encryptedMessage);
                    if ($decryptedMessage === $messageToEncrypt) {
                        $checks['gpg']['canDecrypt'] = true;
                    }
                } catch (Exception $e) {
                }
            }
        }

        return $checks;
    }

    /**
     * Check if it can decrypt and verify signature
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgCanDecryptVerify($checks = [])
    {
        $checks['gpg']['canDecryptVerify'] = false;
        if ($checks['gpg']['gpgKeyPublicInKeyring']) {
            $_gpg = new \gnupg();
            $_gpg->seterrormode(\gnupg::ERROR_EXCEPTION);
            $_gpg->addencryptkey(Configure::read('passbolt.gpg.serverKey.fingerprint'));
            $_gpg->addsignkey(Configure::read('passbolt.gpg.serverKey.fingerprint'), Configure::read('passbolt.gpg.serverKey.passphrase'));
            $messageToEncrypt = 'test message';
            try {
                $encryptedMessage2 = $_gpg->encryptsign($messageToEncrypt);
                $_gpg->decryptverify($encryptedMessage2, $decryptedMessage2);
                if ($decryptedMessage2 === $messageToEncrypt) {
                    $checks['gpg']['canDecryptVerify'] = true;
                }
            } catch (Exception $e) {
            }
        }

        return $checks;
    }

    /**
     * Check if it can verify
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgCanSign($checks = [])
    {
        $checks['gpg']['canSign'] = false;
        if ($checks['gpg']['gpgKeyPublicInKeyring']) {
            $_gpg = new \gnupg();
            $_gpg->seterrormode(\gnupg::ERROR_EXCEPTION);
            $_gpg->addsignkey(Configure::read('passbolt.gpg.serverKey.fingerprint'), Configure::read('passbolt.gpg.serverKey.passphrase'));
            $messageToEncrypt = 'test message';
            try {
                $signature = $_gpg->sign($messageToEncrypt);
                if ($signature !== false) {
                    $checks['gpg']['canSign'] = true;
                }
            } catch (Exception $e) {
            }
        }

        return $checks;
    }

    /**
     * Check if it can verify
     *
     * @param array $checks List of checks
     * @return array
     */
    public static function gpgCanVerify($checks = [])
    {
        $checks['gpg']['canVerify'] = false;
        if ($checks['gpg']['canDecryptVerify']) {
            $_gpg = new \gnupg();
            $_gpg->seterrormode(\gnupg::ERROR_EXCEPTION);
            $messageToEncrypt = 'test message';

            try {
                $_gpg->addencryptkey(Configure::read('passbolt.gpg.serverKey.fingerprint'));
                $_gpg->adddecryptkey(Configure::read('passbolt.gpg.serverKey.fingerprint'), Configure::read('passbolt.gpg.serverKey.passphrase'));
                $signature = $_gpg->sign($messageToEncrypt);
                $encryptedMessage = $_gpg->encryptsign($messageToEncrypt);
                $plaintext = "";

                // Verify encrypted message
                $info = $_gpg->verify($encryptedMessage, false, $plaintext);
                if ($info !== false && isset($info['fingerprint']) && Configure::read('passbolt.gpg.serverKey.fingerprint') == $info['fingerprint']) {
                    $checks['gpg']['canVerify'] = true;
                }

                // Verify signature
                $info = $_gpg->verify($signature, false, $messageToEncrypt);
                if ($info !== false && isset($info[0]['fingerprint']) && Configure::read('passbolt.gpg.serverKey.fingerprint') == $info[0]['fingerprint']) {
                    $checks['gpg']['canVerify'] = true;
                }
            } catch (Exception $e) {
            }
        }

        return $checks;
    }
}
