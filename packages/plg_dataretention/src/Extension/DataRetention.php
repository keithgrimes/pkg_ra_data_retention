<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.rotatelogs
 *
 * @copyright   (C) 2023 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblerswebs\Plugin\Task\DataRetention\Extension;

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Ramblerswebs\Component\Ra_data_retention\Administrator\Helper\Ra_data_retentionHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A task plugin. Offers 1 task routines Rotate Logs
 * {@see ExecuteTaskEvent}.
 *
 * @since 5.0.0
 */
final class DataRetention extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var string[]
     * @since 5.0.0
     */
    private const TASKS_MAP = [
        'dataretention.applyretention' => [
            'langConstPrefix' => 'PLG_TASK_APPLYRETENTION',
            'method'          => 'ApplyRetention',
            'form'            => 'ApplyRetentionForm',
        ],
        'dataretention.emptytrash' => [
            'langConstPrefix' => 'PLG_TASK_EMPTYTRASH',
            'method'          => 'EmptyTrash',
            'form'            => 'EmptyTrashForm',
        ],
        'dataretention.removefiles' => [
            'langConstPrefix' => 'PLG_TASK_REMOVEFILES',
            'method'          => 'RemoveFiles',
            'form'            => 'RemoveFilesForm',
        ],
    ];

    /**
     * @var boolean
     * @since 5.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 5.0.0
     */
    public static function getSubscribedEvents(): array
    {
        // This task is disabled if the Data Retention component is not installed or has been unpublished
		if (!ComponentHelper::isEnabled('com_ra_data_retention'))
		{
			return [];
		}

        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Method for applying retention policies which have been defined
     *
     * @param   ExecuteTaskEvent  $event  The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @since  5.0.0
     * @throws \Exception
     */
    private function ApplyRetention(ExecuteTaskEvent $event): int
    {
        // Make sure Data Retention is installed and enabled.
		$component = ComponentHelper::isEnabled('com_ra_data_retention')
        ? $this->getApplication()->bootComponent('com_ra_data_retention')
        : null;

        if (!($component instanceof MVCFactoryServiceInterface))
        {
            throw new RuntimeException('The Data Retention component is not installed or has been disabled.');
        }

        ra_data_retentionHelper::startJournal("RETENTION");
        ra_data_retentionHelper::logJournal("RETENTION", "Applying Data Retention","");

        $params = ComponentHelper::getParams('com_ra_data_retention');
		$testmode = $params->get('testmode', 0);

        $maxretention = $this->readConfigSetting('maxretention', 840);
        $minphoto = $this->readConfigSetting('minphoto', 1);
        $minorders = $this->readConfigSetting('minorder', 12);
        $minredirects = $this->readConfigSetting('minredirects', 6);
        ra_data_retentionHelper::logJournal("RETENTION", "Read Config Settings maxretention: " . $maxretention . ", minphoto: " . $minphoto . ", minorders: " . $minorders . ", minredirects: " . $minredirects,"");
        
		ra_data_retentionHelper::CalculateFullRetentions("ARTICLE", $maxretention, $testmode);
		ra_data_retentionHelper::CalculateFullRetentions("PHOTO", $maxretention, $testmode);
		ra_data_retentionHelper::CalculateFullRetentions("WEBLINK", $maxretention, $testmode);

        $status_content = $this->ApplyRetentionTable('#__content', 'ARTICLE', $testmode, $maxretention, $event);
        $this->RemoveUnpublishedTable('#__content', 'ARTICLE', $testmode, $maxretention, $event);
        $status_weblinks =  $this->ApplyRetentionTable('#__weblinks', 'WEBLINKS', $testmode, $maxretention, $event);
        $this->RemoveUnpublishedTable('#__weblinks', 'WEBLINKS', $testmode, $maxretention, $event);
        $status_events =  $this->ApplyRetentionEvents('#__eventgallery_folder', 'PHOTO', $testmode, $maxretention, $event);

        // Limit the number of events. Ensure we hold a minimum number
        $this->LimitRetentionEvents('#__eventgallery_folder', 'PHOTO', $testmode, $minphoto);

        // Empty out the redirect links, only keep those which are published
        $this->removeRedirects('#__redirect_links', $minredirects);

        // Empty out the J2Store orders
        $this->removeJ2StoreOrders('#__j2store_orders', $minorders);

        // Return and error status if there was one.
        $return_status = ($status_content != Status::OK || $status_weblinks != Status::OK || $status_events != Status::OK) ? Status::INVALID_EXIT : Status::OK;
        ra_data_retentionHelper::stopJournal("RETENTION");

        return $return_status;
    }

    private function readConfigSetting($setting, $default) : int
    {
        $params = ComponentHelper::getParams('com_ra_data_retention');
		$testmode = $params->get('testmode', 0);

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $conditions = array(
            $db->quoteName('setting') . ' = ' . $db->quote(strtoupper($setting)),
            $db->quoteName('testmode') . ' = ' . $testmode
        );

        $query->select($db->quoteName('value'));
        $query->from($db->quoteName('#__ra_retention_settings'));
        $query->where($conditions);
        
        $db->setQuery($query);

        $result = $db->loadResult();
        if (is_null($result)) $result = $default;

        unset($query);
        unset($db);

        return $result;
    }

    private function removeRedirects($table, $keepMonths) : int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $select = $db->getQuery(true);
        try {
            // Set the state to Trashed and the modified date to the current date and time.
            $conditions = array(
                $db->quoteName('published') . ' != 1', 
                'DATE_ADD(' . $db->quoteName('modified_date') . ', INTERVAL ' .$keepMonths. ' MONTH) < NOW() '
            );

            // First log which items are going to be unpublished.
            $select->select($db->quoteName(['id', 'old_url', 'new_url', 'modified_date']));
            $select->from($db->quoteName($table));
            $select->where($conditions);

            $db->setQuery($select);
            $result = $db->loadAssocList();
			$datetime_now = date("Y-m-d H:i");
            foreach ($result as $file)
            {
                // Log the Entry
                ra_data_retentionHelper::logJournal("RETENTION", "Removing redirect from " . $table . " - Item: ". $file['old_url'] . ' , modified: ' . $file['modified_date'], $file['id'] . "/" .$datetime_now . "/" . $file('new_url'));
            }

            // Now delete the redirects.
            $query->delete($db->quoteName($table));
            $query->where($conditions);
    
            $db->setQuery($query);
    
            $result = $db->execute();
        }
        catch (Error $e)
        {
            unset($select);
            unset($query);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($select);
        unset($query);
        unset($db);    
    
        return Status::OK;
    }

    private function removeJ2StoreOrders($table, $keepMonths) : int
    {
        $db    = $this->getDatabase();
        $select = $db->getQuery(true);
        $query = $db->getQuery(true);
        try {
            // Set the state to Trashed and the modified date to the current date and time.
            $conditions = array(
                'DATE_ADD(' . $db->quoteName('modified_on') . ', INTERVAL ' .$keepMonths. ' MONTH) < NOW() '
            );

            // First log which items are going to be unpublished.
            $select->select($db->quoteName(['j2store_order_id', 'order_id', 'modified_on']));
            $select->from($db->quoteName($table));
            $select->where($conditions);

            $db->setQuery($select);
            $result = $db->loadAssocList();
			$datetime_now = date("Y-m-d H:i");
            foreach ($result as $file)
            {
                // Log the Entry
                ra_data_retentionHelper::logJournal("RETENTION", "Trashing J2Store Order - Invoice: ". $file['j2store_order_id'] . ' , modified on: ' . $file['modified_on'], $file['j2store_order_id'] . " / OrderID: " . $file('order_id'));
            }

            $query->delete($db->quoteName($table));
            $query->where($conditions);
    
            $db->setQuery($query);
    
            $result = $db->execute();

            // Now remove child table information for the orders removed
            $this->removeJ2StoreOrderChild('#__j2store_orderinfos', 'order_id', '#__j2store_orders', 'order_id');
            $this->removeJ2StoreOrderChild('#__j2store_ordertaxes', 'order_id', '#__j2store_orders', 'order_id');
            $this->removeJ2StoreOrderChild('#__j2store_ordershippings', 'order_id', '#__j2store_orders', 'order_id');
            $this->removeJ2StoreOrderChild('#__j2store_orderitems', 'order_id', '#__j2store_orders', 'order_id');
            $this->removeJ2StoreOrderChild('#__j2store_orderitemattributes', 'orderitem_id', '#__j2store_orderitems', 'j2store_orderitem_id');
            $this->removeJ2StoreOrderChild('#__j2store_orderhistories', 'order_id', '#__j2store_orders', 'order_id');
            $this->removeJ2StoreOrderChild('#__j2store_orderdownloads', 'order_id', '#__j2store_orders', 'order_id');
            $this->removeJ2StoreOrderChild('#__j2store_orderfees', 'order_id', '#__j2store_orders', 'order_id');
            $this->removeJ2StoreOrderChild('#__j2store_orderdiscounts', 'order_id', '#__j2store_orders', 'order_id');
            $this->removeJ2StoreOrderChild('#__j2store_cartitems', 'cart_id', '#__j2store_carts', 'j2store_cart_id');
            // Now clear the address / customer table down
            $this->removeJ2StoreAddresses();
        }
        catch (Error $e)
        {
            unset($select);
            unset($query);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($select);
        unset($query);
        unset($db);    
    
        return Status::OK;
    }

    private function removeJ2StoreOrderChild($parenttable, $parentcol, $childtable, $childcol) : int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        try {
            // Set the state to Trashed and the modified date to the current date and time.
            $conditions = array(
                $db->quoteName($parentcol) . ' NOT IN (SELECT ' . $db->quoteName($childcol) . ' FROM ' . $db->quoteName($childtable) . ')'
            );

            $query->delete($db->quoteName($parenttable));
            $query->where($conditions);
    
            $db->setQuery($query);
    
            $result = $db->execute();
        }
        catch (Error $e)
        {
            unset($query);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($query);
        unset($db);    
    
        return Status::OK;
    }

    private function removeJ2StoreAddresses() : int
    {
        $db    = $this->getDatabase();
        $man_query = $db->getQuery(true);
        $ven_query = $db->getQuery(true);
        $order_query = $db->getQuery(true);
        
        $query = $db->getQuery(true);
        try {
            $man_query->select($db->quoteName('address_id'));
            $man_query->from($db->quoteName('#__j2store_manufacturers'));
            $ven_query->select($db->quoteName('address_id'));
            $ven_query->from($db->quoteName('#__j2store_vendors'));

            $order_query->select($db->quoteName('j2store_address_id'));
            $order_query->from($db->quoteName('#__j2store_addresses'));
            $conditions = array(
                $db->quoteName('email') . ' IN (SELECT ' . $db->quoteName('user_email') . ' FROM ' . $db->quoteName('#__j2store_orders') . ')'
            );
            $order_query->where($conditions);

            $db->setQuery($man_query);
            $man_result = $db->loadColumn();
            
            $db->setQuery($ven_query);
            $ven_result = $db->loadColumn();

            $db->setQuery($order_query);
            $order_result = $db->loadColumn();

            // Merge the arrays 
            $results = array_merge($man_result, $ven_result, $order_result);

            // Now we have an array of items to keep, we can delete the remainder
            $query->delete('#__j2store_addresses');
            $valid_addresses = $query->bindArray($results);
            $query->where($db->quoteName('j2store_address_id') . ' NOT IN (' . implode(',', $valid_addresses) . ')');
            
            // Execute the delete
            $db->setQuery($query);
            $result = $db->execute();
        }
        catch (Error $e)
        {
            unset($man_query);
            unset($ven_query);
            unset($orders_query);
            unset($query);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($man_query);
        unset($ven_query);
        unset($orders_query);
        unset($query);
        unset($db);    
    
        return Status::OK;
    }

    private function ApplyRetentionTable($table, $type, $testmode, $maxMonths, ExecuteTaskEvent $event): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $select = $db->getQuery(true);

        try {
            // Set the state to Trashed and the modified date to the current date and time.
            $fields = array(
                $db->quoteName('a.state') . ' = -2', 
                $db->quoteName('a.modified') . ' = NOW()'
            );

            $conditions = array(
                $db->quoteName('rc.type') . ' = ' . $db->quote($type), 
                $db->quoteName('rc.testmode') . ' = ' . $testmode,
                $db->quoteName('a.state') . ' = 1',
                $db->quoteName('a.publish_down') . ' IS NULL',
                $db->quoteName('rc.months') . ' <> ' . $maxMonths,
                'DATE_ADD(' . $db->quoteName('a.publish_up') . ', INTERVAL rc.months MONTH) < CURRENT_DATE()    '
            );

            // First log which items are going to be unpublished.
            $select->select($db->quoteName(['a.id', 'a.title']));
            $select->from($db->quoteName($table, 'a'));
            $select->join('INNER', $db->quoteName('#__ra_calc_retention_categories','rc') . ' ON ' . $db->quoteName('a.catid') . '=' . $db->quoteName('rc.catid'));
            $select->where($conditions);

            $db->setQuery($select);
            $result = $db->loadAssocList();
			$datetime_now = date("Y-m-d H:i");
            foreach ($result as $file)
            {
                // Log the Entry
                ra_data_retentionHelper::logJournal("RETENTION", "Trashing item from " . $table . " as per policy - Item: ". $file['title'], $file['id'] . "/" .$datetime_now);
            }

            $query->update($db->quoteName($table, 'a'));
            $query->set($fields);
            $query->join('INNER', $db->quoteName('#__ra_calc_retention_categories','rc') . ' ON ' . $db->quoteName('a.catid') . '=' . $db->quoteName('rc.catid'));
            $query->where($conditions);
    
            $db->setQuery($query);
    
            $result = $db->execute();
        }
        catch (Error $e)
        {
            unset($select);
            unset($query);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($select);
        unset($query);
        unset($db);    
    
        return Status::OK;
    }

    private function RemoveUnpublishedTable($table, $type, $testmode, $maxMonths, ExecuteTaskEvent $event): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $select = $db->getQuery(true);
        try {
            // Set the state to Trashed and the modified date to the current date and time.
            $fields = array(
                $db->quoteName('a.state') . ' = -2', 
                $db->quoteName('a.modified') . ' = NOW()'
            );

            $conditions = array(
                $db->quoteName('a.state') . ' = 1',
                $db->quoteName('a.publish_down') . ' <= NOW()',
            );

            // First log which items are going to be unpublished.
            $select->select($db->quoteName(['a.id', 'a.title', 'a.modified', 'a.publish_down']));
            $select->from($db->quoteName($table, 'a'));
            $select->where($conditions);

            $db->setQuery($select);
            $result = $db->loadAssocList();
			$datetime_now = date("Y-m-d H:i");
            foreach ($result as $file)
            {
                // Log the Entry
                ra_data_retentionHelper::logJournal("RETENTION", "Trashing expired item from " . $table . " - Item: ". $file['title'] . ' , publish down: ' . $file['publish_down'], $file['id'] . "/" .$datetime_now);
            }

            // Now update them
            $query->update($db->quoteName($table, 'a'));
            $query->set($fields);
            $query->where($conditions);
    
            $db->setQuery($query);
    
            $result = $db->execute();
        }
        catch (Error $e)
        {
            unset($select);
            unset($query);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($select);
        unset($query);
        unset($db);    
    
        return Status::OK;
    }

    private function ApplyRetentionEvents($table, $type, $testmode, $maxMonths, ExecuteTaskEvent $event): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $select = $db->getQuery(true);
        try {
            // Set the state to Trashed and the modified date to the current date and time.
            $fields = array(
                $db->quoteName('a.published') . ' = 0', 
                $db->quoteName('a.modified') . ' = NOW()'
            );

            $conditions = array(
                $db->quoteName('rc.type') . ' = ' . $db->quote($type), 
                $db->quoteName('rc.testmode') . ' = ' . $testmode,
                $db->quoteName('a.published') . ' = 1',
                $db->quoteName('rc.months') . ' <> ' . $maxMonths,
                'DATE_ADD(' . $db->quoteName('a.date') . ', INTERVAL rc.months MONTH) < CURRENT_DATE()    '
            );
            // First log which items are going to be unpublished.
            $select->select($db->quoteName(['a.id', 'a.folder', 'a.date']));
            $select->from($db->quoteName($table, 'a'));
            $select->join('INNER', $db->quoteName('#__ra_calc_retention_categories','rc') . ' ON ' . $db->quoteName('a.catid') . '=' . $db->quoteName('rc.catid'));
            $select->where($conditions);

            $db->setQuery($select);
            $result = $db->loadAssocList();
			$datetime_now = date("Y-m-d H:i");
            foreach ($result as $file)
            {
                // Log the Entry
                $folder = $file['folder'];
                $id = $file['id'];
                $date = $file['date'];
                ra_data_retentionHelper::logJournal("RETENTION", "EventGallery (" . $table . ") - Unpublishing Folder: ". $folder , $id . "/" . $date );
            }

            $query->update($db->quoteName($table, 'a'));
            $query->set($fields);
            $query->join('INNER', $db->quoteName('#__ra_calc_retention_categories','rc') . ' ON ' . $db->quoteName('a.catid') . '=' . $db->quoteName('rc.catid'));
            $query->where($conditions);
    
            $db->setQuery($query);
    
            $result = $db->execute();
        }
        catch (Error $e)
        {
            unset($select);
            unset($query);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($select);
        unset($query);
        unset($db);    
    
        return Status::OK;
    }

    private function LimitRetentionEvents($table, $type, $testmode, $minEvents): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        try {
            // Set the state to Trashed and the modified date to the current date and time.
            $conditions = array(
                $db->quoteName('a.type') . ' = ' . $db->quote($type), 
                $db->quoteName('a.testmode') . ' = ' . $testmode
            );
            

            $query->select($db->quoteName('a.catid'));
            $query->from($db->quoteName('#__ra_retention_categories', 'a'));
            $query->where($conditions);
            
            $db->setQuery($query);
    
            $result = $db->loadObjectList();

            $queryPublish = $db->getQuery(true);
            $queryPublish->update($db->quoteName($table));
            $queryPublish->set($db->quoteName('published') . " = 1");
            $queryPublish->where($db->quoteName('id') . ' = :idval');
            foreach ($result as $category)
            {
                // Get the top n events
                $eventsToKeep = $this->GetTopEvents($table, $type, $testmode, $category->catid, $minEvents);
                foreach ($eventsToKeep as $event)
                {
                    ra_data_retentionHelper::logJournal("RETENTION", "EventGallery (" . $table . ") - Keeping Folder with ID: ". $event, $event);

                    // Need to keep this event, so set it back to published
                    $queryPublish->bind(':idval', $event);
                    $db->setQuery($queryPublish);
                    $db->execute();
                }
            }
            unset($queryPublish);
        }
        catch (Error $e)
        {
            unset($query);
            unset($queryPublish);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($query);
        unset($db);    
    
        return Status::OK;
    }
    private function GetTopEvents($table, $type, $testmode, $category, $minEvents): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query2 = $db->getQuery(true); //Query to see if there is already a number of items being displayed
        try {
            $fields = array(
                $db->quoteName('a.id')
            );

            $conditions = array(
                $db->quoteName('rc.type') . ' = ' . $db->quote($type), 
                $db->quoteName('rc.testmode') . ' = ' . $testmode,
                $db->quoteName('a.published') . ' = 0',
                $db->quoteName('a.catid') . ' = ' .$category,
            );
            // Set the state to Trashed and the modified date to the current date and time.
            $query2->select('COUNT(' .$db->quoteName('a.id'). ')');
            $query2->from($db->quoteName($table, 'a'));
            $query2->join('INNER', $db->quoteName('#__ra_calc_retention_categories','rc') . ' ON ' . $db->quoteName('a.catid') . '=' . $db->quoteName('rc.catid'));
            $query2->where(array(
                $db->quoteName('a.published') . ' = 1',
                $db->quoteName('rc.type') . ' = ' . $db->quote($type),
                $db->quoteName('a.catid') . ' = ' .$category
            ));

            $db->setQuery($query2);
            $countPublished = $db->loadResult();
            
            // Check to see if we are displaying enough items
            if ($countPublished < $minEvents)
            {
                // We are not displaying enough events so find some more

                $query->select($db->quoteName('a.id'));
                $query->from($db->quoteName($table, 'a'));
                $query->join('INNER', $db->quoteName('#__ra_calc_retention_categories','rc') . ' ON ' . $db->quoteName('a.catid') . '=' . $db->quoteName('rc.catid'));
                $query->where($conditions);
                $query->order('date DESC LIMIT ' . ($minEvents - $countPublished));
                
                $db->setQuery($query);
        
                $result = $db->loadColumn();
            }
            else{
                // Return an empty array so there is nothing to add
                $result = array();
            }
        }
        catch (Error $e)
        {
            unset($query);
            unset($query2);
            unset($db);    
            return array();
        }
        unset($query);
        unset($query2);
        unset($db);    
    
        return $result;
    }
    /**
     * Method for removing items located within the trash as part of retention policy.
     *
     * @param   ExecuteTaskEvent  $event  The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @since  5.0.0
     * @throws \Exception
     */
    private function EmptyTrash(ExecuteTaskEvent $event): int
    {
		$component = ComponentHelper::isEnabled('com_ra_data_retention')
        ? $this->getApplication()->bootComponent('com_ra_data_retention')
        : null;

        if (!($component instanceof MVCFactoryServiceInterface))
        {
            throw new RuntimeException('The Data Retention component is not installed or has been disabled.');
        }

        ra_data_retentionHelper::startJournal("EMPTYTRASH");

        // Find how long you need to keep the trash for
        $monthsToKeep = $this->readConfigSetting('MINTRASH', 6);
        ra_data_retentionHelper::logJournal("EMPTYTRASH", "Empty Trash Commencing. MonthsToKeep: ". $monthsToKeep, $monthsToKeep);

        // Remove from tables based on the length of time you need to keep the trash
        $status_content = $this->EmptyTrashTable('#__content', $monthsToKeep, $event);
        $status_weblinks = $this->EmptyTrashTable('#__weblinks', $monthsToKeep, $event);
        $status_events = $this->EmptyTrashEvents($monthsToKeep, $event);

        // Return the appropriate status
        $return_status = ($status_content != Status::OK || $status_weblinks != Status::OK || $status_events != Status::OK) ? Status::INVALID_EXIT : Status::OK;
        
        ra_data_retentionHelper::stopJournal("EMPTYTRASH");
        return $return_status;
    }

    private function EmptyTrashTable($table, $monthsToKeep, ExecuteTaskEvent $event): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $selectquery = $db->getQuery(true);
        try {
            // Delete items from the trash where they are greater than 6 months old
            $conditions = 'state = -2' ; 
            $conditions = $conditions . ' AND DATE_ADD(modified, INTERVAL ' . $monthsToKeep . ' MONTH) < NOW()';
    
            // First log what we are going to remove
            $selectquery->select($db->quoteName(['id', 'title', 'modified']));
            $selectquery->from($db->quoteName($table));
            $selectquery->where($conditions);

            $db->setQuery($selectquery) ;
            $result = $db->loadAssocList();
            foreach ($result as $file)
            {
                // Log the Entry
                ra_data_retentionHelper::logJournal("EMPTYTRASH", $table . " - Entry Removed: ". $file['title'], $file['id'] . '/' . $file['modified'] . '/Months to Keep: ' . $monthsToKeep);
            }

            $query->delete($db->quoteName($table));
            $query->where($conditions);
    
            $db->setQuery($query);
    
            $result = $db->execute();
        }
        catch (Error $e)
        {
            unset($selectquery);
            unset($query);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($selectquery);
        unset($query);
        unset($db);    
    
        return Status::OK;
    }

    private function EmptyTrashEvents($monthsToKeep, ExecuteTaskEvent $event): int
    {
        $db    = $this->getDatabase();
        $folderquery = $db->getQuery(true);
        $filequery = $db->getQuery(true);
        $selectquery = $db->getQuery(true);
        $folderlistquery = $db->getQuery(true);

        try {
            // Delete items from the trash where they are greater than 6 months old
            $folderconditions = array(
                $db->quoteName('published') . ' = 0', 
                'DATE_ADD(' . $db->quoteName('modified') .', INTERVAL ' . $monthsToKeep . ' MONTH) < NOW()'
            );

            // First need to log the files being removed from the event
            $selectquery->select($db->quoteName(['fo.description','fi.folder','fi.file','fi.modified']));
            $selectquery->from($db->quoteName('#__eventgallery_file', 'fi'));
            $selectquery->join('INNER', $db->quoteName('#__eventgallery_folder','fo') . ' ON ' . $db->quoteName('fi.folder') . '=' . $db->quoteName('fo.folder'));
            $selectquery->where(array(
                $db->quoteName('fo.published') . ' = 0',
                "DATE_ADD(" . $db->quoteName('fo.modified') .", INTERVAL " . $monthsToKeep . " MONTH) < NOW()"));

            $db->setQuery($selectquery) ;
            $result = $db->loadAssocList();
            foreach ($result as $file)
            {
                // Log the Entry
                ra_data_retentionHelper::logJournal("EMPTYTRASH","EventGallery - File Removed: ". $file['file'] . " From Event :" . $file['folder'], $file['description'] . '/' . $file['modified'] . '/Months to Keep: ' . $monthsToKeep);
            }
    
            $filequery = "DELETE " . $db->quoteName('#__eventgallery_file') . 
                        " FROM " . $db->quoteName('#__eventgallery_file') . 
                        " INNER JOIN " . $db->quoteName('#__eventgallery_folder') . 
                        " ON " . $db->quoteName('#__eventgallery_file.folder') . " = " . $db->quoteName('#__eventgallery_folder.folder') . 
                        " WHERE " . $db->quoteName('#__eventgallery_folder.published') . " = 0 " . 
                        " AND DATE_ADD(" . $db->quoteName('#__eventgallery_folder.modified') .", INTERVAL " . $monthsToKeep . " MONTH) < NOW()";

            // Remove the files before removing the folder
            $db->setQuery($filequery);    
            $result = $db->execute();

            // Now we need to determine if the folder is being removed
            $folderlistquery->select($db->quoteName(['folder','description','modified'])) ;
            $folderlistquery->from($db->quoteName('#__eventgallery_folder'));
            $folderlistquery->where($folderconditions);
            $db->setQuery($folderlistquery) ;
            $result = $db->loadAssocList();
            foreach ($result as $folder)
            {
                // Log the Entry
                ra_data_retentionHelper::logJournal("EMPTYTRASH","Event Gallery - Event Removed: ". $folder['folder'], $folder['description'] . '/' . $folder['modified'] . '/Months to Keep: ' . $monthsToKeep);
            }

            // Files should now be gone, so remove the folder
            $folderquery->delete($db->quoteName('#__eventgallery_folder'));
            $folderquery->where($folderconditions);
            $db->setQuery($folderquery);    
            $result = $db->execute();
        }
        catch (Error $e)
        {
            unset($filequery);
            unset($folderquery);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($selectquery);
        unset($folderlistquery);
        unset($filequery);
        unset($folderquery);
        unset($db);    
    
        return Status::OK;
    }

    private function RemoveFiles(ExecuteTaskEvent $event): int
    {
        // Make sure Data Retention is installed and enabled.
		$component = ComponentHelper::isEnabled('com_ra_data_retention')
        ? $this->getApplication()->bootComponent('com_ra_data_retention')
        : null;

        if (!($component instanceof MVCFactoryServiceInterface))
        {
            throw new RuntimeException('The Data Retention component is not installed or has been disabled.');
        }

        ra_data_retentionHelper::startJournal("DELETEFILES");

        // First iterate each of the folders to search and obtain details of their contents
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        try {
            // Delete items from the trash where they are greater than 6 months old    
            $query->select($db->quoteName(['filepath','exclude']));
            $query->from($db->quoteName('#__ra_retention_filepaths'));
            $query->where($db->quoteName('state') . ' = 1');         // Only use published entries
    
            $db->setQuery($query);
            // Load the list of paths
            $paths = $db->loadObjectList();

            // Create the exclude array
            $exclude = array();
            foreach ($paths as $path)
            {
                // if this is a path to exclude then add to the list
                if ($path->exclude == 1)
                {
                    // add it to the list
                    array_push($exclude, $path->filepath);
                }
            }
            // Now iterate those which are not excluded.
            foreach ($paths as $path)
            {
                if ($path->exclude == 0)
                {
                    // Now we need to handle the specific path held in $path
                    $this->RemoveFilesFromPath($path->filepath, $exclude);
                    // Remove any empty folders
                    $this->RemoveEmptyDirectoriesFromPath($path->filepath, $exclude);
                }
            }
        }
        catch (Error $e)
        {
            unset($query);
            unset($db);    
            return Status::INVALID_EXIT;
        }
        unset($query);
        unset($db);    

        ra_data_retentionHelper::stopJournal("DELETEFILES");

        return Status::OK;
    }

    private function RemoveEmptyDirectoriesFromPath($path, $excludepath)
    {
        // First get a list of the structure of the path
        $fullpath = Path::clean(JPATH_ROOT . '/' . $path) ;
        $folders = Folder::listFolderTree($fullpath, '', 100);
        $exclude = array('index.html');
        // $root = array('id' => -1, 'parent' => -1, 'name' => '', 'relpath' => $path, 'fullname' => $fullpath, '');
        // Add the root one to the array
        // array_push($folders, $root);
        // Try getting a list of files
        $subfolders = array();
        foreach ($folders as $key => $folder)
        {
            $parent = $folder['parent'];
            $id = $folder['id'];
            // Create entries for the current and parent.
            if (!array_key_exists($id, $subfolders)) $subfolders[$id] = 0;
            if (!array_key_exists($parent, $subfolders)) $subfolders[$parent] = 0;
            // Increment the count of subfolders
            $subfolders[$parent]++;
        }
        $emptySubfolders = true;
        while ($emptySubfolders)
        {
            $removeFolders = array();
            // Assume there are none, until we remove a folder
            $emptySubfolders = false;
            // Iterate each folder and see if there are any empty ones
            foreach($folders as $key => $folder)
            {
                $id = $folder['id'];
                $parent = $folder['parent'];

                // check if there are any subfolders
                if ($subfolders[$id] == 0)
                {
                    // There are no subfolders, so check to see if there are files
                    $files = Folder::files($folder['fullname'], '.', false, false, $exclude);
                    if (count($files) == 0)
                    {
                        // Delete the physical folder here.
                        if (!$this->excludePath($folder['fullname'], $excludepath))
                        {
                            // Only remove it if it actually has not been excluded
                            Folder::delete($folder['fullname']);
                        }

                        // The folder is empty so remove it, but on
                        $removeFolders[$key] = $folder;

                        // Now we need to remove it from the array and discount the values
                        $subfolders[$parent]-- ;
                    }
                }
            }
            // remove the folders which are empty so we don't iterate them again
            foreach($removeFolders as $key => $removefolder)
            {
                $id = $removefolder['id'];
                unset ($subfolders[$id]);
                unset ($folders[$key]);

                // We have removes some folders so loop again.
                $emptySubfolders = true;
            }
            unset($removeFolders);
        }
        return;
    }

    private function excludePath($path, $exclude) : bool
    {
        $returnval = false;
        foreach ($exclude as $excludepath)
        {
            if (str_contains(strtoupper($path), strtoupper($excludepath)))
            {
                $returnval = true;
            }
        }
        return $returnval;
    }

    private function RemoveFilesFromPath($path, $excludepaths)
    {
        // First get a list of the structure of the path
        $fullpath = Path::clean(JPATH_ROOT . '/' . $path) ;
        $folders = Folder::listFolderTree($fullpath, '', 100);
        $root = array('id' => -1, 'parent' => -1, 'name' => '', 'relpath' => $path, 'fullname' => $fullpath, '');
        // Add the root one to the array
        array_push($folders, $root);
        // Try getting a list of files
        $exclude = array();
        // You now have the folders within the path. Iterate them looking at the files
        foreach ($folders as $folder)
        {
            if (!$this->excludePath($folder['fullname'], $excludepaths))
            {
                // get the files in the folder
                $files = Folder::files($folder['fullname'], '.', false, false, $exclude);

                // Iterate each file
                foreach ($files as $file)
                {
                    // Handle each folder in turn
                    // Get the files in the folder
                    if (!$this->FindFileInTables($file))
                    {
                        // Create the full path to the file.
                        $fullfilename = Path::clean($folder['fullname'] . '/' . $file);
                        //Log the file being removed
                        ra_data_retentionHelper::logJournal("DELETEFILES", $file, $fullfilename);
                        // File has not been found so delete it.
                        File::delete($fullfilename);
                    }
                }
            }
        }
        return;
    }

    private function FindFileInTables($file) : bool
    {
        $found = false ; // Assume it has not been found
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        try {
            // Delete items from the trash where they are greater than 6 months old    
            $query->select($db->quoteName(['tablename','columnname']));
            $query->from($db->quoteName('#__ra_retention_filetables'));
            $query->where($db->quoteName('state') . ' = 1');         // Only use published entries
    
            $db->setQuery($query);
            // Load the list of paths
            $tables = $db->loadObjectList();
            foreach ($tables as $table)
            {
                // Now we need to handle the specific path held in $path
                $found = $this->FindFileInTable($file, $table->tablename, $table->columnname);
                // If it was found the exit the loop as you don't need to continue.
                if ($found) break;
            }
        }
        catch (Error $e)
        {
            unset($query);
            unset($db);
            // Exception occured so assume the file was found.    
            return true;
        }
        unset($query);
        unset($db);    
    
        return $found;
    }


    private function FindFileInTable($file, $tablename, $columnname) : bool
    {
        $found = false ; // Assume it has not been found
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        try {
            // Delete items from the trash where they are greater than 6 months old    
            $query->select('COUNT(' .$db->quoteName($columnname) . ')');
            $query->from($db->quoteName($tablename));
            $query->where($db->quoteName($columnname) . ' LIKE :file');
            $filename = '%'.$file.'%';
            $query->bind(':file', $filename);
    
            $db->setQuery($query);
            // Load the list of paths
            $count = $db->loadResult();
            $found = ($count > 0) ? true : false;
            if (!$found)
            {
                // Not found so check the urlencoded value
                $urlencoded = '%' . urlencode($file) . '%';
                $query->bind(':file', $urlencoded);
                $db->setQuery($query);
                // Load the list of paths
                $count = $db->loadResult();
                $found = ($count > 0) ? true : false;
            }
            // If still not found convert spaces to the hex code (%20) and research
            if (!$found)
            {
                // Not found so check the urlencoded value
                $coded = '%' . str_replace(" ","%20", $file) . '%';
                $query->bind(':file', $coded);
                $db->setQuery($query);
                // Load the list of paths
                $count = $db->loadResult();
                $found = ($count > 0) ? true : false;
            }
        }
        catch (Error $e)
        {
            unset($query);
            unset($db);
            // Exception occured so assume the file was found.    
            return true;
        }
        unset($query);
        unset($db);    
    
        return $found;

    }
    /**
     * Get log files from log folder
     *
     * @param   string  $path  The folder to get log files
     *
     * @return  array   The log files in the given path grouped by version number (not rotated files have number 0)
     *
     * @since   5.0.0
     */
    private function getLogFiles($path)
    {
        $logFiles = [];
        $files    = Folder::files($path, '\.php$');

        foreach ($files as $file) {
            $parts = explode('.', $file);

            /*
             * Rotated log file has this filename format [VERSION].[FILENAME].php. So if $parts has at least 3 elements
             * and the first element is a number, we know that it's a rotated file and can get it's current version
             */
            if (\count($parts) >= 3 && is_numeric($parts[0])) {
                $version = (int) $parts[0];
            } else {
                $version = 0;
            }

            if (!isset($logFiles[$version])) {
                $logFiles[$version] = [];
            }

            $logFiles[$version][] = $file;
        }

        return $logFiles;
    }
}
