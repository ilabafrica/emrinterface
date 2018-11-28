<?php

namespace ILabAfrica\EMRInterface;
use Auth;
use App\Models\Name;
use App\Models\Test;
use App\Models\Gender;
use GuzzleHttp\Client;
use App\Models\Patient;
use App\Models\TestType;
use App\Models\Encounter;
use App\Models\TestStatus;
use App\Models\MeasureType;
use Illuminate\Http\Request;
use App\Models\EncounterClass;
use App\Models\TestTypeCategory;
use Illuminate\Database\Eloquent\Model;
use ILabAfrica\EMRInterface\DiagnosticOrder;
use ILabAfrica\EMRInterface\TestTypeMapping;
use ILabAfrica\EMRInterface\DiagnosticOrderStatus;
use GuzzleHttp\Exception\ClientException;


class EMR extends Model{

    protected $table = 'emrs';

    public $timestamps = false;

    // return test menu
    public function testMenu()
    {
        $testTypes = TestTypeCategory::with('testTypes')->get();

        return response()->json($testTypes);
    }

    // receive and add test request on queue
    public function receiveTestRequest(Request $request)
    {
        if (Auth::guard('tpa_api')->user()->emr->data_standard == 'sanitas') {
            $rules = [
                'patient' => 'required',
            ];

        }else{
            $rules = [
                'subject' => 'required',
                'orderer' => 'required',
                'item' => 'required',
            ];
        }

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator);
        } else {
            try {
                if (Auth::guard('tpa_api')->user()->emr->data_standard == 'sanitas') {
                    $gender = ['Male' => Gender::male, 'Female' => Gender::female];

                    $name = new Name;
                    $name->text = $request->input('patient.fullName');
                    $name->save();

                    //Check if patient exists, if true dont save again
                    $patient = Patient::firstOrNew([
                        'identifier' => $request->input('patient.id'),
                    ]);
                    $patient->identifier = $request->input('patient.id');
                    $patient->name_id = $name->id;
                    $patient->gender_id = $gender[$request->input('patient.gender')];
                    $patient->birth_date = $request->input('patient.dateOfBirth');
                    $patient->address = $request->input('address.address');
                    // $patient->phone_number = $request->input('address.phoneNumber');
                    $patient->created_by = Auth::guard('tpa_api')->user()->id;
                    $patient->save();

                    try
                    {
                        $testName = trim($request->input('investigation'));
                        $testTypeId = TestType::where('name', 'like', $testName)->orderBy('name')->firstOrFail()->id;
                    } catch (ModelNotFoundException $e) {
                        Log::error("The test type ` $testName ` does not exist:  ". $e->getMessage());
                        // todo: send appropriate error message
                        return null;
                    }

                    $visitType = ['ip' => EncounterClass::inpatient, 'op' => EncounterClass::outpatient];

                    //Check if visit exists, if true dont save again
                    $encounter = Encounter::firstOrNew([
                        'identifier' => $request->input('patientVisitNumber'),
                        'encounter_class_id' => $visitType[$request->input('orderStage')],
                        'patient_id' => $patient->id,
                    ]);

                        $test = Test::firstOrNew([
                            'identifier' => $request->input('labNo'),
                        ]);
                        $test->test_type_id = $testTypeId;
                        $test->test_status_id = TestStatus::pending;
                        $test->created_by = Auth::guard('tpa_api')->user()->id;
                        $test->requested_by = $request->input('requestingClinician');

                        \DB::transaction(function() use ($encounter, $test) {
                            $encounter->save();
                            $test->visit_id = $encounter->id;
                            $test->save();
                        });
                }else{
                    $patient = Patient::where('identifier',$request->input('subject.identifier'));

                    // male | female | other | unknown
                    $gender = ['male' => Gender::male, 'female' => Gender::female]; 

                    // if patient exists
                    if ($patient->count()) {
                        $patient = $patient->first();

                    }else{
                        // create patient entry
                        $name = new Name;
                        $name->text = $request->input('subject.name');
                        $name->save();

                        // save subject in patient
                        $patient = new Patient;
                        $patient->identifier = $request->input('subject.identifier');
                        $patient->name_id = $name->id;
                        $patient->gender_id = $gender[$request->input('subject.gender')];
                        $patient->birth_date = $request->input('subject.birthDate');
                        $patient->created_by = Auth::guard('tpa_api')->user()->id;
                        $patient->save();
                    }
                    $encounterClass = ['inpatient' => EncounterClass::inpatient, 'outpatient' => EncounterClass::outpatient];

                    // on the lab side, assuming each set of requests represent an encounter
                    $encounter = new Encounter;
                    $encounter->identifier = $request->input('subject.identifier');
                    $encounter->patient_id = $patient->id;
                    $encounter->location_id = $request->input('location_id');
                    $encounter->practitioner_name = $request->input('orderer.name');
                    $encounter->practitioner_contact = $request->input('orderer.contact');
                    $encounter->encounter_class_id = $encounterClass[$request->input('encounter.class')];
                    $encounter->practitioner_organisation = $request->input('orderer.organisation');
                    $encounter->save();

                    // recode each item in DiagnosticOrder to keep track of what has happened to it
                    foreach ($request->input('item') as $item) {

                        // save order items in tests
                        $test = new Test;
                        $test->encounter_id = $encounter->id;
                        $test->identifier = $request->input('subject.identifier');// using patient for now

                        if (\ILabAfrica\EMRInterface\EMR::where('third_party_app_id', Auth::guard('tpa_api')->user()->id)->first()->knows_test_menu) {
                            $test->test_type_id = $item['test_type_id'];
                        }else{
                            $test->test_type_id = EmrTestTypeAlias::where('emr_alias',$item['test_type_id'])->first()->test_type_id;
                        }

                        $test->test_status_id = TestStatus::pending;
                        $test->created_by = Auth::guard('tpa_api')->user()->id;
                        $test->requested_by = $request->input('orderer.name');// practitioner
                        $test->save();

                        $diagnosticOrder = new DiagnosticOrder;
                        $diagnosticOrder->test_id = $test->id;
                        $diagnosticOrder->save();
                    }
                }

                return response()->json(['message' => 'Test Request Received']);
            } catch (\Illuminate\Database\QueryException $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
    }

    public function getToken($testID, $thirdPartyEmail)
    {
        $clientLogin = new Client();
        // send results for individual tests for starters
        $loginResponse = $clientLogin->request('POST', 'http://play.test/api/tpa/login', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json'
            ],
            'json' => [
                'email' => $thirdPartyEmail,
                'password' => 'password'
             ],
        ]);

       if ($loginResponse->getStatusCode() == 200) {
            \Log::info('login success');
            $accessToken = json_decode($loginResponse->getBody()->getContents())->access_token;
            \App\Models\ThirdPartyAccess::where('email',$thirdPartyEmail)->update(['access_token' => $accessToken]);

            $this->sendTestResults($testID);
        }
    }

    public function sendTestResults($testID)
    {
        $diagnosticOrder = DiagnosticOrder::where('test_id',$testID);

        // if order is from emr
        if ($diagnosticOrder->count()) {
            $diagnosticOrder->first();
            $test = Test::find($testID)->load('results');
            \Log::info($test->thirdPartyCreator->access);

            $thirdPartyAccess = \App\Models\ThirdPartyAccess::where('email',$test->thirdPartyCreator->access->email);
            if ($thirdPartyAccess->count()) {
                $accessToken = $thirdPartyAccess->first()->access_token;
            }else{
                $accessToken = '';
            }
        }else{
            return;
        }

        if ($test->thirdPartyCreator->emr->data_standard == 'sanitas') {

            $result = '';
            $jsonResultString = sprintf('{"labNo": "%s","requestingClinician": "%s", "result": "%s", "verifiedby": "%s", "techniciancomment": "%s"}', 
                                $test->identifier, $test->tested_by, $result, $test->verified_by, $test->comment);
            $results = "labResult=".urlencode($jsonResultString);

        }elseif($test->thirdPartyCreator->emr->data_standard == 'fhir'){
            $measures = [];
            foreach ($test->results as $result) {
                if ($result->measure->measure_type_id == MeasureType::numeric) {
                    $measures[] = [
                        'code' => $result->measure->name,
                        'valueString' => $result->result,
                    ];
                }else if ($result->measure->measure_type_id == MeasureType::alphanumeric) {
                    $measures[] = [
                        'code' => $result->measure->name,
                        'valueString' => $result->measureRange->display,
                    ];
                }else if ($result->measure->measure_type_id == MeasureType::multi_alphanumeric) {
                    // adjust to capture multiple, will need some looping of measure ranges
                    $measures[] = [
                        'code' => $result->measure->name,
                        'valueString' => $result->measureRange->display,
                    ];
                }else if ($result->measure->measure_type_id == MeasureType::free_text) {
                    $measures[] = [
                        'code' => $result->measure->name,
                        'valueString' => $result->result,
                    ];
                }
            }
            $results = [
                'resourceType' => 'DiagnosticReport',
                'identifier' => $test->encounter->patient->identifier, // emr patient Identifier
                'subject' => [
                    'identifier' => $test->encounter->patient->identifier, // emr patient Identifier
                ], // R!  The subject of the report, usually, but not always, the patient
                'result' => [ // Observations - simple, or complex nested groups
                    'resourceType' => 'Observation',
                    'identifier' => $test->id, // emr test Identifier, say using loinc... for us to aggre
                    'effectiveDateTime' => $test->time_completed,
                    'issued' => $test->time_sent, // Date/Time this was made available
                    'performer' => $test->testedBy->name, // Who is responsible for the observation
                    'component' => $measures,
                ],
            ];
        }

        $client = new Client();

        // use verb to decide
        if ($test->thirdPartyCreator->emr->data_standard == 'sanitas') {
            $response = $client->request('GET', $test->thirdPartyCreator->emr->result_url.'?'.$results, ['debug' => true]);
        }else{
            try {
                // send results for individual tests
                $response = $client->request('POST', $test->thirdPartyCreator->emr->result_url, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-type' => 'application/json',
                        'Authorization' => 'Bearer '.$accessToken
                    ],
                    'json' => $results
                ]);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $this->getToken($test->id, $test->thirdPartyCreator->access->email);
            }
        }
        if ($response->getStatusCode() == 200) {
            $diagnosticOrder->update(['diagnostic_order_status_id' => DiagnosticOrderStatus::result_sent]);
        }
    }
}
