<?php

namespace phpMyFAQ\Export;

/**
 * JSON Export class for phpMyFAQ.
 *
 * This Source Code Form is subject to the terms of the Mozilla Public License,
 * v. 2.0. If a copy of the MPL was not distributed with this file, You can
 * obtain one at http://mozilla.org/MPL/2.0/.
 *
 * @package phpMyFAQ
 * @author Thorsten Rinne <thorsten@phpmyfaq.de>
 * @copyright 2015-2019 phpMyFAQ Team
 * @license http://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link https://www.phpmyfaq.de
 * @since 2015-12-29
 */

use phpMyFAQ\Category;
use phpMyFAQ\Configuration;
use phpMyFAQ\Date;
use phpMyFAQ\Export;
use phpMyFAQ\Faq;
use phpMyFAQ\Strings;

if (!defined('IS_VALID_PHPMYFAQ')) {
    exit();
}

/**
 * Class Json
 * @package phpMyFAQ\Export
 */
class Json extends Export
{
    /**
     * Constructor.
     *
     * @param Faq $faq FaqHelper object
     * @param Category $category Entity object
     * @param Configuration $config Configuration
     */
    public function __construct(Faq $faq, Category $category, Configuration $config)
    {
        $this->faq = $faq;
        $this->category = $category;
        $this->_config = $config;
    }

    /**
     * Generates the export.
     *
     * @param int $categoryId Entity Id
     * @param bool $downwards If true, downwards, otherwise upward ordering
     * @param string $language Language
     *
     * @return string
     */
    public function generate($categoryId = 0, $downwards = true, $language = '')
    {
        $generated = [];

        // Initialize categories
        $this->category->transform($categoryId);

        $faqdata = $this->faq->get(FAQ_QUERY_TYPE_EXPORT_XML, $categoryId, $downwards, $language);

        if (count($faqdata)) {
            foreach ($faqdata as $data) {

                $generated[] = [
                    'faq' => [
                        'id' => $data['id'],
                        'language' => $data['lang'],
                        'category' => $this->category->getPath($data['category_id'], ' >> '),
                        'keywords' => $data['keywords'],
                        'question' => strip_tags($data['topic']),
                        'answer' => Strings::htmlspecialchars($data['content']),
                        'author' => $data['author_name'],
                        'last_modified' => Date::createIsoDate($data['lastmodified'])
                    ]
                ];
            }
        }

        header('Content-type: application/json');

        return json_encode($generated);
    }
}
