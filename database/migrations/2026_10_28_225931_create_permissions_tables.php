<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('permission', 40);
            $table->unique(['role', 'permission']);
        });

        // Member ko role ke upar extra permission
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 40);
            $table->unique(['user_id', 'permission']);
        });

        $rows = [];
        foreach (Permission::defaults() as $role => $permissions) {
            foreach ($permissions as $p) {
                $rows[] = ['role' => $role, 'permission' => $p->value];
            }
        }
        DB::table('role_permissions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
    }
};
