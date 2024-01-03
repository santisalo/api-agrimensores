<?php

namespace App\Http\Controllers;

use App\Http\Clases\Constantes;
use App\Http\Clases\MensajesRespuesta;
use App\Http\Clases\Utils;
use App\Http\Clases\Respuesta;
use App\Http\Controllers\Controller;
use App\Models\DetalleMovimientoStock;
use App\Models\MovimientoStock;
use App\Models\OrdenTrabajo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrdenTrabajoController extends Controller
{
    public function getTodos(Request $request)
    {

        $input = $request->all();

        //Validator
        $valRules = [
            'userId' => 'integer|required',
            'fechaDesde' => 'string|nullable',
            'fechaHasta' => 'string|nullable',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $userId = $params['userId'];
        $fechaDesde = $params['fechaDesde'];
        $fechaHasta = $params['fechaHasta'];

        //Parametros
        $listaBD = OrdenTrabajo::when($userId, function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->when($fechaDesde, function ($query) use ($fechaDesde) {
                $query->where('fecha_hora_envio', '>=', $fechaDesde);
            })
            ->when($fechaHasta, function ($query) use ($fechaHasta) {
                $query->where('fecha_hora_envio', '<=', $fechaHasta);
            })
            ->orderBy('fecha_hora_envio', 'desc')
            ->get();


        $listaDevolver = collect();
        if ($listaBD) {
            foreach ($listaBD as $item) {
                $obj = Utils::ajustarObjeto($item);
                $listaDevolver->push($obj);
            }
        }

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $listaDevolver,
            200
        );
    }

    public function getTodosSinPaginacion()
    {

        $listaBD = OrdenTrabajo::get();

        $listaDevolver = collect();
        if ($listaBD) {
            foreach ($listaBD as $item) {
                $listaDevolver->push(Utils::ajustarObjeto($item));
            }
        }

        $devolver = $listaDevolver;

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $devolver,
            200
        );
    }

    public function getTodosPaginado(Request $request)
    {
        $cantPorPag = Constantes::ITEMS_POR_PAGINA;
        $input = $request->all();

        //Validator
        $valRules = [
            'nombre' => 'string|sometimes|required',
            'userId' => 'integer|sometimes|required',
            'nroPagina' => 'string|sometimes|required',
            'options' => 'json|sometimes|required',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);

        $nombre = $params['nombre'] ? $params['nombre'] : null;
        $userId = $params['userId'] ? $params['userId'] : null;
        $options = $params['options'];

        Log::info($options);

        $nroPagina = $options['page'] ? $options['page'] : 1;
        $cantPorPag = $options['itemsPerPage'] ? $options['itemsPerPage'] : Constantes::ITEMS_POR_PAGINA;

        $listaBD = OrdenTrabajo::when($options['search']['valor'], function ($query) use ($options) {
            $query->where($options['search']['campo'], 'LIKE', "%" . $options['search']['valor'] . "%");
        })
            ->when($options && array_key_exists('categoriaId', $options) && $options['categoriaId'], function ($query) use ($options) {
                $query->where('categoria_id', $options['categoriaId']);
            })
            ->when($userId, function ($query) use ($userId) {
                $query->where('user_id', $userId);
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
                $listaDevolver->push(Utils::ajustarObjeto($item));
            }
        }

        $devolver = [
            "datos" => $listaDevolver,
            "pagTotal" => $pagTotal,
            "pagActual" => $pagActual,
            "pagTotalItems" => $pagTotalItems
        ];

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $devolver,
            200
        );
    }

    public function postCreate(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'idOt' => 'required',
            'geo' => 'required',
            'fechaHoraCarga' => 'required',
            'fechaHoraValidacion' => 'required',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $idOt = $params['idOt'];
        $geo = $params['geo'];
        $fechaHoraCarga = $params['fechaHoraCarga'];
        $fechaHoraValidacion = $params['fechaHoraValidacion'];

        $objOrdenTrabajo = OrdenTrabajo::where('id_ot', $idOt)->exists();
        if ($objOrdenTrabajo) {
            return new Respuesta(-3, MensajesRespuesta::respuestas['ERROR_OT_EXISTENTE'], null, 409);
        }

        // get user
        $user = auth()->user();
        $token = $user->api_token;

        $respuestaOrdenTrabajo = OrdenTrabajo::postLoginApiCpia($user->id_matricula, $idOt, explode(',', $geo)[0], explode(',', $geo)[1], $fechaHoraValidacion, $token);

        Log::info($respuestaOrdenTrabajo);

        $objOrdenTrabajoNuevo = new OrdenTrabajo();
        $objOrdenTrabajoNuevo->id_ot = $idOt;
        $objOrdenTrabajoNuevo->user_id = $user->id;
        $objOrdenTrabajoNuevo->geo = $geo;
        $objOrdenTrabajoNuevo->fecha_hora_carga = $fechaHoraCarga;
        $objOrdenTrabajoNuevo->fecha_hora_validacion = $fechaHoraValidacion;
        $objOrdenTrabajoNuevo->fecha_hora_envio = date('Y-m-d H:i:s');
        $objOrdenTrabajoNuevo->estado_orden_trabajo = Constantes::ESTADO_ORDEN_TRABAJO_ENVIADO_ID;
        $objOrdenTrabajoNuevo->save();

        $objDevolver = $objOrdenTrabajoNuevo->id;
        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $objDevolver,
            200
        );
    }

    public function putUpdate(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'id' => 'integer|required',
            'nombre' => 'required|string',
            'descripcion' => 'nullable|string',
            'costo' => 'required|numeric',
            'impuestoId' => 'required|numeric',
            'categoriaId' => 'nullable|numeric',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'];
        $nombre = $params['nombre'];
        $descripcion = $params['descripcion'];
        $costo = $params['costo'];
        $impuestoId = $params['impuestoId'];
        $categoriaId = $params['categoriaId'];


        $objOrdenTrabajoBD = OrdenTrabajo::find($id);

        if (!$objOrdenTrabajoBD) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_NO_ENCONTRADO'], null, 404);
        }

        $objOrdenTrabajo = OrdenTrabajo::where('nombre', $nombre)->where('id', '!=', $id)->exists();
        if ($objOrdenTrabajo) {
            return new Respuesta(-3, MensajesRespuesta::respuestas['ERROR_NOMBRE_EXISTENTE'], null, 409);
        }

        $objOrdenTrabajoEdicion = OrdenTrabajo::find($id);
        $objOrdenTrabajoEdicion->nombre = $nombre;
        $objOrdenTrabajoEdicion->descripcion = $descripcion;
        $objOrdenTrabajoEdicion->costo = $costo;
        $objOrdenTrabajoEdicion->impuesto_id = $impuestoId;
        $objOrdenTrabajoEdicion->categoria_id = $categoriaId;
        $objOrdenTrabajoEdicion->save();

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            null,
            200
        );
    }
    public function putUpdateCosto(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'id' => 'integer|required',
            'costoNuevo' => 'required|string',
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'];
        $costoNuevo = $params['costoNuevo'];

        $objOrdenTrabajoBD = OrdenTrabajo::find($id);

        if (!$objOrdenTrabajoBD) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_NO_ENCONTRADO'], null, 404);
        }

        $objOrdenTrabajoEdicion = OrdenTrabajo::find($id);
        $objOrdenTrabajoEdicion->costo = $costoNuevo;
        $objOrdenTrabajoEdicion->save();

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            null,
            200
        );
    }

    public function getDatosRemoto(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'nroOrdenTrabajo' => 'string|required',
        ];

        $validator = Validator::make($input, $valRules);
        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $nroOrdenTrabajo = $params['nroOrdenTrabajo'];
        $idMatricula = null;
        $token = null;

        try {
            $user = auth()->user();
            $idMatricula = $user->id_matricula;
            $token = $user->api_token;
        } catch (\Throwable $th) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_TOKEN'], $validator->errors(), 422);
        }

        $ordenTrabajo = OrdenTrabajo::getOrdenTrabajoApiCpia($nroOrdenTrabajo, $idMatricula, $token);

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $ordenTrabajo,
            200
        );
    }

    
    public function getTodosRemoto()
    {
        try {
            $user = auth()->user();
            $idMatricula = $user->id_matricula;
            $token = $user->api_token;
        } catch (\Throwable $th) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_TOKEN'], MensajesRespuesta::respuestas['ERROR_TOKEN'], 422);
        }

        $ordenesTrabajo = OrdenTrabajo::getOrdenesTrabajoApiCpia($idMatricula, $token);

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $ordenesTrabajo,
            200
        );
    }

    public function getDatos(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'id' => 'integer|required',
        ];

        $validator = Validator::make($input, $valRules);
        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'];

        $listaBD = OrdenTrabajo::find($id);

        $devolver = Utils::ajustarObjeto($listaBD);

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            $devolver,
            200
        );
    }

    public function postDelete(Request $request)
    {
        $input = $request->all();
        $valRules = [
            'id' => 'required|numeric'
        ];

        $validator = Validator::make($input, $valRules);

        if ($validator->fails()) {
            return new Respuesta(-1, MensajesRespuesta::respuestas['ERROR_VALIDACION'], $validator->errors(), 422);
        }

        //Parametros

        $params = Utils::transformaValRules($valRules, $request);
        $id = $params['id'];

        //Obtiene
        $objOrdenTrabajoBD = OrdenTrabajo::find($id);

        if (!$objOrdenTrabajoBD) {
            return new Respuesta(-2, MensajesRespuesta::respuestas['ERROR_NO_ENCONTRADO'], null, 404);
        }

        $objOrdenTrabajoPedido = OrdenTrabajoPedido::where('ordentrabajo_id', $id)->get();
        if ($objOrdenTrabajoPedido->count()) {
            return new Respuesta(-3, MensajesRespuesta::respuestas['ERROR_ORDENTRABAJO_NO_ELIMINABLE'], null, 409);
        }

        $arrayUniqueMovimientosStockIds = [];
        $objDetalleMovimientoStock = DetalleMovimientoStock::where('ordentrabajo_id', $id)->get();
        foreach ($objDetalleMovimientoStock as $item) {
            if (!in_array($item->movimiento_stock_id, $arrayUniqueMovimientosStockIds)) {
                $arrayUniqueMovimientosStockIds[] = $item->movimiento_stock_id;
            }
            $item->delete();
        }

        $objMovimientoStock = MovimientoStock::where('id', $arrayUniqueMovimientosStockIds)->get();
        foreach ($objMovimientoStock as $item) {
            $item->delete();
        }

        $objOrdenTrabajoBD->delete();

        return new Respuesta(
            1,
            MensajesRespuesta::respuestas['OK_1'],
            null,
            200
        );
    }
}
