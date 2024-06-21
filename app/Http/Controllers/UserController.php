<?php

namespace App\Http\Controllers;

use App\Http\Clases\App;
use App\Http\Clases\Constantes;
use App\Http\Clases\MensajesRespuesta;
use App\Http\Clases\Utils;
use App\Http\Clases\Respuesta;
use App\Http\Controllers\Controller;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\MovimientoCredito;
use App\Pago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['postCreateFromApp', 'postCreate', 'postValidarCreacionApp']]);
    }

    public function getTodos(Request $request)
    {


        $listaBD = User::orderBy('email')->get();

        $listaDevolver = collect();
        if ($listaBD) {
            foreach ($listaBD as $item) {
                $listaDevolver->push(Utils::ajustarObjeto($item));
            }
        }

        return new Respuesta(1, MensajesRespuesta::respuestas['OK_1'], $listaDevolver, null);
    }

    public function getTodosPaginado(Request $request)
    {
        $cantPorPag = Constantes::ITEMS_POR_PAGINA;

        $input = $request->all();

        $valRules = [
            'nroPagina' => 'numeric|sometimes|required',
            'dni' => 'string|sometimes|required',
            'rolID' => 'integer|sometimes|required',
            'apellido' => 'integer|sometimes|required',
            'options' => 'json|sometimes|required',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        //Parametros

        $params = Utils::transformaValRules($valRules, $request);
        $options = $params['options'];
        $dni = $params['dni'];
        $rolID = $params['rolID'];
        $apellido = $params['apellido'];

        $nroPagina = $options['page'] ? $options['page'] : 1;
        $cantPorPag = $options['itemsPerPage'] ? $options['itemsPerPage'] : Constantes::ITEMS_POR_PAGINA;

        $listaBD = User::when($apellido != null, function ($query) use ($apellido) {
            $query->where('apellido', 'LIKE', '%' . $apellido . '%');
        })
            ->when($rolID != null, function ($query) use ($rolID) {
                $query->where('rol_id', $rolID);
            })
            ->when($options['search']['valor'], function ($query) use ($options) {
                $query->where($options['search']['campo'], 'LIKE', "%" . $options['search']['valor'] . "%");
            })
            ->when($options && count($options['sortBy']), function ($query) use ($options) {
                foreach ($options['sortBy'] as $key => $sort) {
                    $tipoSort = $options['sortDesc'][$key] ? "ASC" : "DESC";
                    $query->orderBy($sort, $tipoSort);
                }
            });

        if ($options['itemsPerPage'] === -1) {
            $listaBD = $listaBD->get();
            $pagTotalItems = count($listaBD);
        } else {
            $listaBD = $listaBD->paginate($cantPorPag, ['*'], 'page', $nroPagina);
            $pagTotalItems = $listaBD->total();
        }

        //Datos de paginado
        $pagTotal = ceil($pagTotalItems / $cantPorPag);
        $pagActual = $nroPagina;

        $listaDevolver = collect();
        if ($listaBD) {
            foreach ($listaBD as $item) {
                $listaDevolver->push(Utils::ajustarObjeto($item, ['updated_at', 'deleted_at', 'created_user_id', 'updated_user_id', 'deleted_user_id']));
            }
        }

        $devolver = [
            "datos" => $listaDevolver,
            "pagTotal" => $pagTotal,
            "pagActual" => $pagActual,
            "pagTotalItems" => $pagTotalItems
        ];

        return new Respuesta(1, MensajesRespuesta::respuestas['OK_1'], $devolver, 200);
    }

    public function getDatos(Request $request)
    {
        $input = $request->all();

        $valRules = [
            'id' => 'required|integer'
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        //Parametros
        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'];

        //Obtiene usuario
        $objUserBD = User::find($id);

        if (!$objUserBD) {
            return new Respuesta(-2, 'Error', MensajesRespuesta::respuestas['ERROR_NO_ENCONTRADO'], 404);
        }

        $objDevolver = Utils::ajustarObjeto($objUserBD);

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $objDevolver,
            200
        );
    }

    public function postDelete(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'id' => 'required|integer',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            Log::info("Error de validacion: " . $validator->errors());
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'];

        $objUser = User::where('id', $id)->first();
        if (!$objUser) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_NO_ENCONTRADO'], null, 409);
        }

        $objOrdenTrabajo = OrdenTrabajo::where('user_id', $id)->first();
        if ($objOrdenTrabajo) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_ORDENES_TRABAJO_EXISTENTES'], null, 409);
        }

        $objUser::destroy($id);

        $objDevolver = true;

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $objDevolver,
            200
        );
    }
    public function postUpdate(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'id' => 'required|integer',
            'nombre' => 'required|string',
            'apellido' => 'required|string',
            'dni' => 'required|string',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            Log::info("Error de validacion: " . $validator->errors());
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'];
        $nombre = $params['nombre'];
        $apellido = $params['apellido'];
        $dni = $params['dni'];

        $objUser = User::where('id', $id)->first();
        if (!$objUser) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_NO_ENCONTRADO'], null, 409);
        }

        $objUser->nombre = $nombre;
        $objUser->apellido = $apellido;
        $objUser->dni = $dni;
        $objUser->save();

        $objDevolver = $objUser->id;

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $objDevolver,
            200
        );
    }
    public function postCreate(Request $request)
    {
        $input = $request->all();
        Log::info($input);
        $valRules = [
            'nombre' => 'required|string',
            'apellido' => 'required|string',
            'dni' => 'required|string',
            'email' => 'required|string',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            Log::info("Error de validacion: " . $validator->errors());
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $nombre = $params['nombre'];
        $apellido = $params['apellido'];
        $dni = $params['dni'];
        $email = $params['email'];

        $objUserExistente = User::where('email', $email)->first();
        if ($objUserExistente) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_ENCONTRADO_USUARIO_EMAIL'], null, 409);
        }

        $objUserBD = new User();
        $objUserBD->email = $email;
        $objUserBD->nombre = $nombre;
        $objUserBD->apellido = $apellido;
        $objUserBD->dni = $dni;
        $objUserBD->habilitado = 1;
        $objUserBD->password = bcrypt(sha1('123456'));
        $objUserBD->rol = "agrimensor";
        $objUserBD->save();

        $objDevolver = $objUserBD->id;

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $objDevolver,
            200
        );
    }
    
    public function postValidarCreacionApp(Request $request)
    {
        $input = $request->all();
        Log::info($input);
        $valRules = [
            'email' => 'required|string',
            'password' => 'required|string',
            'deviceUuid' => 'required|string',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            Log::info("Error de validacion: " . $validator->errors());
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $email = $params['email'];
        $password = $params['password'];
        $deviceUuid = $params['deviceUuid'];

        $objUser = User::where('email', $email)->first();
        if (!$objUser) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_NO_ENCONTRADO'], null, 409);
        }

        $objUserExistente = User::where('email', $email)->where('registrado', 1)->first();
        if ($objUserExistente) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_ENCONTRADO_REGISTRADO'], null, 409);
        }

        $objUserExistente = User::where('email', $email)->where('registrado', 0)->where('habilitado', 0)->first();
        if ($objUserExistente) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_ENCONTRADO_DESHABILITADO'], null, 409);
        }


        $loginApiCpiaResponse = User::postLoginApiCpia($email, $password);
        Log::info($loginApiCpiaResponse);
        if ($loginApiCpiaResponse['status'] != "OK") {
            return new Respuesta(-2, $loginApiCpiaResponse['message'], null, 409);
        }

        $devolver = true;

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $devolver,
            200
        );
    }
    public function postCreateFromApp(Request $request)
    {
        $input = $request->all();
        Log::info($input);
        $valRules = [
            'email' => 'required|string',
            'password' => 'required|string',
            'deviceUuid' => 'required|string',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            Log::info("Error de validacion: " . $validator->errors());
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $email = $params['email'];
        $password = $params['password'];
        $deviceUuid = $params['deviceUuid'];

        $objUser = User::where('email', $email)->first();
        if (!$objUser) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_NO_ENCONTRADO'], null, 409);
        }

        $objUserExistente = User::where('email', $email)->where('registrado', 1)->first();
        if ($objUserExistente) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_ENCONTRADO_REGISTRADO'], null, 409);
        }

        $objUserExistente = User::where('email', $email)->where('registrado', 0)->where('habilitado', 0)->first();
        if ($objUserExistente) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_ENCONTRADO_DESHABILITADO'], null, 409);
        }


        $loginApiCpiaResponse = User::postLoginApiCpia($email, $password);
        Log::info($loginApiCpiaResponse);
        if ($loginApiCpiaResponse['status'] != "OK") {
            return new Respuesta(-2, $loginApiCpiaResponse['message'], null, 409);
        }

        $idMatricula = null;
        $nroMatricula = null;
        if (count($loginApiCpiaResponse['matriculas'])) {
            foreach ($loginApiCpiaResponse['matriculas'][0] as $key => $value) {
                if (array_key_exists('matricula', $value)) {
                    if (array_key_exists('idMatricula', $value['matricula']) && array_key_exists('nroMatricula', $value['matricula'])) {
                        $idMatricula = $value['matricula']['idMatricula'];
                        $nroMatricula = $value['matricula']['nroMatricula'];
                        break;
                    }
                }
            }
        }

        $objUser->email = $email;
        $objUser->password = bcrypt(sha1($password));
        $objUser->registrado = 1;
        $objUser->nro_matricula = $nroMatricula;
        $objUser->id_matricula = $idMatricula;
        $objUser->device_uuid = $deviceUuid;
        $objUser->api_token = $loginApiCpiaResponse['token'];
        $objUser->save();

        $token = null;

        try {
            if (!$token = JWTAuth::attempt(['email' => $email, 'password' => sha1($password)])) {
                Log::info($email . ' --++-- ' . sha1($password));
                return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_CREDENCIALES'], null, 409);
            }
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return new Respuesta(-3, MensajesRespuesta::respuestas['ERROR_CREDENCIALES'], $th->getMessage(), 409);
        }

        $devolver = Utils::ajustarObjeto($objUser);
        $devolver['token'] = $token;

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $devolver,
            200
        );
    }
    public function postHabilitar(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'id' => 'required|integer',
            'habilitado' => 'required|integer',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            Log::info("Error de validacion: " . $validator->errors());
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'];
        $habilitado = $params['habilitado'];

        $objUser = User::where('id', $id)->first();
        if (!$objUser) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_NO_ENCONTRADO'], null, 409);
        }

        $objUser = User::where('id', $id)->where('habilitado', "!=", $habilitado)->first();
        if (!$objUser) {
            if ($habilitado){
                return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_YA_HABILITADO'], null, 409);
            }
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_NO_HABILITADO'], null, 409);
        }

        $objUser->habilitado = $habilitado;
        $objUser->save();

        $objDevolver = $objUser->id;

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $objDevolver,
            200
        );
    }
    public function postActualizacionUuid(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'id' => 'required|integer',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            Log::info("Error de validacion: " . $validator->errors());
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'];

        $objUser = User::where('id', $id)->where('device_uuid', '!=', null)->first();
        if (!$objUser) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_USUARIO_SIN_ACT_UUID'], null, 409);
        }

        $objUser->device_uuid = null;
        $objUser->save();

        $objDevolver = $objUser->id;

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $objDevolver,
            200
        );
    }
}
