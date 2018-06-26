<?php

namespace ILabAfrica\EMRInterface;
use App\Models\TestType;

class EMR {

    // return test menu
    public function testMenu()
    {
        $testTypes = TestType::select('name')->get();
        return response()->json($testTypes);
    }

    // receive and add test request on queue
    public function receiveTestRequest(Request $request)
    {

        return response()->json(['testrequest' => 'comming through']);
        $rules = [
            'name' => 'required',
        ];
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator);
        } else {

            try {

                // return response()->json($measureType);
                return response()->json(['testrequest' => 'comming through']);
            } catch (\Illuminate\Database\QueryException $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
    }

    // call using... like at the point of test completed if adhoc says there is etc
    // return EMR::sendTestResults();
    // TODO: check previous implementations: returning of results
    public function sendTestResults($testID)
    {
        $test = Test::find($testID)->load('results');

        $results = [
            'field_name' => 'abc',
            'other_field' => '123',
            'nested_field' => [
                'nested' => 'hello'
            ]
        ];

        // send results for individual tests for starters
        // TODO: make url dynamic work out a .env arrangement say EMR_URL
        $promise = $client->requestAsync('POST', 'http://oauth.local/api/receiveresults', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-type' => 'application/json'
            ],
            'json' => $results
        ]);

        $promise->then(
            function (ResponseInterface $response) {
                if ($response->getStatusCode() == 200) {
                    /*
                        TODO:
                        set state of the request to etc sent...
                        create a table to keep track of requests
                    */
                }else{
                    /*
                        TODO:
                        flag as sending failure see how to have in the table
                    */
                }
                \Log::info($response->getBody()->getContents());
            },
            function (RequestException $e) {
                \Log::info($e->getMessage());
                \Log::info($e->getRequest()->getMethod());
            }
        );
    }
}












