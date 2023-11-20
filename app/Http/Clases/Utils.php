<?php

namespace App\Http\Clases;

class Utils
{
    /* AJUSTAR OBJETO */
    // Ajustar objeto que trae Eloquent para mandarselo al Front end
    // Transforma a camel case
    // Las reglas establecen que campos se van quitar, por defecto: created_at, updated_at, deleted_at
    public static function ajustarObjeto($obj, $reglasUnset = ['created_at', 'updated_at', 'deleted_at', 'created_user_id', 'updated_user_id', 'deleted_user_id'])
    {
        //Ahora si la funcion en sí
        $arr = json_decode(json_encode($obj), true);
        self::recursive_unset($arr, $reglasUnset);
        return self::mapArrayKeys($arr);
    }

    // Quita las propiedades de created_at, updated_at y deleted_at
    private static function recursive_unset(&$array, $reglasUnset)
    {
        foreach ($reglasUnset as $key => $regla) {
            unset($array[$regla]);
        }
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::recursive_unset($value, $reglasUnset);
            }
        }
    }

    // Remapea las keys del arreglo
    private static function mapArrayKeys(array $xs)
    {
        $out = array();
        foreach ($xs as $key => $value) {
            if (explode('_', $key)[0] == 'json') {
                $out[self::underToCamel($key)] = $value ? json_decode($value) : null;
            }
            $out[self::underToCamel($key)] = is_array($value) ? self::mapArrayKeys($value) : $value;
        }
        return $out;
    }

    // snake case a camel case (los Id los hace ID)
    private static function underToCamel($str)
    {
        $ucF = array_map('ucfirst', explode('_', $str));
        foreach ($ucF as $key => $val) {
            if ($val == 'Id' && $key > 0) {
                $ucF[$key] = 'Id';
            }
        }
        return lcfirst(implode('', $ucF));
    }

    /* FIN AJUSTAR OBJETO */
    public static function ajustarArreglo($valores, $indice)
    {
        $array = [];
        foreach ($valores as $key => $val) {
            $array[] = $val[$indice];
        }
        return $array;
    }

    /* Transforma VALRULES en Variables */
    // Controla nulo
    // En String: Revisa casos especiales (nombre, apellido, etc: a uppercase. email, usuario: a lowercase)
    // En Json: hace el json_decode
    // En Numeric: revisa si es int o float y los castea
    // En Integer: castea a int
    public static function transformaValRules($rules, $request)
    {
        $variables = [];

        // Iterar todas las reglas
        foreach ($rules as $key => $regla) {
            $variables[$key] = null;

            if ($key == "nroPagina") {
                // Si es nroPagina, al no traer el param tiene que defaultear a 1
                // sino a null
                $variables[$key] = $request->has($key) ? intval($request->input($key)) : 1;
            } else if (strpos($regla, 'mimes:') !== false) {
                // verificar si es archivo
                $variables[$key] = $request->hasFile($key) ? $request->file($key) : null;
            } else {
                $variables[$key] = $request->has($key) ? $request->input($key) : null;
            }

            // Procesamiento posterior para convertir los valores
            if ($variables[$key] != null) {
                if (strpos($regla, 'string') !== false) {
                    if (in_array($key, array("username", "usuario", "email"))) {
                        $variables[$key] = strtolower(trim($variables[$key]));
                    }
                    if (in_array($key, array("apellido", "nombre"))) {
                        $variables[$key] = trim($variables[$key]);
                    }
                }
                if (strpos($regla, 'json') !== false) {
                    $variables[$key] = json_decode($variables[$key], true);
                }
                if (strpos($regla, 'numeric') !== false) {
                    if ((int) $variables[$key] == $variables[$key]) {
                        $variables[$key] = intval($variables[$key]);
                    } else {
                        $variables[$key] = floatval($variables[$key]);
                    }
                }
                if (strpos($regla, 'integer') !== false) {
                    $variables[$key] = intval($variables[$key]);
                }
                if (strpos($regla, 'boolean') !== false) {
                    if ($variables[$key] === true) {
                        $variables[$key] = 1;
                    } else if ($variables[$key] === false) {
                        $variables[$key] = 0;
                    } else {
                        $variables[$key] = $variables[$key] == 'false' || $variables[$key] == '0' ? 0 : 1;
                    }
                }
            }

            // En caso de ser string, verificar de que no sea "null"
            // para cuando se manda con FormData
            if ($variables[$key] === "null" || $variables[$key] === "undefined") {
                $variables[$key] = null;
            }

            // Cuando la regla es "sometimes" el validator no valida
            // los vacios. Devolver nulo si el valor es vacio.
            if ($variables[$key] === "" && strpos($regla, 'sometimes') !== false) {
                $variables[$key] = null;
            }
        }

        return $variables;
    }
}
