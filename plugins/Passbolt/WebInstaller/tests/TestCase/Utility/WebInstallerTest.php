<?php
/**
 * Passbolt ~ Open source password manager for teams
 * Copyright (c) Passbolt SARL (https://www.passbolt.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Passbolt SARL (https://www.passbolt.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.passbolt.com Passbolt(tm)
 * @since         2.5.0
 */
namespace Passbolt\WebInstaller\Test\TestCase\Utility;

use App\Model\Entity\AuthenticationToken;
use App\Model\Entity\Role;
use Cake\Core\Configure;
use Cake\Core\Exception\Exception;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Passbolt\WebInstaller\Test\Lib\ConfigurationTrait;
use Passbolt\WebInstaller\Test\Lib\DatabaseTrait;
use Passbolt\WebInstaller\Utility\DatabaseConfiguration;
use Passbolt\WebInstaller\Utility\WebInstaller;

class WebInstallerTest extends TestCase
{
    use ConfigurationTrait;
    use DatabaseTrait;

    public function setUp()
    {
        parent::setUp();
        $this->skipTestIfNotWebInstallerFriendly();
        $this->backupConfiguration();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->restoreConfiguration();
    }

    public function testWebInstallerUtilityInitDatabaseConnectionSuccess()
    {
        $webInstaller = new WebInstaller(null);
        $databaseSettings = Configure::read('Testing.Datasources.test');
        $webInstaller->setSettings('database', $databaseSettings);
        $webInstaller->initDatabaseConnection();
        $connection = ConnectionManager::get('test');
        $connected = DatabaseConfiguration::testConnection($connection);
        $this->assertTrue($connected);
    }

    public function testWebInstallerUtilityInitDatabaseConnectionError()
    {
        $this->markTestIncomplete('Cannot be tested, the PDO Exception is not caught by the DatabaseConfiguration::testConnection function when executed in a testsuite. Isolating the tests make it working but break other tests such as the GpgGenerateKey tests.');
        $webInstaller = new WebInstaller(null);
        $databaseSettings = Configure::read('Testing.Datasources.test');
        $databaseSettings['host'] = 'invalid-host';
        $webInstaller->setSettings('database', $databaseSettings);
        $webInstaller->initDatabaseConnection();
        $connection = ConnectionManager::get('test');
        $connected = DatabaseConfiguration::testConnection($connection);
        $this->assertFalse($connected);
    }

    public function testWebInstallerUtilityGpgGenerateKeySuccess()
    {
        $webInstaller = new WebInstaller(null);
        $gpgSettings = [
            'name' => 'Aurore Avarguès-Weber',
            'email' => 'invalid-email',
            'comment' => 'Bees are everything'
        ];
        $webInstaller->setSettings('gpg', $gpgSettings);
        $webInstaller->generateGpgKey();

        $gpgSettings = $webInstaller->getSettings('gpg');
        $this->assertNotNull($gpgSettings['fingerprint']);
        $this->assertEquals(Configure::read('passbolt.gpg.serverKey.public'), $gpgSettings['public']);
        $this->assertEquals(Configure::read('passbolt.gpg.serverKey.private'), $gpgSettings['private']);
        $this->assertFileExists(Configure::read('passbolt.gpg.serverKey.public'));
        $this->assertFileExists(Configure::read('passbolt.gpg.serverKey.private'));
    }

    public function testWebInstallerUtilityGpgImportKeySuccess()
    {
        $webInstaller = new WebInstaller(null);
        $gpgSettings = [
            'armored_key' => file_get_contents(PASSBOLT_TEST_DATA_GPGKEY_PATH . DS . 'server_prod_unsecure_private.key')
        ];
        $webInstaller->setSettings('gpg', $gpgSettings);
        $webInstaller->importGpgKey();

        $gpgSettings = $webInstaller->getSettings('gpg');
        $this->assertNotNull($gpgSettings['fingerprint']);
        $this->assertEquals(Configure::read('passbolt.gpg.serverKey.public'), $gpgSettings['public']);
        $this->assertEquals(Configure::read('passbolt.gpg.serverKey.private'), $gpgSettings['private']);
        $this->assertFileExists(Configure::read('passbolt.gpg.serverKey.public'));
        $this->assertFileExists(Configure::read('passbolt.gpg.serverKey.private'));
    }

    public function testWebInstallerUtilityWritePassboltConfigFileSuccess()
    {
        $webInstaller = new WebInstaller(null);

        // Add the database configuration.
        $databaseSettings = Configure::read('Testing.Datasources.test');
        $webInstaller->setSettings('database', $databaseSettings);

        // Add the gpg configuration to generate a new server key.
        $gpgSettings = [
            'name' => 'Aurore Avarguès-Weber',
            'email' => 'invalid-email',
            'comment' => 'Bees are everything'
        ];
        $webInstaller->setSettings('gpg', $gpgSettings);
        $webInstaller->generateGpgKey();

        // Add the email configuration.
        $emailSettings = [
            'sender_name' => 'Passbolt Test',
            'sender_email' => 'test@passbolt.com',
            'host' => 'unreachable_host',
            'tls' => true,
            'port' => 587,
            'username' => 'test@passbolt.com',
            'password' => 'password',
            'send_test_email' => true,
            'email_test_to' => 'test@passbolt.com',
        ];
        $webInstaller->setSettings('email', $emailSettings);

        // Add the options configuration.
        $optionsSettings = [
            'full_base_url' => Configure::read('app.full_base_url'),
            'public_registration' => 0,
            'force_ssl' => 0
        ];
        $webInstaller->setSettings('options', $optionsSettings);

        $webInstaller->writePassboltConfigFile();
        $this->assertFileExists(CONFIG . 'passbolt.php');
    }

    public function testWebInstallerUtilityInstallDatabaseSuccess()
    {
        $webInstaller = new WebInstaller(null);
        $databaseSettings = Configure::read('Testing.Datasources.test');
        $webInstaller->setSettings('database', $databaseSettings);
        $webInstaller->initDatabaseConnection();
        $this->truncateTables();
        $webInstaller->installDatabase();
        try {
            DatabaseConfiguration::validateSchema();
        } catch (Exception $e) {
            $this->assertTrue(false);
        }
        $this->assertTrue(true);
    }

    public function testWebInstallerUtilityCreateFirstUserSuccess()
    {
        $webInstaller = new WebInstaller(null);
        $databaseSettings = Configure::read('Testing.Datasources.test');
        $webInstaller->setSettings('database', $databaseSettings);
        $webInstaller->initDatabaseConnection();
        $this->truncateTables();
        $webInstaller->installDatabase();
        $Users = TableRegistry::get('Users');
        $roleAdminId = $Users->Roles->getIdByName(Role::ADMIN);
        $userSettings = [
            'username' => 'aurore@passbolt.com',
            'profile' => [
                'first_name' => 'Aurore',
                'last_name' => 'Avarguès-Weber',
            ],
            'deleted' => false,
            'role_id' => $roleAdminId
        ];
        $webInstaller->setSettings('first_user', $userSettings);
        $webInstaller->createFirstUser();

        $user = $Users->find()
            ->where(['username' => 'aurore@passbolt.com'])
            ->contain(['Profiles', 'AuthenticationTokens'])
            ->first();
        $this->assertEquals($userSettings['username'], $user->username);
        $this->assertEquals($roleAdminId, $user->role_id);
        $this->assertEquals(false, $user->deleted);
        $this->assertEquals(false, $user->active);
        $this->assertEquals($userSettings['profile']['first_name'], $user->profile->first_name);
        $this->assertEquals($userSettings['profile']['last_name'], $user->profile->last_name);
        $this->assertEquals(AuthenticationToken::TYPE_REGISTER, $user->authentication_tokens[0]->type);
    }

    public function testWebInstallerUtilityWriteLicenseFile()
    {
        if (file_exists(PLUGINS . DS . 'Passbolt' . DS . 'License')) {
            $webInstaller = new WebInstaller(null);
            $licenseSettings = [
                'license_key' => file_get_contents(PLUGINS . DS . 'Passbolt' . DS . 'License' . DS . 'tests' . DS . 'data' . DS . 'license' . DS . 'license_dev')
            ];
            $webInstaller->setSettings('license', $licenseSettings);

            $webInstaller->writeLicenseFile();
            $this->assertFileExists(CONFIG . 'license');
        }
        $this->assertTrue(true);
    }
}
