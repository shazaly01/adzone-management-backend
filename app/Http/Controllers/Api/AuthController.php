<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        // --- تحميل الصلاحيات (نفس المنطق السابق) ---
        $user->load('roles');
        if ($user->hasRole('Super Admin')) {
             $allPermissions = Permission::all();
             if ($user->roles->isNotEmpty()) {
                 $user->roles->first()->permissions = $allPermissions;
             }
        } else {
            $user->load('roles.permissions');
        }

        // --- إنشاء التوكن (هذا هو المفتاح لتطبيقات الهاتف) ---
        // نحذف التوكنات القديمة لتجنب التراكم
        $user->tokens()->delete();

        // ننشئ توكن جديد
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token, // نعيد التوكن للفرونت
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        // حذف التوكن الحالي فقط
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
