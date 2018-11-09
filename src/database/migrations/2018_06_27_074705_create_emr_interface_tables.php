<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use ILabAfrica\EMRInterface\DiagnosticOrderStatus;
use Illuminate\Database\Migrations\Migration;

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
            $table->integer('client_id')->unsigned();
            $table->integer('test_type_id')->unsigned();
            $table->string('emr_alias');
        });

        Schema::create('diagnostic_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('diagnostic_order_status_id')->unsigned()
                ->default(DiagnosticOrderStatus::result_pending);
            $table->integer('test_id')->unsigned();
            $table->integer('test_type_mapping_id')->unsigned()->nullable();
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
            $table->boolean('knows_test_menu')->default(1);
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