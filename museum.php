<?php

function processMessage($update) {
    $slots = array("type_event","name_event","visit_date","number_person",
        "visitor_name","visitor_phone");
    $slotValues = [
        'type_event' => null,
        'name_event' => null,
        'visit_date' => null,
        'number_person' => null,
        'visitor_name' => null,
        'visitor_phone' => null
    ];
    $slotMessages = [
        'type_event' => 'Do you want to book for workshop or exhibition?',
        'name_event' => 'Which event are you interested in?',
        'visit_date' => 'Which day are you coming?',
        'number_person' => 'How many visitors?',
        'visitor_name' => 'What is your name?',
        'visitor_phone' => 'What is your phone number?'
    ];
    $actionToSlotMapping = [
        'bookVisit' => 'type_event',
        'Input.type_event' => 'name_event',
        'Input.name_event' => 'visit_date',
        'Input.visit_date' => 'number_person',
        'Input.number_person' => 'visitor_name',
        'Input.visitor_name' => 'visitor_phone'
    ];
    $expectedSlot = $actionToSlotMapping[$update["queryResult"]["action"]];
    $filledSlots = getFilledSlots($update,$slotValues);

    //iterate over params
    $params = $update["queryResult"]["parameters"];
    foreach($params as $key => $value){
        if(!in_array($key,$filledSlots)){
            array_push($filledSlots,$key);
            $slotValues[$key]=$value;
        }
    }

    $actualSlot = $expectedSlot;
    foreach($slots as $slot){
        //use the first slot which isn't yet in filled slots
        if (!in_array($slot, $filledSlots)){
            $actualSlot = $slot;
            break;
        }
    }
    $sessionid = $update["session"];
    $contextName = $sessionid.'/contexts/awaiting_'.$actualSlot;
    sendMessage(array(
        "fulfillmentText" => $slotMessages[$actualSlot],
        "outputContexts" => array(
            array(
                "name" => $contextName,
                "lifespanCount" => 1
            )
        )
    ));

    //save reservation to database
    if ($update["queryResult"]["action"] == "Input.visitor_phone"){
        $outputContexts = $update["queryResult"]["outputContexts"];
        foreach($outputContexts as $outputContext)
        {
            if(endsWith($outputContext["name"],'/session-vars'))
            {
                $typeEvent=$outputContext["parameters"]["type_event"];
                $nameEvent=$outputContext["parameters"]["name_event"];
                $nop = $outputContext["parameters"]["number_person"];
                $visitDate = $outputContext["parameters"]["visit_date"];
                $visitorName = $outputContext["parameters"]["visitor_name"];
                $visitorPhone = $outputContext["parameters"]["visitor_phone"];
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://example-c6d1.restdb.io/rest/visits",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => "{\"Date\":\"".$visitDate."\",
                                            \"Number\":\"".$nop."\",
                                            \"Name\":\"".$visitorName."\",
                                            \"Phone\":\"".$visitorPhone."\",
                                            \"EventName\":\"".$nameEvent."\",
                                            \"EventType\":\"".$typeEvent."\"}",
                    CURLOPT_HTTPHEADER => array(
                        "Cache-Control: no-cache",
                        "Content-Type: application/json",
                        "Postman-Token: 2a94321b-210a-46f2-99fd-99757f620b43",
                        "x-apikey: a8cd5a3d5581ace46afc92c0d786152a7406e"
                    ),
                ));
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
                if ($err) {
                    echo "cURL Error #:" . $err;
                } else {
                    sendMessage(array(
                        "fulfillmentText" => 'Thank you! Your reservation is successfully received!'
                    ));
                }
            }
        }
    }

    elseif ($update["queryResult"]["action"] == "saveFeedback"){
        $outputContexts = $update["queryResult"]["outputContexts"];
        foreach($outputContexts as $outputContext)
        {
            if(endsWith($outputContext["name"],'/session-vars'))
            {
                $fbName = $outputContext["parameters"]["fb_name"];
                $fbPhone = $outputContext["parameters"]["fb_phone"];
                $fbEmail = $outputContext["parameters"]["fb_email"];
                $fbComment = $outputContext["parameters"]["fb_comment"];
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://example-c6d1.restdb.io/rest/museumfeedback",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => "{\"Name\":\"".$fbName."\",\"Phone\":\"".$fbPhone."\",
                    \"Email\":\"".$fbEmail."\",\"Comment\":\"".$fbComment."\"}",
                    CURLOPT_HTTPHEADER => array(
                        "Cache-Control: no-cache",
                        "Content-Type: application/json",
                        "Postman-Token: 2a94321b-210a-46f2-99fd-99757f620b43",
                        "x-apikey: a8cd5a3d5581ace46afc92c0d786152a7406e"
                    ),
                ));
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
                if ($err) {
                    echo "cURL Error #:" . $err;
                } else {
                    sendMessage(array(
                        "fulfillmentText" => 'Thank you! Your feedback was successfully received!'
                    ));
                }
            }
        }
    exit();}


}

function getFilledSlots($update, &$slotValues){
    $filledSlots = array();
    $outputContexts = $update["queryResult"]["outputContexts"];
    foreach($outputContexts as $outputContext)
    {
        if(endsWith($outputContext["name"],'/session-vars'))
        {
            if (empty($outputContext["parameters"])) break;
            $params = $outputContext["parameters"];
            foreach($params as $key => $value){
                if(!endsWith($key,'.original') && $params[$key.'.original']!==''){
                    array_push($filledSlots,$key);
                    $slotValues[$key]=$value;
                }

            }
        }
    }
    return $filledSlots;
}

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function sendMessage($parameters) {
    echo json_encode($parameters);
}

$update_response = file_get_contents("php://input");
$update = json_decode($update_response, true);
if (isset($update["queryResult"]["action"])) {
    processMessage($update);
}

