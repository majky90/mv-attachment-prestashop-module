<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.1.0.
 */
function upgrade_module_1_1_0($module): bool
{
    $tableName = _DB_PREFIX_ . 'mv_attachment';

    // Keep update non-blocking and idempotent to avoid forced reinstall.
    $createSql = 'CREATE TABLE IF NOT EXISTS `' . bqSQL($tableName) . '` (
        `id_attachment` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `file_name` VARCHAR(255) NOT NULL,
        `display_name` VARCHAR(255) NOT NULL,
        `template_name` VARCHAR(191) NOT NULL,
        `id_lang` INT UNSIGNED NOT NULL,
        `file_mime` VARCHAR(127) NOT NULL,
        `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
        PRIMARY KEY (`id_attachment`),
        KEY `idx_template_lang_active` (`template_name`, `id_lang`, `active`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    @Db::getInstance()->execute($createSql);

    mvAttachmentAddColumnIfMissing($tableName, 'display_name', 'VARCHAR(255) NOT NULL');
    mvAttachmentAddColumnIfMissing($tableName, 'template_name', 'VARCHAR(191) NOT NULL');
    mvAttachmentAddColumnIfMissing($tableName, 'id_lang', 'INT UNSIGNED NOT NULL');
    mvAttachmentAddColumnIfMissing($tableName, 'file_mime', "VARCHAR(127) NOT NULL DEFAULT 'application/octet-stream'");
    mvAttachmentAddColumnIfMissing($tableName, 'active', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');

    $indexSql = "SHOW INDEX FROM `" . bqSQL($tableName) . "` WHERE Key_name = 'idx_template_lang_active'";
    $index = Db::getInstance()->executeS($indexSql);
    if (!is_array($index) || empty($index)) {
        @Db::getInstance()->execute(
            'ALTER TABLE `' . bqSQL($tableName) . '` ADD INDEX `idx_template_lang_active` (`template_name`, `id_lang`, `active`)'
        );
    }

    $uploadDir = rtrim(_PS_UPLOAD_DIR_, '/\\') . DIRECTORY_SEPARATOR . 'mv_attachment' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    if (is_dir($uploadDir)) {
        @file_put_contents($uploadDir . 'index.php', "<?php\nexit;\n");
        @file_put_contents($uploadDir . '.htaccess', "Deny from all\n");
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

    return true;
}

/**
 * Adds a column only when it is missing.
 */
function mvAttachmentAddColumnIfMissing(string $tableName, string $columnName, string $columnSql): void
{
    $columnExistsSql = "SHOW COLUMNS FROM `" . bqSQL($tableName) . "` LIKE '" . pSQL($columnName) . "'";
    $column = Db::getInstance()->getRow($columnExistsSql);

    if (!is_array($column) || empty($column)) {
        @Db::getInstance()->execute(
            'ALTER TABLE `' . bqSQL($tableName) . '` ADD COLUMN `' . bqSQL($columnName) . '` ' . $columnSql
        );
    }
}
