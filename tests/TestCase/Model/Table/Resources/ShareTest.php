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
 * @since         2.0.0
 */

namespace App\Test\TestCase\Model\Table\Resources;

use App\Model\Entity\Permission;
use App\Test\Lib\AppTestCase;
use App\Utility\UuidFactory;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

class ShareTest extends AppTestCase
{

    public $Resources;

    public $fixtures = ['app.Base/permissions', 'app.Base/resources', 'app.Base/users', 'app.Base/profiles', 'app.Base/avatars', 'app.Base/gpgkeys', 'app.Base/roles', 'app.Base/groups_users', 'app.Base/groups', 'app.Base/secrets'];

    public function setUp()
    {
        parent::setUp();
        $this->Resources = TableRegistry::get('Resources');
        $this->Permissions = TableRegistry::get('Permissions');
    }

    public function tearDown()
    {
        unset($this->Resources);
        unset($this->Permissions);

        parent::tearDown();
    }

    protected function getValidSecret()
    {
        return '-----BEGIN PGP MESSAGE-----
Version: GnuPG v1.4.12 (GNU/Linux)

hQEMAwvNmZMMcWZiAQf9HpfcNeuC5W/VAzEtAe8mTBUk1vcJENtGpMyRkVTC8KbQ
xaEr3+UG6h0ZVzfrMFYrYLolS3fie83cj4FnC3gg1uijo7zTf9QhJMdi7p/ASB6N
y7//8AriVqUAOJ2WCxAVseQx8qt2KqkQvS7F7iNUdHfhEhiHkczTlehyel7PEeas
SdM/kKEsYKk6i4KLPBrbWsflFOkfQGcPL07uRK3laFz8z4LNzvNQOoU7P/C1L0X3
tlK3vuq+r01zRwmflCaFXaHVifj3X74ljhlk5i/JKLoPRvbxlPTevMNag5e6QhPQ
kpj+TJD2frfGlLhyM50hQMdJ7YVypDllOBmnTRwZ0tJFAXm+F987ovAVLMXGJtGO
P+b3c493CfF0fQ1MBYFluVK/Wka8usg/b0pNkRGVWzBcZ1BOONYlOe/JmUyMutL5
hcciUFw5
=TcQF
-----END PGP MESSAGE-----';
    }

    public function testSuccess()
    {
        // Define actors of this tests
        $resourceId = UuidFactory::uuid('resource.id.cakephp');
        $resource = $this->Resources->get($resourceId, ['contain' => ['Permissions', 'Secrets']]);
        // Users
        $userAId = UuidFactory::uuid('user.id.ada');
        $userBId = UuidFactory::uuid('user.id.betty');
        $userEId = UuidFactory::uuid('user.id.edith');
        $userFId = UuidFactory::uuid('user.id.frances');
        $userJId = UuidFactory::uuid('user.id.jean');
        $userKId = UuidFactory::uuid('user.id.kathleen');
        $userLId = UuidFactory::uuid('user.id.lynne');
        $userMId = UuidFactory::uuid('user.id.marlyn');
        $userNId = UuidFactory::uuid('user.id.nancy');
        // Groups
        $groupBId = UuidFactory::uuid('group.id.board');
        $groupFId = UuidFactory::uuid('group.id.freelancer');
        $groupAId = UuidFactory::uuid('group.id.accounting');

        // Build the changes.
        $changes = [];
        $secrets = [];

        // Users permissions changes.
        // Change the permission of the user Ada to read (no users are expected to be added or removed).
        $changes[] = ['id' => UuidFactory::uuid("permission.id.$resourceId-$userAId"), 'type' => Permission::READ];
        // Delete the permission of the user Betty.
        $changes[] = ['id' => UuidFactory::uuid("permission.id.$resourceId-$userBId"), 'delete' => true];
        // Add an owner permission for the user Edith
        $changes[] = ['aro' => 'User', 'aro_foreign_key' => $userEId, 'type' => Permission::OWNER];
        $secrets[] = ['user_id' => $userEId, 'data' => $this->getValidSecret()];

        // Groups permissions changes.
        // Change the permission of the group Board (no users are expected to be added or removed).
        $changes[] = ['id' => UuidFactory::uuid("permission.id.$resourceId-$groupBId"), 'type' => Permission::OWNER];
        // Delete the permission of the group Freelancer.
        $changes[] = ['id' => UuidFactory::uuid("permission.id.$resourceId-$groupFId"), 'delete' => true];
        // Add a read permission for the group Accounting.
        $changes[] = ['aro' => 'Group', 'aro_foreign_key' => $groupAId, 'type' => Permission::READ];
        $secrets[] = ['user_id' => $userFId, 'data' => $this->getValidSecret()];

        // Share dry run.
        $result = $this->Resources->share($resource, $changes, $secrets);
        $this->assertNotFalse($result);
        $this->markTestIncomplete('test access');
    }

    /*
     * The format validation is done by the Permissions model.
     * @see App\Test\TestCase\Model\Table\Permissions\PatchEntitiesWithChangesTest
     */
    public function testValidationError()
    {
        $resourceApacheId = UuidFactory::uuid('resource.id.apache');
        $resourceAprilId = UuidFactory::uuid('resource.id.april');
        $userAId = UuidFactory::uuid('user.id.ada');
        $userEId = UuidFactory::uuid('user.id.edith');
        $testCases = [
            // Check some validation format rules, just to ensure they are well returned by the
            // PatchEntitiesWithChanges function
            'cannot update a permission that does not exist' => [
                'errorField' => 'permissions.0.id.permission_exists',
                'data' => [
                    'permissions' => [[
                        'id' => UuidFactory::uuid()]]]
            ],
            'cannot delete a permission of another resource' => [
                'errorField' => 'permissions.0.id.permission_exists',
                'data' => [
                    'permissions' => [[
                        'id' => UuidFactory::uuid("permission.id.$resourceAprilId-$userAId"),
                        'delete' => true]]
                ]
            ],
            'cannot add a permission with invalid data' => [
                'errorField' => 'permissions.0.aro_foreign_key._required',
                'data' => [
                    'permissions' => [
                        ['aro' => 'User', 'type' => Permission::OWNER]]]
            ],
            'cannot update a permission with a wrong permission type' => [
                'errorField' => 'permissions.0.type.inList',
                'data' => [
                    'permissions' => [[
                        'id' => UuidFactory::uuid("permission.id.$resourceApacheId-$userAId"), 'type' => 42]]]
            ],
            'cannot add a secret with invalid data' => [
                'errorField' => 'secrets.0.data.isValidGpgMessage',
                'data' => [
                    'permissions' => [[
                        'aro' => 'User', 'aro_foreign_key' => $userEId, 'type' => Permission::READ]],
                    'secrets' => [[
                        'user_id' => $userEId, 'data' => 'INVALID GPG MESSAGE']]
                ]
            ],
            // Test build rules.
            'cannot remove the latest owner' => [
                'errorField' => 'permissions.at_least_one_owner',
                'data' => [
                    'permissions' => [[
                        'id' => UuidFactory::uuid("permission.id.$resourceApacheId-$userAId"),
                        'delete' => true]]
                ]
            ],
            'cannot add a permissions for a deleted user' => [
                'errorField' => 'permissions.0.aro_foreign_key.aro_exists',
                'data' => [
                    'permissions' => [[
                        'aro' => 'User',
                        'aro_foreign_key' => UuidFactory::uuid('user.id.sofia'),
                        'type' => Permission::OWNER]]]
            ],
            'cannot add a permissions for an inactive user' => [
                'errorField' => 'permissions.0.aro_foreign_key.aro_exists',
                'data' => [
                    'permissions' => [[
                        'aro' => 'User',
                        'aro_foreign_key' => UuidFactory::uuid('user.id.ruth'),
                        'type' => Permission::OWNER]]]
            ],
        ];

        foreach ($testCases as $caseLabel => $case) {
            $resource = $this->Resources->get($resourceApacheId, ['contain' => ['Permissions', 'Secrets']]);
            $permissions = Hash::get($case, 'data.permissions', []);
            $secrets = Hash::get($case, 'data.secrets', []);
            $this->Resources->share($resource, $permissions, $secrets);
            $this->assertEntityError($resource, $case['errorField']);
        }
    }

    public function testErrorRuleResourceIsNotSoftDeleted()
    {
        $resourceId = UuidFactory::uuid('resource.id.jquery');
        $resource = $this->Resources->get($resourceId, ['contain' => ['Permissions', 'Secrets']]);
        $data = [[
            'aro' => 'User',
            'aro_foreign_key' => UuidFactory::uuid('user.id.ada'),
            'type' => Permission::OWNER]];
        $this->Resources->share($resource, $data);
        $errorField = 'id.resource_is_not_soft_deleted';
        $this->assertEntityError($resource, $errorField);
    }
}
