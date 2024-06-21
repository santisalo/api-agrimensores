<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class OrdenTrabajo extends Model
{
    protected $table = 'orden_trabajo';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_ot',
        'estado_orden_trabajo',
        'geo',
        'fecha_hora_carga',
        'fecha_hora_validacion',
        'fecha_hora_envio'
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
        'user_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    public static function getOrdenTrabajoApiCpia($nroOrdenTrabajo, $idMatricula, $token)
    {
        // make a GET to the getOt route
        $client = new \GuzzleHttp\Client([
            'verify' => false,
        ]);
        $response = $client->get('https://apiapp.nubecpia.com.ar/api/ordenTrabajo?idMatricula='.$idMatricula.'&nroOrdenTrabajo='.$nroOrdenTrabajo
            , [
                'headers' => [
                    'Authorization' => 'Bearer '.$token
                ]
            ]);
        $respuesta = $response->getBody()->getContents();
        return json_decode($respuesta, true);
    }
    public static function getOrdenesTrabajoApiCpia($idMatricula, $token)
    {
        // make a GET to the getOt route
        $client = new \GuzzleHttp\Client([
            'verify' => false,
        ]);
        $response = $client->get('https://apiapp.nubecpia.com.ar/api/ordenesTrabajo?idMatricula='.$idMatricula
            , [
                'headers' => [
                    'Authorization' => 'Bearer '.$token
                ]
            ]);
        $respuesta = $response->getBody()->getContents();
        return json_decode($respuesta, true);
    }
    public static function postLoginApiCpia($idMatricula, $nroOrdenTrabajo, $latitud, $longitud, $fechaHora, $token)
    {
        // make a POST to the login route
        $client = new \GuzzleHttp\Client([
            'verify' => false,
        ]);
        $response = $client->post('https://apiapp.nubecpia.com.ar/api/ordenTrabajo/registroGeo', [
            'form_params' => [
                'idMatricula' => $idMatricula,
                'nroOrdenTrabajo' => $nroOrdenTrabajo,
                'latitud' => $latitud,
                'longitud' => $longitud,
                'fechaHora' => $fechaHora
            ],
            'headers' => [
                'Authorization' => 'Bearer '.$token
            ]
        ],);
        $respuesta = $response->getBody()->getContents();
        return json_decode($respuesta, true);
    }
}
