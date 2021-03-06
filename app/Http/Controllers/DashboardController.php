<?php namespace EID\Http\Controllers;

use EID\Http\Requests;
use EID\Http\Controllers\Controller;

use EID\Dashboard;
use EID\SamplesData;
use EID\TreatmentIndication;

use EID\Hub;
use EID\District;
use EID\Facility;

use EID\Mongo;

use Validator;
use Lang;
use Redirect;
use Request;
use Session;
use Log;

class DashboardController extends Controller {
	//private $mongo = \MongoClient::connect('vldash');

	public function __construct(){
		$this->months=\MyHTML::initMonths();
		$this->mongo=Mongo::connect();
		$this->conditions=$this->_setConditions();

		//$this->middleware('auth');
	}

	public function init(){
		$to_date=date("Ym");
		$fro_date=$this->_dateNMonthsBack();
		return view("vdash",compact("fro_date","to_date"));
	}

	public function downloadCsv($type)
	{
		if(Request::ajax()) {
      		$data = Input::all();
      		print_r($data);die;
        }

		//$data = Item::get()->toArray();
		//return Excel::create('itsolutionstuff_example', function($excel) use ($data) {
		//	$excel->sheet('mySheet', function($sheet) use ($data)
	    //    {
		//		$sheet->fromArray($data);
	    //    });
		//})->download($type);
	}
	private function _setConditions(){
		extract(\Request::all());
	
		if((empty($fro_date) && empty($to_date))||$fro_date=='all' && $to_date=='all'){
			$to_date=date("Ym");
			$fro_date=$this->_dateNMonthsBack();
		}


		$conds=[];
		$conds['$and'][]=['year_month'=>  ['$gte'=> (int)$fro_date] ];
		$conds['$and'][]=[ 'year_month'=>  ['$lte'=> (int)$to_date] ];
		if(!empty($districts)&&$districts!='[]') $conds['$and'][]=[ 'district_id'=>  ['$in'=> json_decode($districts)] ];
		if(!empty($hubs)&&$hubs!='[]') $conds['$and'][]=[ 'hub_id'=>  ['$in'=> json_decode($hubs)] ];
		if(!empty($age_ids)&&$age_ids!='[]') {
			
			$age_bands=json_decode($age_ids);
			$number_of_age_bands=sizeof($age_bands);

			$lower_age_band=0;
			$upper_age_band=0;
			if($number_of_age_bands > 0){
				$lower_age_band=$age_bands[0];
				$last_index = $number_of_age_bands - 1;
				$upper_age_band=$age_bands[$last_index];
			}

			
			$conds['$and'][]=[ 'age_group_id'=>  ['$gte'=> (int)$lower_age_band] ];
			$conds['$and'][]=[ 'age_group_id'=>  ['$lte'=> (int)$upper_age_band] ];
			
		}
			
		if(!empty($genders)&&$genders!='[]') $conds['$and'][]=[ 'gender'=>  ['$in'=> json_decode($genders)] ];
		//if(!empty($regimens)&&$regimens!='[]') $conds['$and'][]=[ 'regimen_group_id'=>  ['$in'=> json_decode($regimens)] ];
		if(!empty($regimens)&&$regimens!='[]') $conds['$and'][]=[ 'regimen'=>  ['$in'=> json_decode($regimens)] ];
		if(!empty($lines)&&$lines!='[]') $conds['$and'][]=[ 'regimen_line'=>  ['$in'=> json_decode($lines)] ];
		if(!empty($indications)&&$indications!='[]')
			$conds['$and'][]=[ 'treatment_indication_id'=>  ['$in'=> json_decode($indications)] ];
		
		if( !empty($emtct) && $emtct!='[]') {
			
			$emtct_array =json_decode($emtct);
			
			if (count($emtct_array) == 1) {
		
				$emtct_value = $emtct_array[0];
				
				if($emtct_value  == 'pregnancy_status'){
					
					$conds['$and'][]=[ 'pregnancy_status'=>  'Yes' ];
					
				}else if($emtct_value == 'breast_feeding_status'){
					
					$conds['$and'][]=[ 'breastfeeding_status'=> 'Yes' ];
					
				}
			}else{
				foreach ($emtct_array as $value) {
					if($value == 'pregnancy_status'){
						$pregancy_status_array = array(0 => 'Yes' );
						$conds['$or'][]=[ 'pregnancy_status'=>  'Yes' ];
					}else if($value == 'breast_feeding_status'){
						$conds['$or'][]=[ 'breastfeeding_status'=>  'Yes' ];
					}else if($value == 'initiated_art_because_pmtct'){
						$pmtct_option_B_plus_id_in_DB_array= array(0 =>1);
						$conds['$or'][]=[ 'treatment_indication_id'=>  ['$in'=> $pmtct_option_B_plus_id_in_DB_array] ];
					}
			  }
			}
		}//end emtct if

		if(!empty($tb_status)&&$tb_status!='[]'){
			$tb_status_array = json_decode($tb_status);
			foreach ($tb_status_array as $key => $value) {
				if($value == 'y')
					$conds['$and'][]=[ 'active_tb_status'=> 'Yes'];
				elseif($value == 'n')
					$conds['$and'][]=[ 'active_tb_status'=> 'No'];
				elseif($value == 'x')
					$conds['$and'][]=[ 'active_tb_status'=> 'UNKNOWN'];
			}
		}
		
	  	
		return $conds;
	}


	public function other_data(){
		$hubs=iterator_to_array($this->mongo->hubs->find());
		$districts=iterator_to_array($this->mongo->districts->find());
		$facilities=iterator_to_array($this->mongo->facilities->find());
		$regimens=iterator_to_array($this->mongo->regimens->find());
		
		return compact("hubs","districts","facilities","regimens");
	}
	
   
	private function _latestNMonths($n=12){
        $ret=[];
        $m=date('m');
        $y=date('Y');
        for($i=1;$i<=$n;$i++){
            if($m==0){
                $m=12;
                $y--;
            }
            array_unshift($ret, $y.str_pad($m, 2,0, STR_PAD_LEFT));
            $m--;
        }
        return $ret;
    }

    private function _dateNMonthsBack(){
    	$ret;
    	$n=env('INIT_MONTHS');
        $m=date('m');
        $y=date('Y');
        for($i=1;$i<=$n;$i++){
        	
            if($m==0){
                $m=12;
                $y--;
            }
            if($i==$n){
        		$ret=$y.str_pad($m, 2,0, STR_PAD_LEFT);
        	} 
            $m--;
        }
        return $ret;
    }

	public function live(){

		
		$whole_numbers=$this->_wholeNumbers();//TBD
		//return ['y'=>8,'a'=>9,'c'=>13,'x'=>19];
		$t_indication=$this->_treatmentIndicationNumbers();
		$f_numbers=$this->_facilityNumbers();
		$dist_numbers=$this->_districtNumbers();
		$drn_numbers=$this->_durationNumbers();
		
		
		//$reg_groups=$this->_regimenGroupNumbers();
		$regimen_numbers = $this->_regimenNumbers();
		$reg_times=$this->_regimenTimeNumbers();
		$line_numbers=$this->_lineNumbers();//Done
		$regimen_by_line_of_treatment = $this->_regimenByLineOfTreatment();

		$regimen_names = $this->_regimenNames();
		return compact("whole_numbers","t_indication","f_numbers","dist_numbers","drn_numbers",
			"regimen_numbers","reg_times","line_numbers","regimen_by_line_of_treatment","regimen_names");
	}


	private function _wholeNumbers(){
		
		$match_stage['$match']=$this->conditions;

		$group_stage = array(
			
			'$group' => array(
				'_id' => null, 
				'samples_received' => array('$sum' => 1 ),
				'valid_results' => array('$sum' => array('$cond'=>array(array('$eq' => array('$sample_result_validity','valid')),1,0))),
				'suppressed' => array('$sum' => array('$cond'=>array(array('$eq' => array('$suppression_status','yes')),1,0))),
				'rejected_samples' => array(
					'$sum' => array(
						'$cond'=>array(
							array('$or' => array(
					
								    //'$eq' => array('$rejection_category','eligibility'),
									//'$eq' => array('$rejection_category','data_quality'),
									//'$eq' => array('$rejection_category','sample_quality'),

									'$ne' => array('$rejection_category','UNKNOWN')

								)
								),1,0)
						)
					)
				
			 ));
		
		
		
		$res=$this->mongo->dashboard_new_backend->aggregate($match_stage,$group_stage);
		
		
		return isset($res['result'])?$res['result'][0]:[];


	}



	private function _treatmentIndicationNumbers(){
		$grp=[];
		$grp['_id']='$treatment_indication_id';
		$grp['samples_received'] = ['$addToSet'=>'$vl_sample_id'];

		$projectArray['_id']='$_id';
		$projectArray['samples_received']=['$size'=>'$samples_received'];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$this->conditions],['$group'=>$grp],
			['$project'=>$projectArray]);

		$ret=[];
		if(isset($res['result'])) foreach ($res['result'] as $row) $ret[$row['_id']]=$row['samples_received'];
		
		return $ret;
	}

	private function _lineNumbers(){
		$grp=[];
		$grp['_id']='$regimen_line';
		$grp['samples_received'] = ['$addToSet'=>'$vl_sample_id'];

		$projectArray['_id']='$_id';
		$projectArray['samples_received']=['$size'=>'$samples_received'];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$this->conditions],['$group'=>$grp],
			['$project'=>$projectArray]);

		$ret=[];

		if(isset($res['result'])) foreach ($res['result'] as $row) $ret[$row['_id']]=$row['samples_received'];
		return $ret;
	}

	private function _facilityNumbers(){
		$match_stage['$match']=$this->conditions;

		$group_stage = array(
			
			'$group' => array(
				'_id' => array('district_id'=>'$district_id','hub_id'=>'$hub_id','facility_id'=>'$facility_id'), 
				'samples_received' => array('$sum' => 1 ),
				'valid_results' => array('$sum' => array('$cond'=>array(array('$eq' => array('$sample_result_validity','valid')),1,0))),
				'dbs_samples' => array('$sum' => array('$cond'=>array(array('$eq' => array('$sample_type_id',1)),1,0))),
				'total_results' => array('$sum' => array('$cond'=>array(array('$eq' => array('$tested','yes')),1,0))),
				'suppressed' => array('$sum' => array('$cond'=>array(array('$eq' => array('$suppression_status','yes')),1,0))),
				'rejected_samples' => array(
					'$sum' => array(
						'$cond'=>array(
							array('$or' => array(
									'$eq' => array('$rejection_category','eligibility'),
									'$eq' => array('$rejection_category','data_quality'),
									'$eq' => array('$rejection_category','sample_quality')
								)
								),1,0)
						)
					),
				'unique_patient_count' =>array(
						'$addToSet'=>'$patient_unique_id'
					)
			 ));
		
		$project_stage['$project']=array(
			'samples_received'=>1,'valid_results'=>1,'dbs_samples'=>1,'total_results'=>1,'suppressed' => 1,'rejected_samples'=>1,
			'patients_received'=>array('$size'=>'$unique_patient_count')
			);
		
		$res=$this->mongo->dashboard_new_backend->aggregate($match_stage,$group_stage,$project_stage);
		
		
		return isset($res['result'])?$res['result']:[];
	}
	
	private function _districtNumbers(){
		$match_stage['$match']=$this->conditions;

		$group_stage = array(
			
			'$group' => array(
				'_id' => '$district_id', 
				'samples_received' => array('$sum' => 1 ),
				'valid_results' => array('$sum' => array('$cond'=>array(array('$eq' => array('$sample_result_validity','valid')),1,0))),
				'dbs_samples' => array('$sum' => array('$cond'=>array(array('$eq' => array('$sample_type_id',1)),1,0))),
				'total_results' => array('$sum' => array('$cond'=>array(array('$eq' => array('$tested','yes')),1,0))),
				'suppressed' => array('$sum' => array('$cond'=>array(array('$eq' => array('$suppression_status','yes')),1,0))),
				'rejected_samples' => array(
					'$sum' => array(
						'$cond'=>array(
							array('$or' => array(
									'$eq' => array('$rejection_category','eligibility'),
									'$eq' => array('$rejection_category','data_quality'),
									'$eq' => array('$rejection_category','sample_quality')
								)
								),1,0)
						)
					),
				'unique_patient_count' =>array(
						'$addToSet'=>'$patient_unique_id'
					)
			 ));
		
		$project_stage['$project']=array(
			'samples_received'=>1,'valid_results'=>1,'dbs_samples'=>1,'total_results'=>1,'suppressed' => 1,'rejected_samples'=>1,'sample_quality_rejections'=>1, 'incomplete_form_rejections'=>1,'eligibility_rejections'=>1,'patients_received'=>array('$size'=>'$unique_patient_count')
			);
		
		$res=$this->mongo->dashboard_new_backend->aggregate($match_stage,$group_stage,$project_stage);
		
		
		return isset($res['result'])?$res['result']:[];
	}
	


 	private function _getProcessedDurationNumberAggregates($sample_data){
 		$samples_patients_received= $sample_data['samples_patients_received'];
		$suppressed=$sample_data['suppressed'];

		$validResults=$sample_data['validResults'];
		$rejectedSamples=$sample_data['rejectedSamples'];
		
		$sampleQualityRejections=$sample_data['sampleQualityRejections'];
		$eligibilityRejections=$sample_data['eligibilityRejections'];
		$incompleteFormRejections=$sample_data['incompleteFormRejections'];
		

		$dbs_samples=$sample_data['dbs_samples'];
		$totalResults=$sample_data['totalResults'];

		
		$districtsAggregates=[];
		
		foreach ($samples_patients_received as $key => $value) {
			
			$_id = $value['_id'];
			$year_month = $_id;

			$aggregates['_id']=$_id;//year_month
			$aggregates['year_month']=$year_month;
			$aggregates['samples_received']=$value['samples_received'];
			$aggregates['patients_received']=$value['patients_received'];

			
			$dummySuppressed = $this->searchArray($suppressed, '_id',$year_month);
			$dummySuppressed != false ? $aggregates['suppressed']=$dummySuppressed['suppressed'] : 
										$aggregates['suppressed']=0;
			
			$index = array_search(intval($value['_id']), array_column($validResults, '_id'));
			$dummyValidResults = $this->searchArray($validResults, '_id',$year_month);
			$dummyValidResults != false ? $aggregates['valid_results']=$dummyValidResults['valid_results'] : 
										$aggregates['valid_results']=0;	
			
			$dummyRejections = $this->searchArray($rejectedSamples, '_id',$year_month);
			$dummyRejections != false ? $aggregates['rejected_samples']=$dummyRejections['rejected_samples'] : 
										$aggregates['rejected_samples']=0;

			/* sample_quality_rejections */
			$dummyRejections = $this->searchArray($sampleQualityRejections, '_id',$year_month);
			$dummyRejections != false ? $aggregates['sample_quality_rejections']=$dummyRejections['rejected_samples'] : 
										$aggregates['sample_quality_rejections']=0;

			/* eligibility_rejections */
			$dummyRejections = $this->searchArray($eligibilityRejections, '_id',$year_month);
			$dummyRejections != false ? $aggregates['eligibility_rejections']=$dummyRejections['rejected_samples'] : 
										$aggregates['eligibility_rejections']=0;

			/* incomplete_form_rejections */
			$dummyRejections = $this->searchArray($incompleteFormRejections, '_id',$year_month);
			$dummyRejections != false ? $aggregates['incomplete_form_rejections']=$dummyRejections['rejected_samples'] : 
										$aggregates['incomplete_form_rejections']=0;

			$dummyDbsSamples = $this->searchArray($dbs_samples, '_id',$year_month);
			$dummyDbsSamples != false ? $aggregates['dbs_samples']=$dummyDbsSamples['dbs_samples'] : 
										$aggregates['dbs_samples']=0;

			$dummyTotalResults = $this->searchArray($totalResults, '_id',$year_month);
			$dummyTotalResults != false ? $aggregates['total_results']=$dummyTotalResults['total_results'] : 
										$aggregates['total_results']=0;

			$districtsAggregates[$key]=$aggregates;
			
		}
 		return $districtsAggregates;
 	}
 	private function _getProcessedRegimenNumbersAggregates($sample_data){
 		$samples_patients_received= $sample_data['samples_received'];
		$suppressed=$sample_data['suppressed'];

		$validResults=$sample_data['validResults'];
		$totalResults=$sample_data['totalResults'];

		
		$regimenAggregates=[];
		
		foreach ($samples_patients_received as $key => $value) {
			
			$_id = $value['_id'];
			$regimen = $_id;

			$aggregates['_id']=$_id;
			$aggregates['regimen']=$regimen;
			$aggregates['samples_received']=$value['samples_received'];
			
			$dummySuppressed = $this->searchArray($suppressed, '_id',$regimen);
			$dummySuppressed != false ? $aggregates['suppressed']=$dummySuppressed['suppressed'] : 
										$aggregates['suppressed']=0;
			
			$index = array_search(intval($value['_id']), array_column($validResults, '_id'));
			$dummyValidResults = $this->searchArray($validResults, '_id',$regimen);
			$dummyValidResults != false ? $aggregates['valid_results']=$dummyValidResults['valid_results'] : 
										$aggregates['valid_results']=0;	

			$dummyTotalResults = $this->searchArray($totalResults, '_id',$regimen);
			$dummyTotalResults != false ? $aggregates['total_results']=$dummyTotalResults['total_results'] : 
										$aggregates['total_results']=0;

			$regimenAggregates[$key]=$aggregates;
			
		}
 		return $regimenAggregates;
 	}

 	//
 	private function _getProcessedRegimenTimeNumbersAggregates($sample_data){
 		$samples_patients_received= $sample_data['samples_received'];
		$suppressed=$sample_data['suppressed'];
		$validResults=$sample_data['validResults'];
		$totalResults=$sample_data['totalResults'];

		
		$regimenAggregates=[];
		
		foreach ($samples_patients_received as $key => $value) {
			
			$_id = $value['_id'];
			$regimenTimeID = $_id;

			$aggregates['_id']=$_id;
			$aggregates['regimen_time_id']=$regimenTimeID;
			$aggregates['samples_received']=$value['samples_received'];
			
			$dummySuppressed = $this->searchArray($suppressed, '_id',$regimenTimeID);
			$dummySuppressed != false ? $aggregates['suppressed']=$dummySuppressed['suppressed'] : 
										$aggregates['suppressed']=0;
			
			$index = array_search(intval($value['_id']), array_column($validResults, '_id'));
			$dummyValidResults = $this->searchArray($validResults, '_id',$regimenTimeID);
			$dummyValidResults != false ? $aggregates['valid_results']=$dummyValidResults['valid_results'] : 
										$aggregates['valid_results']=0;	

			$dummyTotalResults = $this->searchArray($totalResults, '_id',$regimenTimeID);
			$dummyTotalResults != false ? $aggregates['total_results']=$dummyTotalResults['total_results'] : 
										$aggregates['total_results']=0;

			$regimenAggregates[$key]=$aggregates;
			
		}
 		return $regimenAggregates;
 	}
 	private function searchArray($array_multidimentional, $field, $value)
	{
	   foreach($array_multidimentional as $key => $inner_array)
	   {
	      if ( $inner_array[$field] === $value )
	         return $inner_array;
	   }
	   return false;
	}

	private function searchArrayDistricts($results, $field, $value)
	{
		if(isset($results)){
			foreach ($results as $key => $results_instance) {
				if(intval($results_instance[$field]) == intval($value))
					return $results_instance;
			}
		}
		else{
			return false;
		}
	   
	}
	
	private function _getSuppressedByWholeNumbers(){
		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'suppression_status'=>  ['$in'=> ['yes']] ];
		
		$grp=[];
		$grp['_id']=null;
		$grp['suppressed']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
	}
 	private function _getSuppressedByDistrict(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'suppression_status'=>  ['$in'=> ['yes']] ];
		$grp=[];
		$grp['_id']='$district_id';
		$grp['suppressed']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getSuppressedByFacility(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'suppression_status'=>  ['$in'=> ['yes']] ];
		$grp=[];
		$grp['_id']=array('district_id'=>'$district_id','hub_id'=>'$hub_id','facility_id'=>'$facility_id');

		$grp['suppressed']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getSuppressedByDurationNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'suppression_status'=>  ['$in'=> ['yes']] ];
		$grp=[];
		$grp['_id']='$year_month';
		$grp['suppressed']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getSuppressedByRegimenNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'suppression_status'=>  ['$in'=> ['yes']] ];
		$grp=[];
		$grp['_id']='$regimen';
		$grp['suppressed']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
    
    private function _getSuppressedByRegimenTimeNumbers(){
    	$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'suppression_status'=>  ['$in'=> ['yes']] ];
		$grp=[];
		$grp['_id']='$regimen_time_id';
		$grp['suppressed']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
    }
    private function _getValidResultsByWholeNumbers(){
    	$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'sample_result_validity'=>  ['$in'=> ['valid']] ];
		$grp=[];
		$grp['_id']=null;
		$grp['valid_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
    }
 	private function _getValidResultsByDistrict(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'sample_result_validity'=>  ['$in'=> ['valid']] ];
		$grp=[];
		$grp['_id']='$district_id';
		$grp['valid_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getValidResultsByFacility(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'sample_result_validity'=>  ['$in'=> ['valid']] ];
		$grp=[];
		$grp['_id']=array('district_id'=>'$district_id','hub_id'=>'$hub_id','facility_id'=>'$facility_id');
		$grp['valid_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getValidResultsByRegimenNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'sample_result_validity'=>  ['$in'=> ['valid']] ];
		$grp=[];
		$grp['_id']='$regimen';
		$grp['valid_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getValidResultsByDurationNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'sample_result_validity'=>  ['$in'=> ['valid']] ];
		$grp=[];
		$grp['_id']='$year_month';
		$grp['valid_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}

 	
    private function _getValidResultsByTimeRegimenNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'sample_result_validity'=>  ['$in'=> ['valid']] ];
		$grp=[];
		$grp['_id']='$regimen_time_id';
		$grp['valid_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getRejectedSamplesByWholeNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'rejection_category'=>  ['$in'=> ['eligibility','data_quality','sample_quality']] ];
		$grp=[];
		$grp['_id']=null;
		$grp['rejected_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getRejectedSamplesByDistrict(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'rejection_category'=>  ['$in'=> ['eligibility','data_quality','sample_quality']] ];
		$grp=[];
		$grp['_id']='$district_id';
		$grp['rejected_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}

 	private function _getRejectedSamplesByFacility(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'rejection_category'=>  ['$in'=> ['eligibility','data_quality','sample_quality']] ];
		$grp=[];
		$grp['_id']=array('district_id'=>'$district_id','hub_id'=>'$hub_id','facility_id'=>'$facility_id');
		$grp['rejected_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getSampleQualityRejectionsByDurationNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'rejection_category'=>  ['$in'=> ['sample_quality']] ];
		$grp=[];
		$grp['_id']='$year_month';
		$grp['rejected_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getRejectedSamplesByDurationNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'rejection_category'=>  ['$in'=> ['eligibility','data_quality','sample_quality']] ];
		$grp=[];
		$grp['_id']='$year_month';
		$grp['rejected_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getEligibilityRejectionsByDurationNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'rejection_category'=>  ['$in'=> ['eligibility']] ];
		$grp=[];
		$grp['_id']='$year_month';
		$grp['rejected_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 
 	}
 	private function _getIncompleteFormRejectionsByDurationNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'rejection_category'=>  ['$in'=> ['data_quality']] ];
		$grp=[];
		$grp['_id']='$year_month';
		$grp['rejected_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
 	}

 	private function _getDbsSamplesByDistrict(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'sample_type_id'=>  ['$in'=> [1]] ];
		$grp=[];
		$grp['_id']='$district_id';
		$grp['dbs_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		
		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getDbsSamplesByFacility(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'sample_type_id'=>  ['$in'=> [1]] ];
		$grp=[];
		$grp['_id']=array('district_id'=>'$district_id','hub_id'=>'$hub_id','facility_id'=>'$facility_id');
		$grp['dbs_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		
		return isset($res['result'])?$res['result']:[];
 	}
 	private function _getDbsSamplesByDurationNumbers(){
 		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'sample_type_id'=>  ['$in'=> [1]] ];
		$grp=[];
		$grp['_id']='$year_month';
		$grp['dbs_samples']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		
		return isset($res['result'])?$res['result']:[];
 	}
	private function _getTotalResultsByDistrict(){
		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'tested'=>  ['$in'=> ['yes']] ];
		$grp=[];
		$grp['_id']='$district_id';
		$grp['total_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
	}
	private function _getTotalResultsByFacility(){
		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'tested'=>  ['$in'=> ['yes']] ];
		$grp=[];
		//$grp['_id']='$facility_id';		
		$grp['_id']=array('district_id'=>'$district_id','hub_id'=>'$hub_id','facility_id'=>'$facility_id');

		$grp['total_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
	}
	private function _getTotalResultsByDurationNumbers(){
		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'tested'=>  ['$in'=> ['yes']] ];
		$grp=[];
		$grp['_id']='$year_month';
		$grp['total_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
	}

	private function _getTotalResultsByRegimenNumbers(){
		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'tested'=>  ['$in'=> ['yes']] ];
		$grp=[];
		$grp['_id']='$regimen';
		$grp['total_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
	}

	private function _getTotalResultsByTimeRegimenNumbers(){
		$extendedConditions=$this->conditions;
		$extendedConditions['$and'][]=[ 'tested'=>  ['$in'=> ['yes']] ];
		$grp=[];
		$grp['_id']='$regimen_time_id';
		$grp['total_results']=['$sum'=>1];
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$extendedConditions],['$group'=>$grp]);

		return isset($res['result'])?$res['result']:[];
	}
	/*private function _durationNumbers($conds){
		$cols=" `year_month`,
				SUM(samples_received-dbs_samples) AS plasma_samples,
				SUM(dbs_samples) AS dbs_samples,
				SUM(suppressed) AS suppressed,
				SUM(valid_results) AS valid_results,
				SUM(sample_quality_rejections) AS sample_quality_rejections,
				SUM(eligibility_rejections) AS eligibility_rejections,
				SUM(incomplete_form_rejections) AS incomplete_form_rejections				
				";
		return SamplesData::getSamplesData($cols,$conds,'year_month');
	}
	*/
	private function _durationNumbers(){
		

	    $match_stage['$match']=$this->conditions;

		$group_stage = array(
			
			'$group' => array(
				'_id' => '$year_month', 
				'samples_received' => array('$sum' => 1 ),
				'valid_results' => array('$sum' => array('$cond'=>array(array('$eq' => array('$sample_result_validity','valid')),1,0))),
				'dbs_samples' => array('$sum' => array('$cond'=>array(array('$eq' => array('$sample_type_id',1)),1,0))),
				'total_results' => array('$sum' => array('$cond'=>array(array('$eq' => array('$tested','yes')),1,0))),
				'suppressed' => array('$sum' => array('$cond'=>array(array('$eq' => array('$suppression_status','yes')),1,0))),
				'rejected_samples' => array(
					'$sum' => array(
						'$cond'=>array(
							array('$ne' => array('$rejection_category','UNKNOWN') 
								),1,0)
						)
					),
				'sample_quality_rejections' => array(
					'$sum' => array(
						'$cond'=>array(
							array('$eq' => array('$rejection_category','sample_quality') 
								),1,0)
						)
					),
				'incomplete_form_rejections' => array(
					'$sum' => array(
						'$cond'=>array(
							array('$eq' => array('$rejection_category','data_quality') 
								),1,0)
						)
					),
				'eligibility_rejections' => array(
					'$sum' => array(
						'$cond'=>array(
							array('$eq' => array('$rejection_category','eligibility') 
								),1,0)
						)
					),
				'unique_patient_count' =>array(
						'$addToSet'=>'$patient_unique_id'
					)
			 ));
		$sort_stage = array(
			'$sort' => array(
				'_id'=>1
				)
			);
		$project_stage['$project']=array(
			'samples_received'=>1,'valid_results'=>1,'dbs_samples'=>1,'total_results'=>1,'suppressed' => 1,'rejected_samples'=>1,
			'patients_received'=>array('$size'=>'$unique_patient_count')
			);
		
		$res=$this->mongo->dashboard_new_backend->aggregate($match_stage,$group_stage,$sort_stage,$project_stage);
		
		
		return isset($res['result'])?$res['result']:[];
	}

	/*private function _regimenGroupNumbers(){
		$grp=[];
		$grp['_id']='$regimen_group_id';
		$grp['samples_received']=['$sum'=>'$samples_received'];
		$grp['suppressed']=['$sum'=>'$suppressed'];
		$grp['total_results']=['$sum'=>'$total_results'];
		$grp['valid_results']=['$sum'=>'$valid_results'];

		$res=$this->mongo->dashboard_data->aggregate(['$match'=>$this->conditions],['$group'=>$grp]);
		return isset($res['result'])?$res['result']:[];
	}*/

	private function _regimenNumbers(){
		$grp=[];
		/*$grp['_id']='$regimen';
		$grp['samples_received']=['$sum'=>'$samples_received'];
		$grp['suppressed']=['$sum'=>'$suppressed'];
		$grp['total_results']=['$sum'=>'$total_results'];
		$grp['valid_results']=['$sum'=>'$valid_results'];

		$res=$this->mongo->dashboard_data_refined->aggregate(['$match'=>$this->conditions],['$group'=>$grp]);
		return isset($res['result'])?$res['result']:[];
		*/
		$grp['_id']='$regimen';
		$grp['samples_received'] = ['$addToSet'=>'$vl_sample_id'];
		
		$projectArray['_id']='$_id';
		$projectArray['samples_received']=['$size'=>'$samples_received'];
	
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$this->conditions],['$group'=>$grp],
			['$project'=>$projectArray]);
	
		
		$samples_received= isset($res['result'])?$res['result']:[];
		$suppressed=$this->_getSuppressedByRegimenNumbers();
		$validResults=$this->_getValidResultsByRegimenNumbers();
		$totalResults=$this->_getTotalResultsByRegimenNumbers();

		$sample_data=[];
		$sample_data['samples_received']=$samples_received;
		$sample_data['suppressed']=$suppressed;
		$sample_data['validResults']=$validResults;
		$sample_data['totalResults']=$totalResults;
		
		return $this->_getProcessedRegimenNumbersAggregates($sample_data);
	}

	private function _regimenTimeNumbers(){
		$grp=[];
		/*
		$grp['_id']='$regimen_time_id';
		$grp['samples_received']=['$sum'=>'$samples_received'];
		$grp['suppressed']=['$sum'=>'$suppressed'];
		$grp['total_results']=['$sum'=>'$total_results'];
		$grp['valid_results']=['$sum'=>'$valid_results'];
		
		$res=$this->mongo->dashboard_data_refined->aggregate(['$match'=>$this->conditions],['$group'=>$grp]);
		return isset($res['result'])?$res['result']:[];
		*/
		$grp['_id']='$regimen_time_id';
		$grp['samples_received'] = ['$addToSet'=>'$vl_sample_id'];
		
		$projectArray['_id']='$_id';
		$projectArray['samples_received']=['$size'=>'$samples_received'];
	
		$res=$this->mongo->dashboard_new_backend->aggregate(['$match'=>$this->conditions],['$group'=>$grp],
			['$project'=>$projectArray]);
	
		
		$samples_received= isset($res['result'])?$res['result']:[];
		$suppressed=$this->_getSuppressedByRegimenTimeNumbers();
		$validResults=$this->_getValidResultsByTimeRegimenNumbers();
		$totalResults=$this->_getTotalResultsByTimeRegimenNumbers();

		$sample_data=[];
		$sample_data['samples_received']=$samples_received;
		$sample_data['suppressed']=$suppressed;
		$sample_data['validResults']=$validResults;
		$sample_data['totalResults']=$totalResults;
		
		return $this->_getProcessedRegimenTimeNumbersAggregates($sample_data);
	}
	
	private function _regimenByLineOfTreatment(){
		$grp=[];
		$grp['_id']='$regimen_line';
		$grp['samples_received']=['$sum'=>'$samples_received'];
		$grp['suppressed']=['$sum'=>'$suppressed'];
		$grp['total_results']=['$sum'=>'$total_results'];
		$grp['valid_results']=['$sum'=>'$valid_results'];

		$res=$this->mongo->dashboard_data_refined->aggregate(['$match'=>$this->conditions],['$group'=>$grp]);
		return isset($res['result'])?$res['result']:[];
	}

	private function _regimenNames(){
		$sql = "SELECT * FROM vl_appendix_regimen";

		
        $regimen_names = null;
        
        try{
        	//ini_set('memory_limit','384M');
        	$regimen_names =  \DB::connection('live_db')->select($sql);
 
        }catch(\Illuminate\Database\QueryException $e){
        	Log::info("---error fetching all regimen names from mysql---");
        	Log::error($e->getMessage());
        	
        }
		
		return $regimen_names;
	}
	private function median($arr){
		sort($arr);
		$quantity=count($arr);
		$half_quantity=(int)($quantity/2);
		$ret=0;
		if($quantity%2==0){
			 $ret=($arr[($half_quantity-1)]+$arr[$half_quantity])/2;
		}else{
			$ret=$arr[$half_quantity];
		}
		return $ret;
	}


	private function totalSums($totals){
		$ret=0;
		foreach ($totals as $lvl_id => $reg_data) {
			foreach ($reg_data as $reg_id => $dist_data) {
				$ret+=array_sum($dist_data);				
			}
		}
		return $ret;
	}


	private function getTotalsByMonth($arr){
		$ret=$this->months;
		foreach ($arr as $lvl_id => $reg_data) {
			foreach ($reg_data as $reg_id => $dist_data) {
				foreach ($dist_data as $month_data) {
					foreach ($month_data as $mth => $val) $ret[$mth]+=$val;			
				}								
			}
		}
		return $ret;
	}

	private function getAverageRate($arr_up,$arr_down){
		$ret=0;
		$ttl_up=0;
		$ttl_down=0;
		foreach ($arr_up as $lvl_id => $reg_data) {
			foreach ($reg_data as $reg_id => $dist_data) {
				foreach ($dist_data as $dist_id => $month_data) {
					$ttl_up+=array_sum($month_data);
					$ttl_down+=array_sum($arr_down[$lvl_id][$reg_id][$dist_id]);
				}
			}
		}
		$ret=$ttl_down>0?($ttl_up/$ttl_down)*100:0;
		$ret=round($ret,1);
		return $ret;
	}

	private function getAverageRatesByMonth($arr_up,$arr_down){
		$up_res=$this->months;
		$down_res=$this->months;
		$ret=$this->months;
		foreach ($arr_up as $lvl_id => $reg_data) {
			foreach ($reg_data as $reg_id => $dist_data) {
				foreach ($dist_data as $dist_id => $month_data) {
					foreach ($month_data as $mth => $val){
						$up_res[$mth]+=$val;
						$down_res[$mth]+=$arr_down[$lvl_id][$reg_id][$dist_id][$mth];
					}		
				}								
			}
		}

		foreach ($up_res as $m => $v) {			
			$ret_val=$down_res[$m]>0?($up_res[$m]/$down_res[$m])*100:0;
			$ret[$m]=round($ret_val,1);
		}
		return $ret;
	}



	/*

	I would say that he is a ‘master’, if it were not for my belief that no one ‘masters’ anything, that each finds or makes his candle, then tries to see by the guttering light. Mum has made a good candle. And Mum has good eyes.

	Gwendolyn Brooks


	Whether you are witness or executioner, the victim whose humanity you can never erase
	knows with clarity, more solid than granite that no matter which side you are on,
	any day or night, an injury to one remains an injury to all
	some where on this coninent, the voice of the ancient warns, that those who shit on the road, will find flies on their way back..

	*/

}