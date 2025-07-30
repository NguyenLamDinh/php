<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SessionService
{
    private $sessionPrefix = 'customer_session:';
    private $sessionExpire = 7200; // 2 giờ (7200 giây)

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

        // Lưu session vào database cache table
        DB::table('cache')->updateOrInsert(
            ['key' => $sessionKey],
            [
                'value' => json_encode($sessionData),
                'expiration' => Carbon::now()->addSeconds($this->sessionExpire)->timestamp
            ]
        );

        return $sessionId;
    }

    /**
     * Lấy thông tin session
     */
    public function getSession($sessionId)
    {
        $sessionKey = $this->sessionPrefix . $sessionId;

        $session = DB::table('cache')
            ->where('key', $sessionKey)
            ->where('expiration', '>', Carbon::now()->timestamp)
            ->first();

        if (!$session) {
            return null;
        }

        $sessionData = json_decode($session->value, true);

        // Cập nhật last activity
        $this->updateLastActivity($sessionId);

        return $sessionData;
    }

    /**
     * Cập nhật thời gian hoạt động cuối
     */
    public function updateLastActivity($sessionId)
    {
        $sessionKey = $this->sessionPrefix . $sessionId;

        $session = DB::table('cache')->where('key', $sessionKey)->first();

        if ($session) {
            $sessionData = json_decode($session->value, true);
            $sessionData['last_activity'] = Carbon::now()->toISOString();

            DB::table('cache')
                ->where('key', $sessionKey)
                ->update([
                    'value' => json_encode($sessionData),
                    'expiration' => Carbon::now()->addSeconds($this->sessionExpire)->timestamp
                ]);
        }
    }

    /**
     * Xóa session
     */
    public function destroySession($sessionId)
    {
        $sessionKey = $this->sessionPrefix . $sessionId;
        DB::table('cache')->where('key', $sessionKey)->delete();
    }

    /**
     * Lấy tất cả session đang hoạt động của customer
     */
    public function getActiveSessions($customerId)
    {
        $sessions = DB::table('cache')
            ->where('key', 'like', $this->sessionPrefix . '%')
            ->where('expiration', '>', Carbon::now()->timestamp)
            ->get();

        $activeSessions = [];

        foreach ($sessions as $session) {
            $sessionData = json_decode($session->value, true);

            if ($sessionData && $sessionData['customer_id'] == $customerId) {
                $sessionId = str_replace($this->sessionPrefix, '', $session->key);
                $activeSessions[] = [
                    'session_id' => $sessionId,
                    'created_at' => $sessionData['created_at'],
                    'last_activity' => $sessionData['last_activity'],
                    'ip_address' => $sessionData['ip_address'],
                    'user_agent' => $sessionData['user_agent'],
                ];
            }
        }

        return $activeSessions;
    }

    /**
     * Xóa tất cả session của customer
     */
    public function destroyAllUserSessions($customerId)
    {
        $sessions = DB::table('cache')
            ->where('key', 'like', $this->sessionPrefix . '%')
            ->get();

        foreach ($sessions as $session) {
            $sessionData = json_decode($session->value, true);

            if ($sessionData && $sessionData['customer_id'] == $customerId) {
                DB::table('cache')->where('key', $session->key)->delete();
            }
        }
    }

    /**
     * Kiểm tra session có tồn tại và hợp lệ không
     */
    public function isValidSession($sessionId)
    {
        return $this->getSession($sessionId) !== null;
    }

    /**
     * Tạo session ID ngẫu nhiên
     */
    private function generateSessionId()
    {
        return Str::random(64);
    }

    /**
     * Làm sạch session hết hạn
     */
    public function cleanExpiredSessions()
    {
        DB::table('cache')
            ->where('key', 'like', $this->sessionPrefix . '%')
            ->where('expiration', '<=', Carbon::now()->timestamp)
            ->delete();
    }
}
