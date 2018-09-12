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
namespace App\Test\Lib\Model;

use App\Utility\UuidFactory;

trait UsersModelTrait
{
    /**
     * Get a dummy resource with test data.
     * The comment returned passes a default validation.
     *
     * @param array $data Custom data that will be merged with the default content.
     * @return array Comment data
     */
    public static function getDummyUser($data = [])
    {
        $entityContent = [
            'name' => UuidFactory::uuid('user.id.dummy'),
            'username' => 'dummy@passbolt.com',
            'role_id' => UuidFactory::uuid('role.id.user'),
            'deleted' => false,
            'active' => false,
            'profile' => [
                'first_name' => 'dummy',
                'last_name' => 'content'
            ]
        ];
        $entityContent = array_merge($entityContent, $data);

        return $entityContent;
    }

    /**
     * Asserts that an object has all the attributes a user should have.
     *
     * @param object $user
     */
    protected function assertUserAttributes($user)
    {
        $attributes = ['id', 'role_id', 'username', 'active', 'deleted', 'created', 'modified'];
        $this->assertObjectHasAttributes($attributes, $user);
    }

    /**
     * Asserts than a user is soft deleted.
     *
     * @param string $id
     */
    protected function assertUserIsSoftDeleted($id)
    {
        $user = $this->Users->get($id);
        $this->assertTrue($user->deleted);

        $groupsUsers = $this->GroupsUsers->find()->where(['user_id' => $id])->count();
        $this->assertEquals(0, $groupsUsers);

        $permissions = $this->Permissions->find()->where(['aro_foreign_key' => $id])->count();
        $this->assertEquals(0, $permissions);

        $secrets = $this->Secrets->find()->where(['user_id' => $id])->count();
        $this->assertEquals(0, $secrets);

        $favorites = $this->Favorites->find()->where(['user_id' => $id])->count();
        $this->assertEquals(0, $favorites);
    }

    /**
     * Asserts than a user is not soft deleted.
     *
     * @param string $id
     */
    protected function assertUserIsNotSoftDeleted($id)
    {
        $user = $this->Users->get($id);
        $this->assertFalse($user->deleted);
    }
}
