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

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Component\ComponentHelper;
use Ramblerswebs\Component\Ra_data_retention\Administrator\Helper\Ra_data_retentionHelper;

/**
 * Methods supporting a list of Tests records.
 *
 * @since  1.0.0
 */
class PhotoretentionsModel extends ListModel
{
	/**
	* Constructor.
	*
	* @param   array  $config  An optional associative array of configuration settings.
	*
	* @see        JController
	* @since      1.6
	*/
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'r.id',
				'state', 'r.state',
				'months', 'r.months',
				'ordering', 'r.ordering',
				'created_by', 'r.created_by',
				'modified_by', 'r.modified_by',
				'category', 'r.catid',
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   Elements order
	 * @param   string  $direction  Order direction
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// List state information.
		parent::populateState('id', 'ASC');

		$context = $this->getUserStateFromRequest($this->context.'.filter.search', 'filter_search');
		$this->setState('filter.search', $context);

		// Split context into component and optional section
		if (!empty($context))
		{
			$parts = FieldsHelper::extract($context);

			if ($parts)
			{
				$this->setState('filter.component', $parts[0]);
				$this->setState('filter.section', $parts[1]);
			}
		}
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return  string A store id.
	 *
	 * @since   1.0.0
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.state');

		
		return parent::getStoreId($id);
		
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  DatabaseQuery
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		if (JDEBUG) { JLog::add("[models][articleretentions] call to getListQuery", JLog::DEBUG, "com_ra_data_retention"); }
	
		$query->select($this->getState('list.select','DISTINCT r.*'))
			->from($db->quoteName('#__ra_retention_categories', 'r'));

		// Join over the categories.
		$query->select($db->quoteName('c.path', 'category_path'))
			->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = r.catid');
				
		$orderCol  = $this->state->get('list.ordering', 'id');
		$listDirn = $this->getState('list.direction', 'ASC');
	
		$query->order($db->escape($orderCol).' '.$db->escape($listDirn));
		
		// Filter by published state
		$published = $this->getState('filter.state');

		if (is_numeric($published))
		{
			$query->where('r.state = ' . (int) $published);
		}
		elseif (empty($published))
		{
			$query->where('(r.state IN (0, 1))');
		}
		// Determine whether we are running in test mode or not.
		$params = ComponentHelper::getParams('com_ra_data_retention');
		$testmode = $params->get('testmode', 0);
		$query->where('testmode = ' . $testmode);
		$query->where('type = "PHOTO"');
		

		// Filter by search in title
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('r.id = ' . (int) substr($search, 3));
			}
			else
			{
				$search = $db->Quote('%' . $db->escape($search, true) . '%');
				$query->where('c.path LIKE ' . $search);
			}
		}

		if ($orderCol && $orderDirn)
		{
			$query->order($db->escape($orderCol . ' ' . $orderDirn));
		}
		return $query;
	}

	/**
	 * Get an array of data items
	 *
	 * @return mixed Array of data items on success, false on failure.
	 */
	public function getItems()
	{
		$items = parent::getItems();
		

		return $items;
	}
}
