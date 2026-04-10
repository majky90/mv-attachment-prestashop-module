<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class mv_attachment extends Module
{
    private const TABLE = 'mv_attachment';
    private const UPLOAD_FOLDER = 'mv_attachment';
    private const MAX_UPLOAD_SIZE_BYTES = 5242880; // 5 MB
    /** @var string[] */
    private const MAIL_TEMPLATE_EXTENSIONS = ['html', 'txt', 'twig'];

    /** @var array<string, string[]> */
    private const ALLOWED_MIME_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'],
        'rar' => ['application/vnd.rar', 'application/x-rar-compressed', 'application/octet-stream'],
        'txt' => ['text/plain', 'application/octet-stream'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip', 'application/octet-stream'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip', 'application/octet-stream'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
    ];

    public function __construct()
    {
        $this->name = 'mv_attachment';
        $this->tab = 'administration';
        $this->version = '1.0.9';
        $this->author = 'Marián Varga';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('E-mail Attachments');
        $this->description = $this->l('The module makes it easy to insert any required documents directly into e-mail.');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('actionMailSendBeforeOut')
            && $this->registerOptionalHook('actionEmailSendBefore')
            && $this->createDatabaseTable()
            && $this->ensureUploadDirectory();
    }

    public function uninstall(): bool
    {
        return $this->dropDatabaseTable()
            && $this->removeUploadDirectory()
            && parent::uninstall();
    }

    public function getContent(): string
    {
        $this->ensureHookRegistration();

        $output = $this->postProcess();

        $status = (string) Tools::getValue('mv_attachment_status', '');
        if ($status === 'created') {
            $output .= $this->displayConfirmation($this->l('Attachment has been saved successfully.'));
        } elseif ($status === 'updated') {
            $output .= $this->displayConfirmation($this->l('Attachment has been updated successfully.'));
        } elseif ($status === 'deleted') {
            $output .= $this->displayConfirmation($this->l('Attachment deleted successfully.'));
        }

        $output .= $this->renderList();
        $output .= $this->renderForm();

        return $output;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function hookActionMailSendBeforeOut(array &$params): void
    {
        $this->attachFilesToMailParams($params);
    }

    /**
     * Compatibility hook used in some PrestaShop 8/9 mail flows.
     *
     * @param array<string, mixed> $params
     */
    public function hookActionEmailSendBefore(array &$params): void
    {
        $this->attachFilesToMailParams($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function attachFilesToMailParams(array &$params): void
    {
        $templateRaw = (string) ($params['template'] ?? $params['templateName'] ?? '');
        $templateName = $this->normalizeTemplateName($templateRaw);
        $idLang = (int) ($params['id_lang'] ?? $params['idLang'] ?? 0);

        if ($templateName === '' || $idLang <= 0) {
            return;
        }

        $templateNameHtml = pSQL($templateName . '.html');
        $templateNameTxt = pSQL($templateName . '.txt');
        $templateNameTwig = pSQL($templateName . '.twig');
        $templateNameHtmlTwig = pSQL($templateName . '.html.twig');

        $sql = 'SELECT file_name, display_name, file_mime
                FROM `' . _DB_PREFIX_ . self::TABLE . '`
                WHERE active = 1
                    AND template_name IN (\'' . pSQL($templateName) . '\', \'' . $templateNameHtml . '\', \'' . $templateNameTxt . '\', \'' . $templateNameTwig . '\', \'' . $templateNameHtmlTwig . '\')
                    AND id_lang = ' . (int) $idLang;

        $rows = Db::getInstance()->executeS($sql);
        if (!is_array($rows) || empty($rows)) {
            return;
        }

        $targetAttachmentKey = $this->resolveMailAttachmentTargetKey($params);
        if (!isset($params[$targetAttachmentKey]) || !is_array($params[$targetAttachmentKey])) {
            $params[$targetAttachmentKey] = [];
        }

        if (!isset($params['_mv_attachment_added']) || !is_array($params['_mv_attachment_added'])) {
            $params['_mv_attachment_added'] = [];
        }

        foreach ($rows as $row) {
            $fileName = (string) ($row['file_name'] ?? '');
            if ($fileName === '') {
                continue;
            }

            $signature = hash('sha256', $templateName . '|' . $idLang . '|' . $fileName);
            if (isset($params['_mv_attachment_added'][$signature])) {
                continue;
            }

            $filePath = $this->getUploadAbsolutePath() . $fileName;
            if (!is_file($filePath) || !is_readable($filePath)) {
                continue;
            }

            $content = @file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            $attachment = [
                'content' => $content,
                'name' => $this->buildAttachmentEmailName((string) ($row['display_name'] ?? ''), $fileName),
                'mime' => $this->resolveOutgoingMimeType($fileName, (string) ($row['file_mime'] ?: 'application/octet-stream')),
            ];

            $params[$targetAttachmentKey][] = $attachment;
            $params['_mv_attachment_added'][$signature] = true;
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveMailAttachmentTargetKey(array $params): string
    {
        if (array_key_exists('fileAttachment', $params)) {
            return 'fileAttachment';
        }

        return 'attachments';
    }

    private function resolveOutgoingMimeType(string $fileName, string $storedMime): string
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension === 'zip') {
            return 'application/zip';
        }

        return $storedMime !== '' ? $storedMime : 'application/octet-stream';
    }

    private function registerOptionalHook(string $hookName): bool
    {
        $hookId = (int) Hook::getIdByName($hookName);
        if ($hookId <= 0) {
            return true;
        }

        return $this->registerHook($hookName);
    }

    private function ensureHookRegistration(): void
    {
        if (!$this->isRegisteredInHook('actionMailSendBeforeOut')) {
            $this->registerHook('actionMailSendBeforeOut');
        }

        $optionalHookId = (int) Hook::getIdByName('actionEmailSendBefore');
        if ($optionalHookId > 0 && !$this->isRegisteredInHook('actionEmailSendBefore')) {
            $this->registerHook('actionEmailSendBefore');
        }
    }

    private function normalizeTemplateName(string $template): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        $template = str_replace('\\', '/', $template);
        $template = basename($template);

        // Handle chained names like order_conf.html.twig and normalize to order_conf.
        do {
            $previousTemplate = $template;
            $template = preg_replace('/\.(html|txt|twig)$/i', '', $template) ?: $template;
        } while ($template !== $previousTemplate);

        $template = strtolower($template);

        return $template;
    }

    private function buildAttachmentEmailName(string $displayName, string $storedFileName): string
    {
        $displayName = trim($displayName);
        $storedExt = strtolower((string) pathinfo($storedFileName, PATHINFO_EXTENSION));

        if ($displayName === '') {
            return $storedFileName;
        }

        if ($storedExt === '') {
            return $displayName;
        }

        $displayExt = strtolower((string) pathinfo($displayName, PATHINFO_EXTENSION));
        if ($displayExt === '') {
            return $displayName . '.' . $storedExt;
        }

        return $displayName;
    }

    private function createDatabaseTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
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

        return (bool) Db::getInstance()->execute($sql);
    }

    private function dropDatabaseTable(): bool
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`';

        return (bool) Db::getInstance()->execute($sql);
    }

    public function renderList(): string
    {
        $sql = 'SELECT a.id_attachment, a.file_name, a.display_name, a.template_name, a.file_mime, a.active, l.name AS language
                FROM `' . _DB_PREFIX_ . self::TABLE . '` a
                LEFT JOIN `' . _DB_PREFIX_ . 'lang` l ON (l.id_lang = a.id_lang)
                ORDER BY a.id_attachment DESC';

        $attachments = Db::getInstance()->executeS($sql);
        if (!is_array($attachments)) {
            $attachments = [];
        }

        $fieldsList = [
            'id_attachment' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'display_name' => [
                'title' => $this->l('Displayed name'),
            ],
            'file_name' => [
                'title' => $this->l('Stored file'),
            ],
            'template_name' => [
                'title' => $this->l('Template'),
            ],
            'language' => [
                'title' => $this->l('Language'),
            ],
            'file_mime' => [
                'title' => $this->l('MIME'),
            ],
            'active' => [
                'title' => $this->l('Active'),
                'align' => 'center',
                'type' => 'bool',
            ],
        ];

        $helper = new HelperList();
        $helper->module = $this;
        $helper->title = $this->l('Uploaded attachments');
        $helper->shopLinkType = '';
        $helper->table = self::TABLE;
        $helper->identifier = 'id_attachment';
        $helper->actions = ['edit', 'delete'];
        $helper->show_toolbar = false;
        $helper->listTotal = count($attachments);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->simple_header = false;
        $helper->no_link = true;

        return $helper->generateList($attachments, $fieldsList);
    }

    public function renderForm(): string
    {
        $idAttachment = (int) Tools::getValue('id_attachment', 0);
        $isEdit = Tools::getIsset('update' . self::TABLE) && $idAttachment > 0;
        $editedRow = $isEdit ? $this->getAttachmentById($idAttachment) : [];

        $languages = Language::getLanguages(false);
        $templates = $this->getTemplateOptions();

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMvAttachmentSave';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->show_toolbar = false;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) $this->context->language->id;

        $helper->fields_value = [
            'id_attachment' => (int) Tools::getValue('id_attachment', (int) ($editedRow['id_attachment'] ?? 0)),
            'display_name' => Tools::getValue('display_name', (string) ($editedRow['display_name'] ?? '')),
            'template_name' => $this->normalizeTemplateName((string) Tools::getValue('template_name', (string) ($editedRow['template_name'] ?? ''))),
            'id_lang' => (int) Tools::getValue('id_lang', (int) ($editedRow['id_lang'] ?? (int) $this->context->language->id)),
            'active' => (int) Tools::getValue('active', 1),
        ];

        if (!Tools::getIsset('submitMvAttachmentSave') && isset($editedRow['active'])) {
            $helper->fields_value['active'] = (int) $editedRow['active'];
        }

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $isEdit ? $this->l('Edit attachment') : $this->l('Add attachment'),
                    'icon' => 'icon-paperclip',
                ],
                'input' => [
                    [
                        'type' => 'hidden',
                        'name' => 'id_attachment',
                    ],
                    [
                        'type' => 'file',
                        'label' => $this->l('Attachment file'),
                        'name' => 'MV_ATTACHMENT_FILE',
                        'required' => !$isEdit,
                        'desc' => $this->l('Allowed: PDF, ZIP, RAR, TXT, DOC, DOCX, XLS, XLSX, ODT, ODS, PNG, JPG, GIF. For edit, file is optional.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Displayed attachment name'),
                        'name' => 'display_name',
                        'required' => true,
                        'maxlength' => 255,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('E-mail template'),
                        'name' => 'template_name',
                        'required' => true,
                        'options' => [
                            'query' => $templates,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Select the e-mail template where this attachment should be used.'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Language'),
                        'name' => 'id_lang',
                        'required' => true,
                        'options' => [
                            'query' => $languages,
                            'id' => 'id_lang',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Active'),
                        'name' => 'active',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $isEdit ? $this->l('Update') : $this->l('Save'),
                    'class' => 'btn btn-primary pull-right',
                ],
            ],
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    public function postProcess(): string
    {
        if (Tools::getIsset('delete' . self::TABLE)) {
            $deleteResult = $this->processDeleteAttachment();
            if ($deleteResult === true) {
                Tools::redirectAdmin($this->buildModuleAdminUrl('deleted'));
            }

            return (string) $deleteResult;
        }

        if (!Tools::getIsset('submitMvAttachmentSave')) {
            return '';
        }

        $idAttachment = (int) Tools::getValue('id_attachment', 0);
        $isEdit = $idAttachment > 0;
        $existingAttachment = $isEdit ? $this->getAttachmentById($idAttachment) : [];

        if ($isEdit && empty($existingAttachment)) {
            return $this->displayError($this->l('Attachment record for edit was not found.'));
        }

        $displayName = trim((string) Tools::getValue('display_name', ''));
        $templateName = $this->normalizeTemplateName((string) Tools::getValue('template_name', ''));
        $idLang = (int) Tools::getValue('id_lang', 0);
        $active = (int) Tools::getValue('active', 1) === 1 ? 1 : 0;

        $allowedTemplates = array_column($this->getTemplateOptions(), 'id');

        if ($displayName === '' || !Validate::isCleanHtml($displayName)) {
            return $this->displayError($this->l('Please enter a valid displayed attachment name.'));
        }

        if ($templateName === '') {
            return $this->displayError($this->l('Please select an e-mail template.'));
        }

        if (!empty($allowedTemplates) && !in_array($templateName, $allowedTemplates, true)) {
            return $this->displayError($this->l('Please select a valid template from the available list.'));
        }

        if ($idLang <= 0 || !Language::getLanguage($idLang)) {
            return $this->displayError($this->l('Please select a valid language.'));
        }

        $hasUploadedFile = isset($_FILES['MV_ATTACHMENT_FILE'])
            && is_array($_FILES['MV_ATTACHMENT_FILE'])
            && (int) ($_FILES['MV_ATTACHMENT_FILE']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if (!$hasUploadedFile && !$isEdit) {
            return $this->displayError($this->l('Attachment file is required.'));
        }

        $finalFileName = (string) ($existingAttachment['file_name'] ?? '');
        $finalMimeType = (string) ($existingAttachment['file_mime'] ?? 'application/octet-stream');
        $newFilePath = '';

        if ($hasUploadedFile) {
            $file = $_FILES['MV_ATTACHMENT_FILE'];
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return $this->displayError($this->l('File upload failed. Please try again.'));
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');
            $originalName = (string) ($file['name'] ?? '');

            if ($tmpName === '' || $originalName === '' || !is_uploaded_file($tmpName)) {
                return $this->displayError($this->l('Invalid uploaded file.'));
            }

            $fileSize = (int) ($file['size'] ?? 0);
            if ($fileSize <= 0) {
                return $this->displayError($this->l('Uploaded file is empty.'));
            }

            if ($fileSize > self::MAX_UPLOAD_SIZE_BYTES) {
                return $this->displayError(
                    sprintf($this->l('Uploaded file exceeds the allowed maximum size (%s MB).'), (string) (self::MAX_UPLOAD_SIZE_BYTES / 1024 / 1024))
                );
            }

            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            if (!isset(self::ALLOWED_MIME_BY_EXTENSION[$extension])) {
                return $this->displayError($this->l('Allowed formats are PDF, ZIP, RAR, TXT, DOC, DOCX, XLS, XLSX, ODT, ODS, PNG, JPG and GIF only.'));
            }

            $mimeType = $this->detectMimeType($tmpName);
            $allowedMimeTypes = self::ALLOWED_MIME_BY_EXTENSION[$extension];
            if ($mimeType === '') {
                return $this->displayError($this->l('Unable to detect MIME type for uploaded file.'));
            }

            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                return $this->displayError($this->l('This MIME type is not allowed for the uploaded file.'));
            }

            if (!$this->ensureUploadDirectory()) {
                return $this->displayError($this->l('Unable to create upload directory.'));
            }

            $finalFileName = bin2hex(random_bytes(16)) . '.' . $extension;
            $newFilePath = $this->getUploadAbsolutePath() . $finalFileName;
            $finalMimeType = $mimeType;

            if (!move_uploaded_file($tmpName, $newFilePath)) {
                return $this->displayError($this->l('Unable to move uploaded file.'));
            }
        }

        $dbData = [
            'file_name' => pSQL($finalFileName),
            'display_name' => pSQL($displayName),
            'template_name' => pSQL($templateName),
            'id_lang' => (int) $idLang,
            'file_mime' => pSQL($finalMimeType),
            'active' => (int) $active,
        ];

        $saved = $isEdit
            ? Db::getInstance()->update(self::TABLE, $dbData, 'id_attachment = ' . (int) $idAttachment)
            : Db::getInstance()->insert(self::TABLE, $dbData);

        if (!$saved) {
            if ($newFilePath !== '' && is_file($newFilePath)) {
                @unlink($newFilePath);
            }

            return $this->displayError($this->l('Failed to save attachment into database.'));
        }

        if ($isEdit && $newFilePath !== '' && !empty($existingAttachment['file_name'])) {
            $oldFile = $this->getUploadAbsolutePath() . (string) $existingAttachment['file_name'];
            if (is_file($oldFile) && $oldFile !== $newFilePath) {
                @unlink($oldFile);
            }
        }

        Tools::redirectAdmin($this->buildModuleAdminUrl($isEdit ? 'updated' : 'created'));

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function getAttachmentById(int $idAttachment): array
    {
        if ($idAttachment <= 0) {
            return [];
        }

        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE id_attachment = ' . (int) $idAttachment
        );

        return is_array($row) ? $row : [];
    }

    /**
     * @return true|string
     */
    private function processDeleteAttachment()
    {
        $idAttachment = (int) Tools::getValue('id_attachment', 0);
        if ($idAttachment <= 0) {
            return $this->displayError($this->l('Invalid attachment ID.'));
        }

        $row = Db::getInstance()->getRow(
            'SELECT file_name FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE id_attachment = ' . (int) $idAttachment
        );

        if (!is_array($row) || empty($row['file_name'])) {
            return $this->displayError($this->l('Attachment not found.'));
        }

        $deleted = Db::getInstance()->delete(self::TABLE, 'id_attachment = ' . (int) $idAttachment);
        if (!$deleted) {
            return $this->displayError($this->l('Failed to delete attachment record.'));
        }

        $filePath = $this->getUploadAbsolutePath() . (string) $row['file_name'];
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        return true;
    }

    private function buildModuleAdminUrl(string $status = ''): string
    {
        $params = [
            'configure' => $this->name,
            'module_name' => $this->name,
            'tab_module' => $this->tab,
        ];

        if ($status !== '') {
            $params['mv_attachment_status'] = $status;
        }

        return $this->context->link->getAdminLink('AdminModules', true, [], $params);
    }

    private function ensureUploadDirectory(): bool
    {
        $directory = $this->getUploadAbsolutePath();

        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                return false;
            }
        }

        // Basic hardening for direct directory access.
        @file_put_contents($directory . 'index.php', "<?php\nexit;\n");
        @file_put_contents($directory . '.htaccess', "Deny from all\n");

        return true;
    }

    private function removeUploadDirectory(): bool
    {
        $directory = $this->getUploadAbsolutePath();
        if (!is_dir($directory)) {
            return true;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($directory);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function getTemplateOptions(): array
    {
        $templates = [];
        $mailDirectories = [
            _PS_MAIL_DIR_,
            _PS_THEME_DIR_ . 'mails' . DIRECTORY_SEPARATOR,
        ];

        foreach ($mailDirectories as $mailDirectory) {
            if (!is_dir($mailDirectory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($mailDirectory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $extension = strtolower((string) $file->getExtension());
                if (!in_array($extension, self::MAIL_TEMPLATE_EXTENSIONS, true)) {
                    continue;
                }

                $name = $this->normalizeTemplateName((string) $file->getFilename());
                if ($name === '') {
                    continue;
                }

                $templates[$name] = [
                    'id' => $name,
                    'name' => $name,
                ];
            }
        }

        ksort($templates);

        return array_values($templates);
    }

    private function getUploadAbsolutePath(): string
    {
        return rtrim(_PS_UPLOAD_DIR_, '/\\') . DIRECTORY_SEPARATOR . self::UPLOAD_FOLDER . DIRECTORY_SEPARATOR;
    }

    private function detectMimeType(string $filePath): string
    {
        if (!is_file($filePath)) {
            return '';
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) @finfo_file($finfo, $filePath);
                @finfo_close($finfo);

                return $mime;
            }
        }

        return '';
    }
}
