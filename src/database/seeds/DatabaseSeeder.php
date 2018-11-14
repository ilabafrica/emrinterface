<?php namespace ILabAfrica\EMRInterface\Database;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // TODO: CREATE CONFIG UI FOR THIS
        $defaultId = \App\ThirdPartyApp::create([
            'id' => (string) Str::uuid(),
            'name' => 'Default EMR',
            'email' => 'default@emr.dev',
            'password' =>  bcrypt('password'),
        ])->id;

        $mL4AfrikaId = \App\ThirdPartyApp::create([
            'id' => (string) Str::uuid(),
            'name' => 'ML4Afrika',
            'email' => 'ml4afrika@emr.dev',
            'password' =>  bcrypt('password'),
        ])->id;

        $sanitas = \App\ThirdPartyApp::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sanitas',
            'email' => 'sanitas@emr.dev',
            'password' =>  bcrypt('password'),
        ])->id;

        // default
        \ILabAfrica\EMRInterface\EMR::create([
            'result_url' => 'http://play.local/api/medbookresult',
            'third_party_app_id' => $defaultId,
            'data_standard' => 'fhir',
            'knows_test_menu' => 1,
        ]);

        // ml4afrika
        \ILabAfrica\EMRInterface\EMR::create([
            'result_url' => 'http://play.local/api/ml4afrikaresult',
            'third_party_app_id' => $mL4AfrikaId,
            'data_standard' => 'fhir',
            'knows_test_menu' => 0,
        ]);

        // sanitas
        \ILabAfrica\EMRInterface\EMR::create([
            'result_url' => 'http://play.local/api/sanitasresult',
            'third_party_app_id' => $sanitas,
            'data_standard' => 'sanitas',
            'knows_test_menu' => 1,
        ]);
    }
}
