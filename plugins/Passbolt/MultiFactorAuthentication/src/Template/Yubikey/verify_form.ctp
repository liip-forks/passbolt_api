<?php
    use Cake\Core\Configure;
    use Passbolt\MultiFactorAuthentication\Utility\MfaSettings;

    $title = __('Multi factor authentication verification');
    $this->assign('title', $title);
    $this->Html->css('themes/anew/api_login.min.css?v=' . Configure::read('passbolt.version'), ['block' => 'css', 'fullBase' => true]);
    $this->assign('pageClass', 'login');
?>
<div class="login-form ">
    <h1>
        <?= __('Plug the yubikey into a USB port and touch it.'); ?>
    </h1>
    <?= $this->form->create($verifyForm); ?>
    <?= $this->form->control('hotp', [
        'label' => 'Yubikey OTP',
        'type' => 'password'
    ]); ?>
    <div class="input checkbox">
        <input type="checkbox" name="remember" value="remember" id="remember">
        <label for="remember" ><?= __('Remember this device for a month.'); ?></label>
    </div>
    <?= $this->element('formActions', ['providers' => $providers, 'currentProvider' => MfaSettings::PROVIDER_YUBIKEY]); ?>
    <?= $this->form->end(); ?>
</div>