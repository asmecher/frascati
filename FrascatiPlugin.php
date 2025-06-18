<?php

/**
 * @file FrascatiPlugin.php
 *
 * Copyright (c) 2024 Public Knowledge Project
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace APP\plugins\generic\frascati;

use APP\core\Application;
use PKP\controlledVocab\ControlledVocab;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class FrascatiPlugin extends GenericPlugin
{
    /**
     * Constants
     */

    // Define which vocabularies are supported, and the languages in them
    public const ALLOWED_VOCABS_AND_LANGS = [
        ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD => ['en'],
    ];


    /**
     * Public
     *
     * @param null|mixed $mainContextId
     */

    // GenericPlugin methods

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);
        if ($success && $this->getEnabled()) {
            // Add hook
            Hook::add('API::vocabs::external', $this->setData(...));
            Hook::add('Form::config::after', $this->addVocabularyToKeywordsField(...));

        }
        return $success;
    }

    public function addVocabularyToKeywordsField($hookName, $args)
    {

        $formConfig = &$args[0];
        if ($formConfig['id'] == 'metadata' || $formConfig['id'] == 'titleAbstract') {
            // Find the keywords field
            foreach ($formConfig['fields'] as $key => $field) {
                if ($field['name'] == 'keywords') {

                    $vocabularies = [];

                    foreach ($formConfig['supportedFormLocales'] as $locale) {
                        $localeKey = $locale['key'];
                        $jsonPath = dirname(__FILE__) . "/classifications.{$localeKey}.json";
                        if (file_exists($jsonPath)) {
                            $vocabularyData = json_decode(file_get_contents($jsonPath), true)['items'];

                            $vocabularies[] = [
                                'locale' => $localeKey,
                                'addButtonLabel' => __('plugins.generic.frascati.addFrascatiKeywords'),
                                'modalTitleLabel' => __('plugins.generic.frascati.addFrascatiTitle'),
                                'items' => $vocabularyData
                            ];
                        }
                    }

                    if (!empty($vocabularies)) {
                        $formConfig['fields'][$key]['vocabularies'] = $vocabularies;
                    }

                    break; // No need to continue once we found the field
                }
            }
        }
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
                if (strpos(strtolower($name), $termSanitized) !== false) {
                    $classifications[] = [
                        'name' => $name,
                        'source' => 'Frascati',
                        'identifier' => $number,
                    ];
                }
            }
        }
        return $classifications;
    }
}
