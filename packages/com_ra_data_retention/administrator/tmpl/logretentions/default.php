<?php
/**
 * @version    CVS: 1.0.0
 * @package    COM_RA_DATA_RETENTION
 * @author     Keith Grimes <yellow.submarine@ramblers-webs.org.uk>
 * @copyright  2024 Keith Grimes
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;


use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// Import CSS
$wa =  $this->document->getWebAssetManager();
$wa->useStyle('com_ra_data_retention.admin')
    ->useScript('com_ra_data_retention.admin');

$user      = Factory::getApplication()->getIdentity();
$userId    = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn  = $this->state->get('list.direction');
$canOrder  = $user->authorise('core.edit.state', 'com_ra_data_retention');

$saveOrder = $listOrder == 'r.ordering';

if (!empty($saveOrder))
{
	$saveOrderingUrl = 'index.php?option=com_ra_data_retention&task=logretentions.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
	HTMLHelper::_('draggablelist.draggable');
}

?>

<form action="<?php echo Route::_('index.php?option=com_ra_data_retention&view=logretentions'); ?>" method="post"
	  name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<!-- First Render the searchtools before we display the table data -->
				<?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

				<!-- Now display the table of information -->
				<div class="clearfix"></div>
				<table class="table table-striped" id="retentionList">
					<thead>
					<tr><!-- Header row for filtering -->
						<!-- Now display the time  -->
						<th  scope="col" class="w-1 text-center">
							<?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_DATA_RETENTION_HEADING_LOGTIME', 'time', $listDirn, $listOrder); ?>
						</th>
							
						<!-- Category Path is next up -->
						<th class='left'>
							<?php echo HTMLHelper::_('searchtools.sort',  'COM_RA_DATA_RETENTION_HEADING_LOGSUMMARY', 'summary', $listDirn, $listOrder); ?>
						</th>
							

						<!-- Finally the id of the entry in the table -->
						<th scope="col" class="w-3 d-none d-lg-table-cell" >

							<?php echo HTMLHelper::_('searchtools.sort',  'JGRID_HEADING_ID', 'id', $listDirn, $listOrder); ?>
						</th>					
					</tr>
					</thead>
					<tfoot>
					<tr>
						<!-- Add the footer which displays the number of entries -->
						<td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
							<?php echo $this->pagination->getListFooter(); ?>
						</td>
					</tr>
					</tfoot>
					<!-- Now we are on the main body of the table -->
					<tbody <?php if (!empty($saveOrder)) :?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" <?php endif; ?>>
					<!-- Iterate each item to display, and display it according to the header -->
					<?php foreach ($this->items as $i => $item) :
						$ordering   = ($listOrder == 'a.ordering');
						$canCreate  = $user->authorise('core.create', 'com_ra_data_retention');
						$canEdit    = $user->authorise('core.edit', 'com_ra_data_retention');
						$canCheckin = $user->authorise('core.manage', 'com_ra_data_retention');
						$canChange  = $user->authorise('core.edit.state', 'com_ra_data_retention');
						?>
						<tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>
							<td class="text-center">
								<?php echo $this->escape(substr($item->time, -8)); ?>
							</td>
							
							<td>
								<a href="<?php echo Route::_('index.php?option=com_ra_data_retention&task=logretention.edit&id='.(int) $item->id); ?>">
								<?php echo $this->escape($item->summary); ?>
								</a>
							</td>
							
							<td class="d-none d-lg-table-cell">
								<?php echo $item->id; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<input type="hidden" name="task" value=""/>
				<input type="hidden" name="boxchecked" value="0"/>
				<input type="hidden" name="list[fullorder]" value="<?php echo $listOrder; ?> <?php echo $listDirn; ?>"/>
				<?php echo HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>