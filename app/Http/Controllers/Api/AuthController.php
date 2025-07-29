<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\SessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    protected $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Đăng ký customer mới
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:customers',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customer = Customer::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
            ]);

            // Tạo session cho customer mới đăng ký
            $sessionId = $this->sessionService->createSession($customer->id, [
                'name' => $customer->name,
                'email' => $customer->email,
            ]);

            // Cập nhật last login
            $customer->updateLastLogin();

            return response()->json([
                'success' => true,
                'message' => 'Customer registered successfully',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'created_at' => $customer->created_at,
                    ],
                    'session_id' => $sessionId,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Đăng nhập customer
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customer = Customer::where('email', $request->email)->first();

            if (!$customer || !$customer->checkPassword($request->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Tạo session mới
            $sessionId = $this->sessionService->createSession($customer->id, [
                'name' => $customer->name,
                'email' => $customer->email,
            ]);

            // Cập nhật last login
            $customer->updateLastLogin();

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'last_login_at' => $customer->last_login_at,
                    ],
                    'session_id' => $sessionId,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Đăng xuất customer (xóa session hiện tại)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->header('X-Session-ID') ?: $request->get('session_id');

            if (!$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session ID is required'
                ], 400);
            }

            $this->sessionService->destroySession($sessionId);

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thông tin customer hiện tại
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $customer = $request->auth_customer; // Được set bởi middleware

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'last_login_at' => $customer->last_login_at,
                        'created_at' => $customer->created_at,
                        'updated_at' => $customer->updated_at,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get customer info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách session đang hoạt động
     */
    public function activeSessions(Request $request): JsonResponse
    {
        try {
            $customer = $request->auth_customer;
            $sessions = $this->sessionService->getActiveSessions($customer->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'sessions' => $sessions,
                    'total' => count($sessions)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get active sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Đăng xuất tất cả session (logout all devices)
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $customer = $request->auth_customer;
            $this->sessionService->destroyAllUserSessions($customer->id);

            return response()->json([
                'success' => true,
                'message' => 'All sessions have been terminated'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout all sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
