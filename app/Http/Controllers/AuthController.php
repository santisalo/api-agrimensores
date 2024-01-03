<?php

namespace App\Http\Controllers;

use App\Http\Clases\MensajesRespuesta;
use App\Http\Clases\Respuesta;
use App\Http\Clases\Utils;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'email' => 'required|string',
            'password' => 'required|string',
            'admin' => 'nullable|boolean',
            'deviceUuid' => 'nullable|string',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }


        $params = Utils::transformaValRules($valRules, $request);
        $email = $params['email'];
        $password = $params['password'];
        $admin = $params['admin'];
        $deviceUuid = $params['deviceUuid'];

        $objUser = User::where('email', $email)->first();
        if (!$objUser) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_INEXISTENTE'], null, 409);
        }

        Log::info('deviceUuid: ' . $deviceUuid);
        Log::info('device_uuid: ' . $objUser->device_uuid);

        if ($objUser->device_uuid) {
            if ($objUser->device_uuid != $deviceUuid) {
                return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_DEVICE_UUID'], null, 409);
            }
        }

        $objUser = User::where('email', $email)->where('habilitado', 1)->first();
        if (!$objUser) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_NO_HABILITADO'], null, 409);
        }

        if ($admin) {
            if ($objUser->rol != "admin") {
                return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_CREDENCIALES'], null, 409);
            }
        } else {
            $loginApiCpiaResponse = User::postLoginApiCpia($email, $password);
            if ($loginApiCpiaResponse['status'] != "OK") {
                return new Respuesta(-2, $loginApiCpiaResponse['message'], null, 409);
            }

            $objUser->password = bcrypt(sha1($password));
            $objUser->api_token = $loginApiCpiaResponse['token'];
        }
        if (!$objUser->device_uuid) {
            $objUser->device_uuid = $deviceUuid;
        }

        $objUser->save();

        try {
            if (!$token = JWTAuth::attempt(['email' => $email, 'password' => $admin ? $password : sha1($password)])) {
                Log::info($email . ' --+-- ' . $password);
                return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_CREDENCIALES'], null, 409);
            }
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return new Respuesta(-3, MensajesRespuesta::respuestas['ERROR_CREDENCIALES'], $th->getMessage(), 409);
        }

        $devolver = Utils::ajustarObjeto($objUser);
        $devolver['token'] = $token;

        return new Respuesta(1, MensajesRespuesta::respuestas['OK_1'], $devolver, null);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }
}
