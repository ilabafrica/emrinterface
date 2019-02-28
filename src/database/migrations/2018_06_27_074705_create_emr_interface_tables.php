<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use \ILabAfrica\EMRInterface\Models\DiagnosticOrderStatus;

class CreateEmrInterfaceTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('emr_test_type_aliases', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('client_id');
            $table->integer('test_type_id')->unsigned();
            $table->string('emr_alias')->nullable();
            $table->string('system')->nullable();
            $table->string('code')->nullable();
            $table->string('display')->nullable();
            $table->unique(['test_type_id', 'emr_alias']);
        });

        // for alphanumerics
        Schema::create('emr_result_aliases', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('emr_test_type_alias_id')->unsigned();
            $table->integer('measure_range_id')->unsigned()->nullable();
            $table->string('emr_alias');
            $table->unique(['measure_range_id', 'emr_alias']);
        });

        Schema::create('diagnostic_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('diagnostic_order_status_id')->unsigned()
                ->default(DiagnosticOrderStatus::result_pending);
            $table->integer('test_id')->unsigned();
            $table->integer('emr_test_type_alias_id')->unsigned();
            $table->integer('result_sent_attempts')->unsigned()->default(0);
            $table->timestamp('time_sent')->nullable();
            $table->timestamps();
        });

        Schema::create('diagnostic_order_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('display');
        });

        Schema::create('emrs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('result_url');
            $table->uuid('third_party_app_id');
            $table->string('data_standard');// fhir, sanitas
            $table->boolean('knows_test_menu')->default(1);
        });

        // result auto gen additional information/non test type aliase
        Schema::create('emr_additional_infos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('emr_test_type_alias_id')->unsigned();
            $table->string('type',20);//date,number,details,requested,
        });

       /* Diagnostic Order Statuses */
        $diagnosticOrderStatuses = [
            [
                'id' => '1',
                'code' => 'result_pending',
                'display' => 'Result Pending'
            ],
            [
                'id' => '2',
                'code' => 'result_sent',
                'display' => 'Result Sent'
            ],
        ];
        foreach ($diagnosticOrderStatuses as $diagnosticOrderStatus) {
            DiagnosticOrderStatus::create($diagnosticOrderStatus);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}