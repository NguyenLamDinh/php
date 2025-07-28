<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SessionService
{
    private $redis;
    private $sessionPrefix = 'customer_session:';
    private $sessionExpire = 7200; // 2 giờ (7200 giây)

    public function __construct()
    {
        $this->redis = Redis::connection('session');
    }

    /**
     * Tạo session mới cho customer
     */
    public function createSession($customerId, $customerData = [])
    {
        $sessionId = $this->generateSessionId();
        $sessionKey = $this->sessionPrefix . $sessionId;

        $sessionData = [
            'customer_id' => $customerId,
            'customer_data' => $customerData,
            'created_at' => Carbon::now()->toISOString(),
            'last_activity' => Carbon::now()->toISOString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent', ''),
        ];

        // Lưu session vào Redis với thời gian expire
        $this->redis->setex($sessionKey, $this->sessionExpire, json_encode($sessionData));

        return $sessionId;
    }

    /**
     * Lấy thông tin session
     */
    public function getSession($sessionId)
    {
        $sessionKey = $this->sessionPrefix . $sessionId;
        $sessionData = $this->redis->get($sessionKey);

        if (!$sessionData) {
            return null;
        }

        $data = json_decode($sessionData, true);

        // Cập nhật last_activity và gia hạn session
        $data['last_activity'] = Carbon::now()->toISOString();
        $this->redis->setex($sessionKey, $this->sessionExpire, json_encode($data));

        return $data;
    }

    /**
     * Xóa session (logout)
     */
    public function destroySession($sessionId)
    {
        $sessionKey = $this->sessionPrefix . $sessionId;
        return $this->redis->del($sessionKey);
    }

    /**
     * Kiểm tra session có tồn tại không
     */
    public function sessionExists($sessionId)
    {
        $sessionKey = $this->sessionPrefix . $sessionId;
        return $this->redis->exists($sessionKey);
    }

    /**
     * Xóa tất cả session của một customer
     */
    public function destroyAllUserSessions($customerId)
    {
        $pattern = $this->sessionPrefix . '*';
        $keys = $this->redis->keys($pattern);

        foreach ($keys as $key) {
            $sessionData = $this->redis->get($key);
            if ($sessionData) {
                $data = json_decode($sessionData, true);
                if ($data['customer_id'] == $customerId) {
                    $this->redis->del($key);
                }
            }
        }
    }

    /**
     * Tạo session ID ngẫu nhiên
     */
    private function generateSessionId()
    {
        return Str::random(60) . '_' . time();
    }

    /**
     * Lấy danh sách session đang active của customer
     */
    public function getActiveSessions($customerId)
    {
        $pattern = $this->sessionPrefix . '*';
        $keys = $this->redis->keys($pattern);
        $sessions = [];

        foreach ($keys as $key) {
            $sessionData = $this->redis->get($key);
            if ($sessionData) {
                $data = json_decode($sessionData, true);
                if ($data['customer_id'] == $customerId) {
                    $sessions[] = [
                        'session_id' => str_replace($this->sessionPrefix, '', $key),
                        'created_at' => $data['created_at'],
                        'last_activity' => $data['last_activity'],
                        'ip_address' => $data['ip_address'],
                    ];
                }
            }
        }

        return $sessions;
    }
}
