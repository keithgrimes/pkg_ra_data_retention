<?php
/**
 * @version    CVS: 1.0.0
 * @package    COM_RA_DATA_RETENTION
 * @author     Keith Grimes <yellow.submarine@ramblers-webs.org.uk>
 * @copyright  2024 Keith Grimes
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblerswebs\Component\Ra_data_retention\Administrator\View\Logretentions;
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Ramblerswebs\Component\Ra_data_retention\Administrator\Helper\Ra_data_retentionHelper;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\HTML\Helpers\Sidebar;
/**
 * View class for a list of Tests.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	protected $state;
	protected $items;
	protected $pagination;

	/**
	 * Display the view
	 *
	 * @param   string  $tpl  Template name
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function display($tpl = null)
	{
		$this->state = $this->get('State');
		$this->items = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->filterForm = $this->get('FilterForm');
		$this->activeFilters = $this->get('ActiveFilters');

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}
		// Get a reference to the current form
		$this->form = $this->getModel()->getForm();
		$this->form->bind(array(
			'logrun' => $this->getState('jform.logrun', '0'),
			'filter.search' => $this->getState('filter.search', ''), 
			'filter.limit' => $this->getState('filter.limit', '25')));

		// Add the toolbars for the page
		$this->addToolbar();
		// render the sidebar
		$this->sidebar = Sidebar::render();
		// Display based on the template
		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function addToolbar()
	{
		$state = $this->get('State');
		$canDo = Ra_data_retentionHelper::getActions();

		// Set the title at the top of the page
		ToolbarHelper::title(Text::_('COM_RA_DATA_RETENTION_TITLE_LOGRETENTIONS'), "generic");

		// Get a link to the toolbar
		$toolbar = Toolbar::getInstance('toolbar');

		// Display the options button if the user is an administrator.
		if ($canDo->get('core.admin'))
		{
			$toolbar->preferences('com_ra_data_retention');
		}

		// Set sidebar action
		Sidebar::setAction('index.php?option=com_ra_data_retention&view=logretentions');
	}
	
	/**
	 * Check if state is set
	 *
	 * @param   mixed  $state  State
	 *
	 * @return bool
	 */
	public function getState($state)
	{
		// Not sure if this is actually used.
		return isset($this->state->{$state}) ? $this->state->{$state} : false;
	}
}
