<?php

/**
 * 14/08/26 CB created
 * 09/09/26 CB completeion report added
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
    public function completion(){
        ToolBarHelper::title('Completion Report');
        $sql =  'SELECT key_value FROM #__ra_control WHERE record_type=3 ';
        $date = $this->toolsHelper->getValue($sql);
        if (is_null($date)) {
           echo 'Wizard has not yet been completed<br>';
        } else {
            echo 'Wizard completed on ' . $date . '<br>';
        }
        echo $this->toolsHelper->backButton($this->back);           
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
 
    public function showLog(){
        ToolBarHelper::title('Logfile records');

        $sql = "SELECT date_format(log_date, '%a %e-%m-%y') as Date, ";
        $sql .= "date_format(log_date, '%H:%i:%s.%u') as Time, ";
        $sql .= "record_type, ";
        $sql .= "ref, ";
        $sql .= "message ";
        $sql .= "FROM #__ra_logfile ";
        $sql .= "WHERE sub_system ='RA Setup' ";
        $sql .= "ORDER BY log_date DESC, record_type ";
        if ($this->toolsHelper->showSql($sql)) {
            echo "<h5>End of logfile records</h5>";
        } else {
            echo 'Error: ' . $this->toolsHelper->error . '<br>';
        }
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

    public function test() {   
        $this->app->enqueueMessage('Test message', 'success');
        $this->setRedirect(Route::_('index.php?option=com_ra_setup&view=reports', false));
    }
}