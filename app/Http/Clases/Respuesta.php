<?php

namespace App\Http\Clases;

class Respuesta
{
    private $estado;
    private $mensaje;
    private $codigo;
    private $datos;
    private $excepcion;

    public function __construct(
        $estado,
        $mensaje,
        $datos,
        $codigo = 200,
        $excepcion = null
    ) {
        $this->estado = $estado;
        $this->codigo = $codigo;

        if ($mensaje != null) {
            $this->mensaje = $mensaje;
        } else {
            if ($estado > 0) {
                $this->mensaje = "Ok";
            } else {
                $this->mensaje = "Error";
            }
        }
        $this->excepcion = $excepcion;
        $this->datos = $datos;
    }

    public function __toString()
    {
        return json_encode(["datos" => $this->datos, "estado" => $this->estado, "mensaje" => $this->mensaje, "codigo" => $this->codigo, "excepcion" => $this->excepcion]);
    }

    public function getEstado()
    {
        return $this->estado;
    }
}
