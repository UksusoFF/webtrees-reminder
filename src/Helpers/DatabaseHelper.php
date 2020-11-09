<?php

namespace UksusoFF\WebtreesModules\Reminder\Helpers;

use Illuminate\Database\Capsule\Manager as DB;

class DatabaseHelper
{
    public function getUserList(int $start, int $length): array
    {
        return [
            DB::table('user')
                ->where('user_id', '>', 0)
                ->skip($start)
                ->take($length)
                ->get([
                    'user_id',
                    'real_name',
                    'email',
                ]),
            DB::table('user')
                ->where('user_id', '>', 0)
                ->count(),
        ];
    }
}
