<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.0.1.
 */
function upgrade_module_1_0_1($module): bool
{
    $ok = true;

    if (is_object($module) && method_exists($module, 'registerHook') && method_exists($module, 'isRegisteredInHook')) {
        if (!$module->isRegisteredInHook('actionMailSendBeforeOut')) {
            $ok = $ok && (bool) $module->registerHook('actionMailSendBeforeOut');
        }

        if (class_exists('Hook')) {
            $optionalHookId = (int) Hook::getIdByName('actionEmailSendBefore');
            if ($optionalHookId > 0 && !$module->isRegisteredInHook('actionEmailSendBefore')) {
                $ok = $ok && (bool) $module->registerHook('actionEmailSendBefore');
            }
        }
    }

    $uploadDir = rtrim(_PS_UPLOAD_DIR_, '/\\') . DIRECTORY_SEPARATOR . 'mv_attachment' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return true;
    }

    @file_put_contents($uploadDir . 'index.php', "<?php\nexit;\n");
    @file_put_contents($uploadDir . '.htaccess', "Deny from all\n");

    return true;
}
