<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Test
 * @author     Keith Grimes <yellow.submarine@ramblers-webs.org.uk>
 * @copyright  2024 Keith Grimes
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblerswebs\Component\Ra_data_retention\Administrator\Model;
// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Table\Table;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Event\Model;
use \Joomla\CMS\Component\ComponentHelper;
use \Ramblerswebs\Component\Ra_data_retention\Administrator\Helper\Ra_data_retentionHelper;

/**
 * Test model.
 *
 * @since  1.0.0
 */
class ArticleretentionModel extends AdminModel
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 *
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_RA_DATA_RETENTION';

	/**
	 * @var    string  Alias to manage history control
	 *
	 * @since  1.0.0
	 */
	public $typeAlias = 'com_ra_data_retention.retention.articleretention';

	/**
	 * @var    null  Item data
	 *
	 * @since  1.0.0
	 */
	protected $item = null;

	
	

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param   string  $type    The table type to instantiate
	 * @param   string  $prefix  A prefix for the table class name. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  Table    A database object
	 *
	 * @since   1.0.0
	 */
	public function getTable($type = 'Articleretention', $prefix = 'Administrator', $config = array())
	{
		return parent::getTable($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  \JForm|boolean  A \JForm object on success, false on failure
	 *
	 * @since   1.0.0
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Initialise variables.
		$app = Factory::getApplication();

		// Get the form.
		$form = $this->loadForm(
								'com_ra_data_retention.articleretention', 
								'articleretention',
								array(
									'control' => 'jform',
									'load_data' => $loadData 
								)
							);

		

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.0.0
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState('com_ra_data_retention.edit.articleretention.data', array());

		if (empty($data))
		{
			if ($this->item === null)
			{
				$this->item = $this->getItem();
			}

			$data = $this->item;
			
		}

		return $data;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed    Object on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function getItem($pk = null)
	{
		
			if ($item = parent::getItem($pk))
			{
				if (isset($item->params))
				{
					$item->params = json_encode($item->params);
				}
				
				// Do any procesing on fields here if needed
			}

			return $item;
		
	}

	/**
	 * Method to duplicate an Test
	 *
	 * @param   array  &$pks  An array of primary key IDs.
	 *
	 * @return  boolean  True if successful.
	 *
	 * @throws  Exception
	 */
	public function duplicate(&$pks)
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();
        $dispatcher = $this->getDispatcher();

		// Access checks.
		if (!$user->authorise('core.create', 'com_ra_data_retention'))
		{
			throw new \Exception(Text::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
		}

		$context    = $this->option . '.' . $this->name;

		// Include the plugins for the save events.
		PluginHelper::importPlugin($this->events_map['save']);

		$table = $this->getTable();

		foreach ($pks as $pk)
		{
			
				if ($table->load($pk, true))
				{
					// Reset the id to create a new record.
					$table->id = 0;

					if (!$table->check())
					{
						throw new \Exception($table->getError());
					}
					

					// Trigger the before save event.
					$beforeSaveEvent = new Model\BeforeSaveEvent($this->event_before_save, [
						'context' => $context,
						'subject' => $table,
						'isNew'   => true,
						'data'    => $table,
					]);
					
						// Trigger the before save event.
						$result = $dispatcher->dispatch($this->event_before_save, $beforeSaveEvent)->getArgument('result', []);
					
					
					if (in_array(false, $result, true) || !$table->store())
					{
						throw new \Exception($table->getError());
					}

					// Trigger the after save event.
					$dispatcher->dispatch($this->event_after_save, new Model\AfterSaveEvent($this->event_after_save, [
						'context' => $context,
						'subject' => $table,
						'isNew'   => true,
						'data'    => $table,
					]));				
				}
				else
				{
					throw new \Exception($table->getError());
				}
			
		}

		// Clean cache
		$this->cleanCache();

		return true;
	}

	/**
	 * Prepare and sanitise the table prior to saving.
	 *
	 * @param   Table  $table  Table Object
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function prepareTable($table)
	{
		jimport('joomla.filter.output');

		if (empty($table->id))
		{
			// Set ordering to the last item if not set
			if (@$table->ordering === '')
			{
				$db = $this->getDbo();
				$db->setQuery('SELECT MAX(ordering) FROM #__ra_retention_categories');
				$max             = $db->loadResult();
				$table->ordering = $max + 1;
			}
		}
	}

	public function validate($form, $data, $group = null)
	{
		// Determine whether we are running in test mode or not.
		$params = ComponentHelper::getParams('com_ra_data_retention');
		$testmode = $params->get('testmode', 0);

		if ($data["months"] <= 0)
		{
			$this->setError(JText::_("COM_RA_DATA_RETENTION_MONTHS_INVALID"));
			return false;
		}
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		
		$query->select('catid')
				->from($db->quoteName('#__ra_retention_categories','r'));
		$query->where("catid = " . $data["catid"]);
		$query->where("testmode = " . $testmode);
		$db->setQuery($query);
		$catid = $db->loadResult();
		if ($catid != null && $data["id"] <= 0)
		{
			$this->setError(Text::_("COM_RA_DATA_RETENTION_ALREADY_EXISTS"));
			return false;
		}
		return parent::validate($form, $data);		
	}
	
	public function save($data)
	{
		// Determine whether we are running in test mode or not.
		$params = ComponentHelper::getParams('com_ra_data_retention');
		$testmode = $params->get('testmode', 0);
		$maxretention = $params->get('maxretention', 0);

		// Call the parent function to save the data.
		$data['type'] = "ARTICLE";
		$data['testmode'] = $testmode;
		$status =  parent::save($data);
		// Now update the sub tables.
		Ra_data_retentionHelper::CalculateFullRetentions("ARTICLE", $maxretention ,$testmode);
		return $status;
	}
}
