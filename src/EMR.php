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
        $rules = [
            'subject' => 'required',
            'orderer' => 'required',
            'item' => 'required',
        ];
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator);
        } else {
            try {

                if (Auth::guard('tpa_api')->user()->emr->data_standard == 'sanitas') {
                    $gender = ['Male' => Gender::male, 'Female' => Gender::female];

                    //Check if patient exists, if true dont save again
                    $patient = Patient::firstOrNew([
                        'identifier' => $labRequest->patient->id,
                    ]);
                    $patient->patient_number = $labRequest->patient->id;
                    $patient->name = $labRequest->patient->fullName;
                    $patient->gender_id = $gender[$labRequest->patient->gender];
                    $patient->dob = $labRequest->patient->dateOfBirth;
                    $patient->address = $labRequest->address->address;
                    $patient->phone_number = $labRequest->address->phoneNumber;
                    $patient->created_by = Auth::guard('tpa_api')->user()->id
                    $patient->save();

                    try
                    {
                        $testName = trim($labRequest->investigation);
                        $testTypeId = TestType::where('name', 'like', $testName)->orderBy('name')->firstOrFail()->id;
                    } catch (ModelNotFoundException $e) {
                        Log::error("The test type ` $testName ` does not exist:  ". $e->getMessage());
                        // todo: send appropriate error message
                        return null;
                    }

                    $visitType = ['ip' => 'In-patient', 'op' => 'Out-patient'];//Should be a constant

                    //Check if visit exists, if true dont save again
                    $encounter = Encounter::firstOrNew([
                        'identifier' => $labRequest->patientVisitNumber,
                        'encounter_class_id' => $visitType[$labRequest->orderStage],
                        'patient_id' => $patient->id,
                    ]);

                    //Check if parentLabNO is 0 thus its the main test and not a measure
                    if($labRequest->parentLabNo == '0' || $this->isPanelTest($labRequest))
                    {
                        //Check via the labno, if this is a duplicate request and we already saved the test
                        $test = Test::firstOrNew([
                            'external_id' => $labRequest->labNo,
                        ]);
                        $test->test_type_id = $testTypeId;
                        $test->test_status_id = TestStatus::pending;
                        $test->created_by = Auth::guard('tpa_api')->user()->id
                        //Created by external system 0
                        $test->requested_by = $labRequest->requestingClinician;

                        \DB::transaction(function() use ($encounter, $test) {
                            $encounter->save();
                            $test->visit_id = $encounter->id;
                            $test->specimen_id = $specimen->id;
                            $test->save();
                        });
                    }
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
                    $visitType = ['ip' => EncounterClass::inpatient, 'op' => EncounterClass::outpatient];

                    // on the lab side, assuming each set of requests represent an encounter
                    $encounter = new Encounter;
                    $encounter->identifier = $request->input('subject.identifier');
                    $encounter->patient_id = $patient->id;
                    $encounter->location_id = $request->input('location_id');
                    $encounter->practitioner_name = $request->input('orderer.name');
                    $encounter->practitioner_contact = $request->input('orderer.contact');
                    $encounter->encounter_class_id = $visitType[$labRequest->orderStage];
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

    public function sendTestResults($testID)
    {
        $diagnosticOrder = DiagnosticOrder::where('test_id',$testID);
        // if order is from emr
        if ($diagnosticOrder->count()) {
            $diagnosticOrder->first();
            $test = Test::find($testID)->load('results');
        }else{
            return;
        }

        if ($test->thirdPartyCreator->emr->data_standard == 'sanitas') {

            // $result = $matchingResult->result." ". $range ." ".$unit;
            // $formattedMeasures = $measures;
            // $formattedMeasures = '';
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
            // send results for individual tests
            $response = $client->request('POST', $test->thirdPartyCreator->emr->result_url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-type' => 'application/json'
                ],
                'json' => $results
            ]);
        }

        if ($response->getStatusCode() == 200) {
            $diagnosticOrder->update(['diagnostic_order_status_id' => DiagnosticOrderStatus::result_sent]);
        }
    }

    // used by sanitas
    public function isPanelTest($labRequest)
    {
        //If parent is panel test
        if($labRequest->parentLabNo != '0'){
            $parent = ExternalDump::where('lab_no', $labRequest->parentLabNo)->first();
            $panel = Panel::where('name', 'like', trim($parent->investigation))->where('active', 1)->orderBy('name')->first();
            if (isset($panel)) {
                //If is one of the child test of panel
                foreach ($panel->testTypes as $testType) {
                    if($testType->name == $labRequest->investigation) {
                        return true;
                    }
                }
            }
        }
    }
}
