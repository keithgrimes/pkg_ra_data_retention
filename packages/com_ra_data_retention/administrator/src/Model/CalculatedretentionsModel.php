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
class CalculatedretentionsModel extends ListModel
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
				'id',
				'catid',
				'months',
				'isCalculated', 
				'type'
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
        if (JDEBUG) { JLog::add("[models][calculatedretentions] call to getListQuery", JLog::DEBUG, "com_ra_data_retention"); }

		// Determine whether we are running in test mode or not.
		$params = ComponentHelper::getParams('com_ra_data_retention');
		$testmode = $params->get('testmode', 0);
		$maxretention = $params->get('maxretention', 0);

		ra_data_retentionHelper::CalculateFullRetentions("ARTICLE", $maxretention, $testmode);
		ra_data_retentionHelper::CalculateFullRetentions("PHOTO", $maxretention, $testmode);
		ra_data_retentionHelper::CalculateFullRetentions("WEBLINK", $maxretention, $testmode);

		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		$query->select('r.id as id, r.catid as catid, r.months as months, r.isCalculated as isCalculated, r.type as type')
			  ->from($db->quoteName('#__ra_calc_retention_categories', 'r'));

		$search = $this->getState('filter.search');
		$type = $this->getState('filter.type');
		if (empty($type)) { $type="ARTICLE"; }
		$where = 'type = ' . $db->quote($type);

		// Join over the categories.
		$query->select($db->quoteName('c.path', 'category_path'))
			->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = r.catid');
		if (!empty($search))
		{
			$where = $where . ' AND c.path LIKE ' . $db->quote('%' . $search . '%');
		}

		$calculated = $this->getState('filter.isCalculated');
		if (!empty($calculated))
		{
			if($calculated == '2')
			{
				$where = $where . ' AND isCalculated = true '; 
			}
			elseif ($calculated == '1')
			{
				$where = $where . ' AND isCalculated = false '; 				
			}
		} 
		// Determine whether we are running in test mode or not.
		$params = ComponentHelper::getParams('com_ra_data_retention');
		$testmode = $params->get('testmode', 0);
		$where = $where . 'AND testmode = ' . $testmode;
		
		$query->where($where);
		
		$orderCol  = $this->state->get('list.ordering', 'catid');
		$listDirn = $this->getState('list.direction', 'asc');

		$query->order($db->escape($orderCol).' '.$db->escape($listDirn));
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
