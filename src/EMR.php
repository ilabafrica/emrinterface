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

                $patient = Patient::where('identifier',$request->input('subject.identifier'));

                // if patient exists
                if ($patient->count()) {
                    $patient = $patient->first();

                }else{
                    // create patient entry
                    $name = new Name;
                    $name->text = $request->input('subject.name');
                    $name->save();

                    // male | female | other | unknown
                    $gender = new Gender;
                    $gender->code = $request->input('subject.gender');
                    $gender->display = ucfirst($request->input('subject.gender'));
                    $gender->save();

                    // save subject in patient
                    $patient = new Patient;
                    $patient->identifier = $request->input('subject.identifier');
                    $patient->name_id = $name->id;
                    $patient->gender_id = $gender->id;
                    $patient->birth_date = $request->input('subject.birthDate');
                    $patient->created_by = Auth::guard('tpa_api')->user()->id;
                    $patient->save();
                }

                // on the lab side, assuming each set of requests represent an encounter
                $encounter = new Encounter;
                $encounter->identifier = $request->input('subject.identifier');
                $encounter->patient_id = $patient->id;
                $encounter->location_id = $request->input('location_id');
                $encounter->practitioner_name = $request->input('orderer.name');
                $encounter->practitioner_contact = $request->input('orderer.contact');
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

        $client = new Client();
        // send results for individual tests for starters
        // dd(Auth::guard('tpa_api')->user()->id);

        $response = $client->request('POST', $test->createdBy->emr->result_url, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json'
            ],
            'json' => $results
        ]);

        if ($response->getStatusCode() == 200) {
            $diagnosticOrder->update(['diagnostic_order_status_id' => DiagnosticOrderStatus::result_sent]);
        }
    }
}
