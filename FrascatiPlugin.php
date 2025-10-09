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
use APP\core\Request;
use PKP\controlledVocab\ControlledVocab;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use Laravel\Scout\Builder;
use PKP\config\Config;
use APP\submission\Submission;
use APP\facades\Repo;
use PKP\facades\Locale;

class FrascatiPlugin extends GenericPlugin
{
    /**
     * Constants
     */

    // Define which vocabularies are supported, and the languages in them
    public const ALLOWED_VOCABS_AND_LANGS = [
        ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_SUBJECT => ['en'],
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
        if ($success) {
            if ($this->getEnabled($mainContextId)) {
                // Add hooks for data model
                Hook::add('API::vocabs::external', $this->setData(...));
                Hook::add('Form::config::after', $this->addVocabularyToSubjectsField(...));
            }

            if (Config::getVar('search', 'driver') == 'opensearch') {
                // Index Frascati roots for faceted browsing when using OpenSearch
                Hook::add('OpenSearchEngine::update', function(string $hookName, array &$json, Submission $submission) {
                    if ($this->getEnabledForContextId($submission->getData('contextId'))) {
                        $subjects = Repo::controlledVocab()->getBySymbolic(
                            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_SUBJECT,
                            Application::ASSOC_TYPE_PUBLICATION,
                            $submission->getCurrentPublication()->getId(),
                            [Locale::getLocale(), $submission->getData('locale'), Locale::getPrimaryLocale()]
                        );
                        $frascatiData = $this->getFrascatiData(Locale::getLocale());
                        $frascatiBases = [];
                        foreach ($frascatiData as $base) {
                            foreach ($base['items'] as $subheading) {
                                if (in_array($subheading['label'], $subjects['en'] ?? [])) {
                                    $frascatiBases[] = $base['label'];
                                }
                            }
                        }
                        $json['body']['frascatiBases'] = array_values(array_unique($frascatiBases));
                    }
                    return Hook::CONTINUE;
                });
                Hook::add('SearchHandler::search::builder', function(string $hookName, Builder $builder, Request $request) {
                    $context = $request->getContext();
                    if ($context && $this->getEnabledForContextId($context->getId())) {
                        $builder->whereIn('frascatiBases', $request->getUserVar('frascatiBases'));
                    }
                });
                Hook::add('OpenSearchEngine::buildQuery', function(string $hookName, array &$query, array &$filter, Builder $builder, Builder $originalBuilder) {
                    if ($originalBuilder->wheres['contextId'] && $this->getEnabledForContextId($originalBuilder->wheres['contextId'])) {
                        $frascatiBases = $builder->whereIns['frascatiBases'] ?? [];
                        if (!empty($frascatiBases)) {
                            $filter[] = ['terms' => ['frascatiBases.keyword' => $frascatiBases]];
                        }
                        unset($builder->whereIns['frascatiBases']);
                    }
                    return Hook::CONTINUE;
                });
            }

        }
        return $success;
    }

    protected function getFrascatiData(string $locale) : array
    {
        $jsonPath = dirname(__FILE__) . "/classifications.{$locale}.json";
        if (file_exists($jsonPath)) {
            return json_decode(file_get_contents($jsonPath), true)['items'];
        }

        // Fall back on English if the locale-specific file doesn't exist
        $jsonPath = dirname(__FILE__) . "/classifications.en.json";
        return json_decode(file_get_contents($jsonPath), true)['items'];
    }

    /**
     * Add vocabulary data to the subjects field.
     */
    public function addVocabularyToSubjectsField(string $hookName, array $args)
    {

        $formConfig = &$args[0];
        if ($formConfig['id'] == 'metadata' || $formConfig['id'] == 'forTheEditors') {
            // Find the subjects field
            foreach ($formConfig['fields'] as $key => $field) {
                if ($field['name'] == 'subjects') {

                    $vocabularies = [];

                    foreach ($formConfig['supportedFormLocales'] as $locale) {
                        $localeKey = $locale['key'];

                        $vocabularyData = $this->getFrascatiData($localeKey);
                        $vocabularies[] = [
                            'locale' => $localeKey,
                            'addButtonLabel' => __('plugins.generic.frascati.addFrascatiSubjects'),
                            'modalTitleLabel' => __('plugins.generic.frascati.addFrascatiTitle'),
                            'items' => $vocabularyData
                        ];
                    }

                    if (!empty($vocabularies)) {
                        $formConfig['fields'][$key]['vocabularies'] = $vocabularies;
                    }

                    break; // No need to continue once we found the field
                }
            }
        }

        return Hook::CONTINUE;
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
        // Note that the current development version of the core only supports extending Subjects.
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

    function getEnabledForContextId(int $contextId) {
        static $enabledStates = [];
        return $enabledStates[$contextId] ??= $this->getSetting($contextId, 'enabled');
    }
}
