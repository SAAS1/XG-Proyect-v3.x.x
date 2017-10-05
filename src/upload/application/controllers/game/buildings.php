<?php
/**
 * Buildings Controller
 *
 * PHP Version 5.5+
 *
 * @category Controller
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.1.0
 */

namespace application\controllers\game;

use application\core\Controller;
use application\libraries\buildings\Building;
use application\libraries\DevelopmentsLib;
use application\libraries\FormatLib;
use application\libraries\FunctionsLib;
use Exception;

/**
 * Buildings Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.1.0
 */
class Buildings extends Controller
{
    const MODULE_ID = 3;
    
    /**
     *
     * @var \Buildings
     */
    private $_building = null;
    
    /**
     * List of currently available buildings
     * 
     * @var array
     */
    private $_allowed_buildings = [];
    
    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // load Model
        parent::loadModel('game/buildings');
        
        // Check module access
        FunctionsLib::moduleMessage(FunctionsLib::isModuleAccesible(self::MODULE_ID));

        // init a new building object with the current building queue
        $this->setUpBuildings();
        
        // time to do something
        $this->runAction();
        
        // build the page
        $this->buildPage();
    }
    
    /**
     * Creates a new building object that will handle all the building
     * creation methods and actions
     * 
     * @return void
     */
    private function setUpBuildings()
    {
        $this->_building = new Building(
            $this->getPlanetData(),
            $this->getUserData(),
            $this->getObjects()
        );
        
        $this->_allowed_buildings = $this->getAllowedBuildings();
    }
    
    /**
     * Run an action
     * 
     * @return void
     */
    private function runAction()
    {
        try {
            $action             = filter_input(INPUT_GET, 'cmd');
            $reload             = filter_input(INPUT_GET, 'r');
            $building           = filter_input(INPUT_GET, 'building', FILTER_VALIDATE_INT);
            $list_id            = filter_input(INPUT_GET, 'listid', FILTER_VALIDATE_INT);
            $allowed_actions    = ['cancel', 'destroy', 'insert', 'remove'];
            $new_queue          = '';
            
            if (!is_null($action)) {
 
                if (in_array($action, $allowed_actions)) {

                    if ($this->canInitBuildAction($building, $list_id)) {

                        switch ($action) {
                            case 'cancel':
                                $this->_building->cancelBuilding();
                                break;
                            
                            case 'destroy':
                                $this->_building->tearDownBuilding($building);
                                break;

                            case 'insert':
                                $this->_building->addBuilding($building);
                                break;
                            
                            case 'remove':
                                $this->_building->removeBuilding($list_id);
                                break;
                        }
                        
                        $this->Buildings_Model->updatePlanetBuildingQueue(
                            $building,
                            $this->_building->getNewQueue(),
                            $this->getPlanetData()['planet_id']
                        );
                    }
                    
                    if ($reload == 'overview') {

                        header('location:game.php?page=overview');
                    } else {

                        header('location:game.php?page=' . $this->getCurrentPage());
                    }
                }

                throw new Exception('"cancel", "destroy", "insert", and "remove" are the valid options');
            }

        } catch (Exception $e) {
            
            die('Caught exception: ' . $e->getMessage() . "\n");
        }
    }
    
    /**
     * Build the page
     * 
     * @return void
     */
    private function buildPage()
    {        
        /**
         * Parse the items
         */
        $page                   = [];
        $page['BuildingsList']  = $this->buildListOfBuildings();
        
        // display the page
        parent::$page->display(
            parent::$page->get('buildings/buildings_builds')->parse($page)
        );
    }
    
    /**
     * Build the list of buildings
     * 
     * @return string
     */
    private function buildListOfBuildings()
    {
        $buildings_list = '';
        
        if (!is_null($this->_allowed_buildings)) {
            
            foreach ($this->_allowed_buildings as $building_id) {
                
                $buildings_list .= parent::$page->get('buildings/buildings_builds_row')->parse(
                    $this->setListOfBuildingsItem($building_id)
                );
            }
        }
        
        return $buildings_list;
    }
    
    /**
     * Build each building block
     * 
     * @param int $building_id Building ID
     * 
     * @return array
     */
    private function setListOfBuildingsItem($building_id)
    {
        $item_to_parse  = [];
        
        $item_to_parse['dpath']         = DPATH;
        $item_to_parse['i']             = $building_id;
        $item_to_parse['nivel']         = $this->getBuildingLevelWithFormat($building_id);
        $item_to_parse['n']             = $this->getLang()['tech'][$building_id];
        $item_to_parse['descriptions']  = $this->getLang()['res']['descriptions'][$building_id];
        $item_to_parse['price']         = $this->getBuildingPriceWithFormat($building_id);  
        $item_to_parse['time']          = $this->getBuildingTimeWithFormat($building_id);
        $item_to_parse['click']         = $this->getActionButton($building_id);  

        return $item_to_parse;
    }
    
    /**
     * Expects a building ID to format the level
     * 
     * @param int $building_id Building ID
     * 
     * @return string
     */
    private function getBuildingLevelWithFormat($building_id)
    {        
        return DevelopmentsLib::setLevelFormat(
            $this->getBuildingLevel($building_id)
        );
    }
    
    /**
     * Expects a building ID to calculate and format the price
     * 
     * @param int $building_id Building ID
     * 
     * @return string
     */
    private function getBuildingPriceWithFormat($building_id)
    {
        return DevelopmentsLib::formatedDevelopmentPrice(
            $this->getUserData(),
            $this->getPlanetData(),
            $building_id,
            true,
            $this->getBuildingLevel($building_id)
        );
    }  
    
    /**
     * Expects a building ID to format the level
     * 
     * @param int $building_id Building ID
     * 
     * @return string
     */
    private function getBuildingTimeWithFormat($building_id)
    {
        return DevelopmentsLib::formatedDevelopmentTime(
            $this->getBuildingTime($building_id)
        );
    }   
    
    /**
     * Expects a building ID, runs several validations and then returns a button,
     * based on the validations
     * 
     * @param int $building_id Building ID
     * 
     * @return string
     */
    private function getActionButton($building_id)
    {
        $build_url  = 'game.php?page=' . $this->getCurrentPage() . '&cmd=insert&building=' . $building_id;
        
        // validations
        $is_development_payable = DevelopmentsLib::isDevelopmentPayable($this->getUserData(), $this->getPlanetData(), $building_id, true, false);
        $is_on_vacations        = parent::$users->isOnVacations($this->getUserData());
        $have_fields            = DevelopmentsLib::areFieldsAvailable($this->getPlanetData());
        $is_queue_full          = $this->_building->isQueueFull();
        $queue_element          = $this->_building->getCountElementsOnQueue();

        // check fields
        if (!$have_fields) {

            return $this->buildButton('all_occupied');
        }
            
        // check queue, payable and vacations
        if ($is_queue_full or !$is_development_payable or $is_on_vacations) {

            return $this->buildButton('not_allowed'); 
        }
        
        // check if there's any work in progress
        if ($this->isWorkInProgress($building_id)) {
            
            return $this->buildButton('work_in_progress'); 
        }
        
        // if a queue was already set
        if ($queue_element > 0) {

            return FunctionsLib::setUrl($build_url, '', $this->buildButton('allowed_for_queue'));
        }
        
        // any other case
        return FunctionsLib::setUrl($build_url, '', $this->buildButton('allowed'));
    }
    
    /**
     * 
     * @param int $building_id  Building ID
     * @param int $list_id      List ID
     * 
     * @return boolean
     */
    private function canInitBuildAction($building_id, $list_id)
    {
        if (!in_array($building_id, $this->_allowed_buildings)) {
            
            return false;
        }

        if ($this->isWorkInProgress($building_id)) {

            return false;
        }

        return true;
    }
    
    /**
     * Expects a building ID to calculate the building level
     * 
     * @param int $building_id Building ID
     * 
     * @return int
     */
    private function getBuildingLevel($building_id)
    {        
        return $this->getPlanetData()[$this->getObjects()->getObjects()[$building_id]];
    }
    
    /**
     * Expects a building ID to calculate the building time
     * 
     * @param int $building_id Building ID
     * 
     * @return int
     */
    private function getBuildingTime($building_id)
    {
        return DevelopmentsLib::developmentTime(
            $this->getUserData(),
            $this->getPlanetData(),
            $building_id,
            $this->getBuildingLevel($building_id)
        );
    }
    
    /**
     * Get the properties for each button type
     * 
     * @param string $button_code Button code
     * 
     * @return string
     */
    private function buildButton($button_code)
    {
        $listOfButtons  = [
            'all_occupied'      => ['color' => 'red', 'lang' => 'bd_no_more_fields'],
            'allowed'           => ['color' => 'green', 'lang' => 'bd_build'],
            'not_allowed'       => ['color' => 'red', 'lang' => 'bd_build'],
            'allowed_for_queue' => ['color' => 'green', 'lang' => 'bd_add_to_list'],
            'work_in_progress'  => ['color' => 'red', 'lang' => 'bd_working']
        ];
        
        $color      = ucfirst($listOfButtons[$button_code]['color']);
        $text       = $this->getLang()[$listOfButtons[$button_code]['lang']];
        $methodName = 'color' . $color;
        
        return FormatLib::$methodName($text);
    }
    
    /**
     * Determine if there's any work in progress
     * 
     * @param int $building_id Building ID
     * 
     * @return boolean
     */
    private function isWorkInProgress($building_id)
    {
        $working_buildings  = [14, 15, 21];
        
        if ($building_id == 31 && DevelopmentsLib::isLabWorking($this->getUserData())) {

            return true;
        }
        
        if (in_array($building_id, $working_buildings) && DevelopmentsLib::isShipyardWorking($this->getPlanetData())) {

            return true;
        }
        
        return false;
    }
    
    /**
     * Determine the current page and validate it
     * 
     * @return array
     * 
     * @throws Exception
     */
    private function getCurrentPage()
    {
        try {
            $get_value      = filter_input(INPUT_GET, 'page');
            $allowed_pages  = ['resources', 'station'];

            if (in_array($get_value, $allowed_pages)) {

                return $get_value;
            }
            
            throw new Exception('"resources" and "station" are the valid options');

        } catch (Exception $e) {
            
            die('Caught exception: ' . $e->getMessage() . "\n");
        }
    }
    
    /**
     * Get an array with an allowed set of items for the current page,
     * filtering by page and available technologies
     * 
     * @return array
     */
    private function getAllowedBuildings()
    {
        $allowed_buildings = [
            'resources' => [
                1 => [1, 2, 3, 4, 12, 22, 23, 24],
                3 => [12, 22, 23, 24]
            ],
            'station'   => [
                1 => [14, 15, 21, 31, 33, 34, 44],
                3 => [14, 21, 41, 42, 43]
            ]
        ];

        return array_filter($allowed_buildings[$this->getCurrentPage()][$this->getPlanetData()['planet_type']], function($value) {
            return DevelopmentsLib::isDevelopmentAllowed(
                $this->getUserData(),
                $this->getPlanetData(),
                $value
            );
        });
    }
}

/* end of buildings.php */
