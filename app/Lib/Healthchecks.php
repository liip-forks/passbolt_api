<?php
App::uses('Validation', 'Utility');
App::uses('Migration', 'Lib/Migration');
App::uses('HttpSocket', 'Network/Http');

// Uses Gpg Utility.
if (!class_exists('\Passbolt\Gpg')) {
	App::import( 'Model/Utility', 'Gpg' );
}

/**
 * Class Healthchecks
 *
 * @copyright (c) 2017 Passbolt SARL
 * @licence GNU Affero General Public License http://www.gnu.org/licenses/agpl-3.0.en.html
 */
class Healthchecks {
    static public function all() {
        $checks = [];
        $checks = array_merge(Healthchecks::environment(), $checks);
        $checks = array_merge(Healthchecks::configFiles(), $checks);
        $checks = array_merge(Healthchecks::core(), $checks);
        $checks = array_merge(Healthchecks::ssl(), $checks);
        $checks = array_merge(Healthchecks::database(), $checks);
        $checks = array_merge(Healthchecks::gpg(), $checks);
        $checks = array_merge(Healthchecks::application(), $checks);
        $checks = array_merge(Healthchecks::devTools(), $checks);
        return $checks;
    }

/**
 * Return core checks:
 * - phpVersion: php version is superior to 5.2.8
 * - pcre: unicode support
 * - tmpWritable: the TMP directory is writable for the current user
 *
 * @return array
 */
    static public function environment() {
        $checks['environment']['phpVersion'] = (version_compare(PHP_VERSION, '5.2.8', '>='));
        $checks['environment']['pcre'] = (Validation::alphaNumeric('cakephp'));
		$checks['environment']['tmpWritable'] = self::_checkRecursiveDirectoryWritable(TMP);
        $checks['environment']['imgPublicWritable'] = self::_checkRecursiveDirectoryWritable(IMAGES . 'public/');
        return $checks;
    }

	/**
	 * Check that a directory and its content are writable
	 *
	 * @param $path
	 * @return boolean
	 */
	static private function _checkRecursiveDirectoryWritable($path) {
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
		foreach ( $iterator as $name => $fileInfo ) {
			if (in_array($fileInfo->getFilename(), ['.', '..', 'empty'])) {
				continue;
			}
			if (!is_writable($name)) {
				return false;
			}
		}

		return true;
	}

/**
 * Return config file checks:
 * - configFiles.core true if file is present
 * - configFiles.app true if file is present
 * - configFiles.database true if file is present
 * - configFiles.email true if file is present
 *
 * @return array
 */
    static public function configFiles() {
        $files = ['core', 'app', 'database', 'email'];
        $checks = [];
        foreach($files as $file) {
            $checks['configFile'][$file] = (file_exists(APP . 'Config' . DS . $file . '.php'));
        }
        return $checks;
    }

/**
 * Check core file configuration
 * - cache: settings are set
 * - debugDisabled: the core.debug is set to 0
 * - salt: true if non default salt is used
 * - cipherSeed: true if non default cipherSeed is used
 *
 * @return array
 */
    static public function core() {
        $settings = Cache::settings();
        $checks['core']['cache'] = (!empty($settings));
        $checks['core']['debugDisabled'] = (Configure::read('debug') === 0);
        $checks['core']['salt'] = (Configure::read('Security.salt') !== 'DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi');
        $checks['core']['cipherSeed'] = (Configure::read('Security.cipherSeed') !== '76859309657453542496749683645');
        $checks['core']['fullBaseUrl'] = (Configure::read('App.fullBaseUrl') !== null);
        $checks['core']['validFullBaseUrl'] = Validation::url(Configure::read('App.fullBaseUrl'), true);
        $checks['core']['info']['fullBaseUrl'] = Configure::read('App.fullBaseUrl');

        // Check if the URL is reachable
        $checks['core']['fullBaseUrlReachable'] = false;
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET'
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $url = Configure::read('App.fullBaseUrl') . '/healthcheck/status.json';
            $response = @file_get_contents($url, false, $context);
            if(isset($response)) {
                $json = json_decode($response);
                if(isset($json->body)) {
                    $checks['core']['fullBaseUrlReachable'] = ($json->body === 'OK');
                }
            }
        } catch(Exception $e) {
        }

        return $checks;
    }

/**
 * Return database checks:
 * - connect:  can connect to the detabase
 * - tablesPrefixes: not using tablesPrefix
 * - tableCount: at least one table is present
 * - info.tableCount: number of tables installed
 * - defaultContent: some default contennt (4 roles)
 *
 * @return array
 */
    static public function database() {
        // init results to false by default
        $checks = [];
        $cases = ['connect', 'supportedBackend', 'tablesPrefix', 'tablesCount', 'defaultContent'];
        foreach ($cases as $case) {
            $checks['database'][$case] = false;
        }

        // No point to check anything if config file is not present
        $config = Healthchecks::configFiles();
        if(!$config['configFile']['database'])  {
            return $checks;
        }

        // Check config file content
        include_once APP . 'Config' . DS . 'database.php';
        $db = new DATABASE_CONFIG();
        if($db->default['datasource'] == 'Database/Mysql') {
            $checks['database']['supportedBackend'] = true;
        }
        if($db->default['prefix'] == '') {
            // Database prefix are not supported
            // https://github.com/passbolt/passbolt_api/issues/56
            $checks['database']['tablesPrefix'] = true;
        }

        // Check if can connect to database
        try {
            App::uses('ConnectionManager', 'Model');
            ConnectionManager::getDataSource('default');
            $checks['database']['connect'] = true;
        } catch (Exception $connectionError) {
            return $checks;
        }

        // Check if tables are present
        $checks['database']['info']['tablesCount'] = 0;
        try {
            $User = Common::getModel('User');
            $tables = $User->query('show tables;');
            if( isset($tables) && sizeof($tables)) {
                $checks['database']['tablesCount'] = (sizeof($tables) > 0);
                $checks['database']['info']['tablesCount'] = sizeof($tables);
            }
        } catch (Exception $connectionError) {
            return $checks;
        }

        // Check if some default data is present
        // We only check the number of roles
        try {
            $Role = Common::getModel('Role');
            $i = $Role->find('count');
            $checks['database']['defaultContent'] = ($i > 3);
        } catch (Exception $e) {
            return $checks;
        }

        return $checks;
    }

/**
 * Application checks
 * - latestVersion: true if using latest version
 * - schema: schema up to date no need to do a migration
 * - info.remoteVersion
 * - sslForce: enforcing the use of SSL
 * - seleniumDisabled: true if selenium API is disabled
 * - registrationClosed: true if registration is not open
 * - jsProd: true if using minified/concatenated javascript
 *
 * @return array $checks
 * @access private
 */
    static public function application() {
        try {
            $checks['application']['info']['remoteVersion'] = Migration::getLatestTagName();
            $checks['application']['latestVersion'] = Migration::isLatestVersion();
        } catch (exception $e) {
            $checks['latestVersion'] = null;
        }
        $checks['application']['schema'] = !Migration::needMigration();
        $checks['application']['robotsIndexDisabled'] = !Configure::read('App.meta.robots.index');
        $checks['application']['sslForce'] = Configure::read('App.ssl.force');
        $checks['application']['sslFullBaseUrl'] = !(strpos(Configure::read('App.fullBaseUrl'),'https') === false);
        $checks['application']['seleniumDisabled'] = !Configure::read('App.selenium.active');
        $checks['application']['registrationClosed'] = !Configure::read('App.registration.public');
        $checks['application']['jsProd'] = (Configure::read('App.js.build') === 'production');
		$checks['application']['emailNotificationEnabled'] = !(preg_match('/false/', json_encode(Configure::read('EmailNotification.send'))) === 1);

        $checks = array_merge(Healthchecks::appUser(), $checks);
        return $checks;
    }

/**
 * Gpg checks
 *
 * @return array $checks
 * @access private
 */
     static public function gpg() {
     	 // Check gpg php module is installed and enabled
         $checks['gpg']['lib'] = (class_exists('gnupg'));

         // Check fingerprint is set
         $checks['gpg']['gpgKey'] = (Configure::read('GPG.serverKey.fingerprint') != null);
         $checks['gpg']['gpgKeyNotDefault'] = (Configure::read('GPG.serverKey.fingerprint') != '2FC8945833C51946E937F9FED47B0811573EE67E');

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

         // Check key file exist and are readable
		 $checks['gpg']['gpgKeyPublic'] = (Configure::read('GPG.serverKey.public') != null);
		 $checks['gpg']['gpgKeyPublicReadable'] = is_readable(Configure::read('GPG.serverKey.public'));
		 $checks['gpg']['gpgKeyPrivate'] = (Configure::read('GPG.serverKey.private') != null);
		 $checks['gpg']['info']['gpgKeyPrivate'] = Configure::read('GPG.serverKey.private');
		 $checks['gpg']['gpgKeyPrivateReadable'] = is_readable(Configure::read('GPG.serverKey.private'));

		 // Check that the private key match the fingerprint
		 $checks['gpg']['gpgKeyPrivateFingerprint'] = false;
		 $checks['gpg']['gpgKeyPublicFingerprint'] = false;
		 $checks['gpg']['gpgKeyPublicEmail'] = false;
		 if($checks['gpg']['gpgKeyPublicReadable'] && $checks['gpg']['gpgKeyPrivateReadable'] && $checks['gpg']['gpgKey']) {
			 $gpg = new Passbolt\Gpg();
			 $privateKeydata = file_get_contents(Configure::read('GPG.serverKey.private'));
			 $privateKeyInfo = $gpg->getKeyInfo($privateKeydata);
			 if ($privateKeyInfo['fingerprint'] === Configure::read('GPG.serverKey.fingerprint')) {
				 $checks['gpg']['gpgKeyPrivateFingerprint'] = true;
			 }
			 $publicKeydata = file_get_contents(Configure::read('GPG.serverKey.public'));
			 $publicKeyInfo = $gpg->getKeyInfo($publicKeydata);
			 if ($publicKeyInfo['fingerprint'] === Configure::read('GPG.serverKey.fingerprint')) {
				$checks['gpg']['gpgKeyPublicFingerprint'] = true;
			 }
			 App::uses('Gpgkey', 'Model');
			 $checks['gpg']['gpgKeyPublicEmail'] = Gpgkey::uidContainValidEmail($publicKeyInfo['uid']);
		 }

		 // Check that the private key is present in the keyring.
		 $checks['gpg']['gpgKeyPrivateInKeyring'] = false;
		 if ($checks['gpg']['gpgHome'] && Configure::read('GPG.serverKey.fingerprint')) {
			 $gpg = new Passbolt\Gpg();
			 $keyInfo = $gpg->getKeyInfoFromKeyring(Configure::read('GPG.serverKey.fingerprint'));
			 if (!empty($keyInfo)) {
				 $checks['gpg']['gpgKeyPrivateInKeyring'] = true;
			 }
		 }

		 // Check that the server can be used for encrypting/decrypting
		 if ($checks['gpg']['gpgKeyPrivateInKeyring']) {
			 $_gpg = new gnupg();
			 $_gpg->addencryptkey(Configure::read('GPG.serverKey.fingerprint'));
			 $_gpg->addsignkey(Configure::read('GPG.serverKey.fingerprint'), Configure::read('GPG.serverKey.passphrase'));
			 $messageToEncrypt = 'test message';
			 $encryptedMessage = '';

			 // Try to encrypt a message.
			 try {
				 $encryptedMessage = $_gpg->encryptsign($messageToEncrypt);
				 if ($encryptedMessage !== false) {
					 $checks['gpg']['canEncrypt'] = true;
				 } else {
					 $checks['gpg']['canEncrypt'] = false;
				 }
			 }
			 catch ( Exception $e ) {
				 $checks['gpg']['canEncrypt'] = false;
			 }

			 // Try to decrypt the message.
			 if ($checks['gpg']['canEncrypt']) {
				 $_gpg->adddecryptkey(Configure::read('GPG.serverKey.fingerprint'), Configure::read('GPG.serverKey.passphrase'));
				 $decryptedMessage = '';

				 try {
					 $_gpg->decryptverify($encryptedMessage, $decryptedMessage);
					 if ($decryptedMessage !== $messageToEncrypt) {
						 $checks['gpg']['canDecrypt'] = false;
					 } else {
						 $checks['gpg']['canDecrypt'] = true;
					 }
				 }
				 catch ( Exception $e ) {
					 $checks['gpg']['canDecrypt'] = false;
				 }
			 }
		 }

		 return $checks;
     }

/**
 * Check that users are set in the database
 * - app.adminCount there is at least an admin in the database
 *
 * @access private
 * @return array
 */
    static public function appUser() {
        $checks['application']['adminCount'] = false;

        // no point checking for records if can not connect
        $checks = Healthchecks::database();
        if (!$checks['database']['connect']) {
            return $checks;
        }

        // check number of admin user
        $User = Common::getModel('User');
        try {
            $i = $User->find('count', [
                'conditions' => ['Role.name' => Role::ADMIN],
                'contain' => ['Role' => [
                    'fields' => [
                        'Role.id',
                        'Role.name'
                    ]
                ]]
            ]);
            $checks['application']['adminCount'] = ($i > 0);
        } catch(Exception $e) {
        }

        return $checks;
    }

/**
 * Development tools check
 * - devTools.debugKit debugkit plugin is present
 * - devTools.phpunit phpunit is installed
 * - devTools.phpunitVersion phpunit version == 3.7.38
 *
 * @return array
 * @access private
 */
    static public function devTools() {
        $checks['devTools']['phpunit'] = (class_exists('PHPUnit_Runner_Version'));
        $checks['devTools']['phpunitVersion'] = ($checks['devTools']['phpunit'] && PHPUnit_Runner_Version::id() === '3.7.38');
        $checks['devTools']['info']['phpunitVersion'] = PHPUnit_Runner_Version::id();
        return $checks;
    }

/**
 * SSL certs check
 * - devTools.debugKit debugkit plugin is present
 * - devTools.phpunit phpunit is installed
 * - devTools.phpunitVersion phpunit version == 3.7.38
 *
 * @return array
 * @access private
 */
    static public function ssl() {
        $checks['ssl'] = [
            'peerValid' => false,
            'hostValid' => false,
            'notSelfSigned' => false
        ];

        // No point to check anything if Core file is not valid
        $config = Healthchecks::core();
        if (!$config['core']['fullBaseUrlReachable'])  {
            return $checks;
        }
        $url = Configure::read('App.fullBaseUrl') . '/healthcheck/status.json';

        // Check peer
        try {
            $HttpSocket = new HttpSocket(array(
                'ssl_verify_peer' => true,
                'ssl_verify_host' => false,
                'ssl_allow_self_signed' => true
            ));
            $response = $HttpSocket->get($url);
            $checks['ssl']['peerValid'] = $response->isOk();
        } catch(Exception $e) {
            $checks['ssl']['info'] = $e->getMessage();
            return $checks;
        }

        // Check host
        try {
            $HttpSocket = new HttpSocket(array(
                'ssl_verify_peer' => true,
                'ssl_verify_host' => true,
                'ssl_allow_self_signed' => true
            ));
            $response = $HttpSocket->get($url);
            $checks['ssl']['hostValid'] = $response->isOk();
        } catch(Exception $e) {
            $checks['ssl']['info'] = $e->getMessage();
            return $checks;
        }

        // Check self-signed
        try {
            $HttpSocket = new HttpSocket(array(
                'ssl_verify_peer' => true,
                'ssl_verify_host' => true,
                'ssl_allow_self_signed' => false
            ));
            $response = $HttpSocket->get($url);
            $checks['ssl']['notSelfSigned'] = $response->isOk();
        } catch(Exception $e) {
            return $checks;
        }

        return $checks;
    }
}
