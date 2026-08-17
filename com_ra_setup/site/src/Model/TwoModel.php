<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

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

    public function setFacebookModulesPublished(bool $published): void {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                ->update($db->quoteName('#__modules'))
                ->set($db->quoteName('published') . ' = ' . ($published ? 1 : 0))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_ra_facebook'))
                ->where($db->quoteName('client_id') . ' = 0');
        $db->setQuery($query)->execute();
    }

    protected function loadFormData() {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ra_setup.two.data', []);
        $article = $this->getHomeArticle();
        $facebookParams = $this->getFacebookModuleParams();

        if ($article) {
            if (trim((string) ($data['title'] ?? '')) === '') {
                $data['title'] = (string) $article->title;
            }

            if (trim((string) ($data['body'] ?? '')) === '') {
                $data['body'] = (string) $article->introtext . (string) $article->fulltext;
            }
        }

        if (!array_key_exists('facebook_link', $data)) {
            $data['facebook_link'] = trim((string) $facebookParams->get('url', ''));
        }

        if (!array_key_exists('facebook_text', $data)) {
            $data['facebook_text'] = trim((string) $facebookParams->get('caption', 'Join us on Facebook!'));
        }

        if (!array_key_exists('facebook', $data)) {
            $data['facebook'] = $data['facebook_link'] === '' ? '0' : '1';
        } else {
            $data['facebook'] = (string) $data['facebook'];
        }

        return $data;
    }

    private function getFacebookModuleParams(): Registry {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_ra_facebook'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->order($db->quoteName('published') . ' DESC')
                ->order($db->quoteName('ordering') . ' ASC')
                ->order($db->quoteName('id') . ' ASC');
        $params = $db->setQuery($query, 0, 1)->loadResult();

        return new Registry(is_string($params) ? $params : '');
    }

}
