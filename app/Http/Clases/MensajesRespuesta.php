<?php

namespace App\Http\Clases;

class MensajesRespuesta
{
    const respuestas = [
        "OK_1" => "Ok",
        "ERROR" => "Error",
        "ERROR_VALIDACION" => "Error de validación",
        "ERROR_NO_ENCONTRADO" => "Item no encontrado",
        "ERROR_ENCONTRADO" => "Item ya existente",
        "ERROR_ENCONTRADO_USUARIO_EMAIL" => "Ya hay un usuario con este email",
        "ERROR_ENCONTRADO_REGISTRADO" => "Usuario ya existente",
        "ERROR_USUARIO_NO_ENCONTRADO" => "Usuario Inexistente",
        "ERROR_DEVICE_UUID" => "Dispositivo actual no habilitado, solicitar habilitación de nuevo dispositivo al CPIA",
        "ERROR_TOKEN" => "Error de Token",
        "ERROR_USUARIO_ORDENES_TRABAJO_EXISTENTES" => "El usuario tiene ordenes de trabajo cargadas",
        "ERROR_USUARIO_SIN_ACT_UUID" => "Ya tiene habilitada la actualización de dispositivo",
        "ERROR_USUARIO_NO_HABILITADO" => "Usuario no habilitado",
        "ERROR_USUARIO_YA_HABILITADO" => "Usuario ya habilitado",
        "ERROR_USUARIO_INEXISTENTE" => "Usuario inexistente",
        "ERROR_CREDENCIALES" => "Credenciales incorrectas",
        "ERROR_OT_EXISTENTE" => "Ya existe una OT con este Numero de OT",
    ];
}
