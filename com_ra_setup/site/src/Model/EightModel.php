<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;

class EightModel extends BaseDatabaseModel
{
    public function completeWizard(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $homeId = $this->getHomeArticleId();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('featured') . ' = 0');
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('featured') . ' = 1')
            ->set($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('id') . ' = ' . $homeId);
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__content_frontpage'));
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__content_frontpage'))
            ->columns([
                $db->quoteName('content_id'),
                $db->quoteName('ordering'),
                $db->quoteName('featured_up'),
                $db->quoteName('featured_down'),
            ])
            ->values($homeId . ', 0, NULL, NULL');
        $db->setQuery($query)->execute();

        $completedAt = Factory::getDate()->toSql();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ra_control'))
            ->where($db->quoteName('record_type') . ' = 3');
        $exists = (int) $db->setQuery($query)->loadResult() > 0;

        if ($exists) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ra_control'))
                ->set($db->quoteName('key_value') . ' = ' . $db->quote($completedAt))
                ->where($db->quoteName('record_type') . ' = 3');
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ra_control'))
                ->columns([
                    $db->quoteName('record_type'),
                    $db->quoteName('key_value'),
                ])
                ->values('3, ' . $db->quote($completedAt));
        }

        $db->setQuery($query)->execute();
    }

    private function getHomeArticleId(): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote('home'))
            ->order($db->quoteName('id') . ' ASC');
        $homeId = (int) $db->setQuery($query, 0, 1)->loadResult();

        if ($homeId < 1) {
            throw new \RuntimeException('Unable to find the Home article.');
        }

        return $homeId;
    }
}
