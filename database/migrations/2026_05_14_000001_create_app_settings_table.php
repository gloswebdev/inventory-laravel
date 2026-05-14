<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Seed default values
        $defaults = [
            ['key' => 'erp_api_base_url',         'value' => 'https://logicapi.algebraerp.com/API/SYNWOOD',  'label' => 'ERP Base URL',              'group' => 'erp_api'],
            ['key' => 'erp_api_key',               'value' => 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14',        'label' => 'API Key',                   'group' => 'erp_api'],
            // ProductWiseInventory params
            ['key' => 'inventory_api_branch',      'value' => 'ALL',                                          'label' => 'Branch (Inventory API)',     'group' => 'inventory_api'],
            ['key' => 'inventory_api_item',        'value' => 'ALL',                                          'label' => 'Item (Inventory API)',       'group' => 'inventory_api'],
            ['key' => 'factory_stock_branch',      'value' => '2',                                            'label' => 'Factory Branch Code',       'group' => 'inventory_api'],
            // ProductMaster params
            ['key' => 'product_master_itemdetcode','value' => '0',                                            'label' => 'Itemdetcode',               'group' => 'product_master_api'],
            ['key' => 'product_master_usercode',   'value' => '0',                                            'label' => 'Usercode',                  'group' => 'product_master_api'],
            ['key' => 'product_master_branchcode', 'value' => '0',                                            'label' => 'Branchcode',                'group' => 'product_master_api'],
            ['key' => 'product_master_page_number','value' => '1',                                            'label' => 'PageNumber',                'group' => 'product_master_api'],
            ['key' => 'product_master_rows',       'value' => '10000',                                        'label' => 'RowsOfPage',                'group' => 'product_master_api'],
            ['key' => 'product_master_txn_type',   'value' => 'Old',                                          'label' => 'TxnType',                   'group' => 'product_master_api'],
        ];

        foreach ($defaults as $setting) {
            DB::table('app_settings')->insertOrIgnore(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
