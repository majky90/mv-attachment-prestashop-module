<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.0.4.
 */
function upgrade_module_1_0_4($module): bool
{
    if (is_object($module) && method_exists($module, 'registerHook') && method_exists($module, 'isRegisteredInHook')) {
        if (!$module->isRegisteredInHook('actionMailSendBeforeOut')) {
            $module->registerHook('actionMailSendBeforeOut');
        }

        if (class_exists('Hook')) {
            $optionalHookId = (int) Hook::getIdByName('actionEmailSendBefore');
            if ($optionalHookId > 0 && !$module->isRegisteredInHook('actionEmailSendBefore')) {
                $module->registerHook('actionEmailSendBefore');
            }
        }
    }

    return true;
}
