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
use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabEntry;
use PKP\submission\SubmissionKeywordDAO;

class FrascatiPlugin extends GenericPlugin
{

    /**
     * Constants
     */

    // Define which vocabularies are supported, and the languages in them
    const ALLOWED_VOCABS_AND_LANGS = [
        ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD => ['en'],
    ];


    /**
     * Public 
     */

    // GenericPlugin methods

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);
        if ($success && $this->getEnabled()) {
            // Add hook
            Hook::add('API::vocabs::external', $this->setData(...));
        }
        return $success;
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

    /**
     * Public
     */
    public function setData(string $hookName, string $vocab, ?string $term, string $locale, array &$data, &$entries, $illuminateRequest, $response, $request): bool
    {
        // Here we define which form field the plugin is triggered in.
        // You can also define the language is the specific field while some vocabularies might only work
        // with specific languages.
        // Note that the current development version of the core only supports extending Keywords.
        // However, this will be extended to other fields as well, like Discipline.
        if (!isset(self::ALLOWED_VOCABS_AND_LANGS[$vocab]) || !in_array($locale, self::ALLOWED_VOCABS_AND_LANGS[$vocab])) {
            return Hook::CONTINUE;
        }

	// Only return suggestions from the vocabulary
        $data = $this->fetchData($term, $locale);
error_log(print_r($data,true));
        return HOOK::CONTINUE;
    }

    /**
     * Private
     */
    private function fetchData(?string $term, string $locale): array
    {
        // You might want to consider sanitazing the search term before it is 
        // passed to an API or used to search within a local file
        $termSanitized = strtolower($term ?? '');

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
                if (strpos(strtolower($name), $termSanitized) !== false) $classifications[] = [
                    'name' => $name,
                    'source' => 'Frascati',
                    'identifier' => $number,
                ];
            }
        }
        return $classifications;
    }
}
