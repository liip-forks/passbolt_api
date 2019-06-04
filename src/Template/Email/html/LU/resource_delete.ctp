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
 * @since         2.0.0
 */
use App\Utility\Purifier;
use Passbolt\EmailNotificationSettings\Utility\EmailNotificationSettings;
use Cake\Routing\Router;
if (PHP_SAPI === 'cli') {
    Router::fullBaseUrl($body['fullBaseUrl']);
}
$user = $body['user'];
$resource = $body['resource'];
echo $this->element('Email/module/avatar',[
    'url' => Router::url(DS . $user->profile->avatar->url['small'], true),
    'text' => $this->element('Email/module/avatar_text', [
        'username' => Purifier::clean($user->username),
        'first_name' => Purifier::clean($user->profile->first_name),
        'last_name' => Purifier::clean($user->profile->last_name),
        'datetime' => $resource->modified,
        'text' => __('{0} deleted the password {1}', null, Purifier::clean($resource->name))
    ])
]);

$text = __('Name: {0}', Purifier::clean($resource->name)) . '<br/>';

if (EmailNotificationSettings::get('show.username')) {
    $text .= __('Username: {0}', Purifier::clean($resource->username)) . '<br/>';
}
if (EmailNotificationSettings::get('show.uri')) {
    $text .= __('URL: {0}', Purifier::clean($resource->uri)) . '<br/>';
}
if (EmailNotificationSettings::get('show.description')) {
    $text .= __('Description: {0}', Purifier::clean($resource->description)) . '<br/>';
}
echo $this->element('Email/module/text', [
    'text' => $text
]);

echo $this->element('Email/module/button', [
    'url' => Router::url('/', true),
    'text' => __('log in passbolt')
]);
