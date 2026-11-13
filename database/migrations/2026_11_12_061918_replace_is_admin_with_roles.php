<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Move admins from the is_admin column to Spatie roles: "admin" and "user".
     */
    public function up(): void
    {
        $roleIds = [
            'admin' => Role::findOrCreate('admin', 'web')->id,
            'user' => Role::findOrCreate('user', 'web')->id,
        ];

        // Plain inserts, so this does not depend on the User model as it changes later.
        DB::table('users')->select(['id', 'is_admin'])->orderBy('id')->each(function (object $user) use ($roleIds): void {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $user->is_admin ? $roleIds['admin'] : $roleIds['user'],
                'model_type' => 'App\\Models\\User',
                'model_id' => $user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        $adminIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'admin')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->pluck('model_has_roles.model_id');

        DB::table('users')->whereIn('id', $adminIds)->update(['is_admin' => true]);
    }
};
