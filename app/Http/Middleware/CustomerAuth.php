<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SessionService;
use App\Models\Customer;

class CustomerAuth
{
    private $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    public function handle(Request $request, Closure $next)
    {
        $sessionId = $request->header('X-Session-ID') ?: $request->get('session_id');

        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Session ID required',
                'error_code' => 'SESSION_REQUIRED'
            ], 401);
        }

        $sessionData = $this->sessionService->getSession($sessionId);

        if (!$sessionData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired session',
                'error_code' => 'SESSION_INVALID'
            ], 401);
        }

        // Lấy thông tin customer
        $customer = Customer::find($sessionData['customer_id']);
        if (!$customer) {
            $this->sessionService->destroySession($sessionId);
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
                'error_code' => 'CUSTOMER_NOT_FOUND'
            ], 401);
        }

        // Thêm customer và session info vào request
        $request->merge([
            'auth_customer' => $customer,
            'auth_session_id' => $sessionId,
            'auth_session_data' => $sessionData
        ]);

        return $next($request);
    }
}
