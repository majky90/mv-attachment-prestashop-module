<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.1.1.
 */
function upgrade_module_1_1_1($module)
{
    try {
        if (class_exists('Logger')) {
            Logger::addLog('mv_attachment: starting upgrade 1.1.1', 1, null, 'Module', null, true);
        }

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

        if (class_exists('Logger')) {
            Logger::addLog('mv_attachment: upgrade 1.1.1 finished successfully', 1, null, 'Module', null, true);
        }
    } catch (Exception $e) {
        if (class_exists('Logger')) {
            Logger::addLog('mv_attachment: upgrade 1.1.1 exception: ' . $e->getMessage(), 3, null, 'Module', null, true);
        }
    }

    // Keep upgrade non-blocking in all cases.
    return true;
}
