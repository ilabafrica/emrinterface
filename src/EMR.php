<?php

namespace ILabAfrica\EMRInterface;
use Auth;
use App\Models\Name;
use App\Models\Test;
use App\Models\Gender;
use App\Models\Patient;
use App\Models\TestType;
use App\Models\Encounter;
use App\Models\TestStatus;
use Illuminate\Http\Request;
use App\Models\TestTypeCategory;
use ILabAfrica\EMRInterface\DiagnosticOrder;
use ILabAfrica\EMRInterface\DiagnosticOrderStatus;

class EMR {

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
                    $patient->created_by = Auth::user()->id;
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
                    $test->test_type_id = $item['test_type_id'];
                    $test->test_status_id = TestStatus::pending;
                    $test->created_by = Auth::user()->id;
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
        if ($diagnosticOrder) {
            $diagnosticOrder->first();
            $test = Test::find($testID)->load('results');
        }else{
            return;
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
                'component' => $test->results
            ],
        ];

        // send results for individual tests for starters
        $promise = $client->requestAsync('POST', env('EMR_RESULT_URL'), [
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json'
            ],
            'json' => $results
        ]);

        $promise->then(function (ResponseInterface $response) {

            if ($response->getStatusCode() == 200) {
                $diagnosticOrder->diagnostic_order_status_id = DiagnosticOrderStatus::result_sent;
                $diagnosticOrder->save();
            }

            \Log::info($response->getBody()->getContents());

        }, function (RequestException $e) {
            \Log::info($e->getMessage());
            \Log::info($e->getRequest()->getMethod());
        });
    }
}
