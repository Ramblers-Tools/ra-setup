<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;

class TwoModel extends FormModel {

    public function getForm($data = [], $loadData = true) {
        return $this->loadForm(
                        'com_ra_setup.two',
                        'two',
                        [
                            'control' => 'jform',
                            'load_data' => $loadData,
                        ]
        );
    }

    public function getHomeArticle(): ?object {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('title'),
                    $db->quoteName('introtext'),
                    $db->quoteName('fulltext'),
                ])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('alias') . ' = ' . $db->quote('home'))
                ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($query, 0, 1);

        return $db->loadObject();
    }

    public function updateHomeArticle(string $title, string $body): bool {
        $article = $this->getHomeArticle();

        if (!$article) {
            throw new \RuntimeException('Unable to find the Home article with alias "home".');
        }

        $articleModel = Factory::getApplication()
                ->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Article', 'Administrator', ['ignore_request' => true]);

        if (!$articleModel) {
            throw new \RuntimeException('Unable to load the Joomla Article model.');
        }

        $current = $articleModel->getItem((int) $article->id);

        if (!$current || empty($current->id)) {
            throw new \RuntimeException('Unable to load the Home article through Joomla.');
        }

        $data = [
            'id' => (int) $current->id,
            'catid' => (int) $current->catid,
            'title' => $title,
            'alias' => (string) $current->alias,
            'introtext' => $body,
            'fulltext' => '',
            'state' => (int) $current->state,
            'access' => (int) $current->access,
            'language' => (string) $current->language,
        ];

        if (!$articleModel->save($data)) {
            throw new \RuntimeException(
                    'Unable to update the Home article: ' . (string) $articleModel->getError()
            );
        }

        return true;
    }

    protected function loadFormData() {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ra_setup.two.data', []);
        $article = $this->getHomeArticle();

        if ($article) {
            if (trim((string) ($data['title'] ?? '')) === '') {
                $data['title'] = (string) $article->title;
            }

            if (trim((string) ($data['body'] ?? '')) === '') {
                $data['body'] = (string) $article->introtext . (string) $article->fulltext;
            }
        }

        $data['facebook'] = (string) ($data['facebook'] ?? '0');

        return $data;
    }

}
