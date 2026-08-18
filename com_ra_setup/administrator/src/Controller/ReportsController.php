<?php

/**
 * 14/08/26 CB created
 */

namespace Ramblers\Component\Ra_setup\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHtml;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

class ReportsController extends FormController {


    protected $back;
    protected $db;
    protected $app;
    protected $toolsHelper;
    protected $prefix;
    protected $query;


    public function __construct() {
        parent::__construct();
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $this->back = 'administrator/index.php?option=com_ra_setup&view=reports';
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function showEntries(){
        $type = $this->app->input->getWord('type', 'group');
        ToolBarHelper::title('Type = ' . $type);
        $toolsTable = new ToolsTable();
        $toolsTable->add_header('Title,Name,Params,Published');
        $sql =  'SELECT title, alias, params, published FROM #__menu ';
        $sql .= 'WHERE note=' . $this->db->q($type);
        $sql .= ' ORDER BY title, alias';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $toolsTable->add_item($row->title);
            $toolsTable->add_item($row->alias);
            $toolsTable->add_item($row->params);
            $toolsTable->add_item($row->published);
            $toolsTable->generate_line();
        }
        $toolsTable->generate_table();
        echo $this->toolsHelper->backButton($this->back);       
    }  
    
    public function showOther(){
        ToolBarHelper::title('Type = Other') ;
        $toolsTable = new ToolsTable();
        $toolsTable->add_header('Title,Name,Params,Status');
    $sql =  'SELECT title, alias, note, params, published FROM #__menu ';
    $sql .= 'WHERE link like "%view=programme%" AND note="" ';
    $sql .= 'ORDER BY title, alias, note ';
//echo "$sql<br>";
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $toolsTable->add_item($row->title);
            $toolsTable->add_item($row->alias);
            $toolsTable->add_item($row->params);
            $toolsTable->add_item($row->published);
            $toolsTable->generate_line();
        }
        $toolsTable->generate_table();
        echo $this->toolsHelper->backButton($this->back);       
    }

}