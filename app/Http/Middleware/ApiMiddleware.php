<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class ApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle($request, Closure $next)
    {
        DB::beginTransaction();
        try {
            $response = $next($request);
            $responseObject = json_decode($response->getContent(), true);
            if ($responseObject == null) {
                DB::rollBack();
                return response()->json(["codigo" => 500, "datos" => null], 500);
            } else {
                if ($responseObject['estado'] <= 0) {
                    DB::rollBack();
                    return response()->json(["codigo" => $responseObject['codigo'], "excepcion" => $responseObject['mensaje'], "datos" => $responseObject['datos']], $responseObject['codigo'] ? $responseObject['codigo'] : 500);
                }
                DB::commit();
                return response()->json(["datos" => $responseObject['datos']], $responseObject['codigo'] ? $responseObject['codigo'] : 200);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(["codigo" => 500, "excepcion" => $e->getMessage(), "datos" => null], 500);
        }

        return json_decode($response);
    }
}
