<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HandleCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request):\Illuminate\Http\Response  $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $allowedOrigins = config('cors.allowed_origins', [
            'http://localhost:5173',
            'http://localhost:5174',
            'http://localhost:3000',
            'http://localhost:8080',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:5174',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
            'https://web-production-e6965.up.railway.app',
        ]);

        $origin = $request->header('Origin');
        
        // Check if origin is in allowed list or matches pattern
        $isAllowed = false;
        
        if (in_array($origin, $allowedOrigins)) {
            $isAllowed = true;
        } else {
            // Check patterns
            $patterns = config('cors.allowed_origins_patterns', [
                '#^https://.*\.up\.railway\.app$#',
            ]);
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $origin)) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if ($isAllowed) {
            $response = $request->isMethod('OPTIONS') 
                ? new Response('', 204)
                : $next($request);

            $response->header('Access-Control-Allow-Origin', $origin);
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-Token');
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Max-Age', '3600');
            
            return $response;
        }

        return $next($request);
    }
}
