<?php

namespace App\Http\Clases;

class MensajesRespuesta {
    const respuestas = [
        "OK_1" => "Ok",
        "ERROR" => "Error",
        "ERROR_VALIDACION" => "Error de validación",
        "ERROR_NO_ENCONTRADO" => "Item no encontrado",
        "ERROR_ENCONTRADO" => "Item ya existente",
        "ERROR_USUARIO_NO_HABILITADO" => "Usuario no habilitado",
        "ERROR_USUARIO_INEXISTENTE" => "Usuario inexistente",
        "ERROR_CREDENCIALES" => "Credenciales incorrectas",
        "ERROR_OT_EXISTENTE" => "Ya existe una OT con este Numero de OT",
    ];
}

?>