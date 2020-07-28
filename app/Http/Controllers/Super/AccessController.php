<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\User;
use App\AdminAccess;

class AccessController extends Controller
{
    public function index($AccessId=false){
    	$requestData = trim(file_get_contents("php://input"));
        $userRequestValidate = (json_decode($requestData, TRUE));
        $userRequest = (json_decode($requestData));

        //organisation
        //domain
        //access
        //emails

        if(empty($AccessId)){
        		$validateAccessOrg = 'required|string|unique:admin_accesses';
        		$validateAccessDomain = 'required|string|unique:admin_accesses';
        	}else{
        		$validateAccessOrg = 'required|string|unique:admin_accesses,organisation,'.$AccessId;
        		$validateAccesDomain = 'required|string|unique:admin_accesses,domain,'.$AccessId;
        	}

        	// echo $validateAccesDomain;
        	// die;
        	$validator = Validator::make($userRequestValidate, [
		            'organisation' => $validateAccessOrg,
		            'domain' => $validateAccesDomain,
		            'access' => 'required|string',
		            'emails' => 'string'
		        ]);
        

        if($validator->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'error' => $validator->errors()
            ]);      
        }


        if(!empty($AccessId)){
        	$AdminAccess = AdminAccess::find($AccessId);
        	$type = "updated";
        }else{
        	$AdminAccess = new AdminAccess();
        	$type = "save";
        }

        // $AdminAccess = new AdminAccess();
        if(!is_null($AdminAccess)){
        	if($userRequest->access == 'Public'){
        		$userRequest->emails = '';
        	}elseif($userRequest->access == 'Limited'){
        		if(empty($userRequest->emails)){
        			$response['status'] = false;
        			$response['message'] = "Please enter the emails for limited access.";
        			return $response;
        		}
        		$userRequest->emails = $userRequest->emails;
        	}
        	$AdminAccess->organisation = $userRequest->organisation;
        	$AdminAccess->domain = $userRequest->domain;
        	$AdminAccess->access = $userRequest->access;
        	$AdminAccess->emails = (!empty($userRequest->emails)) ? ($userRequest->emails) : ('');
        	$AdminAccess->status = 'Permit';

        	try{
        		if($AdminAccess->save()){
        			$getEmails= explode(",", $AdminAccess->emails);
        			foreach($getEmails as $email){
        				
        				$User = User::select('email')->where('email', $email)->first();
        				if(empty($User)){
							$User = new User();
	        				$User->email = $email;
	        				$User->user_role = 'admin';
	        				$User->save();
        				}
        			}
        			$response['status'] = true;
        			$response['message'] = "Access saved";
        			$response['result'] = $AdminAccess;
        		}
        	}
        	catch(\Exception $ex){
        		$response['status'] = false;
        		// $response['message'] = "Something went wrong. Please try again.";
        		$response['message'] = $ex->getMessage();
        	}
        	
        }else{
        	$response['status'] = false;
        	$response['message'] = "Something went wrong. Please try again.";
        }
        return $response;

    }

    public function changeUserStatus($id){
    	$requestData = trim(file_get_contents("php://input"));
        $userRequestValidate = (json_decode($requestData, TRUE));
        $userRequest = (json_decode($requestData));

        $User = User::find($id);
        if(!empty($User)){
        	$User->status = $userRequest->status;
        	// $User->save();

        	try{
        		$User->save();
        		$response['status'] = true;
        		$response['message'] = "User Status Updated";
        	}catch(\Exception $ex){
        		$response['status'] = false;
        		$response['message'] = "Something went wrong. Please try again.";
        	}
        }else{
        	$response['status'] = false;
        	$response['message'] = "We cannot find that email in our records. Please check the input again.";
        }
        return $response;
    }

    public function changeOrgStatus($id){
    	$requestData = trim(file_get_contents("php://input"));
        $userRequestValidate = (json_decode($requestData, TRUE));
        $userRequest = (json_decode($requestData));

        if($userRequest->status == 'Permit'){
        	$UserStatus = 1;
        }elseif($userRequest->status == 'Revoke'){
        	$UserStatus = 0;
        }
        $AdminAccess = AdminAccess::find($id);
        if(!empty($AdminAccess)){
        	$AdminAccess->status = $userRequest->status;
        	$AdminAccess->save();

        	try{
        		if($AdminAccess->save()){
        			$getEmails= explode(",", $AdminAccess->emails);
        			foreach($getEmails as $email){
        				$User = User::where('email', $email)->first();
        				if(!empty($User)){
        					$User->status = $UserStatus;
        					$User->save();
        				}	
        			}
        		}

        		$response['status'] = true;
        		$response['message'] = "Organisation Status Updated";
        	}catch(\Exception $ex){
        		$response['status'] = false;
        		$response['message'] = "Something went wrong. Please try again.";
        	}
        }else{
        	$response['status'] = false;
        	$response['message'] = "We cannot find that record. Please check the input again.";
        }
        return $response;
    }

    public function login(Request $request){

        $input = $request->all();
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) { 
            return response()->json(['error'=>$validator->errors()], 401);            
        }
        if($user = User::where('email', $input['email'])
        				->where('user_role', 'superAdmin')
        				->first()){
            
            $success['status'] =  true;
            $success['user'] =  $user; 
            $success['token'] = $user->createToken('ProfileSharingApp-admin')->accessToken; 
            
        }else{
        	$success['status'] =  false;
            $success['message'] = "Invalid user.";
        }
        return response()->json($success); 
    }
}
