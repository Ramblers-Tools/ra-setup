<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

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

    public function getHomeArticle() {
        $sql = 'SELECT id, title, introtext,`fulltext` FROM #__content ';
        $sql .= 'WHERE alias = "home" ORDER BY id ASC LIMIT 1';
        echo "$sql<br>";
        return (new ToolsHelper)->getItem($sql);
    }

    public function updateHomeArticle(string $title, string $body): bool {
        $article = $this->getHomeArticle();

        if (!$article) {
            throw new \RuntimeException('Unable to find the Home article with alias "home".');
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__content'))
                ->set($db->quoteName('title') . ' = ' . $db->quote($title))
                ->set($db->quoteName('introtext') . ' = ' . $db->quote($body))
                ->set($db->quoteName('fulltext') . ' = ' . $db->quote(''))
                ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->set($db->quoteName('modified_by') . ' = ' . (int) Factory::getApplication()->getIdentity()->id)
                ->where($db->quoteName('id') . ' = ' . (int) $article->id);

        $db->setQuery($query);
        $db->execute();

        return true;
    }

    protected function loadFormData() {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ra_setup.two.data', []);

        if (!empty($data)) {
            return $data;
        }

        $article = $this->getHomeArticle();

        if (!$article) {
            return ['facebook' => '0'];
        }

        return [
            'title' => $article->title,
            'body' => $article->introtext . $article->fulltext,
            'facebook' => '0',
        ];
    }

}
