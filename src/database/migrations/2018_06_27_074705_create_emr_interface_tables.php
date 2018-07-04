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
        Schema::create('diagnostic_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('request_status_id')
                ->unsigned()
                ->default(DiagnosticOrderStatus::RESULT_PENDING);
            $table->integer('test_id')->unsigned();
            $table->timestamps();
        });

        Schema::create('diagnostic_order_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('display');
        });


       /* Diagnostic Order Statuses */
        $diagnosticOrderStatuses = [
            ['id' => '1', 'name' => 'result_pending'],
            ['id' => '2', 'name' => 'result_sent'],
        ];

        foreach ($diagnosticOrderStatuses as $diagnosticOrderStatus) {
            DiagnosticOrderStatus::create($diagnosticOrderStatus);
        }
        echo "diagnostic_order_statuses Seeded\n";

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