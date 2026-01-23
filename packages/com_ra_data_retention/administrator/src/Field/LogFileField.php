<?php

namespace Ramblerswebs\Component\Ra_data_retention\Administrator\Field;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;

/**
 * Form Field class for capturing IATA airport code
 * This approach extends GroupedlistField and overrides getGroups
 */
class LogFileField extends GroupedlistField
{
    public function getGroups()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        
        $query->select($db->quoteName(['id', 'type', 'start', 'finish']))
                ->from($db->quoteName('#__ra_retention_journal'))
                ->order($db->quoteName('id') . ' DESC');

        $db->setQuery($query);

        $results = $db->loadObjectList();
        $currentdate = "";
        $logs = [];
        foreach($results as $row)
        {
            $newdate = new \DateTimeImmutable($row->start, new \DateTimeZone('Europe/London'));
            $dt = $newdate->format('Y-m-d');
            if (strcmp($currentdate, $dt) != 0)
            {
                // See if we have any logs to store
                if (count($logs) > 0)
                {
                    // We have some entries to log
                    $groups[$currentdate] = $logs ;
                    unset($logs);
                    
                }
                // Update the current date to the entry just received
                $currentdate = $dt;
                $logs = [];
            }
            $finishdate = new \DateTimeImmutable($row->finish, new \DateTimeZone('Europe/London'));

            // Now we need to store the current log entry found
            $logs[$row->id] = $newdate->format('H:i:s') . " - " . $finishdate->format('H:i:s ') . $row->type;
        }
        $groups[$currentdate] = $logs ;

        unset($finishdate);
        unset($newdate);
        unset($logs);
        unset($query);
        unset($db);

		$groups = array_merge(parent::getGroups(), $groups);

		return $groups;
    }
}