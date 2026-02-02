<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Test
 * @author     Keith Grimes <yellow.submarine@ramblers-webs.org.uk>
 * @copyright  2024 Keith Grimes
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblerswebs\Component\Ra_data_retention\Administrator\Helper;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Mail\MailerFactoryAwareTrait;
use \Joomla\CMS\Mail\MailerFactoryAwareInterface;
use \Joomla\CMS\Mail\MailerFactoryInterface;
use \Joomla\CMS\Mail\MailTemplate;

/**
 * Test helper.
 *
 * @since  1.0.0
 */
class Ra_data_retentionHelper implements MailerFactoryAwareInterface
{
	use MailerFactoryAwareTrait;
	/**
	 * Gets the files attached to an item
	 *
	 * @param   int     $pk     The item's id
	 *
	 * @param   string  $table  The table's name
	 *
	 * @param   string  $field  The field's name
	 *
	 * @return  array  The files
	 */
	static $photocategories = array();
	static $fileEntries = array();

	public static function getFiles($pk, $table, $field)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$query
			->select($field)
			->from($table)
			->where('id = ' . (int) $pk);

		$db->setQuery($query);

		return explode(',', $db->loadResult());
	}

	/**
	 * Gets a list of the actions that can be performed.
	 *
	 * @return  CMSObject
	 *
	 * @since   1.0.0
	 */
	public static function getActions()
	{
		$user = Factory::getApplication()->getIdentity();
		$result = new CMSObject;

		$assetName = 'com_ra_data_retention';

		$actions = array(
			'core.admin', 'core.manage', 'core.create', 'core.edit', 'core.edit.own', 'core.edit.state', 'core.delete'
		);

		foreach ($actions as $action)
		{
			$result->set($action, $user->authorise($action, $assetName));
		}

		return $result;
	}

	public static function CalculateFullRetentions($type, $defaultretention, $testmode)
	{
		$categoryRetentions = array() ; // Declare an empty array
		$definedCategories = array() ; // Array of Categories which have been defined
		$removeRetentions = array();
		$removeCalculatedRetentions = array();
		
		// First load the information into local data structures.
		ra_data_retentionHelper::loadCategories($type, $categoryRetentions, $defaultretention);
		ra_data_retentionHelper::loadRetentions($type, $categoryRetentions, $definedCategories, $removeRetentions, $testmode);
		ra_data_retentionHelper::loadCalculatedRetentions($type, $categoryRetentions, $removeCalculatedRetentions, $testmode);
		
		// Update the retentions for each category to ensure they all have the correct value
		ra_data_retentionHelper::calculateCategoryRetentions($categoryRetentions, $defaultretention);
		
		// Now store the values we have calculated
		ra_data_retentionHelper::storeCategoryRetentions($type, $categoryRetentions, $testmode);

		// Remove any defined retentions for which the category no longer exists
		ra_data_retentionHelper::removeCategoryRetentions($type, $removeRetentions, $testmode);		

		// remove any calculated retentions where the cateogory no longer exists.
		ra_data_retentionHelper::removeCalculatedRetentions($type, $removeCalculatedRetentions, $testmode);
	}
	private static function calculateCategoryRetentions(&$categoryRetentions, $defaultretention)
	{
		// Iterate all the categories.
		foreach ($categoryRetentions as $category)
		{
			// If this category does not have a defined value, then find the parent value and store it.
			if ($category->months < 0)
			{
				// No retention period has been defined, so we need the value of the parent. 
				$category->months = ra_data_retentionHelper::findParentRetention($category->catid, $categoryRetentions, $defaultretention);
				// As we had to find the value, this is calculated so set the flag
				$category->isCalculated = true ;
			}
		}
	}
	private static function storeCategoryRetentions($type, $categoryRetentions, $testmode)
	{
		// Now update the table within the SQL database.
		$db = Factory::getContainer()->get('DatabaseDriver');
		foreach ($categoryRetentions as $retention)
		{
			// Update or insert irrespective of change. This ensures the figures are correct.
			$query = $db->getQuery(true);
			// Don't save the root entry, this was auto generated
			if ($retention->catid > 1)
			{
				// There has been a change so it needs to be stored
				if ($retention->originalRetention < 0)
				{
					// This is a new record so INSERT the value
					$columns = array('catid','type','months','isCalculated','testmode');
					$values = array(intval($retention->catid), $db->quote($type), intval($retention->months), intval($retention->isCalculated), intval($testmode));
					// This is an existing record, so update the value which already exists.
					$query->insert($db->quoteName('#__ra_calc_retention_categories'))
							->columns($db->quoteName($columns))
							->values(implode(',', $values));
				}
				else
				{
					$fields = array(
										$db->quoteName('months') . ' = ' . intval($retention->months),
										$db->quoteName('isCalculated') . ' = ' . intval($retention->isCalculated));
					
					$conditions = array('catid = ' . intval($retention->catid), $db->quoteName('type') . ' = "' . $type . '"' ,'testmode = ' . intval($testmode));
					// This is an existing record, so update the value which already exists.
					$query->update($db->quoteName('#__ra_calc_retention_categories'))
							->set($fields)
							->where($conditions);

				}
				$db->setQuery($query);
				$result = $db->execute();
								unset($columns);
								unset($values);
								unset($fields);
								unset($conditions);

			}
			unset($query);
		}
		unset($db);
	}
	private static function removeCategoryRetentions($type, $removeRetentions, $testmode)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		foreach ($removeRetentions as $catid)
		{
			$query = $db->getQuery(true);
			$conditions = 'catid = ' . $catid; 
			$conditions = $conditions . ' AND type = ' . $db->quote($type) . ' AND testmode = ' . $testmode;

			$query->delete($db->quoteName('#__ra_retention_categories'));
			$query->where($conditions);

			$db->setQuery($query);

			$result = $db->execute();
			unset($query);
		}
		unset($db);
	}
	private static function removeCalculatedRetentions($type, $removeCalculatedRetentions, $testmode)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		foreach ($removeCalculatedRetentions as $catid)
		{
			$query = $db->getQuery(true);
			$conditions = 'catid = ' . $catid; 
			$conditions = $conditions . ' AND type = ' . $db->quote($type) . ' AND testmode = ' . $testmode;

			$query->delete($db->quoteName('#__ra_calc_retention_categories'));
			$query->where($conditions);

			$db->setQuery($query);

			$result = $db->execute();
			unset($query);
		}
		unset($db);
	}

	private static function findParentRetention($catid, $categoryRetentions, $defaultretention)
	/*
	** Finds and returns the retention value of the closest parent to the category defined.
	** If nothing is found (reaches the root node) then the default is used. 
	*/
	{
		$current_id = $catid;
		// Loop until you are at the root entry
		while ($current_id > 1)
		{
			// We don't have a value
			if ($categoryRetentions[$current_id]->months >= 0)
			{
				// Found the value
				return $categoryRetentions[$current_id]->months;
			}
			// This one is also not defined, so move up to the next parent
			$current_id = $categoryRetentions[$current_id]->parentID;
		}
		// Nothing found so return the default
		return $defaultretention;
	}

	private static function loadCategories($type, &$categoryRetentions, $defaultretention)
	/*
	** Used to load a raw copy of the categories for the associated type
	** No retention values are applied at this point.
	*/
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$content_type = ($type == "ARTICLE" ? "COM_CONTENT" : ($type == "PHOTO" ? "COM_EVENTGALLERY" : "COM_WEBLINKS"));
		// Define the query to return all the categories which could have a retention period
		$query = $db->getQuery(true);
		$query->select('id, parent_id, path');
		$query->from($db->quoteName('#__categories'));
		$query->where($db->quoteName('extension')." = ".$db->quote($content_type));
		$query->where($db->quoteName('published')." = " . intval(1)); // Only use published entries

		$db->setQuery($query);
		$rowlist = $db->loadAssocList();

		// Add the root entry
		$class = new \stdClass();
		$class->catid = 1;
		$class->path = 'ROOT';	// Store the path to make debugging easier, otherwise not needed.
		$class->parentID = 0; // Store the parent id. 
		$class->isCalculated = false; // Assume it is calculated
		$class->originalRetention = -1; // Default the calculated retention to an invalid value
		$class->originalCalculated = -1 ; // Default the original isCalculated.
		$class->months = $defaultretention; // Default the months to a negative which is invalid
		$class->id = 0; // Default the id to zero (invalid)
		
		// Store the record based on the category id.
		$categoryRetentions[$class->catid] = $class ;				
		
		foreach ($rowlist as $record)
		{
			$catid = $record['id'];
			if (array_key_exists($catid, $categoryRetentions))
			{
				// The record exists (not sure why, so update the parent id)				
				$categoryRetentions[$id]->parentID = $catid > 1 ? $record['parent_id'] : 0;
			}
			else
			{
				// This is a new record so store the appropriate parts
				$class = new \stdClass();
				$class->catid = $catid;
				$class->path = $record['path'];	// Store the path to make debugging easier, otherwise not needed.
				$class->parentID = $catid > 1 ? $record['parent_id'] : 0; // Store the parent id. 
				$class->isCalculated = true; // Assume it is calculated
				$class->originalRetention = -1; // Default the calculated retention to an invalid value
				$class->originalCalculated = -1 ; // Default the original isCalculated.
				$class->months = -1; // Default the months to a negative which is invalid
				$class->id = 0; // Default the id to zero (invalid)
				
				// Store the record based on the category id.
				$categoryRetentions[$class->catid] = $class ;				
			}
		}

		unset($record);
		unset($query);
		unset($db);
	}

	private static function loadRetentions($type, &$categoryRetentions, &$definedCategories, &$removeRetentions, $testmode)
	/*
	** Loads the retention values which have been defined via the UI. 
	** This is only the base values. These are applied to the loaded categories via the load categories method
	** 
	** Pre-Requisite call: loadCategories
	*/
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		// Define the query to return all the categories which could have a retention period
		$query = $db->getQuery(true);
		$query->select('id, type, months, catid');
		$query->from($db->quoteName('#__ra_retention_categories'));
		$query->where($db->quoteName('type')." = ".$db->quote($type));
		$query->where('testmode = ' .  $testmode);

		$db->setQuery($query);
		$rowlist = $db->loadAssocList();
		
		foreach ($rowlist as $record)
		{
			$catid = $record['catid'];
 			if (array_key_exists($catid, $categoryRetentions))
			{
				// The record does exist in the list so update the retention period
				$categoryRetentions[$catid]->months = $record['months'];
	
				// figure taken has been defined in the UI so has not been calculated
				$categoryRetentions[$catid]->isCalculated = false ; 

				// Add the current category to the list of categories which have a defined retention period.
				array_push($definedCategories, $catid);
			}
			else
			{
				// Category does not exist within the system, so ensure it is removed from the retention categories table.
				array_push($removeRetentions, $catid);
			}
		}
		
		unset($record);
		unset($query);
		unset($db);	
	}
	
	private static function loadCalculatedRetentions($type, &$categoryRetentions, &$removeCalculatedRetentions, $testmode)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		
		// Define the query to return all the categories which could have a retention period
		$query = $db->getQuery(true);
		$query->select('id, catid, months, isCalculated');
		$query->from($db->quoteName('#__ra_calc_retention_categories'));
		$query->where($db->quoteName('type')." = ".$db->quote($type));
		$query->where($db->quoteName('testmode') . " = " . $testmode);

		$db->setQuery($query);
		$rowlist = $db->loadAssocList();
		
		foreach ($rowlist as $record)
		{
			$catid = $record['catid'];
			if (array_key_exists($catid, $categoryRetentions))
			{
				// The record exists, so update the appropriate values
				$categoryRetentions[$catid]->originalRetention = $record['months'];
				$categoryRetentions[$catid]->originalCalculated = $record['isCalculated'];
			}
			else
			{
				// Category does not exist within the system, so ensure it is removed.
				array_push($removeCalculatedRetentions, $catid);
			}
		}
		
		unset($record);
		unset($query);
		unset($db);
	}
        
	public static function CalculateFileRetentions()
	{
            // First get a list of the files which have been stored on the filesystem
            $root = JPATH_SITE;
            $base = $root . '/images/group';
            
            ra_data_retentionHelper::IterateFileRetention($base); 
            
            // Now we need to store the file data into the database
            $db = Factory::getContainer()->get('DatabaseDriver');
            $db->truncateTable('#__ra_retention_files');
            
            foreach (ra_data_retentionHelper::$fileEntries as $filedetails)
            {
                $insert_query = $db->getQuery(true);
                
                $columns = array('filename','filepath','article_hits','weblink_hits', 'module_hits');
                $values = array($db->quote($filedetails->filename), $db->quote($filedetails->filepath), intval($filedetails->article_hits), intval($filedetails->weblink_hits), intval($filedetails->module_hits));
                $insert_query->insert($db->quoteName('#__ra_retention_files'))
                                ->columns($db->quoteName($columns))
                                ->values(implode(',', $values));
                $db->setQuery($insert_query);
                $db->execute();


                unset($insert_query);
            }

            unset($db);
        }
        
        public static function IterateFileRetention($path)
        {
			$db = Factory::getContainer()->get('DatabaseDriver');
            $files = scandir($path);
            foreach ($files as $entry)
            {
                // Ensure it is a valid name.
                if ($entry != "." && $entry != "..")
                {
                    $filename = $path . '/' . $entry;
                    if (is_dir($filename))
                    {
                        // This is a directory, so we need to iterate again.
                        ra_data_retentionHelper::IterateFileRetention($filename);
                    }
                    else
                    {
                    	$exceptions = "gpx,html";
						// Check the type of file is not excluded
						$fileparts = pathinfo($filename);
						if (stristr($exceptions, $fileparts['extension']) === FALSE)
						{
	                        // Add this one to file entries
	                        $class = new \stdClass();
							$class->filename = $entry;
							$class->filepath = substr($path, strlen(JPATH_SITE) + 1, strlen($path) - strlen(JPATH_SITE));
							$class->fullpath = $filename;

							// Now calculate the hits for each file
							$class->article_hits = ra_data_retentionHelper::GetFileArticleHits($db, $class->filepath . '/' . $class->filename);
	                        $class->weblink_hits = ra_data_retentionHelper::GetFileWebLinkHits($db, $class->filepath . '/' . $class->filename);
	                        $class->module_hits = ra_data_retentionHelper::GetFileModuleHits($db, $class->filepath . '/' . $class->filename);
	                        	                        
	
	                        // Add this file to the array
							array_push(ra_data_retentionHelper::$fileEntries, $class) ;
						} // Otherwise this is an exception
                    }
                }
            }
            unset($db);
        }
        
        public static function GetFileArticleHits($db, $file)
        {
        	$select_query = $db->getQuery(true);
        	$select_query->select('COUNT(id)')
        		->from($db->quoteName('#__content'));
        	$select_query->where('introtext LIKE ' . $db->quote('%' . $file . '%'));
        	$db->setQuery($select_query);
        	$hits = $db->loadResult();
        	unset($select_query);
        	
        	return($hits);
        }

        public static function GetFileWebLinkHits($db, $file)
        {
        	$select_query = $db->getQuery(true);
        	$select_query->select('COUNT(id)')
        		->from($db->quoteName('#__weblinks'));
        	$select_query->where('url LIKE ' . $db->quote('%' . $file . '%'));
        	$db->setQuery($select_query);
        	$hits = $db->loadResult();
        	unset($select_query);
        	
        	return($hits);
        }

        public static function GetFileModuleHits($db, $file)
        {
        	$select_query = $db->getQuery(true);
        	$select_query->select('COUNT(id)')
        		->from($db->quoteName('#__modules'));
        	$select_query->where('content LIKE ' . $db->quote('%' . $file . '%'));
        	$db->setQuery($select_query);
        	$hits = $db->loadResult();
        	unset($select_query);
        	
        	return($hits);
        }

        public static function move_article_to_trash($articleid)
        {
            // Get a link to the database
            $db = Factory::getContainer()->get('DatabaseDriver');
            // Get a new Query
            $update_query = $db->getQuery(true);
            // Determine the new values
            $update_query->update($db->quoteName("#__content"))
                    ->set("state = -2")
                    ->where("id = " . $articleid);

            $db->setQuery($update_query);
            $row = $db->execute();
            
            // Release the Select Query
            unset($update_query);
            unset($dbo);
        }
        public static function move_weblink_to_trash($weblinkid)
        {
            // Get a link to the database
            $db = Factory::getContainer()->get('DatabaseDriver');
            // Get a new Query
            $update_query = $db->getQuery(true);
            // Determine the new values
            $update_query->update($db->quoteName("#__weblinks"))
                    ->set("state = -2")
                    ->where("id = " . $weblinkid);

            $db->setQuery($update_query);
            $row = $db->execute();
            
            // Release the Select Query
            unset($update_query);
            unset($dbo);
        }

		public static function startJournal($type)
		{
            // Get a link to the database
            $db = Factory::getContainer()->get('DatabaseDriver');
            // Get a new Query
            $insert_query = $db->getQuery(true);
            // Determine the new values
            $columns = array('type','start');
			$datetime_now = date("Y-m-d H:i:s");
            $values = array($db->quote($type), $db->quote($datetime_now));

			$insert_query->insert($db->quoteName('#__ra_retention_journal'))
                                ->columns($db->quoteName($columns))
                                ->values(implode(',', $values));

            $db->setQuery($insert_query);
            $db->execute();
            
            // Release the Select Query
            unset($insert_query);
            unset($db);
		}
		public static function stopJournal($type)
		{
			$activeJournalID = ra_data_retentionHelper::getActiveJournal($type);
			if ($activeJournalID > 0) // Only close if you get a valid acive journal.
			{
				try
				{
					// Get a link to the database
					$db = Factory::getContainer()->get('DatabaseDriver');

					// Get a new Query
					$update_query = $db->getQuery(true);
					// Determine the new values
					$datetime_now = date("Y-m-d H:i:s");
					$fields = array($db->quoteName('finish') . ' = "' . $datetime_now . '"');					
					$conditions = array($db->quoteName('type') . ' = "' . $type . '"' ,$db->quoteName('id') .  ' = ' . intval($activeJournalID));

					$update_query->update($db->quoteName("#__ra_retention_journal"))
							->set($fields)
							->where($conditions);

					$db->setQuery($update_query);
					$db->execute();
				}
				catch (Exception $ex)
				{
					$x = 1 ;
				}
				finally {
					// Release the Select Query
					unset($update_query);
					unset($db);
				}				
			}
		}

		private static function getActiveJournal($type)
		{
			try {
				$activeJournalID = -1;
				// Get a link to the database
				$db = Factory::getContainer()->get('DatabaseDriver');

				// Get a new Query
				$select_query = $db->getQuery(true);
				// Determine the new values
				$conditions = array($db->quoteName('type') . ' = "' . $type . '"');

				$select_query->select("MAX(id)")
						->from($db->quoteName("#__ra_retention_journal"))
						->where($conditions);

				$db->setQuery($select_query);
				$activeJournalID = $db->loadResult();
			}
			catch(Exception $ex){
				$x=1;
			}
			finally{
				// Release the Select Query
				unset($select_query);
				unset($db);
			}
        	
        	return($activeJournalID);
		}

		public static function logJournal($type, $summary, $data)
		{
			$activeJournalID = ra_data_retentionHelper::getActiveJournal($type);
			if ($activeJournalID > 0) // Only close if you get a valid acive journal.
			{
				try
				{
					// Get a link to the database
					$db = Factory::getContainer()->get('DatabaseDriver');

					// Get a new Query
					$insert_query = $db->getQuery(true);
					// Determine the new values
					$columns = array('journal','type','time','summary','data');
					$datetime_now = date("Y-m-d H:i:s");

					$values = array($activeJournalID, $db->quote($type), $db->quote($datetime_now), $db->quote($summary), $db->quote($data));

					$insert_query->insert($db->quoteName('#__ra_retention_journal_entries'))
										->columns($db->quoteName($columns))
										->values(implode(',', $values));

					$db->setQuery($insert_query);
					$db->execute();
				}
				catch (Exception $ex)
				{
					$x = 1 ;
				}
				finally {
					// Release the Select Query
					unset($insert_query);
					unset($db);
				}				
			}
		}

		public static function truncateJournal($type, $maxlog)
		{
            // Get a link to the database
            $db = Factory::getContainer()->get('DatabaseDriver');
            // Get a new Query
            $delete_journal_query = $db->getQuery(true);
            $delete_entry_query = $db->getQuery(true);

			// Determine the the current date only (No time)
			//$datetime_now = date("Y-m-d H:i:s").format("Y-m-d");
			$datetime_now = new \DateTime("now", new \DateTimeZone("Europe/London"));
			$interval = "P" . $maxlog . "M";
			$basedatetime = $datetime_now->sub(new \DateInterval($interval));
			$basedate = $basedatetime->format("Y-m-d");


			$delete_entry_query->delete($db->quoteName('#__ra_retention_journal_entries'))
                                ->where($db->quoteName("type") . " = :type")
								->where($db->quoteName("time") . " <= :basedate")
								->bind(":type", $type)
								->bind(":basedate", $basedate);

			$delete_journal_query->delete($db->quoteName('#__ra_retention_journal'))
                                ->where($db->quoteName("type") . " = :type")
								->where($db->quoteName("start") . " <= :basedate")
								->bind(":type", $type)
								->bind(":basedate", $basedate);

            $db->setQuery($delete_entry_query);
            $db->execute();

			$db->setQuery($delete_journal_query);
            $db->execute();
            
            // Release the Select Query
            unset($delete_entry_query);
			unset($delete_journal_query);
            unset($db);
		}

	public static function sendReport($type, $minLines, $groups)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		// Get a new Query
		$user_query = $db->getQuery(true);
		$fieldlist = $db->quoteName(['u.id', 'u.name', 'u.email', 'u.sendEmail']);
		$fieldlist[0] = 'DISTINCT ' . $fieldlist[0];
		$user_query->select($fieldlist)
				->from($db->quoteName('#__users', 'u'))
				->join('INNER', $db->quoteName('#__user_usergroup_map', 'm') . 'ON (' . $db->quoteName("u.id") . '=' . $db->quoteName('m.user_id') . ')');

		$parameterGroups = $user_query->bindArray($groups);
		$user_query->where($db->quoteName('m.group_id') . ' IN (' . implode(',', $parameterGroups) . ')');

		$db->setQuery($user_query);
		$db->execute();
		$num_rows = $db->getNumRows();
		// Only continue if you have some users to email to.
		if ($num_rows > 0)
		{
			// Get the list of users to send to
			$users = $db->loadObjectList();

			// Need to send the report for the latest run based on the type. 
			$activeJournalID = ra_data_retentionHelper::getActiveJournal($type);
			if ($activeJournalID > 0) // Only close if you get a valid acive journal.
			{
				// Get the actual report. 
				$report_query = $db->getQuery(true);
				$report_query->select($db->quoteName(['time', 'summary', 'data']))
						->from($db->quoteName('#__ra_retention_journal_entries'))
						->where($db->quoteName('journal') . ' = :journalid')
						->order($db->quoteName('id') . ' ASC')
						->bind(':journalid', $activeJournalID);
						
				$db->setQuery($report_query);
				$db->execute();
				$num_rows = $db->getNumRows();
				if ($num_rows > $minLines) // Check we have more lines than the minimum to send.
				{
					// Get the lines for the report
					$reportInformation = $db->loadObjectList();

					// Now load the basic report information
					$journal_query = $db->getQuery(true);
					$journal_query->select($db->quoteName(['type', 'start', 'finish']))
							->from($db->quoteName('#__ra_retention_journal'))
							->where($db->quoteName('id') . ' = :journalid')
							->bind(':journalid', $activeJournalID);

					$db->setQuery($journal_query);
					$journalInfo = $db->loadRow();

					// Now define all the information ready to send the report
					// report is contained within $reportInformation
					// Journal information is contained within $journalInfo
					// User information is contained within $users
					$logdate = 'Today';
					$logdetail = 'Hello World \n This is the log detail';

					// Iterate each member of the groups 
					foreach ($users as $recipient)
					{
						$mController = new MailerController();
						// Define the parameters
						$params = array(
								'recipient' => 'webmaster@wiltsswindonramblers.org.uk',
								'logtype' => $type,
								'logdate' => $logdate, 
								'logdetail' => $logdetail);

						// Send the email out
						$mController->_sendUsingMailTemplate($params);
						unset($mController);
					}					
				}
			}
		}
		unset($report_query);
		unset($user_query);
		unset($journal_query);
		unset($db);
	}
}

class MailerController implements MailerFactoryAwareInterface
{
    use MailerFactoryAwareTrait;

    public function _sendUsingMailTemplate($validData)
    {
		$mailer = Factory::getMailer();
        $mailTemplate = new MailTemplate('com_ra_data_retention.logemail', null, $mailer);
        $mailTemplate->addTemplateData(
            [
				'logdate' => $validData['logdate'],
				'logtype'   => $validData['logtype'],
				'logdetail'   => $validData['logdetail']
            ]
        );
        $mailTemplate->addRecipient($validData['recipient']);

        try {
            $mailTemplate->send();
            // data has been used ok, so clear the fields in the form
            //$this->app->enqueueMessage("Mail successfully sent", 'info');
        } catch (\Exception $e) {
            $this->app->enqueueMessage("Failed to send mail, " . $e->getMessage(), 'error');
        }
    }
}
