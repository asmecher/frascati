<?php

/**
 * @file FrascatiSettingsForm.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FrascatiSettingsForm
 * @brief Form for journal managers to modify Frascati plugin settings
 */

namespace APP\plugins\generic\frascati;

use APP\template\TemplateManager;
use PKP\form\Form;

class FrascatiSettingsForm extends Form
{
    public int $journalId;
    public FrascatiPlugin $plugin;

    /**
     * Constructor
     */
    public function __construct(FrascatiPlugin $plugin, int $journalId)
    {
        $this->journalId = $journalId;
        $this->plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Initialize form data.
     */
    public function initData()
    {
        $this->_data = [
            'requiredFrascatiClasses' => (int) $this->plugin->getSetting($this->journalId, 'requiredFrascatiClasses'),
        ];
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars(['requiredFrascatiClasses']);
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $this->plugin->updateSetting($this->journalId, 'requiredFrascatiClasses', (int) $this->getData('requiredFrascatiClasses'), 'int');
        parent::execute(...$functionArgs);
    }
}
