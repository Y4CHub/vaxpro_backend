<?php

namespace App\Http\Controllers;

use App\Models\HealthWorker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Helpers\GenerateRoleIdHelper;
use App\Helpers\GeneratePasswordHelper;
use App\Services\SmsService;

class AuthController extends Controller
{

    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function register(Request $request)
    {

        if ($request->has("ward_id")) {

            if (User::where('role_id', $request->input('role_id'))
                ->Where('ward_id', $request->input('ward_id'))
                ->doesntExist()
            ) {
                $uid = GenerateRoleIdHelper::generateRoleId($request->account_type, null, null, $request->ward_id);
            } 
            // else if (Role::where('id', $request->role_id)) {
            
            // }
             else {
                return response()->json(['message' => 'This account exists ', 'status' => 409]);
            }
        } else if ($request->has("facility_id")) {
            if (User::where('role_id', $request->input('role_id'))
                ->Where('facility_id', $request->input('facility_id'))
                ->doesntExist()
            ) {
                $uid = GenerateRoleIdHelper::generateRoleId($request->account_type, null, 1, null);
            } else if (
                $request->account_type == "health_worker"
            ) {
                $uid = GenerateRoleIdHelper::generateRoleId($request->account_type, null, 2, null);
            } else if (
                $request->account_type == "branch_admin"
            ) {
                $uid = GenerateRoleIdHelper::generateRoleId($request->account_type, null, 2, null);
            } else {
                return response()->json(["message" => "This account exists ", 'status' => 409]);
            }
        } else if ($request->has("district_id")) {
            if (User::where('role_id', $request->input('role_id'))
                ->Where('district_id', $request->input('district_id'))
                ->doesntExist()
            ) {
                $uid = GenerateRoleIdHelper::generateRoleId($request->account_type, null, $request->district_id, null);
            } else {
                return response()->json(["message" => "This district account exists ", 'status' => 409]);
            }
        } else if ($request->has("region_id")) {
            if (User::where('role_id', $request->input('role_id'))
                ->Where('region_id', $request->input('region_id'))
                ->doesntExist()
            ) {
                $uid = GenerateRoleIdHelper::generateRoleId($request->account_type, $request->region_id, null, null);
            } else {
                return response()->json(["message" => "This region account exists", 'status' => 409]);
            }
        } else {
            if (User::where('role_id', $request->input('role_id'))
                ->doesntExist()
            ) {
                $uid = GenerateRoleIdHelper::generateRoleId($request->account_type, null, null, null);
            } else {
                return response()->json(['message' => 'This ministry account exists ', 'status' => 409]);
            }
        };


        $password = GeneratePasswordHelper::generatePassword();

        if ($uid && $password) {
            $user = User::find($uid);
            $user = User::create([
                'uid' => $uid,
                "role_id" => $request->role_id,
                'password' => Hash::make($password),
                'ward_id' => $request->ward_id,
                'district_id' => $request->district_id,
                'region_id' => $request->region_id,
                'facility_id' => $request->facility_id,
                'contacts' => $request->contacts,
            ]);

            if ($request->account_type == "health_worker") {

                if ($request->has("last_name")) {
                    HealthWorker::create([
                        'staff_id' => $request->staff_id,
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'user_id' => $user->id
                    ]);
                } else {
                    return $request . "REQUEST WITH NO LAST NAME";
                }
            }
        }
        if ($user) {

            $postData = [
                'source_addr' => 'VaxPro',
                'encoding' => 0,
                'schedule_time' => '',
                'message' => 'Umesajiliwa kikamilifu kwenye mfumo wa VaxPro, tumia password-"' . $password . " na uid " . $user["uid"],
                'recipients' => [
                    ['recipient_id' => '1', 'dest_addr' => '255745884099'],
                    ['recipient_id' => '2', 'dest_addr' => '255658004980']
                ]
            ];

            // Send SMS using the service
            // $this->smsService->sendSms($postData);
            return response()->json(['message' => "User successfully added", $password, "status" => 200]);
        } else {
            return response()->json(["message" => "Error occured, Please try again", "status" => 401]);
        }
    }

    public function update(Request $request, $id)
    {
        $user  =  User::find($id);

        if ($request->has('contacts')) {
            $user->contacts = $request->contacts;
        }


        $user->save();

        return response()->json(["message" => "user successfully updated"]);
    }
    public function login(Request $request)
    {
        $credentials = $request->only(["uid", "password"]);

        if (Auth::attempt($credentials)) {

            $token  = $request->user()->createToken("vaxPro")->plainTextToken;

            return response()->json([
                "token" => $token,
                "message" => "logged in",
                "status" => 200,
            ]);
        } else
            return response()->json([
                "message" => "user not found",
                "status" => 404,
            ]);
    }


    public function refreshToken(Request $request)
{
    $user = $request->user();

    

    // Revoke current token
    $request->user()->currentAccessToken()->delete();

    // Generate new token
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json($token,200);
}

    public function logout(Request $request)
    {
        $request->user()->tokens()->where('id', $request->user()->currentAccessToken()->id)->delete();
        return response()->json('Logged out successfully', 200);
    }
}
