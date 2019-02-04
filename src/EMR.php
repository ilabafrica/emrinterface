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
use GuzzleHttp\Exception\ClientException;
use ILabAfrica\EMRInterface\Models\DiagnosticOrder;
use ILabAfrica\EMRInterface\Models\TestTypeMapping;
use ILabAfrica\EMRInterface\Models\EmrTestTypeAlias;
use ILabAfrica\EMRInterface\Models\DiagnosticOrderStatus;


class EMR extends Model{

    protected $table = 'emrs';

    public $timestamps = false;

    // return test menu
    public function testMenu()
    {
        $testTypes = TestTypeCategory::with('testTypes')->get();

        return response()->json($testTypes);
    }

    public function mapTestTypeGet()
    {
        $emrTestTypeAliases = EmrTestTypeAlias::all();

        return response()->json($emrTestTypeAliases);
    }

    public function mapTestTypeStore(Request $request)
    {
        $emrTestTypeAlias = new EmrTestTypeAlias;
        $emrTestTypeAlias->client_id = $request->client_id;
        $emrTestTypeAlias->test_type_id = $request->test_type_id;
        $emrTestTypeAlias->emr_alias = $request->emr_alias;
        $emrTestTypeAlias->system = $request->system;
        $emrTestTypeAlias->code = $request->code;
        $emrTestTypeAlias->display = $request->display;
        $emrTestTypeAlias->save();

        return response()->json($emrTestTypeAlias);
    }

    public function mapTestTypeUpdate(Request $request, $id)
    {
        $emrTestTypeAlias = EmrTestTypeAlias::find($id);
        $emrTestTypeAlias->client_id = $request->client_id;
        $emrTestTypeAlias->test_type_id = $request->test_type_id;
        $emrTestTypeAlias->emr_alias = $request->emr_alias;
        $emrTestTypeAlias->system = $request->system;
        $emrTestTypeAlias->code = $request->code;
        $emrTestTypeAlias->display = $request->display;
        $emrTestTypeAlias->save();

        return response()->json($emrTestTypeAlias);
    }

    public function mapTestTypeDestroy($id)
    {
        $emrTestTypeAlias = EmrTestTypeAlias::find($id)->destroy();

        return response()->json('');
    }

    public function mapResultGet($emrTestTypeAliasId)
    {
        $emrResultAliases = EmrResultAlias::where('emr_test_type_alias_id', $emrTestTypeAliasId)->get();

        return response()->json($emrResultAliases);
    }

    public function mapResultStore(Request $request)
    {
        $emrResultAlias = new EmrResultAlias;
        $emrResultAlias->emr_test_type_alias_id = $request->emr_test_type_alias_id;
        $emrResultAlias->measure_range_id = $request->measure_range_id;
        $emrResultAlias->emr_alias = $request->emr_alias;
        $emrResultAlias->save();

        return response()->json($emrResultAlias);
    }

    public function mapResultUpdate(Request $request, $id)
    {
        $emrResultAlias = EmrResultAlias::find($id);
        $emrResultAlias->emr_test_type_alias_id = $request->emr_test_type_alias_id;
        $emrResultAlias->measure_range_id = $request->measure_range_id;
        $emrResultAlias->emr_alias = $request->emr_alias;
        $emrResultAlias->save();

        return response()->json($emrResultAlias);
    }

    public function mapResultDestroy($id)
    {
        $emrResultAlias = EmrResultAlias::find($id)->destroy();

        return response()->json('');
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
                'contained' => 'required',
                'extension' => 'required',
                'code' => 'required',
                'subject' => 'required',
                'requester' => 'required',
            ];
        }

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            \Log::info(response()->json($validator->errors()));
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
                        \Log::error("The test type ` $testName ` does not exist:  ". $e->getMessage());
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
                }else if (Auth::guard('tpa_api')->user()->emr->data_standard == 'fhir') {
                    $contained =$request->input('contained');
                    $patient = Patient::where('identifier',$contained[0]['identifier']);

                    // male | female | other | unknown
                    $gender = ['male' => Gender::male, 'female' => Gender::female]; 

                    // if patient exists
                    if ($patient->count()) {
                        $patient = $patient->first();

                    }else{
                        // create patient entry
                        $name = new Name;
                        $name->family = $contained[0]['name'][0]['family'];
                        $name->given = $contained[0]['name'][0]['given'][0];
                        $name->text =$name->given." ".$name->family;
                        $name->save();

                        // save subject in patient
                        $patient = new Patient;
                        $patient->identifier = $contained[0]['identifier'][0]['value'];
                        $patient->name_id = $name->id;
                        $patient->gender_id = $gender[$contained[0]['gender']];
                        $patient->birth_date = $contained[0]['birthDate'];
                        $patient->created_by = Auth::guard('tpa_api')->user()->id;
                        $patient->save();
                    }
                    $encounterClass = ['inpatient' => EncounterClass::inpatient, 'outpatient' => EncounterClass::outpatient];

                    // on the lab side, assuming each set of requests represent an encounter
                    $encounter = new Encounter;
                    $encounter->identifier =$contained[0]['identifier'][0]['value'];
                    $encounter->patient_id = $patient->id;
                    $encounter->location_id = $request->input('location_id');
                    $encounter->practitioner_name = $contained[1]['name'][0]['given'][0]." ".$contained[1]['name'][0]['family'];
                    $encounter->practitioner_contact = $contained[1]['telecom'][0]['value'][0];
                    $encounter->save();

                    // recode each item in DiagnosticOrder to keep track of what has happened to it
                    foreach ($request->input('code')['coding'] as $coding) {

                        // save order items in tests
                        $test = new Test;
                        $test->encounter_id = $encounter->id;
                        $test->identifier = $contained[0]['identifier'][0]['value'];// using patient for now

                        if (\ILabAfrica\EMRInterface\EMR::where('third_party_app_id', Auth::guard('tpa_api')->user()->id)->first()->knows_test_menu) {
                            $test->test_type_id = $coding['code'];
                        }else{
                            $test->test_type_id = EmrTestTypeAlias::where('emr_alias',$coding['code'])->first()->test_type_id;
                        }

                        $test->test_status_id = TestStatus::pending;
                        $test->created_by = Auth::guard('tpa_api')->user()->id;
                        $test->requested_by = $request->input('contained')[1]['name'][0]['given'][0]." ".$request->input('contained')[1]['name'][0]['family'];// practitioner
                        $test->save();

                        $diagnosticOrder = new DiagnosticOrder;
                        $diagnosticOrder->test_id = $test->id;
                        $diagnosticOrder->emr_test_type_alias_id = EmrTestTypeAlias::where('emr_alias',$coding['code'])->first()->id;
                        $diagnosticOrder->save();
                    }
                }
                else{
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
                    $encounter->identifier = $request->input('extension.0.valueString');
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
        $loginResponse = $clientLogin->request('POST', env('LOGIN_URL_ML4AFRIKA', 'http://play.test/api/tpa/login'), [
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

            $diagnosticOrder = $diagnosticOrder->first();
            $test = Test::find($testID)->load('results');

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
            $contained = [];
            $resultRreference = [];

            foreach ($test->results as $result) {

                $resultRreference = ["reference" => "#observation".$result->id];

                $emrTestType = EmrTestTypeAlias::find($diagnosticOrder->emr_test_type_alias_id);
                if ($result->measure->measure_type_id == MeasureType::numeric) {

                    $contained[] = [
                      "resourceType"=> "Observation",
                      "id"=> "observation".$result->id,
                      "extension"=> [
                        [
                          "url"=> "http://www.mhealth4afrika.eu/fhir/StructureDefinition/dataElementCode",
                          "valueCode"=> $emrTestType->emr_alias,
                        ]
                      ],
                      "status" => "final",
                      "code"=> [
                        "coding"=> [
                          [
                            "system"=> $emrTestType->system,
                            "code"=> $emrTestType->code,
                            "display"=> $emrTestType->display
                          ]
                        ]
                      ],
                      "effectiveDateTime"=> date_format(date_create($test->time_completed,timezone_open(env('TIMEZONE','Africa/Nairobi'))), 'c'),
                      "performer"=> [
                        [
                          "reference"=> "Practitioner/".$test->testedBy->email
                        ]
                      ],
                      "valueQuantity"=> [
                        "value"=> $result->measureRange->display,
                        "unit"=> $result->measure->unit,
                        "system"=> "http://unitsofmeasure.org",
                        "code"=> $result->measure->unit
                      ]
                    ];

                }else if ($result->measure->measure_type_id == MeasureType::alphanumeric) {

                    $contained[] = [
                      "resourceType"=> "Observation",
                      "id"=> "observation".$result->id,
                      "extension"=> [
                        [
                          "url"=> "http://www.mhealth4afrika.eu/fhir/StructureDefinition/dataElementCode",
                          "valueCode"=> $emrTestType->emr_alias,
                        ]
                      ],
                      "status" => "final",
                      "code"=> [
                        "coding"=> [
                          [
                            "system"=> $emrTestType->system,
                            "code"=> $emrTestType->code,
                            "display"=> $emrTestType->display
                          ]
                        ]
                      ],
                      "effectiveDateTime"=> date_format(date_create($test->time_completed,timezone_open(env('TIMEZONE','Africa/Nairobi'))), 'c'),
                      "performer"=> [
                        [
                          "reference"=> "Practitioner/".$test->testedBy->email,
                        ]
                      ],
                      "valueString"=> $result->measureRange->display,
                    ];
                }else if ($result->measure->measure_type_id == MeasureType::multi_alphanumeric) {
                    $contained[] = [
                      "resourceType"=> "Observation",
                      "id"=> "observation".$result->id,
                      "extension"=> [
                        [
                          "url"=> "http://www.mhealth4afrika.eu/fhir/StructureDefinition/dataElementCode",
                          "valueCode"=> $emrTestType->emr_alias,
                        ]
                      ],
                      "status" => "final",
                      "code"=> [
                        "coding"=> [
                          [
                            "system"=> $emrTestType->system,
                            "code"=> $emrTestType->code,
                            "display"=> $emrTestType->display
                          ]
                        ]
                      ],
                      "effectiveDateTime"=> date_format(date_create($test->time_completed,timezone_open(env('TIMEZONE','Africa/Nairobi'))), 'c'),
                      "performer"=> [
                        [
                          "reference"=> "Practitioner/".$test->testedBy->email
                        ]
                      ],
                      "valueString"=> $result->measureRange->display,
                    ];
                }else if ($result->measure->measure_type_id == MeasureType::free_text) {
                    $contained[] = [
                      "resourceType"=> "Observation",
                      "id"=> "observation".$result->id,
                      "extension"=> [
                        [
                          "url"=> "http://www.mhealth4afrika.eu/fhir/StructureDefinition/dataElementCode",
                          "valueCode"=> $emrTestType->emr_alias,
                        ]
                      ],
                      "status" => "final",
                      "code"=> [
                        "coding"=> [
                          [
                            "system"=> $emrTestType->system,
                            "code"=> $emrTestType->code,
                            "display"=> $emrTestType->display
                          ]
                        ]
                      ],
                      "effectiveDateTime"=> date_format(date_create($test->time_completed,timezone_open(env('TIMEZONE','Africa/Nairobi'))), 'c'),
                      "performer"=> [
                        [
                          "reference"=> "Practitioner/".$test->testedBy->email,
                        ]
                      ],
                      "valueString"=>  $result->result,
                    ];
                }
            }

            $results = [
              "resourceType"=> "DiagnosticReport",
              "contained"=> $contained,//the individual measures
              "extension"=> [
                [
                  "url"=> "http://www.mhealth4afrika.eu/fhir/StructureDefinition/eventId",
                  "valueString"=> $test->encounter->identifier
                ]
              ],
              "identifier"=> [
                [
                  "value"=> "$test->id"
                ]
              ],
              "subject"=> [
                "reference"=>  "Patient/".$test->encounter->patient->identifier
              ],
             "context" => [
                "reference" => "Encounter/".$test->encounter->identifier
              ],
              "status" => "final",
              "code" => [
                "coding" => [
                  [
                    "system" => "http://www.mhealth4afrika.eu/fhir/StructureDefinition/diagnosticReportCode",
                    "code" => "blis-lab"
                  ]
                ]
              ],
              "performer"=> [
                [
                  "actor"=> [
                    "reference"=> "Practitioner/".$test->testedBy->email
                  ]
                ]
              ],
              "result"=> $resultRreference
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
                    \Log::info($response->getStatusCode());
                if ($response->getStatusCode() == 200) {
                    $diagnosticOrder->update(['diagnostic_order_status_id' => DiagnosticOrderStatus::result_sent]);
                    \Log::info('results successfully sent to emr');
                }elseif ($response->getStatusCode() == 204) {
                    \Log::info('204:The server successfully processed the request, but is not returning any content.');
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                \Log::info($e->getMessage());
                DiagnosticOrder::where('test_id',$testID)->update(['result_sent_attempts' => $diagnosticOrder->result_sent_attempts+1]);

                // if attempts are still less than 3
                if (DiagnosticOrder::where('test_id',$testID)->first()->result_sent_attempts<5) {
                    $this->getToken($test->id, $test->thirdPartyCreator->access->email);
                }else{
                    \Log::info('\'result sent attempt\' exhausted');
                }
            }
        }
    }
}
