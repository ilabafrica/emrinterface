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

        $sanitasId = \App\ThirdPartyApp::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sanitas',
            'email' => 'sanitas@emr.dev',
            'password' =>  bcrypt('password'),
        ])->id;

        // default
        \ILabAfrica\EMRInterface\EMR::create([
            'result_url' => 'http://play.test/api/medbookresult',
            'third_party_app_id' => $defaultId,
            'data_standard' => 'fhir',
            'knows_test_menu' => 1,
        ]);

        // ml4afrika
        \ILabAfrica\EMRInterface\EMR::create([
            'result_url' => 'http://play.test/api/ml4afrikaresult',
            'third_party_app_id' => $mL4AfrikaId,
            'data_standard' => 'fhir',
            'knows_test_menu' => 0,
        ]);

        // sanitas
        \ILabAfrica\EMRInterface\EMR::create([
            'result_url' => 'http://play.test/api/sanitasresult',
            'third_party_app_id' => $sanitasId,
            'data_standard' => 'sanitas',
            'knows_test_menu' => 1,
        ]);

        \App\Models\ThirdPartyAccess::create([
            'third_party_app_id' => $defaultId,
            'email' => 'medbook@play.dev',
            'password' =>  'password',
        ]);

        \App\Models\ThirdPartyAccess::create([
            'third_party_app_id' => $mL4AfrikaId,
            'email' => 'ml4afrika@play.dev',
            'password' =>  'password',
        ]);

        \App\Models\ThirdPartyAccess::create([
            'third_party_app_id' => $sanitasId,
            'email' => 'sanitas@play.dev',
            'password' =>  'password',
        ]);

    }
}
