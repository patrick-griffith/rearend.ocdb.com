<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller {

    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['register']]);
        //$this->middleware('auth:api');
    }

    private function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }



    public function getDB($id, $dbname) {
        $user = User::where('dbname', $dbname)->find($id);
        if($user) {
            return response()->json(json_decode($user->json)); 
        } else {
            return response()->json(['frontendMessage' => 'Database not found.'], 404);
        }
    }

    public function setPro() {
        $user = auth()->user();
        $user->pro = 1;
        $user->save();
        return response()->json(['message' => 'success'], 200);
    }

    public function setDB(Request $request) {
        $user = auth()->user();
        $user->json = $request->json;
        $user->save();
        return response()->json(['message' => 'success'], 200);
    }

    public function setSchemaMode($mode) {
        $user = auth()->user();
        $user->schemaMode = $mode;
        $user->save();
        return response()->json(['message' => 'success'], 200);
    }


    public function setAndSendCode(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users,email'
        ]);

        if($validator->fails()) {
            return response()->json(['frontendMessage' => $validator->errors()->first()], 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->code_attempts = 0;
        $user->code = strval(rand(0,9)) . strval(rand(0,9)) . strval(rand(0,9)) . strval(rand(0,9)) . strval(rand(0,9)) . strval(rand(0,9));
        $user->save();

        Mail::send([], [], function ($message) use($user) {
            $message->to($user->email)
                ->subject($user->code . ' - Your OCDB code')
                ->setBody('
                <p>Please enter code <strong>' . $user->code . '</strong> to continue.</p>
                <p>Email: ' . $user->email . '<br/>Password: (encrypted)</p>
                <p>Ok thx bye.</p>'
                , 'text/html');
        });

        return response()->json(['message' => 'success'], 200);
        
    }

    public function verifyCode(Request $request) {
        $validator = Validator::make($request->all(), [
            'code' => 'required|min:6|max:6',
            'email' => 'required|sometimes|nullable|exists:users,email'
        ]);

        if($validator->fails()) {
            return response()->json(['frontendMessage' => $validator->errors()->first()], 422);
        }

        if(!($user = auth()->user())) {
            if(!($user = User::where('email', $request->email)->first())) {
                return response()->json(['frontendMessage' => 'User not found.'], 404);
            }
        } 
        
        if($user->code_attempts >= 5) {
            return response()->json(['frontendMessage' => 'Too many attempts.'], 422);
        } else if($user->code === $request->code) {
            $user->code_attempts = 0;
            $user->save();
            return response()->json(['message' => 'Valid code.'], 200);
        } else {
            $user->code_attempts += 1;
            $user->save();
            return response()->json(['frontendMessage' => 'Wrong code. ' . (5 - $user->code_attempts) . ' attempt(s) remaining.'], 422);
        }
    }

    public function confirm(Request $request) {
        $validator = Validator::make($request->all(), [
            'code' => 'required|min:6|max:6'
        ]);

        if($validator->fails()) {
            return response()->json(['frontendMessage' => $validator->errors()->first()], 422);
        }

        $user = auth()->user();
        if($user->code_attempts >= 5) {
            return response()->json(['frontendMessage' => 'Too many attempts.'], 422);
        } else if($user->code === $request->code) {
            $user->code = null;
            $user->code_attempts = 0;
            $user->confirmed = 1;
            $user->save();
            return $user;
        } else {
            return response()->json(['frontendMessage' => 'Wrong code, yo.'], 422);
        }
    }

    public function updatePassword(Request $request) {
        $validator = Validator::make($request->all(), [
            'code' => 'required|min:6|max:6',
            'email' => 'required|sometimes|email|max:191|exists:users',
            'password' => 'required|confirmed|min:6|max:191'
        ]);

        if($validator->fails()) {
            return response()->json(['frontendMessage' => $validator->errors()->first()], 422);
        }

        if(!($user = auth()->user())) {
            if(!($user = User::where('email', $request->email)->first())) {
                return response()->json(['frontendMessage' => 'User not found.'], 404);
            }
        } 

        if($user->code_attempts >= 5) {
            return response()->json(['frontendMessage' => 'Too many attempts.'], 422);
        } else if($user->code === $request->code) {
            $user->code = null;
            $user->code_attempts = 0;
            $user->confirmed = 1;
            $user->password = Hash::make($request->password);
            $user->save();
            return $user;
        } else {
            return response()->json(['frontendMessage' => 'Wrong code, yo.'], 422);
        }
    }

    public function create(Request $request) {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:191|unique:users',
            'password' => 'required|confirmed|min:6|max:191'
        ]);

        if($validator->fails()) {
            return response()->json(['frontendMessage' => $validator->errors()->first()], 422);
        }

        $user = new User();
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->json = '{"site_title":{"type":"string","value":"Website Name"},"h1":{"type":"string","value":"A Test"},"h2":{"type":"string","value":"This is how we work, and why you should choose us, and more fun nuggets."},"description":{"type":"text","value":"Alright, well, this is a description."}}';
        $user->dbname = self::generateRandomString(40);
        $user->code = strval(rand(0,9)) . strval(rand(0,9)) . strval(rand(0,9)) . strval(rand(0,9)) . strval(rand(0,9)) . strval(rand(0,9));
        $user->save();

        Mail::send([], [], function ($message) use($user) {
            $message->to($user->email)
                ->subject($user->code . ' - Your OCDB code')
                ->setBody('
                <p>Hi! Welcome to One Click DB!</p>
                <p>Please enter code <strong>' . $user->code . '</strong> to continue.</p>
                <p>Email: ' . $user->email . '<br/>Password: (encrypted)</p>
                <p>Ok thx bye.</p>'
                , 'text/html');
        });
        
        return $user;
    }

}