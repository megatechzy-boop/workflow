<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Monthly packages (Mega_Techzy_Packages_Final.pdf); each client has one */
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price'); // rupees per month
            $table->json('platforms');
            $table->unsignedSmallInteger('static_posts')->default(0);
            $table->unsignedSmallInteger('carousel_posts')->default(0);
            $table->unsignedSmallInteger('reels')->default(0);
            $table->json('services');
            $table->unsignedTinyInteger('min_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('notes')->constrained()->nullOnDelete();
            $table->date('package_start')->nullable()->after('package_id');
        });

        $common = ['Captions & Hashtags', 'Monthly Content Calendar'];
        $rows = [
            ['Starter', 7500, ['instagram', 'facebook', 'linkedin'], 4, 4, 4, null,
                [...$common, 'Monthly Performance Report']],
            ['Growth', 15000, ['instagram', 'facebook', 'linkedin'], 6, 6, 6, null,
                ['Content Strategy', ...$common, 'Google Business Profile: 4 posts + review replies', 'Competitor Research (quarterly)', 'Monthly Performance Report']],
            ['Scale', 35000, ['instagram', 'facebook', 'linkedin', 'youtube'], 10, 8, 10, null,
                [...$common, 'Complete Content Strategy', 'Competitor Research', 'Google Business Profile Management', 'Meta Ads Management',
                    'Lead Generation Campaigns', 'Ad Creatives & Copy', 'Lead Tracking', 'Monthly Performance Report', '2 Strategy Calls / Month']],
            ['Premium Growth', 50000, ['instagram', 'facebook', 'linkedin', 'youtube'], 16, 8, 16, 3,
                [...$common, 'Complete Content Strategy', 'Competitor Research', 'Social Media SEO', 'Meta Ads Management', 'Lead Generation Campaigns',
                    'WhatsApp / Lead Form Campaigns', 'Ad Creatives & Copy', 'Lead Tracking', 'Campaign Optimization', 'Monthly Performance Report',
                    'Analytics & Campaign Review', 'Weekly Strategy & Review Call', 'Professional 5-Page Website (first 2 months)',
                    'Google Business Profile Management', 'Website Conversion Optimization']],
        ];
        foreach ($rows as [$name, $price, $platforms, $static, $carousel, $reels, $min, $services]) {
            DB::table('packages')->insert([
                'name' => $name, 'price' => $price, 'platforms' => json_encode($platforms),
                'static_posts' => $static, 'carousel_posts' => $carousel, 'reels' => $reels,
                'services' => json_encode($services), 'min_months' => $min,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Ops Manager sees prices by default (Admin always does)
        DB::table('role_permissions')->insertOrIgnore(['role' => 'manager', 'permission' => 'view_prices']);
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'view_prices')->delete();
        DB::table('user_permissions')->where('permission', 'view_prices')->delete();
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn('package_start');
        });
        Schema::dropIfExists('packages');
    }
};
