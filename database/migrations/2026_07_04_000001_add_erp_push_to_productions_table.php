<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add ERP push tracking columns to productions table
        Schema::table('productions', function (Blueprint $table) {
            $table->string('erp_push_status')->default('pending')->after('user_id');
            // pending | success | failed | skipped
            $table->text('erp_issue_response')->nullable()->after('erp_push_status');
            $table->text('erp_receipt_response')->nullable()->after('erp_issue_response');
        });

        // Seed new ERP push AppSettings
        $newSettings = [
            ['key' => 'erp_push_enabled',        'value' => '0',        'label' => 'Enable ERP Stock Push',         'group' => 'erp_push'],
            ['key' => 'erp_push_base_url',        'value' => 'http://demo.logicerp.com/api', 'label' => 'ERP Push Base URL',  'group' => 'erp_push'],
            ['key' => 'erp_push_username',        'value' => '',         'label' => 'ERP Push Username (Basic Auth)', 'group' => 'erp_push'],
            ['key' => 'erp_push_password',        'value' => '',         'label' => 'ERP Push Password (Basic Auth)', 'group' => 'erp_push'],
            // Receipt Stock settings
            ['key' => 'erp_receipt_doc_prefix',   'value' => 'REC',      'label' => 'Receipt Doc Prefix',            'group' => 'erp_push'],
            ['key' => 'erp_receipt_godown_name',  'value' => 'MAIN',     'label' => 'Receipt Godown Name',           'group' => 'erp_push'],
            ['key' => 'erp_receipt_received_from','value' => '',         'label' => 'Receipt ReceivedFrom',          'group' => 'erp_push'],
            ['key' => 'erp_receipt_issue_to',     'value' => '',         'label' => 'Receipt IssueTo',               'group' => 'erp_push'],
            // Issue Stock settings
            ['key' => 'erp_issue_doc_prefix',     'value' => 'IS',       'label' => 'Issue Doc Prefix',              'group' => 'erp_push'],
            ['key' => 'erp_issue_godown_name',    'value' => '',         'label' => 'Issue Godown Name',             'group' => 'erp_push'],
            ['key' => 'erp_issue_issue_to',       'value' => 'DAMAGE',   'label' => 'Issue IssueTo (mandatory)',     'group' => 'erp_push'],
        ];

        foreach ($newSettings as $setting) {
            DB::table('app_settings')->insertOrIgnore(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn(['erp_push_status', 'erp_issue_response', 'erp_receipt_response']);
        });

        DB::table('app_settings')->whereIn('key', [
            'erp_push_enabled', 'erp_push_base_url', 'erp_push_username', 'erp_push_password',
            'erp_receipt_doc_prefix', 'erp_receipt_godown_name', 'erp_receipt_received_from', 'erp_receipt_issue_to',
            'erp_issue_doc_prefix', 'erp_issue_godown_name', 'erp_issue_issue_to',
        ])->delete();
    }
};
