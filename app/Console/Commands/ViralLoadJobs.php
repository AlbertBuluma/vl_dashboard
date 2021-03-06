<?php namespace EID\Console\Commands;

use Illuminate\Console\Command;
use EID\Dashboard;
use EID\Mongo;
use EID\LiveData;

/**
* There was need to add more fields(especially those of DHIS2) to Mongo. So they have been added. 
* This script drops the existing mongo facilities collection, and creates a new one with DHIS2 fields
*/

class ViralLoadJobs extends Command{

	/**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:run {--L|locations} {--pvls} 
    {--from_date= :in the format yyyy-MM-DD} {--to_date= :in the format yyyy-MM-DD } 
    {--ih} {--regimens} {--IPs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs jobs';

    /**
     * Execute the console command.
     *
     * @return mixed
     */


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->mongo=Mongo::connect();
        $this->db = \DB::connection('direct_db');
    }

    public function handle() {

        $locations_flag = $this->option('locations');
        if($locations_flag == 'locations'){
            $this->_loadFacilitlyLocations();
        }

        $pvls_flag = $this->option('pvls');
        $from_date_value = $this->option('from_date');
        $to_date_value = $this->option('to_date');

        if($pvls_flag == 'pvls'){
            $this->comment("Engine has started at :: ".date('YmdHis'));
            $this->_generatePvlsReport($from_date_value,$to_date_value);
            $this->comment("Engine has ended at :: ".date('YmdHis'));
        }

        $intra_health_flag = $this->option('ih');
        if($intra_health_flag == 'ih'){
            $this->_intraHealth();
        }

        $ips_flag = $this->option('IPs');
        if($ips_flag == 'IPs'){
            $this->_loadIPs();
        }

        $regimens_flag = $this->option('regimens');
        if($regimens_flag == 'regimens'){
            $this->_loadRegimens();
        }
            
    }


    private function _intraHealth(){

        $file = fopen("~/data/vl/VLMerge.csv", "r");
        $data = array();    
        while ( !feof($file)){

            $array_instance = fgetcsv($file);
            //print_r($array_instance);
                $district['id']=$array_instance[0];
                $district['district_name']=$array_instance[1];
                $district['hub_name']=$array_instance[2];
                $district['dhis2_name']=$array_instance[3];
                $district['dhis2_uid']=$array_instance[4];
                
                array_push($data, $district); 
            
        }

        $sql = "select id,facility,district_id,hub_id,dhis2_name,dhis2_uid from backend_facilities";
      return $res=\DB::connection('direct_db')->select($sql);

    }
    private function _loadFacilitlyLocations(){
        echo "\n ....started loading locations .... \n";
        $this->_loadDistricts();
        $this->_loadHubs();
        
        $this->_loadFacilities();
        echo "\n ..... finished loading locations in Mongo ....\n";
    }
    private function _loadDistricts(){
        $this->mongo->districts->drop();
        $res=LiveData::getDistricts();
        foreach($res AS $row){
            $data=['id'=>$row->id,'name'=>$row->district];
            $this->mongo->districts->insert($data);
        }
    }
    private function _loadHubs(){
        $this->mongo->hubs->drop();
        $res=LiveData::getHubs();
        foreach($res AS $row){
            $data=['id'=>$row->id,'name'=>$row->hub];
            $this->mongo->hubs->insert($data);
        }
    }
    private function _loadFacilities(){
        $this->mongo->facilities->drop();
        $res=LiveData::getFacilities();
        foreach($res AS $row){
            
            $data=['id'=>$row->id,'name'=>$row->facility,'dhis2_name'=>$row->dhis2_name,'hub_id'=>$row->hub_id,
            'district_id'=>$row->district_id, 'dhis2_uid'=>$row->dhis2_uid];
            $this->mongo->facilities->insert($data);
        }
    }

    private function _loadRegimens(){
        $this->mongo->regimens->drop();
        $res = LiveData::getRegimens();
        foreach($res AS $row){
            $data=['id'=>$row->id,'name'=>$row->appendix];
            $this->mongo->regimens->insert($data);
        }
    }

    private function _loadIPs(){
        $this->mongo->ips->drop();
        $res=LiveData::getIPs();
        foreach($res AS $row){
            $data=['id'=>$row->id,'name'=>$row->ip];
            $this->mongo->ips->insert($data);
        }
    }

    private function getNumbers($array_instance,$uid){
        $value = 0;

        $dummy_output = isset($array_instance[$uid])? $array_instance[$uid] : 0;
        
        if($dummy_output == 0){
           $value = 0; 
        }else{
            $value = $dummy_output['individuals'];
           
        }

        return $value;
    }
    private function _generatePvlsReport($from_date_parameter,$to_date_parameter){
        $this->comment('generating PVLS report');

        //generate CPHL names.
        $pepfar_pvls_locations = LiveData::getPvlsPepfarLocations();
        //indication

        // - Individuals who did a vL
        $this->comment('vl results...');
        $individualsWithVLresult = LiveData::getIndividualsWithVLresult($from_date_parameter,$to_date_parameter);
        
        // - Individuals with VL suppression
            $this->comment('vl suppression...');
            $individualsWithVLsuppression = LiveData::getIndividualsWithVLsuppression($from_date_parameter,$to_date_parameter);

            $routineIndication = LiveData::getRoutineIndication($from_date_parameter,$to_date_parameter);
            $targetedIndication = LiveData::getTargetedIndication($from_date_parameter,$to_date_parameter);

            $pregantRoutine = LiveData::getPregnantRoutine($from_date_parameter,$to_date_parameter);
            $pregantTargeted = LiveData::getPregnantTargeted($from_date_parameter,$to_date_parameter);

            $breastFeedingRoutine = LiveData::getBreastFeedingRoutine($from_date_parameter,$to_date_parameter);
            $breatFeedingTargeted = LiveData::getBreastFeedingTargeted($from_date_parameter,$to_date_parameter);

            $routine_suppressed_f_below_1 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',1,NULL);
            $routine_suppressed_f_from_1_to_4 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',1,4);
            $routine_suppressed_f_from_5_to_9 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',5,9);
            $routine_suppressed_f_from_10_to_14 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',10,14);
            $routine_suppressed_f_from_15_to_19 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',15,19);
            $routine_suppressed_f_from_20_to_24 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',20,24);
            $routine_suppressed_f_from_25_to_29 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',25,29);
            $routine_suppressed_f_from_30_to_34 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',30,34);
            $routine_suppressed_f_from_35_to_39 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',35,39);
            $routine_suppressed_f_from_40_to_44 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',40,44);
            $routine_suppressed_f_from_45_to_49 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',45,49);
            $routine_suppressed_f_from_50_plus = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',50,NULL);

            $routine_suppressed_m_below_1 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',1,NULL);
            $routine_suppressed_m_from_1_to_4 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',1,4);
            $routine_suppressed_m_from_5_to_9 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',5,9);
            $routine_suppressed_m_from_10_to_14 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',10,14);
            $routine_suppressed_m_from_15_to_19 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',15,19);
            $routine_suppressed_m_from_20_to_24 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',20,24);
            $routine_suppressed_m_from_25_to_29 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',25,29);
            $routine_suppressed_m_from_30_to_34 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',30,34);
            $routine_suppressed_m_from_35_to_39 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',35,39);
            $routine_suppressed_m_from_40_to_44 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',40,44);
            $routine_suppressed_m_from_45_to_49 = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',45,49);
            $routine_suppressed_m_from_50_plus = LiveData::getRoutineSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',50,NULL);

            $targeted_suppressed_f_below_1 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',1,NULL);
            $targeted_suppressed_f_from_1_to_4 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',1,4);
            $targeted_suppressed_f_from_5_to_9 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',5,9);
            $targeted_suppressed_f_from_10_to_14 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',10,14);
            $targeted_suppressed_f_from_15_to_19 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',15,19);
            $targeted_suppressed_f_from_20_to_24 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',20,24);
            $targeted_suppressed_f_from_25_to_29 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',25,29);
            $targeted_suppressed_f_from_30_to_34 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',30,34);
            $targeted_suppressed_f_from_35_to_39 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',35,39);
            $targeted_suppressed_f_from_40_to_44 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',40,44);
            $targeted_suppressed_f_from_45_to_49 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',45,49);
            $targeted_suppressed_f_from_50_plus = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'F',50,NULL);

            $targeted_suppressed_m_below_1 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',1,NULL);
            $targeted_suppressed_m_from_1_to_4 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',1,4);
            $targeted_suppressed_m_from_5_to_9 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',5,9);
            $targeted_suppressed_m_from_10_to_14 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',10,14);
            $targeted_suppressed_m_from_15_to_19 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',15,19);
            $targeted_suppressed_m_from_20_to_24 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',20,24);
            $targeted_suppressed_m_from_25_to_29 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',25,29);
            $targeted_suppressed_m_from_30_to_34 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',30,34);
            $targeted_suppressed_m_from_35_to_39 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',35,39);
            $targeted_suppressed_m_from_40_to_44 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',40,44);
            $targeted_suppressed_m_from_45_to_49 = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',45,49);
            $targeted_suppressed_m_from_50_plus = LiveData::getTargetedSuppressedIndividuals($from_date_parameter,$to_date_parameter,
                'M',50,NULL);

        //last twelve months
            $from_date_12_months_back = $this->getDate12MonthsBack($from_date_parameter);
            
            $individualsWithVLresult_last_12_months = LiveData::getIndividualsWithVLresult($from_date_12_months_back,$to_date_parameter);
            $routine_indication_last_12_months=LiveData::getRoutineIndication($from_date_12_months_back,$to_date_parameter);
            $targeted_indication_last_12_months=LiveData::getTargetedIndication($from_date_12_months_back,$to_date_parameter);

            $pregantRoutine_last_12_months = LiveData::getPregnantRoutine($from_date_12_months_back,$to_date_parameter);
            $pregantTargeted_last_12_months = LiveData::getPregnantTargeted($from_date_12_months_back,$to_date_parameter);

            $breastFeedingRoutine_last_12_months = LiveData::getBreastFeedingRoutine($from_date_12_months_back,$to_date_parameter);
            $breatFeedingTargeted_last_12_months = LiveData::getBreastFeedingTargeted($from_date_12_months_back,$to_date_parameter);

            $routine_f_below_1_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',1,NULL);
            $routine_f_from_1_to_4_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',1,4);
            $routine_f_from_5_to_9_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',5,9);
            $routine_f_from_10_to_14_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',10,14);
            $routine_f_from_15_to_19_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',15,19);
            $routine_f_from_20_to_24_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',20,24);
            $routine_f_from_25_to_29_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',25,29);
            $routine_f_from_30_to_34_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',30,34);
            $routine_f_from_35_to_39_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',35,39);
            $routine_f_from_40_to_44_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',40,44);
            $routine_f_from_45_to_49_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',45,49);
            $routine_f_from_50_plus_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',50,NULL);

            $routine_m_below_1_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',1,NULL);
            $routine_m_from_1_to_4_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',1,4);
            $routine_m_from_5_to_9_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',5,9);
            $routine_m_from_10_to_14_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',10,14);
            $routine_m_from_15_to_19_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',15,19);
            $routine_m_from_20_to_24_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',20,24);
            $routine_m_from_25_to_29_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',25,29);
            $routine_m_from_30_to_34_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',30,34);
            $routine_m_from_35_to_39_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',35,39);
            $routine_m_from_40_to_44_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',40,44);
            $routine_m_from_45_to_49_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',45,49);
            $routine_m_from_50_plus_last_12_months = LiveData::getRoutineIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',50,NULL);

            $targeted_f_below_1_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',1,NULL);
            $targeted_f_from_1_to_4_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',1,4);
            $targeted_f_from_5_to_9_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',5,9);
            $targeted_f_from_10_to_14_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',10,14);
            $targeted_f_from_15_to_19_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',15,19);
            $targeted_f_from_20_to_24_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',20,24);
            $targeted_f_from_25_to_29_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',25,29);
            $targeted_f_from_30_to_34_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',30,34);
            $targeted_f_from_35_to_39_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',35,39);
            $targeted_f_from_40_to_44_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',40,44);
            $targeted_f_from_45_to_49_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',45,49);
            $targeted_f_from_50_plus_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'F',50,NULL);

            $targeted_m_below_1_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',1,NULL);
            $targeted_m_from_1_to_4_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',1,4);
            $targeted_m_from_5_to_9_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',5,9);
            $targeted_m_from_10_to_14_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',10,14);
            $targeted_m_from_15_to_19_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',15,19);
            $targeted_m_from_20_to_24_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',20,24);
            $targeted_m_from_25_to_29_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',25,29);
            $targeted_m_from_30_to_34_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',30,34);
            $targeted_m_from_35_to_39_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',35,39);
            $targeted_m_from_40_to_44_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',40,44);
            $targeted_m_from_45_to_49_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',45,49);
            $targeted_m_from_50_plus_last_12_months = LiveData::getTargetedIndividualsWithVLresults($from_date_12_months_back,$to_date_parameter,
                'M',50,NULL);

        $final_pvls_report_array = array();

        array_push($final_pvls_report_array, [
        'dhis2_hf_id','region','dhis2_district','dhis2_subcounty','dhis2_name','datim_id',
            'art_support','art_im','art_agency','individuals_with_vl_result',
            'individuals_with_vl_suppression','indication_routine','indication_target',
            'pregant_routine','pregant_targeted','breast_feeding_routine','breast_feeding_targeted',
            'routine_suppressed_f_below_1','routine_suppressed_f_from_1_to_4','routine_suppressed_f_from_5_to_9',
            'routine_suppressed_f_from_10_to_14','routine_suppressed_f_from_15_to_19','routine_suppressed_f_from_20_to_24',
            'routine_suppressed_f_from_25_to_29','routine_suppressed_f_from_30_to_34','routine_suppressed_f_from_35_to_39',
            'routine_suppressed_f_from_40_to_44','routine_suppressed_f_from_45_to_49','routine_suppressed_f_from_50_plus',

            'routine_suppressed_m_below_1','routine_suppressed_m_from_1_to_4','routine_suppressed_m_from_5_to_9',
            'routine_suppressed_m_from_10_to_14','routine_suppressed_m_from_15_to_19','routine_suppressed_m_from_20_to_24',
            'routine_suppressed_m_from_25_to_29','routine_suppressed_m_from_30_to_34','routine_suppressed_m_from_35_to_39',
            'routine_suppressed_m_from_40_to_44','routine_suppressed_m_from_45_to_49','routine_suppressed_m_from_50_plus',

            'targeted_suppressed_f_below_1','targeted_suppressed_f_from_1_to_4','targeted_suppressed_f_from_5_to_9',
            'targeted_suppressed_f_from_10_to_14','targeted_suppressed_f_from_15_to_19','targeted_suppressed_f_from_20_to_24',
            'targeted_suppressed_f_from_25_to_29','targeted_suppressed_f_from_30_to_34','targeted_suppressed_f_from_35_to_39',
            'targeted_suppressed_f_from_40_to_44','targeted_suppressed_f_from_45_to_49','targeted_suppressed_f_from_50_plus',

            'targeted_suppressed_m_below_1','targeted_suppressed_m_from_1_to_4','targeted_suppressed_m_from_5_to_9',
            'targeted_suppressed_m_from_10_to_14','targeted_suppressed_m_from_15_to_19','targeted_suppressed_m_from_20_to_24',
            'targeted_suppressed_m_from_25_to_29','targeted_suppressed_m_from_30_to_34','targeted_suppressed_m_from_35_to_39',
            'targeted_suppressed_m_from_40_to_44','targeted_suppressed_m_from_45_to_49','targeted_suppressed_m_from_50_plus',
            'individualsWithVLresult_last_12_months','routine_indication_last_12_months','targeted_indication_last_12_months',
            'pregantRoutine_last_12_months','pregantTargeted_last_12_months','breastFeedingRoutine_last_12_months',
            'breatFeedingTargeted_last_12_months',
            'routine_f_below_1_last_12_months','routine_f_from_1_to_4_last_12_months','routine_f_from_5_to_9_last_12_months',
            'routine_f_from_10_to_14_last_12_months','routine_f_from_15_to_19_last_12_months','routine_f_from_20_to_24_last_12_months',
            'routine_f_from_25_to_29_last_12_months','routine_f_from_30_to_34_last_12_months','routine_f_from_35_to_39_last_12_months',
            'routine_f_from_40_to_44_last_12_months','routine_f_from_45_to_49_last_12_months','routine_f_from_50_plus_last_12_months',
            'routine_m_below_1_last_12_months','routine_m_from_1_to_4_last_12_months','routine_m_from_5_to_9_last_12_months',
            'routine_m_from_10_to_14_last_12_months','routine_m_from_15_to_19_last_12_months','routine_m_from_20_to_24_last_12_months',
            'routine_m_from_25_to_29_last_12_months','routine_m_from_30_to_34_last_12_months','routine_m_from_35_to_39_last_12_months',
            'routine_m_from_40_to_44_last_12_months','routine_m_from_45_to_49_last_12_months','routine_m_from_50_plus_last_12_months',
        'targeted_f_below_1_last_12_months','targeted_f_from_1_to_4_last_12_months','targeted_f_from_5_to_9_last_12_months',
        'targeted_f_from_10_to_14_last_12_months','targeted_f_from_15_to_19_last_12_months','targeted_f_from_20_to_24_last_12_months',
        'targeted_f_from_25_to_29_last_12_months','targeted_f_from_30_to_34_last_12_months','targeted_f_from_35_to_39_last_12_months',
        'targeted_f_from_40_to_44_last_12_months','targeted_f_from_45_to_49_last_12_months','targeted_f_from_50_plus_last_12_months',

        'targeted_m_below_1_last_12_months','targeted_m_from_1_to_4_last_12_months','targeted_m_from_5_to_9_last_12_months',
        'targeted_m_from_10_to_14_last_12_months','targeted_m_from_15_to_19_last_12_months','targeted_m_from_20_to_24_last_12_months',
        'targeted_m_from_25_to_29_last_12_months','targeted_m_from_30_to_34_last_12_months','targeted_m_from_35_to_39_last_12_months',
        'targeted_m_from_40_to_44_last_12_months','targeted_m_from_45_to_49_last_12_months','targeted_m_from_50_plus_last_12_months'
     
            ]);
        foreach ($pepfar_pvls_locations as $key => $row) {

            //check if the location_dhis_uid == 
            $facility_pvls_report  = array(
                'dhis2_hf_id' => $row->dhis2_hf_id,
                'region' => $row->region,
                'dhis2_district' => $row->dhis2_district,
                'dhis2_subcounty'=>$row->dhis2_subcounty,
                'dhis2_name'=>$row->dhis2_name,
                'datim_id'=>$row->datim_id,
                'art_support'=>$row->art_support,
                'art_im'=>$row->art_im,
                'art_agency'=>$row->art_agency,

                'individuals_with_vl_result'=>$this->getNumbers($individualsWithVLresult,$row->dhis2_hf_id),
                'individuals_with_vl_suppression'=> $this->getNumbers($individualsWithVLsuppression,$row->dhis2_hf_id),
                'indication_routine' => $this->getNumbers($routineIndication,$row->dhis2_hf_id),
                'indication_target' =>  $this->getNumbers($targetedIndication,$row->dhis2_hf_id),
                'pregant_routine' => $this->getNumbers($pregantRoutine,$row->dhis2_hf_id),
                'pregant_targeted' =>$this->getNumbers($pregantTargeted,$row->dhis2_hf_id),
                'breast_feeding_routine' => $this->getNumbers($breastFeedingRoutine,$row->dhis2_hf_id),
                'breast_feeding_targeted' => $this->getNumbers($breatFeedingTargeted,$row->dhis2_hf_id),

                'routine_suppressed_f_below_1' =>$this->getNumbers($routine_suppressed_f_below_1,$row->dhis2_hf_id),
                'routine_suppressed_f_from_1_to_4' =>$this->getNumbers($routine_suppressed_f_from_1_to_4,$row->dhis2_hf_id),
                'routine_suppressed_f_from_5_to_9' =>$this->getNumbers($routine_suppressed_f_from_5_to_9,$row->dhis2_hf_id),
                'routine_suppressed_f_from_10_to_14' =>$this->getNumbers($routine_suppressed_f_from_10_to_14,$row->dhis2_hf_id),
                'routine_suppressed_f_from_15_to_19' =>$this->getNumbers($routine_suppressed_f_from_15_to_19,$row->dhis2_hf_id),

                'routine_suppressed_f_from_20_to_24' =>$this->getNumbers($routine_suppressed_f_from_20_to_24,$row->dhis2_hf_id),
                'routine_suppressed_f_from_25_to_29' =>$this->getNumbers($routine_suppressed_f_from_25_to_29,$row->dhis2_hf_id),
                'routine_suppressed_f_from_30_to_34' =>$this->getNumbers($routine_suppressed_f_from_30_to_34,$row->dhis2_hf_id),
                'routine_suppressed_f_from_35_to_39' =>$this->getNumbers($routine_suppressed_f_from_35_to_39,$row->dhis2_hf_id),
                'routine_suppressed_f_from_40_to_44' =>$this->getNumbers($routine_suppressed_f_from_40_to_44,$row->dhis2_hf_id),
                'routine_suppressed_f_from_45_to_49' =>$this->getNumbers($routine_suppressed_f_from_45_to_49,$row->dhis2_hf_id),
                'routine_suppressed_f_from_50_plus' =>$this->getNumbers($routine_suppressed_f_from_50_plus,$row->dhis2_hf_id),

                'routine_suppressed_m_below_1' =>$this->getNumbers($routine_suppressed_m_below_1,$row->dhis2_hf_id),
                'routine_suppressed_m_from_1_to_4' =>$this->getNumbers($routine_suppressed_m_from_1_to_4,$row->dhis2_hf_id),
                'routine_suppressed_m_from_5_to_9' =>$this->getNumbers($routine_suppressed_m_from_5_to_9,$row->dhis2_hf_id),
                'routine_suppressed_m_from_10_to_14' =>$this->getNumbers($routine_suppressed_m_from_10_to_14,$row->dhis2_hf_id),
                'routine_suppressed_m_from_15_to_19' =>$this->getNumbers($routine_suppressed_m_from_15_to_19,$row->dhis2_hf_id),

                'routine_suppressed_m_from_20_to_24' =>$this->getNumbers($routine_suppressed_m_from_20_to_24,$row->dhis2_hf_id),
                'routine_suppressed_m_from_25_to_29' =>$this->getNumbers($routine_suppressed_m_from_25_to_29,$row->dhis2_hf_id),
                'routine_suppressed_m_from_30_to_34' =>$this->getNumbers($routine_suppressed_m_from_30_to_34,$row->dhis2_hf_id),
                'routine_suppressed_m_from_35_to_39' =>$this->getNumbers($routine_suppressed_m_from_35_to_39,$row->dhis2_hf_id),
                'routine_suppressed_m_from_40_to_44' =>$this->getNumbers($routine_suppressed_m_from_40_to_44,$row->dhis2_hf_id),
                'routine_suppressed_m_from_45_to_49' =>$this->getNumbers($routine_suppressed_m_from_45_to_49,$row->dhis2_hf_id),
                'routine_suppressed_m_from_50_plus' =>$this->getNumbers($routine_suppressed_m_from_50_plus,$row->dhis2_hf_id),

                'targeted_suppressed_f_below_1' =>$this->getNumbers($targeted_suppressed_f_below_1,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_1_to_4' =>$this->getNumbers($targeted_suppressed_f_from_1_to_4,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_5_to_9' =>$this->getNumbers($targeted_suppressed_f_from_5_to_9,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_10_to_14' =>$this->getNumbers($targeted_suppressed_f_from_10_to_14,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_15_to_19' =>$this->getNumbers($targeted_suppressed_f_from_15_to_19,$row->dhis2_hf_id),

                'targeted_suppressed_f_from_20_to_24' =>$this->getNumbers($targeted_suppressed_f_from_20_to_24,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_25_to_29' =>$this->getNumbers($targeted_suppressed_f_from_25_to_29,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_30_to_34' =>$this->getNumbers($targeted_suppressed_f_from_30_to_34,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_35_to_39' =>$this->getNumbers($targeted_suppressed_f_from_35_to_39,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_40_to_44' =>$this->getNumbers($targeted_suppressed_f_from_40_to_44,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_45_to_49' =>$this->getNumbers($targeted_suppressed_f_from_45_to_49,$row->dhis2_hf_id),
                'targeted_suppressed_f_from_50_plus' =>$this->getNumbers($targeted_suppressed_f_from_50_plus,$row->dhis2_hf_id),

                'targeted_suppressed_m_below_1' =>$this->getNumbers($targeted_suppressed_m_below_1,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_1_to_4' =>$this->getNumbers($targeted_suppressed_m_from_1_to_4,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_5_to_9' =>$this->getNumbers($targeted_suppressed_m_from_5_to_9,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_10_to_14' =>$this->getNumbers($targeted_suppressed_m_from_10_to_14,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_15_to_19' =>$this->getNumbers($targeted_suppressed_m_from_15_to_19,$row->dhis2_hf_id),

                'targeted_suppressed_m_from_20_to_24' =>$this->getNumbers($targeted_suppressed_m_from_20_to_24,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_25_to_29' =>$this->getNumbers($targeted_suppressed_m_from_25_to_29,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_30_to_34' =>$this->getNumbers($targeted_suppressed_m_from_30_to_34,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_35_to_39' =>$this->getNumbers($targeted_suppressed_m_from_35_to_39,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_40_to_44' =>$this->getNumbers($targeted_suppressed_m_from_40_to_44,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_45_to_49' =>$this->getNumbers($targeted_suppressed_m_from_45_to_49,$row->dhis2_hf_id),
                'targeted_suppressed_m_from_50_plus' =>$this->getNumbers($targeted_suppressed_m_from_50_plus,$row->dhis2_hf_id),

            'individualsWithVLresult_last_12_months' => $this->getNumbers($individualsWithVLresult_last_12_months,$row->dhis2_hf_id),
            'routine_indication_last_12_months'=>$this->getNumbers($routine_indication_last_12_months,$row->dhis2_hf_id),
            'targeted_indication_last_12_months'=>$this->getNumbers($targeted_indication_last_12_months,$row->dhis2_hf_id),
            
            'pregantRoutine_last_12_months'=>$this->getNumbers($pregantRoutine_last_12_months,$row->dhis2_hf_id),
            'pregantTargeted_last_12_months'=>$this->getNumbers($pregantTargeted_last_12_months,$row->dhis2_hf_id),
            'breastFeedingRoutine_last_12_months'=>$this->getNumbers($breastFeedingRoutine_last_12_months,$row->dhis2_hf_id),
            'breatFeedingTargeted_last_12_months'=>$this->getNumbers($breatFeedingTargeted_last_12_months,$row->dhis2_hf_id),

            'routine_f_below_1_last_12_months'=>$this->getNumbers($routine_f_below_1_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_1_to_4_last_12_months'=>$this->getNumbers($routine_f_from_1_to_4_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_5_to_9_last_12_months'=>$this->getNumbers($routine_f_from_5_to_9_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_10_to_14_last_12_months'=>$this->getNumbers($routine_f_from_10_to_14_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_15_to_19_last_12_months'=>$this->getNumbers($routine_f_from_15_to_19_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_20_to_24_last_12_months'=>$this->getNumbers($routine_f_from_20_to_24_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_25_to_29_last_12_months'=>$this->getNumbers($routine_f_from_25_to_29_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_30_to_34_last_12_months'=>$this->getNumbers($routine_f_from_30_to_34_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_35_to_39_last_12_months'=>$this->getNumbers($routine_f_from_35_to_39_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_40_to_44_last_12_months'=>$this->getNumbers($routine_f_from_40_to_44_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_45_to_49_last_12_months'=>$this->getNumbers($routine_f_from_45_to_49_last_12_months,$row->dhis2_hf_id),
            'routine_f_from_50_plus_last_12_months'=>$this->getNumbers($routine_f_from_50_plus_last_12_months,$row->dhis2_hf_id),

            'routine_m_below_1_last_12_months'=>$this->getNumbers($routine_m_below_1_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_1_to_4_last_12_months'=>$this->getNumbers($routine_m_from_1_to_4_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_5_to_9_last_12_months'=>$this->getNumbers($routine_m_from_5_to_9_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_10_to_14_last_12_months'=>$this->getNumbers($routine_m_from_10_to_14_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_15_to_19_last_12_months'=>$this->getNumbers($routine_m_from_15_to_19_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_20_to_24_last_12_months'=>$this->getNumbers($routine_m_from_20_to_24_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_25_to_29_last_12_months'=>$this->getNumbers($routine_m_from_25_to_29_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_30_to_34_last_12_months'=>$this->getNumbers($routine_m_from_30_to_34_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_35_to_39_last_12_months'=>$this->getNumbers($routine_m_from_35_to_39_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_40_to_44_last_12_months'=>$this->getNumbers($routine_m_from_40_to_44_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_45_to_49_last_12_months'=>$this->getNumbers($routine_m_from_45_to_49_last_12_months,$row->dhis2_hf_id),
            'routine_m_from_50_plus_last_12_months'=>$this->getNumbers($routine_m_from_50_plus_last_12_months,$row->dhis2_hf_id),

            'targeted_f_below_1_last_12_months'=>$this->getNumbers($targeted_f_below_1_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_1_to_4_last_12_months'=>$this->getNumbers($targeted_f_from_1_to_4_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_5_to_9_last_12_months'=>$this->getNumbers($targeted_f_from_5_to_9_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_10_to_14_last_12_months'=>$this->getNumbers($targeted_f_from_10_to_14_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_15_to_19_last_12_months'=>$this->getNumbers($targeted_f_from_15_to_19_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_20_to_24_last_12_months'=>$this->getNumbers($targeted_f_from_20_to_24_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_25_to_29_last_12_months'=>$this->getNumbers($targeted_f_from_25_to_29_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_30_to_34_last_12_months'=>$this->getNumbers($targeted_f_from_30_to_34_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_35_to_39_last_12_months'=>$this->getNumbers($targeted_f_from_35_to_39_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_40_to_44_last_12_months'=>$this->getNumbers($targeted_f_from_40_to_44_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_45_to_49_last_12_months'=>$this->getNumbers($targeted_f_from_45_to_49_last_12_months,$row->dhis2_hf_id),
            'targeted_f_from_50_plus_last_12_months'=>$this->getNumbers($targeted_f_from_50_plus_last_12_months,$row->dhis2_hf_id),

            'targeted_m_below_1_last_12_months'=>$this->getNumbers($targeted_m_below_1_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_1_to_4_last_12_months'=>$this->getNumbers($targeted_m_from_1_to_4_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_5_to_9_last_12_months'=>$this->getNumbers($targeted_m_from_5_to_9_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_10_to_14_last_12_months'=>$this->getNumbers($targeted_m_from_10_to_14_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_15_to_19_last_12_months'=>$this->getNumbers($targeted_m_from_15_to_19_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_20_to_24_last_12_months'=>$this->getNumbers($targeted_m_from_20_to_24_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_25_to_29_last_12_months'=>$this->getNumbers($targeted_m_from_25_to_29_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_30_to_34_last_12_months'=>$this->getNumbers($targeted_m_from_30_to_34_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_35_to_39_last_12_months'=>$this->getNumbers($targeted_m_from_35_to_39_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_40_to_44_last_12_months'=>$this->getNumbers($targeted_m_from_40_to_44_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_45_to_49_last_12_months'=>$this->getNumbers($targeted_m_from_45_to_49_last_12_months,$row->dhis2_hf_id),
            'targeted_m_from_50_plus_last_12_months'=>$this->getNumbers($targeted_m_from_50_plus_last_12_months,$row->dhis2_hf_id)

                );

            array_push($final_pvls_report_array, $facility_pvls_report);
        }

        

        echo ".... generating csv...\n";
        $fp = fopen('/tmp/pvls_report'.date('YmdHis').'.csv', 'w');
        foreach ($final_pvls_report_array as $fields) {
             fputcsv($fp, $fields);
        }

        
        fclose($fp);
    }

    private function getDate12MonthsBack($date_parameter){
        $ret;
        $n=env('INIT_MONTHS');
        $m=date('m');
        $y=date('Y');

        $date_array = explode('-', $date_parameter);

        $y= $date_array[0];
        $m= $date_array[1];

        for($i=1;$i<=$n;$i++){
            
            if($m==0){
                $m=12;
                $y--;
            }
            if($i==$n){
                $ret=$y."-".str_pad($m, 2,0, STR_PAD_LEFT);
            } 
            $m--;
        }
        return $ret;
    }

}

