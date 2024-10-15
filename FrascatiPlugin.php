<?php

/**
 * @file FrascatiPlugin.php
 *
 * Copyright (c) 2024 Public Knowledge Project
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace APP\plugins\generic\frascati;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;

use APP\core\Application;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\submission\SubmissionKeywordDAO;

class FrascatiPlugin extends GenericPlugin
{

    /**
     * Constants
     */

    // Define which vocabularies are supported, and the languages in them
    const ALLOWED_VOCABS_AND_LANGS = [
        SubmissionKeywordDAO::CONTROLLED_VOCAB_SUBMISSION_KEYWORD => ['en'],
    ];


    /**
     * Public 
     */

    // GenericPlugin methods

    public function register($category, $path, $mainContextId = null)
    {
        if (Application::isUnderMaintenance()) {
            return true;
        }
        $success = parent::register($category, $path);
        if ($success && $this->getEnabled()) {
            // Add hook
            Hook::add('API::vocabs::external', $this->setData(...));
        }
        return $success;
    }

    public function getActions($request, $actionArgs)
    {
        return parent::getActions($request, $actionArgs);
    }

    public function getDisplayName()
    {
        return __('plugins.generic.frascati.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.frascati.description');
    }

    public function getCanEnable()
    {
        return !!Application::get()->getRequest()->getContext();
    }

    public function getCanDisable()
    {
        return !!Application::get()->getRequest()->getContext();
    }

    public function manage($args, $request)
    {
        return parent::manage($args, $request);
    }

    // Own methods

    /**
     * Public
     */

    public function setData(string $hookName, array $args): bool
    {
        $vocab = $args[0];
        $term = $args[1];
        $locale = $args[2];
        $data = &$args[3];
        $entries = &$args[4];
        $illuminateRequest = $args[5];
        $response = $args[6];
        $request = $args[7];

        // Here we define which form field the plugin is triggered in.
        // You can also define the language is the specific field while some vocabularies might only work
        // with specific languages.
        // Note that the current development version of the core only supports extending Keywords.
        // However, this will be extended to other fields as well, like Discipline.
        if (!isset(self::ALLOWED_VOCABS_AND_LANGS[$vocab]) || !in_array($locale, self::ALLOWED_VOCABS_AND_LANGS[$vocab])) {
            return false;
        }

        // We call the fetchData function will handle the interaction with the vocabulary
        $resultData = $this->fetchData($term, $locale);

        // We replace the vocabulary data coming from the OJS database with fetched data
        // from the external vocabulary and only show those results as suggestions.
        // If you want to show also suggestions from existing keywords in your own database
        // this is where we can make that decision.

        if (!$resultData) {
            $data = [];
            return false;
        }

        $data = $resultData;

        return false;
    }

    /**
     * Private
     */

    private function fetchData(?string $term, string $locale): array
    {
        // You might want to consider sanitazing the search term before it is 
        // passed to an API or used to search within a local file
        $termSanitized = $term ?? "";

        // Here we can set the minimum length for the word that is used for the query
        if (strlen($termSanitized) < 3) {
            return [];
        }

        $doc = new \DOMDocument();
        $doc->load(dirname(__FILE__) . '/classifications.xml');
        $classifications = [];
        foreach ($doc->getElementsByTagName('field') as $fieldNode) {
            foreach ($fieldNode->getElementsByTagName('subfield') as $subfieldNode) {
                $number = $subfieldNode->getAttribute('number');
                $name = $subfieldNode->getAttribute('name');
                if (strpos($name, $termSanitized) !== false) $classifications[] = [
                    'term' => $name,
                    'label' => $name,
                    'uri' => $number,
                    'service' => 'frascati',
                ];
            }
        }
        return $classifications;
    }
}
