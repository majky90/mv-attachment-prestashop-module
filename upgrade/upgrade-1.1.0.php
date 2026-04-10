<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.1.0.
 */
function upgrade_module_1_1_0($module)
{
    // Keep the update flow non-blocking. Structural setup is handled in install and prior upgrades.
    if (is_object($module) && method_exists($module, 'registerHook') && method_exists($module, 'isRegisteredInHook')) {
        if (!$module->isRegisteredInHook('actionMailSendBeforeOut')) {
            @$module->registerHook('actionMailSendBeforeOut');
        }

        if (class_exists('Hook')) {
            $optionalHookId = (int) Hook::getIdByName('actionEmailSendBefore');
            if ($optionalHookId > 0 && !$module->isRegisteredInHook('actionEmailSendBefore')) {
                @$module->registerHook('actionEmailSendBefore');
            }
        }
    }

    return true;
}
