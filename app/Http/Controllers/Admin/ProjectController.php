<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Project;
use App\User;
use App\Portfolio;
use App\Technology;
use App\ProjectGallery;
use Illuminate\Support\Facades\Auth;
use App\Http\Traits\CommonTrait;

class ProjectController extends Controller
{	
	use CommonTrait;
	public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
        $this->id = Auth::user()->id;
            $loggedInUserId = ($this->id);

            $User = User::find($loggedInUserId);

            if(empty($User)){
                    $status = false;
                    $response['message'] = 'Unauthenticated.';
                    return response()->json($response);
                
            }


            // if(!empty($User)){
            //     if($User->user_role != 'admin' ){
            //         $status = false;
            //         $response['message'] = 'Unauthenticated.';
            //         return response()->json($response);
            //     }
            // }else{
            //     $status = false;
            //     $response['message'] = 'Unauthenticated.';
            //     return response()->json($response);
            // }
            return $next($request);
        });
    }

    public function create($Project_id= false){
    	$requestData = trim(file_get_contents("php://input"));
        $userRequestValidate = (json_decode($requestData, TRUE));
        $userRequest = (json_decode($requestData));

        	if(empty($Project_id)){
        		$validateProjectName = 'required|string|unique:projects';
        	}else{
        		$validateProjectName = 'required|string|unique:projects,projectName,'.$Project_id;
        	}
    	
        	$validator = Validator::make($userRequestValidate, [
	            'projectName' => $validateProjectName,
	            // 'projectDescription' => 'required|string',
	            // 'technologyUsed' => 'required|string',
	            // 'projectUrl' => 'required|string',
	            // 'caseStudyUrl' => 'required|string',
	            // 'codeSnippets' => 'required|string',
	            // 'ProjectBannerImage' => 'required|string',
	        ]);
        


        if($validator->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'error' => $validator->errors()
            ]);      
        }
        if(!empty($Project_id)){
        	$Project = Project::find($Project_id);
        	$type = "update";
        }else{
        	$Project = new Project();
        	$type = "save";
        }
        
        if(!(is_null($Project))) {
        	if(
        		(!empty($userRequest->projectName)) && 
        		(!empty($userRequest->projectDescription)) &&
        		(!empty($userRequest->technologyUsed)) &&
        		(!empty($userRequest->RoleOnTheProject)) &&
        		(!empty($userRequest->ProjectBannerImage))){
        			$projectStatus = 'Publish';
        	}else{
        		$projectStatus = 'Draft';
        	}
        	$Project->projectName = $userRequest->projectName;
        	$Project->projectStatus = $projectStatus;
        	$Project->projectDescription = (!empty($userRequest->projectDescription)) ? ($userRequest->projectDescription) : ('');
        	$Project->RoleOnTheProject = (!empty($userRequest->RoleOnTheProject)) ? ($userRequest->RoleOnTheProject) : ('');
        	$Project->technologyUsed = (!empty($userRequest->technologyUsed)) ? (json_encode($userRequest->technologyUsed)) : ('');
        	$Project->projectUrl = (!empty($userRequest->projectUrl)) ? ($userRequest->projectUrl) : ('');
        	$Project->caseStudyUrl = (!empty($userRequest->caseStudyUrl)) ? ($userRequest->caseStudyUrl) : ('');
        	$Project->codeSnippets = (!empty($userRequest->codeSnippets)) ? ($userRequest->codeSnippets) : ('');
        	$Project->ProjectBannerImage = (!empty($userRequest->ProjectBannerImage)) ? ($userRequest->ProjectBannerImage) : ('');
        	$Project->createdBy = $this->id;
        	$Project->updatedBy = $this->id;

        	try{
        		$Project->save();
        		$Project->refresh();

                
                    $Profile = Portfolio::select('projectName','profileStatus', 'id')->whereRaw("find_in_set('".$Project_id."',projectName)")->get();

                    if(!empty($Profile)){
                        // $Profile = $Profile->toArray();
                        // if(!empty($Profile)){
                            $ProjectStatusArr = array();
                            foreach($Profile as $profiles){
                                $projects = $profiles['projectName'];
                                $splitProjects = explode(',', $projects);
                                foreach($splitProjects as $getProject){
                                    $findProject = Project::select('projectStatus')->where('id', $getProject)->first();
                                    if(!empty($findProject)){
                                        $ProjectStatusArr[] = $findProject->projectStatus;
                                    }
                                }
                                if(in_array('Draft', $ProjectStatusArr)){
                                    if($profiles->profileStatus == 'Publish'){
                                        $profiles->profileStatus = 'Draft';
                                    }
                                    
                                }
                                // elseif(in_array('Publish', $ProjectStatusArr)){
                                //     $profiles->profileStatus = 'Publish';
                                // }
                                // $profiles->fill($data);
                                $profiles->save();
                            }
                        // }
                    }

                


        	}catch(\Exception $Ex){
        		$response['status'] = false;
        		// $response['message'] = $Ex->getMessage();
                $response['message'] = "Cannot ".$type." the project. Please try again or contact your administrator.";
        		return $response;
        	}
        	$Project_id = $Project->id;
            // $Project->createdOn = Carbon::now()->toDateTimeString();
            $Project->createdOn = strtotime($Project->created_at);
            $Project->updatedOn = strtotime($Project->updated_at);
            // $Project->technologyUsed = json_decode(($Project->technologyUsed));
            if(!empty($Project->technologyUsed)){
            $technologiesUsed = json_decode($Project->technologyUsed);
            $theTechnlogy = array();
            foreach($technologiesUsed as $technologies){
                $tech_id = $technologies->id;
                $getTech = Technology::find($tech_id);
                $getTech->icon = $this->getImageFromS3($tech_id, "Icon");
                $theTechnlogy[] = $getTech;

            }

            if(!empty($theTechnlogy)){
                $Project->technologyUsed = $theTechnlogy;
            }
        }

        	if(!empty($Project->ProjectBannerImage)){
        		$images = explode(',', $Project->ProjectBannerImage);
        		$ProjectGallery = array();
        		// $count = 1;
        		foreach($images as $fileData){
		            $Image['ImageId'] = md5(uniqid());
		            $Image['file'] = $fileData;
		            $Image['fileUrl'] = $this->getImageUrlFromS3($fileData, "Gallery");
		            $ProjectGallery[] = $Image;
		            // $count++;
        		}

        	}
        	if(!empty($ProjectGallery)){
        		$Project->ProjectBannerImage = $ProjectGallery;
        	}

        	$response['status'] = true;
        	$response['result'] = $Project;
        }
        return $response;
    }

    public function project($project){
  //   	if((int)$project !== $project) {
		// 	$response['status'] = false;
  //   		$response['message'] = "It seems to be an invalid input. Please try again.";
  //   		return $response;
		// }
    	$Project = Project::find($project);
    	// echo "<pre>";print_r($Project);die;
    	if(!empty($Project)){
    		// $Project->ProjectBannerImage = $this->getImageFromS3($project, "gallery");
            if(!empty($Project->ProjectBannerImage)){
                $images = explode(',', $Project->ProjectBannerImage);
                $ProjectGallery = array();
                // $count = 1;
                foreach($images as $fileData){
                    
                    $Image['ImageId'] = md5(uniqid());
                    $Image['file'] = $fileData;
                    $Image['fileUrl'] = $this->getImageUrlFromS3($fileData, "Gallery");
                    $ProjectGallery[] = $Image;
                    // $count++;
                }
            }
            if(!empty($ProjectGallery)){
                $Project->ProjectBannerImage = $ProjectGallery;
            }else{
                $Project->ProjectBannerImage = $project->ProjectBannerImage;
            }

            $technologiesUsed = json_decode($Project->technologyUsed);
            $theTechnlogy = array();
            foreach($technologiesUsed as $technologies){
                $tech_id = $technologies->id;
                $getTech = Technology::find($tech_id);
                $getTech->icon = $this->getImageFromS3($tech_id, "Icon");
                $theTechnlogy[] = $getTech;

            }

            if(!empty($theTechnlogy)){
                $Project->technologyUsed = $theTechnlogy;
            }
    		$response['status'] = true;
    		$response['result'] = $Project;
    	}else{
    		$response['status'] = false;
    		$response['message'] = "It seems to be an invalid input. Please try again.";
    	}
    	return $response;
    }

    public function projects(){

    	$Projects = Project::all();
    	if(!empty($Projects)){
    		foreach($Projects as $project){
                // $project->created_at = 
                $project->createdOn = strtotime($project->created_at);
                $project->updatedOn = strtotime($project->updated_at);

    			// $project->ProjectBannerImage = $this->getImageFromS3($project->id, "Project");

                $technologiesUsed = json_decode($project->technologyUsed);
                $theTechnlogy = array();
                if(!empty($technologiesUsed)){


                foreach($technologiesUsed as $technologies){
                    $tech_id = $technologies->id;
                    $getTech = Technology::find($tech_id);
                    $getTech->icon = $this->getImageFromS3($tech_id, "Icon");
                    $theTechnlogy[] = $getTech;

                }

                if(!empty($theTechnlogy)){
                    $project->technologyUsed = $theTechnlogy;
                }
            }



    			if(!empty($project->ProjectBannerImage)){
        		$images = explode(',', $project->ProjectBannerImage);
        		$ProjectGallery = array();
        		// $count = 1;
        		foreach($images as $fileData){
        			
		            $Image['ImageId'] = md5(uniqid());
		            $Image['file'] = $fileData;
		            $Image['fileUrl'] = $this->getImageUrlFromS3($fileData, "Gallery");
		            $ProjectGallery[] = $Image;
		            // $count++;
        		}
        	}
        	if(!empty($ProjectGallery)){
        		$project->ProjectBannerImage = $ProjectGallery;
        	}else{
        		$project->ProjectBannerImage = $project->ProjectBannerImage;
        	}
    			$ProjectDetails[] = $project;
    		}
    		$response['status'] = true;
    		$response['result'] = $ProjectDetails;
    	}else{
    		$response['status'] = true;
    		$response['message'] = "No project available right now. Please create one.";
    	}
    	return $response;
    }

    public function uploadProjectImage(){

        $this->id = Auth::user()->id;
        $requestData = trim(file_get_contents("php://input"));
        $requestData = rtrim($requestData, ":");
        $userRequest = (json_decode($requestData, true));
        // echo "<pre>";print_r($userRequest);die;
        $images = array();
        // $count = time();
        // $t=
        foreach($userRequest as $fileData){
            // $getImage = $this->uploadFile($getImageData, 'project', $this->id);
            $getImage = $this->uploadFile($fileData['file'], 'gallery');
            $Image['ImageId'] = md5(uniqid());
            $Image['file'] = $getImage;
            $Image['fileUrl'] = $this->getImageUrlFromS3($getImage, "Gallery");
            $images[] = $Image;
            // $count++;
            
        }
        $response['status'] = true;
        $response['result'] = $images;
        return $response;
        // $requestData = trim(file_get_contents("php://input"));
        // $requestData = rtrim($requestData, ":");
        // $userRequest = (json_decode($requestData, true));
        // $getImageData = $userRequest['file'];

        // $getImage = $this->uploadFile($getImageData, 'project', $this->id);
        // $response['status'] = true;
        // $response['file'] =  $getImage;
        // $response['fileUrl'] =  $this->getImageUrlFromS3($getImage, "Project");
        // return $response;

    }

    public function uploadGallery(){
        $this->id = Auth::user()->id;
        $requestData = trim(file_get_contents("php://input"));
        $requestData = rtrim($requestData, ":");
        $userRequest = (json_decode($requestData, true));
        // echo "<pre>";print_r($userRequest);die;
        $images = array();
        // $count = time();
        // $t=
        foreach($userRequest as $fileData){
            // $getImage = $this->uploadFile($getImageData, 'project', $this->id);
            $getImage = $this->uploadFile($fileData['file'], 'gallery');
            $Image['ImageId'] = md5(uniqid());
            $Image['file'] = $getImage;
            $Image['fileUrl'] = $this->getImageUrlFromS3($getImage, "ProjectGallery");
            $images[] = $Image;
            // $count++;
            
        }
        $response['status'] = true;
        $response['result'] = $images;
        return $response;
    }

    public function getGallery($Project){
        $Gallery = ProjectGallery::where('project_id', $Project)->get();
        if(!empty($Gallery)){
            $Gallery = $Gallery->toArray();
            if(!empty($Gallery)){
                foreach($Gallery as $gallery){
                    $gallery['galleryImage'] = $this->getImageFromS3($gallery['id'], "ProjectGallery");
                    $galleryImages[] = $gallery;
                }
                if(!empty($galleryImages)){
                    $response['status'] = true;
                    $response['result'] = $galleryImages;
                }
            }else{
                $response['status'] =false;
                $response['message'] ="No images for this profile available.";
            }
        }
        return $response;

    }

    // public function uploadProjectImage(){
    //     $this->id = Auth::user()->id;
    //     $requestData = trim(file_get_contents("php://input"));
    //     $requestData = rtrim($requestData, ":");
    //     $userRequest = (json_decode($requestData, true));
    //     // echo "<pre>";print_r($userRequest);die;
    //     $ProjectGallery = array();
    //     foreach($userRequest as $fileData){
    //         $images['file'] = $this->uploadFile($fileData['file'], 'project');
    //         $images['fileUrl'] = $this->getImageUrlFromS3($images['file'], "Project");
    //         $ProjectGallery[] = $images;
    //     }
    //     $response['status'] = true;
    //     $response['result'] = $ProjectGallery;
    //     return $response;
    // }

}
