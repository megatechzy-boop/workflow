<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Boost post: the connected user's token (for ads), ad accounts, a client's ad account, and boosts made */
    public function up(): void
    {
        Schema::create('meta_connections', function (Blueprint $table) {
            $table->id();
            $table->text('user_token'); // long-lived user token, encrypted; ads need it (page tokens can't run ads)
            $table->timestamps();
        });

        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_id', 40)->unique(); // "act_123"
            $table->string('name');
            $table->string('currency', 8)->nullable();
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('meta_ad_account_id')->nullable()->after('meta_page_id')->constrained('meta_ad_accounts')->nullOnDelete();
        });

        Schema::create('meta_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_post_id')->constrained('meta_posts')->cascadeOnDelete();
            $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->cascadeOnDelete();
            $table->unsignedInteger('daily_budget'); // rupees
            $table->unsignedTinyInteger('days');
            $table->string('city')->nullable(); // empty = whole India
            $table->unsignedSmallInteger('radius_km')->nullable();
            $table->unsignedTinyInteger('age_min');
            $table->unsignedTinyInteger('age_max');
            $table->string('status', 20)->default('active'); // active | failed
            $table->string('campaign_id', 40)->nullable();
            $table->string('adset_id', 40)->nullable();
            $table->string('ad_id', 40)->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_boosts');
        Schema::table('clients', fn (Blueprint $table) => $table->dropConstrainedForeignId('meta_ad_account_id'));
        Schema::dropIfExists('meta_ad_accounts');
        Schema::dropIfExists('meta_connections');
    }
};
