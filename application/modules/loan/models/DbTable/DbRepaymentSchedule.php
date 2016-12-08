<?php

class Loan_Model_DbTable_DbRepaymentSchedule extends Zend_Db_Table_Abstract
{

    protected $_name = 'ln_loan_group';
    public function getUserId(){
    	$session_user=new Zend_Session_Namespace('auth');
    	return $session_user->user_id;
    	 
    }
    
    function getAllReschedule(){
    	$db = $this->getAdapter();
    	$sql = 'select * from ln_reschedule ';
    	return $db->fetchAll($sql);
    }
    
    function calCulateIRR($total_loan_amount,$loan_amount,$term,$curr){
    	$array =array();//array(-1000,107,103,103,103,103,103,103,103,103,103,103,103);
    	for($j=0; $j<= $term;$j++){
    		if($j==0){
    			$array[]=-$loan_amount;
    		}elseif($j==1){
    			$fixed_principal = round($total_loan_amount/$term,0, PHP_ROUND_HALF_DOWN);
    			$post_fiexed = $total_loan_amount/$term-$fixed_principal;
    			$total_add_first = $this->round_up_currency($curr,$post_fiexed*$term);
    
    			$array[]=($total_add_first+$fixed_principal);
    		}else{
    			$array[]=round($total_loan_amount/$term,0, PHP_ROUND_HALF_DOWN);
    		}
    
    	}
    	$array = array_values($array);
    	return Loan_Model_DbTable_DbIRRFunction::IRR($array);
    }
    function round_up($value, $places)
    {
    	$mult = pow(10, abs($places));
    	return $places < 0 ?
    	ceil($value / $mult) * $mult :
    	ceil($value * $mult) / $mult;
    }
    function round_up_currency($curr_id, $value,$places=-2){
    	//     	return (($curr_id==1)? $this->round_up($value, $places):$value);
    	if ($curr_id==1){
    		return $this->round_up($value, $places);
    	}
    	else{
    		return round($value,2);
    	}
    }
    
    function getSaleInfo($sale_id){
    	$db = $this->getAdapter();
    	$sql="select * from ln_sale where id = $sale_id";
    	return $db->fetchRow($sql);
    }
    
    public function addRepayMentSchedule($data){
    $db = $this->getAdapter();
    	$db->beginTransaction();
    	
    	try{
    		$dbtable = new Application_Model_DbTable_DbGlobal();
    		
    		$sale = $this->getSaleInfo($data['loan_number']);
    		
    		$array = array(
    				'branch_id'				=>$data['branch_id'],
    				'sale_id'				=>$data['loan_number'],
    				'client_id'				=>$data['customer_code'],
    				'reschedule_date'		=>$data['date_buy'],
    				'receipt_no'			=>$data['receipt'],
    				
    				'amount_before'			=>$data['total_sold'],
    				'paid_before'			=>$data['paid_before'],
    				'balance_before'		=>$data['balance_before'],
    				'interestrate_before'	=>$sale['interest_rate'],
    				'payment_method_before'	=>$sale['payment_method'],
    				'period_before'			=>$sale['total_duration'],
    				'cal_startdate'			=>$sale['startcal_date'],
    				'first_paymentbefore'	=>$sale['first_payment'],
    				'end_datebefore'		=>$sale['end_line'],
    				
    				'discount_after'		=>$data['discount'],
    				'total_payment'			=>$data['sold_price'],
    				'other_fee'				=>$data['other_fee'],
    				'payment_method_after'	=>$data['schedule_opt'],
    				'paid_amount_after'		=>$data['deposit'],
    				'balance_after'			=>$data['balance'],
    				
    				'period_after'			=>$data['period'],
    				'interest_after'		=>$data['interest_rate'],			
    				'fixed_payment_after'	=>$data['fixed_payment'],
    				
    				'start_date_after'		=>$data['release_date'],
    				'first_payment_after'	=>$data['first_payment'],
    				'end_date_after'		=>$data['date_line'],
    				
    				'status'				=>1,
    				'note'					=>$data['note'],
    				'user_id'				=>$this->getUserId(),
    				
    				);
    		$this->_name='ln_reschedule';
    		$this->insert($array);
    		
    		if($data['deposit']>0){
    			
    			if($data['schedule_opt']==2){
    				$is_complete = 1;
    			}else{
    				$is_complete = 0;
    			}
    			if($data['deposit']>0){
		    		$this->_name='ln_client_receipt_money';
		    		$array = array(
		    				'branch_id'				=>$data['branch_id'],
		    				'client_id'				=>$data['customer_code'],
		    				'receipt_no'			=>$data['receipt'],
		    				'date_pay'				=>$data['date_buy'],
		    				'land_id'				=>$data['loan_number'],
		    				'date_input'			=>date('Y-m-d'),
		    				'outstanding'			=>$data['sold_price'],
		    				'principal_amount'		=>$data['balance'],
		    				
		    				'total_principal_permonth'		=>$data['deposit'],
		    				'total_principal_permonthpaid'	=>$data['deposit'],
		    				'total_interest_permonth'		=>0,
		    				'total_interest_permonthpaid'	=>0,
		    				'penalize_amount'				=>0,
		    				'penalize_amountpaid'			=>0,
		    				
		    				'service_charge'				=>$data['other_fee'],
		    				'service_chargepaid'			=>$data['other_fee'],
		    				'total_payment'					=>$data['sold_price'],
		    				'amount_payment'				=>$data['deposit'],
		    				'recieve_amount'				=>$data['deposit'],
		    				'balance'						=>$data['balance'],
		    				'payment_option'				=>($data['schedule_opt']==2)?4:1,
		    				'is_completed'					=>$is_complete,
		    				'status'						=>1,
		    				'note'							=>$data['note'],
		    				'user_id'						=>$this->getUserId(),
		    				);
		    		$crm_id = $this->insert($array);
		    		
		    		$this->_name='ln_client_receipt_money_detail';
		    		$array = array(
		    				'crm_id'				=>$crm_id,
		    				'land_id'				=>$data['loan_number'],
		    				'date_payment'			=>$data['date_buy'],
		    				
		    				'capital'				=>$data['sold_price'],
		    				'remain_capital'		=>$data['balance'],
		    				'principal_permonth'	=>$data['deposit'],
		    				'total_interest'		=>0,
		    				 
		    				'total_payment'			=>$data['sold_price'],
		    				'total_recieve'			=>$data['deposit'],
		    				'service_charge'		=>$data['other_fee'],
		    				'penelize_amount'		=>0,
		    				
		    				'is_completed'			=>$is_complete,
		    				'status'				=>1,
		    		);
		    		$this->insert($array);
    			}
    		}
    		
//     		$loan_number = $dbtable->getLoanNumber($data);
//     		$receipt = $dbtable->getReceiptByBranch($data);
    			   $arr = array(
//     				'branch_id'=>$data['branch_id'],
//     			   	'receipt_no'=>$receipt,
//     				'sale_number'=>$loan_number,
//     			   	'house_id'=>$data["land_code"],
//     				'client_id'=>$data['member'],
//     				'price_before'=>$data['total_sold'],
//     			   	'payment_id'=>$data["schedule_opt"],

//     				'discount_amount'=>$data['discount'],
//     				'price_sold'=>$data['sold_price'],
//     				'other_fee'=>$data['other_fee'],
//     				'paid_amount'=>$data['deposit'],
//     				'balance'=>$data['balance'],
//     				'buy_date'=>$data['date_buy'],
//     				'end_line'=>$data['date_line'],
//     				'interest_rate'=>$data['interest_rate'],
//     				'total_duration'=>$data['period'],
//     			   	'startcal_date'=>$data['release_date'],
//     				'first_payment'=>$data['first_payment'],
//     			   	'validate_date'=>$data['first_payment'],
//     				'payment_method'=>1,//$data['loan_type'],
//     				'note'=>$data['note'],
    				'is_reschedule'=>1,
    			   	'create_date'=>date("Y-m-d"),
//     				'staff_id'=>$data['staff_id'],
//     				'comission'=>$data['commission'],
    				
//     				'user_id'=>$this->getUserId()
    				);
    			   $this->_name='ln_sale';
    	    $where = "id =".$data["loan_number"];
    		$id = $this->update($arr, $where);//add group loan
    		unset($datagroup);
    		
//     		$dbg = new Application_Model_DbTable_DbGlobal();
//     		$reciept_no=$dbg->getReceiptnumber($data["branch_id"]);
    		
//     		$arr_client_pay = array(
//     				'branch_id'						=>	$data["branch_id"],
//     				'receipt_no'					=>	$reciept_no,
//     				'date_pay'					    =>	$data['date_buy'],
//     				'date_input'					=>	date("Y-m-d"),
//     				'client_id'                     =>	$data['member'],
//     				'land_id'						=>	$data['land_code'],
//     				'outstanding'                   =>	$data['sold_price'],//ប្រាក់ដើមមុនបង់
//     				'total_principal_permonth'		=>	$data["sold_price"],//ប្រាក់ដើមត្រូវបង់
//     				'total_interest_permonth'		=>	0,
//     				'penalize_amount'				=>	0,
//     				'service_charge'				=>	$data["other_fee"],
//     				'principal_amount'				=>	$data['balance'],//ប្រាក់ដើមនៅសល់បន្ទប់ពីបង់
//     				'total_principal_permonthpaid'	=>	sold_price,//ok ប្រាក់ដើមបានបង
//     				'total_interest_permonthpaid'	=>	0,//ok ការប្រាក់បានបង
//     				'penalize_amountpaid'			=>	0,// ok បានបង
//     				'service_chargepaid'			=>	$data["other_fee"],// okបានបង
//     				'balance'						=>	$data["balance"],
//     				'total_payment'					=>	$data["sold_price"],//ប្រាក់ត្រូវបង់ok
//     				'recieve_amount'				=>	$data["deposit"],//ok
//     				'amount_payment'				=>	$data["deposit"],//brak ban borng
//     				'return_amount'					=>	0,//ok
// //     				'note'							=>	$data['note'],
// //     				'cheque'						=>	$data['cheque'],
//     				'user_id'						=>	$this->getUserId(),
//     				'payment_option'				=>	1,
//     				'status'						=>	1,
//     				'is_completed'					=>	0//if full completed=1
//     		);
//     		$this->_name = "ln_client_receipt_money";
//     		$client_pay = $this->insert($arr_client_pay);
    		
    		$where = " is_completed=0 AND sale_id=".$data['loan_number'];
    		$this->_name="ln_saleschedule";
    		$this->delete($where);
    		$id  =$data['loan_number'];
    		
    		$total_day=0;
    		$old_remain_principal = 0;
    		$old_pri_permonth = 0;
    		$old_interest_paymonth = 0;
    		$old_amount_day = 0;
    		$cum_interest=0;
    		$amount_collect = 1;
    		$remain_principal = $data['balance'];
    		$next_payment = $data['first_payment'];
    		$from_date =  $data['release_date'];
    		$curr_type = 2;//$data['currency_type'];
    		$loop_payment = $data['period'];
    		$borrow_term = $data['period'];
    		$payment_method = $data["schedule_opt"];
    		$pri_permonth=0;
    		
    		$str_next = '+1 month';
//     		$str_next = $dbtable->getNextDateById($data['collect_termtype'],$data['amount_collect']);//for next,day,week,month;
    		for($i=1;$i<=$loop_payment;$i++){
    			if($payment_method==1){
    				
    			}elseif($payment_method==2){
    				
    			}elseif($payment_method==3){//pay by times//check date payment
    				if($i!=1){
    					$remain_principal = $remain_principal-$pri_permonth;//OSប្រាក់ដើមគ្រា
    					$start_date = $next_payment;
    					$next_payment = $dbtable->getNextPayment($str_next, $next_payment, 1,3,$data['first_payment']);
    				}else{
    					$next_payment = $data['first_payment'];
    					$next_payment = $dbtable->checkFirstHoliday($next_payment,3);//normal day
    				}
    				$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    				$total_day = $amount_day;
    				$interest_paymonth = 0;
    				$pri_permonth = round($data['balance']/$borrow_term,2);
    				if($i==$loop_payment){//for end of record only
    					$pri_permonth = $remain_principal;
    				}
    			}elseif($payment_method==4){
			    		if($i!=1){
			    			$remain_principal = $remain_principal-$pri_permonth;//OSប្រាក់ដើមគ្រា
			    			$start_date = $next_payment;
			    			$next_payment = $dbtable->getNextPayment($str_next, $next_payment, 1,3,$data['first_payment']);
			    				
			    		}else{
			    			$next_payment = $data['first_payment'];
			    			$next_payment = $dbtable->checkFirstHoliday($next_payment,3);//normal day
			    		}
			    		$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
			    		$total_day = $amount_day;
			    		$interest_paymonth = $remain_principal*(($data['interest_rate']/12)/100);//fixed 30day
			    		$interest_paymonth = $this->round_up_currency($curr_type, $interest_paymonth);
// 			    		if($data['collect_termtype']==1){
// 			    			$amount_day= $total_day ;
// 			    		}
			    		$pri_permonth = $data['fixed_payment']-$interest_paymonth;
			    		if($i==$loop_payment){//for end of record only
			    			$pri_permonth = $remain_principal;
			    		}
    			   }
    			   if($payment_method==3 OR $payment_method==4){
			    		$old_remain_principal =$old_remain_principal+$remain_principal;
			    		$old_pri_permonth = $old_pri_permonth+$pri_permonth;
			    		$old_interest_paymonth = $this->round_up_currency($curr_type,($old_interest_paymonth+$interest_paymonth));
			    		$cum_interest = $cum_interest+$old_interest_paymonth;
			    		$old_amount_day =$old_amount_day+ $amount_day;
			    		          $this->_name="ln_saleschedule";
			    			        $datapayment = array(
			    			        	'branch_id'=>$data['branch_id'],
			    			        	'sale_id'=>$id,//good
			    			        	'begining_balance'=> $old_remain_principal,//good
			    			        	'begining_balance_after'=> $old_remain_principal,//good
			    			        	'principal_permonth'=> $old_pri_permonth,//good
			    			        	'principal_permonthafter'=>$old_pri_permonth,//good
			    			        	'total_interest'=>$old_interest_paymonth,//good
			    			        	'total_interest_after'=>$old_interest_paymonth,//good
			    			        	'total_payment'=>$old_pri_permonth+$old_interest_paymonth,//good
			    			        	'total_payment_after'=>$old_pri_permonth+$old_interest_paymonth,//good
			    			        	'ending_balance'=>$old_remain_principal-$old_pri_permonth,
			    			        	'cum_interest'=>$cum_interest,
			    			        	'amount_day'=>$old_amount_day,
			    			        	'is_completed'=>0,
			    			        	'date_payment'=>$next_payment,
			    			        	'is_reschedule'=>1,
	// 		    			        	'payment_option'=>$cum_interest,
	// 		    			        	'penelize'=>,
	// 		    			        	'service_charge'=>,
	// 		    			        	'status'=>,
			    			        );
		            		 
			    		$this->insert($datapayment);
			    		$old_remain_principal = 0;
			    		$old_pri_permonth = 0;
			    		$old_interest_paymonth = 0;
			    		$old_amount_day = 0;
			    		$from_date=$next_payment;
    			   }
    		}
	            $db->commit();
	        return 1;
	        }catch (Exception $e){
	            	$db->rollBack();
	            	echo $e->getMessage();exit();
	            	Application_Form_FrmMessage::message("INSERT_FAIL");
	            	Application_Model_DbTable_DbUserLog::writeMessageError($e->getMessage());
	        }

	
    }
    public function getNextDateById($pay_term){
    	if($pay_term==3){
    		$str_next = 'next month';
    	}elseif($pay_term==2){
    		$str_next = 'next week';
    	}else{
    		$str_next = 'next day';
    	}
    	return $str_next;
    }
    public function getSubDaysByPaymentTerm($pay_term){
    	if($pay_term==3){
    		$amount_days =30;
    	}elseif($pay_term==2){
    		$amount_days =7;
    	}else{
    		$amount_days =1;
    	}
    	return $amount_days;
    	
    }
    public function CountDayByDate($start,$end){
    	$db = new Application_Model_DbTable_DbGlobal();
    	return ($db->countDaysByDate($start,$end));
    }
    public function CalculateByMethod($method_type){
    	
    }
    public function getLoanInfo($id){
    	$db=$this->getAdapter();
    $sql=" SELECT 
        (SELECT lf.total_principal FROM `ln_loanmember_funddetail` AS lf WHERE lf. member_id= l.member_id AND status=1 AND lf.is_completed=0 LIMIT 1)  AS total_principal
    	,l.currency_type ,l.interest_rate , l.loan_number,l.payment_method, l.group_id,
    	(SELECT co_id FROM `ln_loan_group` WHERE g_id  = l.group_id LIMIT 1 ) AS co_id , 
    	(SELECT zone_id FROM `ln_loan_group` WHERE g_id  = l.group_id LIMIT 1) AS zone_id,
    	(SELECT level FROM `ln_loan_group` WHERE g_id  = l.group_id LIMIT 1) AS level  
    	 FROM `ln_loan_member` AS l WHERE l.client_id=$id AND status=1 AND l.is_completed=0 ";
    	return $db->fetchRow($sql);
    }
    public function getLoanInfoById($id){
    	$db=$this->getAdapter();
    	$sql=" SELECT
    	(SELECT SUM(total_principal_permonthpaid) FROM `ln_client_receipt_money` WHERE land_id=$id AND status=1 LIMIT 1) AS total_principal,
    	s.* FROM `ln_sale` AS s WHERE s.id=$id AND status=1 AND s.is_completed=0 ";
    	return $db->fetchRow($sql);
    }
    
    public function getClientByTypes($type){
    	$this->_name='ln_loan_member';
    	$sql ="SELECT
    	(SELECT c.client_number FROM `ln_client` AS c WHERE lm.client_id=c.client_id LIMIT 1 )AS client_number,
    	(SELECT c.name_en FROM `ln_client` AS c WHERE lm.client_id=c.client_id LIMIT 1 )AS name_en,
    	lm.client_id ,lm.loan_number
    	FROM `ln_loan_member` AS lm WHERE is_completed = 0 AND status=1 ";
    	$db = $this->getAdapter();
    	$rows = $db->fetchAll($sql);
    	$options=array(0=>'------Select------');
    	if(!empty($rows))foreach($rows AS $row){
    		if($type==1){
    			$lable = $row['client_number'];
    		}elseif($type==2){
    			$lable = $row['name_en'];
    		}
    		else{$lable = $row['loan_number'];
    		}
    		$options[$row['client_id']]=$lable;
    	}
    	return $options;
    }
    public function updateRepaymentSchedule($data){
    	$db = $this->getAdapter();
    	$db->beginTransaction();
    	try{
    		if($data['status_using']==0){
    			$arr_update = array(
    					'status'=>0
    			);
    			$where = ' status = 1 AND g_id = '.$data['id'];
    			$this->update($arr_update, $where);
    			 
    			$this->_name = 'ln_loan_member';
    			$where = ' is_completed = 0 AND status = 1 AND group_id = '.$data['id'].' AND client_id = '.$data["member"];
    			$this->update($arr_update, $where);
    			 
    			 
    			$rows= $this->getAllMemberLoanById($data['id']);
    			$s_where = array();
    			$where = '';
    			foreach ($rows as $id => $row){
    				$s_where[] = "`member_id` = ".$row['member_id'];
    			}
    			$where .= implode(' OR ',$s_where);
    			$where.=" AND status=1 AND is_completed=0 ";
    			 
    			$arr = array(
    					'status'=>0
    			);
    			$this->_name='ln_loanmember_funddetail';
    			 
    			$db->commit();
    			return 1;
    		}
    	
    		$datagroup = array(
    				'group_id'=>$data['member'],
    				'co_id'=>$data['co_id'],
    				'zone_id'=>$data['zone'],
    				'level'=>$data['level'],
    				'date_release'=>$data['release_date'],
    				'date_line'=>$data['date_line'],
    				'create_date'=>date("Y-m-d"),
    				'branch_id'=>$data['branch_id'],
    				'total_duration'=>$data['period'],
    				'first_payment'=>$data['first_payment'],
    				'time_collect'=>$data['time'],
    				'pay_term'=>$data['pay_every'],
    				'payment_method'=>$data['repayment_method'],
    				'holiday'=>$data['every_payamount'],
    				'is_renew'=>0,
    				'loan_type'=>1,
    				'collect_typeterm'=>$data['collect_termtype'],
    				'user_id'=>$this->getUserId()
    		);
    		$g_id = $data['id'];
    		$where = $db->quoteInto('g_id=?', $g_id);
    		$this->update($datagroup, $where);
    		unset($datagroup);
    	
    		$datamember = array(
    				'group_id'=>$g_id,
//     				'loan_number'=>$data['loan_code'],
    				'client_id'=>$data['member'],
    				'payment_method'=>$data['repayment_method'],
    				'currency_type'=>$data['currency_type'],
    				'total_capital'=>$data['total_amount'],//$data[''],
    				'other_fee'=>$data['other_fee'],
    				'admin_fee'=>$data['loan_fee'],
    				'interest_rate'=>$data['interest_rate'],
    				'status'=>1,
    				'is_completed'=>0,
    				'branch_id'=>$data['branch_id'],
    				//'pay_before'=>$data['pay_before'],
    				'pay_after'=>$data['pay_late'],
    				'graice_period'=>$data['graice_pariod'],
    				'amount_collect_principal'=>$data['amount_collect'],
    				'collect_typeterm'=>$data['collect_termtype'],
    				'semi'=>$data['amount_collect_pricipal']
    		);
    		$this->_name='ln_loan_member';
    	
    		$where = $db->quoteInto('group_id=?', $data['id']);
    		$this->update($datamember, $where);
    		unset($datamember);
    	
    		$rows= $this->getAllMemberLoanById($data['id']);
    		$s_where = array();
    		$where = '';
    		foreach ($rows as $id => $row){
    			$s_where[] = "`member_id` = ".$row['member_id'];
    		}
    		$where .= implode(' OR ',$s_where);
    		$where.=" AND status=1 AND is_completed=0 ";
    	
    		$arr = array(
    				'status'=>0
    		);
    		$this->_name='ln_loanmember_funddetail';
    		$this->delete($where);
//     		$this->update($arr, $where);
    		 
    		//     		$arr =array(
    		//     				'branch_id'=>$data['branch_id'],
    		//     				'receipt_number'=>$data['loan_code'],
    		//     				'date'=>$data['release_date'],
    		//     				'create_date'=>date('Y-m-d'),
    		//     				'note'=>'from loan disburse',
    		//     				'from_location'=>1,
    		//     				'user_id'=>$this->getUserId(),
    		//     				'balance'=>$data['total_amount'],
    		//     				'loan_fee'=>$data['loan_fee'],
    		//     				'client_id'=>$data['member'],
    		//     				'currency_type'=>$data['currency_type']
    			
    		//     		);
    		//     		//     			$this->insert($arr);
    		//     		//     			unset($arr);
    		//     		$db_j = new Accounting_Model_DbTable_DbJournal();
    		//     		$jur_id = $db_j->addTransactionJournal($arr);
    		 
    		$remain_principal = $data['total_amount'];
    		$next_payment = $data['first_payment'];
    		$start_date = $data['release_date'];//loan release;
    		$from_date =  $data['release_date'];
    		 
    		$old_remain_principal = 0;
    		$old_pri_permonth = 0;
    		$old_interest_paymonth = 0;
    		$old_amount_day = 0;
    		$amount_collect = 1;
    		$ispay_principal=2;//for payment type = 5;
    		$is_subremain = 2;
    		$curr_type = $data['currency_type'];
    	
    		//for IRR method
    		if($data['repayment_method']==6){
	    		$term_install = $data['period'];
	    		$loan_amount = $data['total_amount'];
	    		$total_loan_amount = $loan_amount+($loan_amount*$data['interest_rate']/100*$term_install);
	    		$irr_interest = $this->calCulateIRR($total_loan_amount,$loan_amount,$term_install,$curr_type);
    		//end of IRR
    		}
    		 
    		$this->_name='ln_loanmember_funddetail';
    		$dbtable = new Application_Model_DbTable_DbGlobal();
    		$borrow_term = $dbtable->getSubDaysByPaymentTerm($data['pay_every'],null);//return amount day for payterm
    		$amount_borrow_term = $borrow_term*$data['period'];//amount of borrow
    		 
    		$fund_term = $dbtable->getSubDaysByPaymentTerm($data['collect_termtype'],null);//return amount day for payterm
    		$amount_fund_term = $fund_term*$data['amount_collect'];
    		 
    		$loop_payment = ($amount_borrow_term)/($amount_fund_term);
    		$payment_method = $data['repayment_method'];
    		//     			for($i=1;$i<=($data['period']/$data['amount_collect']);$i++){
    		for($i=1;$i<=$loop_payment;$i++){
    			$amount_collect = $data['amount_collect'];
    			$day_perterm = $dbtable->getSubDaysByPaymentTerm($data['collect_termtype'],$amount_collect);//return amount day for payterm
    			 
    			//$day_perterm = $dbtable->getSubDaysByPaymentTerm($data['pay_every'],$amount_collect);//return amount day for payterm
    			$str_next = $dbtable->getNextDateById($data['collect_termtype'],$data['amount_collect']);//for next,day,week,month;
    			 
    			if($payment_method==1){//decline//completed
    				//     					$pri_permonth = ($data['total_amount']/($data['period']-$data['graice_pariod'])*$amount_collect);
    				$pri_permonth = $data['total_amount']/(($amount_borrow_term-($data['graice_pariod']*$borrow_term))/$amount_fund_term);
    				$pri_permonth = $this->round_up_currency($curr_type, $pri_permonth);
    				if($i*$amount_collect<=$data['graice_pariod']){//check here//for graice period
    					$pri_permonth = 0;
    				}
    				if($i!=1){
    					if($data['graice_pariod']!=0){//if collect =1 not other check
    						if($i*$amount_collect>$data['graice_pariod']+$amount_collect){//not wright
    							$remain_principal = $remain_principal-$pri_permonth;
    						}else{
    	
    						}
    					}else{
    						$remain_principal = $remain_principal-$pri_permonth;//OSប្រាក់ដើមគ្រា}
    					}
    					if($i==$loop_payment){//check condition here//for end of record only
    						//     							echo $remain_principal;
    						$pri_permonth = $data['total_amount']-$pri_permonth*($i-(($data['graice_pariod']/$amount_collect)+1));//code error here
    							
    					}
    					$start_date = $next_payment;
    					$next_payment = $dbtable->getNextPayment($str_next, $next_payment, $data['amount_collect'],$data['every_payamount'],$data['first_payment']);
    					$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    	
    					$interest_paymonth = $remain_principal*($data['interest_rate']/100/$borrow_term)*$amount_day;
    					//     						$interest_paymonth = $remain_principal*((($amount_fund_term*$data['interest_rate'])/$borrow_term)/100)*($amount_day/$day_perterm);
    	
    				}else{
    					$next_payment = $data['first_payment'];
    					$next_payment = $dbtable->checkFirstHoliday($next_payment,$data['every_payamount']);
    					$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    					$interest_paymonth = $remain_principal*($data['interest_rate']/100/$borrow_term)*$amount_day;
    				}
    			}elseif($payment_method==2){//baloon
    				$pri_permonth=0;
    				if(($i*$amount_fund_term)==$amount_borrow_term){//check here
    					$pri_permonth = ($curr_type==1)?round($data['total_amount'],-2):$data['total_amount'];
    					$pri_permonth =$this->round_up_currency($curr_type, $pri_permonth);
    					$remain_principal = $pri_permonth;//$remain_principal-$pri_permonth;//OSប្រាក់ដើមគ្រា
    				}
    				if($i!=1){
    					$start_date = $next_payment;
    					$next_payment = $dbtable->getNextPayment($str_next, $next_payment, $data['amount_collect'],$data['every_payamount'],$data['first_payment']);
    					$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    				}else{
    					$next_payment = $data['first_payment'];
    					$next_payment = $dbtable->checkFirstHoliday($next_payment,$data['every_payamount']);
    					$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    				}
    				$interest_paymonth = $data['total_amount']*($data['interest_rate']/100/$borrow_term)*$amount_day;
    				//     					$interest_paymonth = $data['total_amount']*((($amount_fund_term*$data['interest_rate'])/$borrow_term)/100)*($amount_day/$day_perterm);
    					
    			}elseif($payment_method==3){//fixed rate
    				$pri_permonth = ($data['total_amount']/($amount_borrow_term/$amount_fund_term));
    				$pri_permonth =$this->round_up_currency($curr_type,$pri_permonth);
    				if($i!=1){
    					$remain_principal = $remain_principal-$pri_permonth;//OSប្រាក់ដើមគ្រា
    					if($i==$loop_payment){//for end of record only
    						$pri_permonth = $remain_principal;
    					}
    					$start_date = $next_payment;
    					$next_payment = $dbtable->getNextPayment($str_next, $next_payment, $data['amount_collect'],$data['every_payamount'],$data['first_payment']);
    				}else{
    					$next_payment = $data['first_payment'];
    				}
    				$amount_day = $dbtable->CountDayByDate($start_date,$next_payment);
    				
    				$interest_paymonth = $data['total_amount']*($data['interest_rate']/100/$borrow_term)*$amount_day;
    				//     					    $interest_paymonth = $data['total_amount']*((($amount_fund_term*$data['interest_rate'])/$borrow_term)/100)*($day_perterm/$day_perterm);
    	
    			}elseif($payment_method==4){//fixed payment full last period yes
    				if($i!=1){
    					$remain_principal = $remain_principal-$pri_permonth;//OSប្រាក់ដើមគ្រា
    					$start_date = $next_payment;
    					$next_payment = $dbtable->getNextPayment($str_next, $next_payment, $data['amount_collect'],$data['every_payamount'],$data['first_payment']);
    					//     						$interest_paymonth = $data['total_amount']*($data['interest_rate']/100)*($amount_day/$day_perterm);
    						
    				}else{
    					$next_payment = $data['first_payment'];
    					$next_payment = $dbtable->checkFirstHoliday($next_payment,$data['every_payamount']);
    				}
    				$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    				$interest_paymonth = $remain_principal*($data['interest_rate']/100/$borrow_term)*$amount_day;
    				$interest_paymonth = $this->round_up_currency($curr_type, $interest_paymonth);
    				$pri_permonth = $data['amount_collect_pricipal']-$interest_paymonth;
    				if($i==$loop_payment){//for end of record only
    					$pri_permonth = $remain_principal;
    				}
    			}elseif($payment_method==5){//semi baloon//ok
    				if($i!=1){
    					$ispay_principal++;
    					$is_subremain++;
    					$pri_permonth=0;
    					if(($is_subremain-1)==$data['amount_collect_pricipal']){
    						$pri_permonth = ($data['total_amount']/$data['period'])*$data['amount_collect_pricipal'];
    						$pri_permonth = $this->round_up_currency($curr_type, $pri_permonth);
    						$is_subremain=1;
    					}
    					if(($ispay_principal-1)==$data['amount_collect_pricipal']+1){
    						$remain_principal = $remain_principal-($data['total_amount']/$data['period'])*$data['amount_collect_pricipal'];
    						$ispay_principal=2;
    					}
    					if($i==$loop_payment){//check condition here//for end of record only
    						$pri_permonth = $remain_principal;
    					}
    					$start_date = $next_payment;
    					$next_payment = $dbtable->getNextPayment($str_next, $next_payment, $data['amount_collect'],$data['every_payamount'],$data['first_payment']);
    					$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    					$interest_paymonth = $remain_principal*($data['interest_rate']/100/$borrow_term)*$amount_day;
    					//     							$interest_paymonth = ($remain_principal*((($amount_fund_term*$data['interest_rate'])/$borrow_term)/100)*($day_perterm/$day_perterm));
    				}else{
    					$pri_permonth = 0;//check if get pri first too much change;
    					$next_payment = $data['first_payment'];
    					$next_payment = $dbtable->checkFirstHoliday($next_payment,$data['every_payamount']);
    					$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    					$interest_paymonth = $data['total_amount']*($data['interest_rate']/100/$borrow_term)*$amount_day;
    					//     						$interest_paymonth = ($data['total_amount']*((($amount_fund_term*$data['interest_rate'])/$borrow_term)/100)*($day_perterm/$day_perterm));
    				}
    			}else{//    fixed payment with fixed rate
    				if($i!=1){
    					$remain_principal = $remain_principal-$pri_permonth;
    					$next_payment = $dbtable->getNextPayment($str_next, $next_payment, $data['amount_collect'],$data['every_payamount'],$data['first_payment']);
    					$amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    					$interest_paymonth = $this->round_up_currency($curr_type,$remain_principal*$irr_interest);
    					$fixed_principal = round($total_loan_amount/$term_install,0, PHP_ROUND_HALF_DOWN);
    					$pri_permonth = $fixed_principal-$interest_paymonth;
    					if($i==$loop_payment){//for end of record only
    						$pri_permonth = $remain_principal;
    							
    						$fixed_principal = round($total_loan_amount/$term_install,0, PHP_ROUND_HALF_DOWN);
    						$fixed_principal= $this->round_up_currency($curr_type,$fixed_principal);
    						$interest_paymonth = $fixed_principal-$remain_principal;
    					}
    						
    				}else{
    					$fixed_principal = round($total_loan_amount/$term_install,0, PHP_ROUND_HALF_DOWN);//fixed
    					$fixed_principal= $this->round_up_currency($curr_type,$fixed_principal);
    					$post_fiexed = $total_loan_amount/$term_install-$fixed_principal;
    					$total_payment_first = $this->round_up_currency($curr_type,$post_fiexed*$term_install);
    					$pri_permonth = $fixed_principal+$total_payment_first;
    					
    					$next_payment = $dbtable->checkFirstHoliday($next_payment,$data['every_payamount']);
    						
    					$amount_day = $dbtable->CountDayByDate($start_date,$next_payment);
    					$interest_paymonth = $this->round_up_currency($curr_type,$loan_amount*($irr_interest));
    					$pri_permonth = ($fixed_principal+$total_payment_first)-$interest_paymonth;
    				}
    			}
    			$old_remain_principal =$old_remain_principal+$remain_principal;
    			$old_pri_permonth = $old_pri_permonth+$pri_permonth;
    			$old_interest_paymonth = $this->round_up_currency($curr_type,($old_interest_paymonth+$interest_paymonth));
    			$old_amount_day =$old_amount_day+ $amount_day;
    			 
    			 
    			if($data['amount_collect']==$amount_collect){
    					
    				$datapayment = array(
    						'member_id'=>$g_id,
    						'total_principal'=>$remain_principal,//good
    						'principal_permonth'=> $old_pri_permonth,//good
    						'principle_after'=> $old_pri_permonth,//good
    						'total_interest'=>$old_interest_paymonth,//good
    						'total_interest_after'=>$old_interest_paymonth,//good
    						'total_payment'=>$old_pri_permonth+$old_interest_paymonth,//good
    						'total_payment_after'=>$old_pri_permonth+$old_interest_paymonth,//good
    						'date_payment'=>$next_payment,//good
    						'is_completed'=>0,
    						'branch_id'=>$data['branch_id'],
    						'status'=>1,
    						'amount_day'=>$old_amount_day,
    						'collect_by'=>$data['co_id']
    				);
    	
    				$this->insert($datapayment);
    				$amount_collect=0;
    				$old_remain_principal = 0;
    				$old_pri_permonth = 0;
    				$old_interest_paymonth = 0;
    				$old_amount_day = 0;
    				
    				$from_date=$next_payment;
    				if($i!=1){
    					$next_payment = $dbtable->checkDefaultDate($str_next, $start_date, $data['amount_collect'],$data['every_payamount'],$data['first_payment']);
    				}
    			}else{
    	
    			}
    			$amount_collect++;
    		}
    		if(($amount_borrow_term)%($amount_fund_term)!=0){///end for record odd number only
    			$start_date = $next_payment;//$dbtable->getNextPayment($str_next, $next_payment, $data['amount_collect'],$data['every_payamount']);
    			$next_payment = $dbtable->checkFirstHoliday($data['date_line'],$data['every_payamount']);
    			$amount_day = $amount_day = $dbtable->CountDayByDate($from_date,$next_payment);
    			if($payment_method==1){
    				$pri_permonth = $remain_principal-$pri_permonth; // $pri_permonth*($amount_day/$amount_fund_term);//check it if khmer currency
    				$interest_paymonth = $pri_permonth*($data['interest_rate']/100/$borrow_term)*$amount_day;
    				$interest_paymonth = $this->round_up_currency($curr_type,$interest_paymonth);
    			}elseif($payment_method==2){
    				$pri_permonth = $this->round_up_currency($curr_type, $pri_permonth);
    				$remain_principal = $pri_permonth;//$remain_principal-$pri_permonth;//OSប្រាក់ដើមគ្រា
    				$interest_paymonth = $pri_permonth*($data['interest_rate']/100/$borrow_term)*$amount_day;
    				$interest_paymonth = $this->round_up_currency($curr_type,$interest_paymonth);
    			}elseif($payment_method==3){
    				$pri_permonth = $remain_principal-$pri_permonth;
    				$interest_paymonth = $data['total_amount']*($data['interest_rate']/100/$borrow_term)*$amount_day;
    				$interest_paymonth = $this->round_up_currency($curr_type,$interest_paymonth);
    			}elseif($payment_method==4){
    				$interest_paymonth = $data['total_amount']*($data['interest_rate']/100/$borrow_term)*$amount_day;
    				//     					$interest_paymonth = ($data['total_amount']*((($amount_fund_term*$data['interest_rate'])/$borrow_term)/100)*($amount_day/$day_perterm));
    				$interest_paymonth = $this->round_up_currency($curr_type,$interest_paymonth);
    				$pri_permonth = $remain_principal-$pri_permonth;
    			}elseif($payment_method==5){
    				$pri_permonth = $remain_principal;
    				$interest_paymonth = $remain_principal*($data['interest_rate']/100/$borrow_term)*$amount_day;
    				$interest_paymonth = $this->round_up_currency($curr_type,$interest_paymonth);
    			}elseif($payment_method==6){
    				$interest_paymonth = $this->round_up_currency($curr_type,$remain_principal*$irr_interest);
    				$fixed_principal = round($total_loan_amount/$term_install,0, PHP_ROUND_HALF_DOWN);
    				$pri_permonth = $remain_principal;
    			}
    			 
    			$datapayment = array(
    					'member_id'=>$g_id,
    					'total_principal'=>$pri_permonth,//good
    					'principal_permonth'=> $pri_permonth,//good
    					'principle_after'=> $pri_permonth,//good
    					'total_interest'=>$interest_paymonth,//good
    					'total_interest_after'=>$interest_paymonth,//good
    					'total_payment'=>$old_pri_permonth+$old_interest_paymonth,//good
    					'total_payment_after'=>$old_pri_permonth+$old_interest_paymonth,//good
    					'date_payment'=>$next_payment,//good
    					'is_completed'=>0,
    					'branch_id'=>$data['branch_id'],
    					'status'=>1,
    					'amount_day'=>$old_amount_day,
    					'collect_by'=>$data['co_id']
    			);
    			$this->insert($datapayment);
    		}
    		$db->commit();
    		return 1;
    	}catch (Exception $e){
    		$db->rollBack();
    		Application_Form_FrmMessage::message("INSERT_FAIL");
    		Application_Model_DbTable_DbUserLog::writeMessageError($e->getMessage());
    	}
    }
    public function getAllMemberLoanById($member_id){//for get id fund detail for update
    	$db = $this->getAdapter();
    	$sql = "SELECT lm.member_id ,lm.client_id,lm.group_id ,lm.loan_number,
    	(SELECT name_kh FROM `ln_client` WHERE client_id = lm.client_id LIMIT 1) AS client_name_kh,
    	(SELECT name_en FROM `ln_client` WHERE client_id = lm.client_id LIMIT 1) AS client_name_en,
    	(SELECT client_number FROM `ln_client` WHERE client_id = lm.client_id LIMIT 1) AS client_number,
    	lm.total_capital,lm.admin_fee,lm.loan_purpose FROM `ln_loan_member` AS lm
    	WHERE lm.status =1 AND lm.group_id = $member_id ";
    	return $db->fetchAll($sql);
    }
    
    public function getLaoForRepaymentSchedule($member_id){
    	$db = $this->getAdapter();
    	$sql = "SELECT
			    	lf.`date_payment`,
			    	lf.`principle_after`,
			    	lf.`total_interest_after`,
			    	lf.`service_charge`,
			    	lf.`penelize`,
			    	lf.`total_payment_after`,
			    	(SELECT lg.`date_release` FROM `ln_loan_group` AS lg WHERE lg.`g_id`=l.`group_id`) AS release_date,
			    	(SELECT lc.`date_input` FROM `ln_client_receipt_money` AS lc,`ln_client_receipt_money_detail` AS lcd WHERE lc.`id`=lcd.`crm_id` AND lcd.`loan_number`=l.`loan_number` AND lcd.`lfd_id`=lf.`id` ORDER BY lc.`date_input` DESC LIMIT 1) AS last_pay_date,
			    	l.client_id,l.currency_type ,l.interest_rate , l.loan_number,l.payment_method, l.group_id,l.branch_id,l.`pay_after`,l.`collect_typeterm`,
			    	(SELECT co_id FROM `ln_loan_group` WHERE g_id  = l.group_id LIMIT 1 ) AS co_id ,
			    	(SELECT zone_id FROM `ln_loan_group` WHERE g_id  = l.group_id LIMIT 1) AS zone_id,
			    	(SELECT LEVEL FROM `ln_loan_group` WHERE g_id  = l.group_id LIMIT 1) AS LEVEL
			    FROM `ln_loan_member` AS l,`ln_loanmember_funddetail` AS lf WHERE lf.`member_id`=l.`member_id` AND lf.`is_completed`=0 AND l.member_id=$member_id AND l.`status`=1 AND l.is_completed=0";
    	return $db->fetchAll($sql);
    }
    
    function  getRescheduleById($id){
    	$db = $this->getAdapter();
    	$sql = "SELECT * FROM `ln_reschedule` where id = $id";
    	return $db->fetchRow($sql);
    }
    
}
  


