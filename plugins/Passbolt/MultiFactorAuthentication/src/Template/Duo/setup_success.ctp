<?php
    use Cake\Core\Configure;
    use Passbolt\MultiFactorAuthentication\Utility\MfaSettings;

    $title = __('Duo multi-factor authentication is enabled!');
    $this->assign('title',	$title);
    $version = Configure::read('passbolt.version');
    $themePath = "themes/$theme/api_main.min.css?v=$version";
    $this->Html->css($themePath, ['block' => 'css', 'fullBase' => true]);
    $this->assign('pageClass', 'iframe mfa');
    $this->Html->script('app/mfa-settings.js?v=' . Configure::read('passbolt.version'), ['block' => 'js', 'fullBase' => true]);
?>
<div class="grid grid-responsive-12">
    <div class="row">
        <div class="col7 last">
            <h3><?= $title; ?></h3>
            <div class="success success-large message animated">
                <?= $this->element('successMark'); ?>
                <div class="additional-information">
                    <p>
                        <?= __('When logging in you will be asked to perform Duo Authentication.'); ?>
                    </p>
                    <p class="created date">
                        <?= __('Since')?>: <?= $body['verified']->nice(); ?>
                    </p>
                    <?= $this->element('turnOffProviderButton', ['provider' => MfaSettings::PROVIDER_DUO]); ?>
                </div>
            </div>
            <?= $this->element('manageProvidersButton'); ?>
        </div>
    </div>
</div>