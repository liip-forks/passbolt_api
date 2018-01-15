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

namespace PassboltTestData\Lib;

use Cake\Console\Shell;
use Exception;

/**
 * Data shell task.
 */
abstract class DataTask extends Shell
{
    /**
     * The entity name the data task target.
     * @var null
     */
    public $entityName = null;

    /**
     * The entity model
     * @var null
     */
    protected $_Entity = null;

    /**
     * Truncate data.
     * @var boolean
     */
    protected $_truncate = true;

    /**
     * execute() method.
     *
     * @throws Exception if the entity name is not defined
     * @return bool true on success
     */
    public function execute()
    {
        if (is_null($this->entityName)) {
            throw new Exception('Entity name not defined');
        }

        $this->loadModel($this->entityName);
        $this->_Entity = $this->{$this->entityName};

        // Truncate the table.
        if ($this->_truncate) {
            $this->_Entity->deleteAll([]);
        }

        $conn = \Cake\Datasource\ConnectionManager::get('default');
        $conn->logQueries(true);

        // Insert the data in the db.
        $data = $this->getData();
        foreach ($data as $row) {
            try {
                $this->saveEntity($row);
            } catch (Exception $e) {
                $this->err(sprintf('Data "%s" from "%s" could not be inserted', $row[array_keys($row)[0]]['id'], $this->entityName));
                $this->err(print_r($row, true));
                $this->warn($e->getMessage());
            }
        }
        $this->out('Data for entity "' . $this->entityName . '" inserted (' . count($data) . ')');

        return true;
    }

    /**
     * Insert an entity.
     *
     * @param array $data entity request data
     * @throws Exception if the entity can not be validated or saved
     * @return void
     */
    public function saveEntity($data)
    {
        $entity = $this->_Entity->newEntity();
        $entity->accessible('*', true);
        $entity->set($data);

        $errors = $entity->getErrors();
        if ($errors) {
            $this->out('Unable to validate the entity data');
            $this->out(json_encode($errors));
            $this->out(json_encode($data));
            throw new Exception('Unable to save the entity data');
        }

        if (!$this->_Entity->save($entity, ['checkRules' => false, 'atomic' => false])) {
            $errors = $entity->getErrors();
            $this->out('Unable to save the entity');
            $this->out(json_encode($errors));
            $this->out(json_encode($data));
            throw new Exception('Unable to save the entity data');
        }
    }
}
