<?php

namespace App\Http\Controllers;

use App\Http\Clases\App;
use App\Http\Clases\Constantes;
use App\Http\Clases\MensajesRespuesta;
use App\Http\Clases\Utils;
use App\Http\Clases\Respuesta;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\MovimientoCredito;
use App\Pago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['postCreate']]);
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
            'id' => 'sometimes|numeric'
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        //Parametros
        
        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'] ? $params['id'] : $loggedUserID;

        //Obtiene usuario
        $objUserBD = User::with('rol')->find($id);

        if (!$objUserBD) {
            return new Respuesta(-2, 'Error', MensajesRespuesta::respuestas['ERROR_NO_ENCONTRADO'], 404);
        }

        $objDevolver = Utils::ajustarObjeto($objUserBD);

        return new Respuesta(
            1,
            'Ok',
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
            'password' => 'required|string',
            'email' => 'required|string',
            'name' => 'required|string',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            Log::info("Error de validacion: " . $validator->errors());
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $password = $params['password'];
        $email = $params['email'];
        $name = $params['name'];


        $objUserExistente = User::where('email', $email)->first();
        if ($objUserExistente) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_ENCONTRADO'], null, 409);
        }

        $objUserBD = new User();
        $objUserBD->name = $name;
        $objUserBD->password = bcrypt($password);
        $objUserBD->email = $email;
        $objUserBD->save();

        $objDevolver = $objUserBD->id;

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $objDevolver,
            200
        );
    }
}
