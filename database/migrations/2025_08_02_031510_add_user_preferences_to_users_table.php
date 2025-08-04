<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserPreferencesToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('language')->default('en');
            $table->string('timezone')->default('utc+1');
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('api_key')->nullable();

            // Notification preferences
            $table->boolean('email_orders')->default(true);
            $table->boolean('email_promotions')->default(false);
            $table->boolean('email_updates')->default(true);
            $table->boolean('push_orders')->default(true);
            $table->boolean('push_promotions')->default(false);
            $table->boolean('push_updates')->default(false);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'language',
                'timezone',
                'two_factor_enabled',
                'api_key',
                'email_orders',
                'email_promotions',
                'email_updates',
                'push_orders',
                'push_promotions',
                'push_updates'
            ]);
        });
    }
}
