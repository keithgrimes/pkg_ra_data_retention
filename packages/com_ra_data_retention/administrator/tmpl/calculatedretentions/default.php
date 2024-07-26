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
	$saveOrderingUrl = 'index.php?option=com_ra_data_retention&task=calculatedretentions.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
	HTMLHelper::_('draggablelist.draggable');
}

?>

<form action="<?php echo Route::_('index.php?option=com_ra_data_retention&view=calculatedretentions'); ?>" method="post"
	  name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
			<?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

				<div class="clearfix"></div>
				<table class="table table-striped" id="retentionList">
					<thead>
					<th class='left'>
						<?php echo HTMLHelper::_('searchtools.sort',  'COM_RA_DATA_RETENTION_RETENTIONS_CATEGORYPATH', 'a.title', $listDirn, $listOrder); ?>
					</th>
						
					<th scope="col" class="w-3 d-none d-lg-table-cell" >
						<?php echo HTMLHelper::_('searchtools.sort',  'COM_RA_DATA_RETENTION_RETENTIONS_MONTHS', 'r.months', $listDirn, $listOrder); ?>
					</th>
					<th scope="col" class="w-3 d-none d-lg-table-cell" >

						<?php echo HTMLHelper::_('searchtools.sort',  'JGRID_HEADING_ID', 'r.id', $listDirn, $listOrder); ?>
					</th>
					
					</tr>
					</thead>
					<tfoot>
					<tr>
						<td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
							<?php echo $this->pagination->getListFooter(); ?>
						</td>
					</tr>
					</tfoot>
					<tbody <?php if (!empty($saveOrder)) :?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" <?php endif; ?>>
					<?php foreach ($this->items as $i => $item) :
						$ordering   = ($listOrder == 'a.ordering');
						$canCreate  = $user->authorise('core.create', 'com_ra_data_retention');
						$canEdit    = $user->authorise('core.edit', 'com_ra_data_retention');
						$canCheckin = $user->authorise('core.manage', 'com_ra_data_retention');
						$canChange  = $user->authorise('core.edit.state', 'com_ra_data_retention');
						?>
						<tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>							
							<td>
								<?php echo $this->escape($item->category_path); ?>
							</td>
							<td align="center">
								<?php echo $item->months; ?>
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