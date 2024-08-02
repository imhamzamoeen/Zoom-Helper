<?php

namespace App\Traits;

use App\Jobs\SendJobErrorMailJob;
use Exception;
use Config;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


trait ZoomTrait
{

    /* in laravel 9 we can change withtoken wihtin rety method  */
    /* so if we will update to the 9 then we can use withtoken change within retry function but here we will check whether cache has that token or not */

    // public $AccessToken; // to store the access token zoom api returns us .. this token is for 1 hour 

    // public function __construct()
    // {

    //     $this->AccessToken = NULL;
    // }


    public function GetAccessToken()
    {

        try {
            $response = Http::retry(2, 100)->acceptJson()->withBasicAuth(Config::get('app.Zoom_client_id'), Config::get('app.Zoom_client_secret'))->post('https://zoom.us/oauth/token?grant_type=account_credentials&account_id=JVDStRVhQd6PS-d3oFMh_A');
            if ($response->successful()) {
                Cache::put('AccessToken', $response['access_token'], $seconds = $response['expires_in']);
                return $response['access_token'];
            }
            // $this->AccessToken= $response['access_token'];
            else
                $response->throw();
        } catch (Exception $e) {
            // dispatch(new SendJobErrorMailJob(['function' => 'GetAccessToken', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
            // we will add a mail that will send us error here  F
        }
    }

    public function GetListOfUsers()
    {
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        // this api return data as paginate .. so it has next page token that we send as query parameter to get next page data..
        try {
            $user = collect();
            $next_page_token = 'try';
            $Query_Parameter = [];
            while (!empty($next_page_token)) {
                $response = Http::withHeaders([
                    'accept' => 'application/json',
                ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 1000, function ($exception = null, $request = null) {
                    if ($exception->response->status() == 401) {
                        //its an invalid token now refresh that token
                        if (!is_null($request))
                            $request->withToken($this->GetAccessToken());
                        else
                            $this->GetAccessToken();
                    }
                    return true;
                })->get('https://api.zoom.us/v2/users', $Query_Parameter);
                if ($response->successful()) {
                    $Query_Parameter['next_page_token'] = $response['next_page_token'];
                    $next_page_token = $response['next_page_token'];

                    foreach ($response['users'] as $eachuser) {
                        $user->push($eachuser);
                    }
                } else {
                    $next_page_token = null;
                }
            }
            return $user;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'GetListOfUsers', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }


    public function GetMeetingsOfUser($UserId = 'me')
    {
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        // this api return data as paginate .. so it has next page token that we send as query parameter to get next page data..
        try {
            $meetings = collect();
            $next_page_token = 'try';
            $Query_Parameter = [];
            while (!empty($next_page_token)) {
                $response = Http::withHeaders([
                    'accept' => 'application/json',
                ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 1000, function ($exception = null, $request = null) {
                    if ($exception->response->status() == 401) {
                        //invalid token 
                        if (!is_null($request))
                            $request->withToken($this->GetAccessToken());
                        else
                            $this->GetAccessToken();
                    } else if ($exception->response->status() == 404) {

                        //user id is wrong 
                        return false;
                    }
                    return true;
                })->get("https://api.zoom.us/v2/users/{$UserId}/meetings", $Query_Parameter);
                if ($response->successful()) {
                    $Query_Parameter['next_page_token'] = $response['next_page_token'];
                    $next_page_token = $response['next_page_token'];

                    foreach ($response['meetings'] as $eachmetting) {
                        $meetings->push($eachmetting);
                    }
                } else {
                    $next_page_token = null;
                }
            }
            return $meetings;
        } catch (Exception $e) {
            // dispatch(new SendJobErrorMailJob(['function' => 'GetMeetingsOfUser', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }


    public function CreateUser($userData = [])
    {
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {
            if (empty($userData))
                return;
            $CreatingUserData = array(
                'action' => 'create',
                'user_info' => $userData
            );
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->withBody(json_encode($CreatingUserData), 'application/json')->retry(2, 1000, function ($exception = null, $request = null) {
                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request))
                        $request->withToken($this->GetAccessToken());
                    else
                        $this->GetAccessToken();

                    return true;
                } else {
                    return false;
                }
                //user id is wrong
            })->post("https://api.zoom.us/v2/users");
            return $response;
        } catch (Exception $e) {

            // dispatch(new SendJobErrorMailJob(['function' => 'CreateUser', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function UpdateUser($UserId = 'me', $UpdatedData = [])
    {
        // this api is used to update the user i.e. the type role manager etc


        if (is_null($UpdatedData))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->withBody(json_encode($UpdatedData), 'application/json')->retry(2, 2000, function ($exception = null, $request = null) {

                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request)) {

                        $request->withToken($this->GetAccessToken());
                    } else {
                        $this->GetAccessToken();
                    }

                    return true;
                } else {
                    return false;
                }
            })->PATCH("https://api.zoom.us/v2/users/{$UserId}");

            return $response;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'UpdateMeeting', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function AssignAssitantToUser($userId = 'me', $AssistantData = [])
    {
        // the userid must be a paid account
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {
            if (empty($AssistantData))
                return;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->withBody(json_encode($AssistantData), 'application/json')->retry(2, 1000, function ($exception = null, $request = null) {
                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request))
                        $request->withToken($this->GetAccessToken());
                    else
                        $this->GetAccessToken();

                    return true;
                } else {
                    return false;
                }
                //user id is wrong
            })->POST("https://api.zoom.us/v2/users/{$userId}/assistants");
            return $response;
        } catch (Exception $e) {

            dispatch(new SendJobErrorMailJob(['function' => 'CreateUser', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }


    public function GetRoles()
    {
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 10000, function ($exception = null, $request = null) {
                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request))
                        $request->withToken($this->GetAccessToken());
                    else
                        $this->GetAccessToken();

                    return true;
                } else {
                    return false;
                }
            })->get("https://api.zoom.us/v2/roles");
            return $response;    //it has total roles and a an array of roles 
        } catch (Exception $e) {

            dispatch(new SendJobErrorMailJob(['function' => 'GetRoles', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function AssignRoleToUser($roleId = 2, $usersArray = null)
    {
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        //2 is the role id of memeber 
        if (empty($usersArray))
            return;
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->withBody(json_encode($usersArray), 'application/json')->retry(2, 1000, function ($exception = null, $request = null) {
                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request))
                        $request->withToken($this->GetAccessToken());
                    else
                        $this->GetAccessToken();

                    return true;
                } else {
                    return false;
                }
            })->post("https://api.zoom.us/v2/roles/{$roleId}/members");

            return $response;
        } catch (Exception $e) {

            dispatch(new SendJobErrorMailJob(['function' => 'AssignRoleToUser', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }
    public function DeleteUser($UserId = null)
    {
        if (is_null($UserId))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 1000, function ($exception = null, $request = null) {
                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request))
                        $request->withToken($this->GetAccessToken());
                    else
                        $this->GetAccessToken();

                    return true;
                } else {
                    return false;
                }
            })->DELETE("https://api.zoom.us/v2/users/{$UserId}", [          // after deleting the user , all his meeting will be transfered to maam nabeela
                'transfer_email' => 'nabeela.arshad@alquranclasses.com',
                'transfer_meeting' => true,
                'transfer_recording' => true,

            ]);

            return $response;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'DeleteUser', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function DeleteRoleToUser($roleId = 2, $MemberId = null)
    {
        if (is_null($MemberId))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 1000, function ($exception = null, $request = null) {
                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request))
                        $request->withToken($this->GetAccessToken());
                    else
                        $this->GetAccessToken();

                    return true;
                } else {
                    return false;
                }
            })->DELETE("https://api.zoom.us/v2/roles/{$roleId}/members/{$MemberId}");

            return $response;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'DeleteRoleToUser', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function GetMeetingDetailsOfMeeting($MeetingId = null)
    {

        // this works for the meeting that are going to happen  in future 

        if (is_null($MeetingId))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 10000, function ($exception = null, $request = null) {
                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request))
                        $request->withToken($this->GetAccessToken());
                    else
                        $this->GetAccessToken();

                    return true;
                } else {
                    return false;
                }
            })->GET("https://api.zoom.us/v2/meetings/{$MeetingId}");

            return $response;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'GetMeetingDetailsOfMeeting', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function CreateMeetingOfUser($UserId = 'me', $meetingData = [])
    {
        if (empty($meetingData))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->withBody(json_encode($meetingData), 'application/json')->retry(3, 5000, function ($exception = null, $request = null) {

                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request)) {
                        Log::info('we got request');
                        $request->withToken($this->GetAccessToken());
                    } else {
                        Log::info('No request');
                        $this->GetAccessToken();
                    }

                    return true;
                } else {
                    return false;
                }
            })->POST("https://api.zoom.us/v2/users/{$UserId}/meetings");

            return $response;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'CreateMeetingOfUser', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function UpdateMeeting($MeetingId = null, $meetingData = [])
    {
        // This API has a rate limit of 100 requests per day. Because of this, a meeting can only be updated for a maximum of 100 times within a 24-hour period.

        // this works for the meeting that are going to happen  
        if (is_null($MeetingId) || empty($meetingData))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->withBody(json_encode($meetingData), 'application/json')->retry(2, 2000, function ($exception = null, $request = null) {

                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request)) {

                        $request->withToken($this->GetAccessToken());
                    } else {
                        $this->GetAccessToken();
                    }

                    return true;
                } else {
                    return false;
                }
            })->PATCH("https://api.zoom.us/v2/meetings/{$MeetingId}");

            return $response;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'UpdateMeeting', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function GetParticipantsOfMeeting($MeetingId = null)
    {
        //This Api is to get the meeting details of a meeting that has passed .. it tells the duration etc

        // this works for the meeting that are going to happen  
        if (is_null($MeetingId))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {
            $participants = collect();
            $next_page_token = 'try';
            $Query_Parameter = [];
            while (!empty($next_page_token)) {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 2000, function ($exception = null, $request = null) {

                    if ($exception->response->status() == 401) {
                        //invalid token 
                        if (!is_null($request)) {

                            $request->withToken($this->GetAccessToken());
                        } else {
                            $this->GetAccessToken();
                        }

                        return true;
                    } else {
                        return false;
                    }
                })->GET("https://api.zoom.us/v2/past_meetings/{$MeetingId}/participants", $Query_Parameter);

                if ($response->successful()) {
                    $Query_Parameter['next_page_token'] = $response['next_page_token'];
                    $next_page_token = $response['next_page_token'];

                    foreach ($response['participants'] as $eachuser) {
                        $participants->push($eachuser);
                    }
                } else {
                    $next_page_token = null;
                }
            }

            return $participants;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'GetParticipantsOfMeeting', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function GetRecordingofMeeting($MeetingId = null)
    {
        // This API return as an array of recording that 0 index has that video recording and 1 has audio onlyy 

        // this works for the meeting that are going to happen  
        if (is_null($MeetingId))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 2000, function ($exception = null, $request = null) {

                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request)) {

                        $request->withToken($this->GetAccessToken());
                    } else {
                        $this->GetAccessToken();
                    }

                    return true;
                } else {
                    return false;
                }
            })->GET("https://api.zoom.us/v2/meetings/{$MeetingId}/recordings");

            return $response;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'GetRecordingofMeeting', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function DeleteRecordingofMeeting($MeetingId = null)
    {
        // This API return as an array of recording that 0 index has that video recording and 1 has audio onlyy 

        // this works for the meeting that are going to happen  
        if (is_null($MeetingId))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 2000, function ($exception = null, $request = null) {

                if ($exception->response->status() == 401) {
                    //invalid token 
                    if (!is_null($request)) {

                        $request->withToken($this->GetAccessToken());
                    } else {
                        $this->GetAccessToken();
                    }

                    return true;
                } else {
                    return false;
                }
            })->Get("https://api.zoom.us/v2/meetings/{$MeetingId}/recordings");

            return $response;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'DeleteRecordingofMeeting', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }

    public function GetAllRecordingMeetingOfUser($UserId = null)
    {
        // This API return as an array of recording that 0 index has that video recording and 1 has audio onlyy 

        // this works for the meeting that are going to happen  
        if (is_null($UserId))
            return;
        if (CheckToken() == false)   // agar token expire hogia tu dobara kro 
            $this->GetAccessToken();
        try {

            $Recodings = collect();
            $next_page_token = 'try';
            $Query_Parameter = [];
            while (!empty($next_page_token)) {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->acceptJson()->withToken(Cache::get('AccessToken'))->retry(2, 2000, function ($exception = null, $request = null) {

                    if ($exception->response->status() == 401) {
                        //invalid token 
                        if (!is_null($request)) {

                            $request->withToken($this->GetAccessToken());
                        } else {
                            $this->GetAccessToken();
                        }

                        return true;
                    } else {
                        return false;
                    }
                })->GET("https://api.zoom.us/v2/users/{$UserId}/recordings", $Query_Parameter);

                if ($response->successful()) {
                    $Query_Parameter['next_page_token'] = $response['next_page_token'];
                    $next_page_token = $response['next_page_token'];

                    foreach ($response['meetings'] as $eachmeeting) {
                        $Recodings->push($eachmeeting);
                    }
                } else {
                    $next_page_token = null;
                }
            }
            return $Recodings;
        } catch (Exception $e) {
            dispatch(new SendJobErrorMailJob(['function' => 'GetAllRecordingMeetingOfUser', 'message' => $e->getMessage()]));
            Log::info($e);
            return $e->getMessage();
        }
    }
}
