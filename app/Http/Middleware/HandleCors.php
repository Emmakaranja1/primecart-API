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
        // Get configuration from config/cors.php
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowedPatterns = config('cors.allowed_origins_patterns', []);
        
        $origin = $request->header('Origin');
        
        
        if ($request->isMethod('OPTIONS')) {
            $response = new Response('', 204);
        } else {
            $response = $next($request);
        }

        
        if (!$origin) {
            return $response;
        }
        
        $isAllowed = false;
        
    
        if (in_array($origin, $allowedOrigins)) {
            $isAllowed = true;
        } 
        
        else if (!empty($allowedPatterns)) {
            foreach ($allowedPatterns as $pattern) {
                if (preg_match($pattern, $origin)) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        
        if ($isAllowed) {
            // Check if response supports header() method (normal Response)
            if (method_exists($response, 'header')) {
                $response->header('Access-Control-Allow-Origin', $origin);
                $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
                $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-Token');
                $response->header('Access-Control-Expose-Headers', 'Authorization, Content-Disposition');
                $response->header('Access-Control-Allow-Credentials', 'true');
                $response->header('Access-Control-Max-Age', '3600');
            } elseif ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
                // For file downloads (Excel), set headers via headers->set()
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-Token');
                $response->headers->set('Access-Control-Expose-Headers', 'Authorization, Content-Disposition');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Max-Age', '3600');
            }
        }
        
        return $response;
    }
}
