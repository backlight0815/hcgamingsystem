<?php

// Rebuild the JMeter 5.x plan after editing a sampler. No secrets are written.
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$argument = static function (string $name, string $value) use ($escape): string {
    return '<elementProp name="'.$escape($name).'" elementType="Argument"><stringProp name="Argument.name">'.$escape($name).'</stringProp><stringProp name="Argument.value">'.$escape($value).'</stringProp><stringProp name="Argument.metadata">=</stringProp></elementProp>';
};
$assertion = static function (string $code, string $groovy) use ($escape): string {
    $script = "import groovy.json.JsonSlurper\n".
        "def body = new JsonSlurper().parseText(prev.getResponseDataAsString())\n".
        "if (prev.getResponseCode() != '{$code}' || !({$groovy})) { AssertionResult.setFailure(true); AssertionResult.setFailureMessage('Unexpected PMS API response: ' + prev.getResponseCode() + ' ' + prev.getResponseDataAsString()) }";
    return '<JSR223Assertion guiclass="TestBeanGUI" testclass="JSR223Assertion" testname="Assert '.$escape($code).' and data" enabled="true"><stringProp name="scriptLanguage">groovy</stringProp><stringProp name="script">'.$escape($script).'</stringProp></JSR223Assertion><hashTree/>';
};
$extractor = static function (string $variable, string $path) use ($escape): string {
    return '<JSONPostProcessor guiclass="JSONPostProcessorGui" testclass="JSONPostProcessor" testname="Extract '.$escape($variable).'" enabled="true"><stringProp name="JSONPostProcessor.referenceNames">'.$escape($variable).'</stringProp><stringProp name="JSONPostProcessor.jsonPathExprs">'.$escape($path).'</stringProp><stringProp name="JSONPostProcessor.match_numbers">1</stringProp><stringProp name="JSONPostProcessor.defaultValues"></stringProp></JSONPostProcessor><hashTree/>';
};
$sampler = static function (string $name, string $method, string $path, ?string $body, string $code, string $check, ?array $extract = null) use ($escape, $assertion, $extractor): string {
    $xml = '<HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="'.$escape($name).'" enabled="true">';
    $xml .= '<elementProp name="HTTPsampler.Arguments" elementType="Arguments"><collectionProp name="Arguments.arguments">';
    if ($body !== null) {
        $xml .= '<elementProp name="" elementType="HTTPArgument"><boolProp name="HTTPArgument.always_encode">false</boolProp><stringProp name="Argument.value">'.$escape($body).'</stringProp><stringProp name="Argument.metadata">=</stringProp></elementProp>';
    }
    $xml .= '</collectionProp></elementProp><stringProp name="HTTPSampler.domain">${host}</stringProp><stringProp name="HTTPSampler.port">${port}</stringProp><stringProp name="HTTPSampler.protocol">${protocol}</stringProp>';
    $xml .= '<stringProp name="HTTPSampler.path">'.$escape($path).'</stringProp><stringProp name="HTTPSampler.method">'.$method.'</stringProp>';
    $xml .= '<boolProp name="HTTPSampler.follow_redirects">false</boolProp><boolProp name="HTTPSampler.use_keepalive">true</boolProp><boolProp name="HTTPSampler.postBodyRaw">'.($body !== null ? 'true' : 'false').'</boolProp></HTTPSamplerProxy><hashTree>';
    if ($name === 'Unauthorized project read') {
        $xml .= '<HeaderManager guiclass="HeaderPanel" testclass="HeaderManager" testname="Override bearer with invalid token" enabled="true"><collectionProp name="HeaderManager.headers"><elementProp name="" elementType="Header"><stringProp name="Header.name">Authorization</stringProp><stringProp name="Header.value">Bearer invalid</stringProp></elementProp></collectionProp></HeaderManager><hashTree/>';
    }
    $xml .= $assertion($code, $check);
    if ($extract) $xml .= $extractor($extract[0], $extract[1]);
    return $xml.'</hashTree>';
};
$transaction = static fn (string $name, string $samplers): string => '<TransactionController guiclass="TransactionControllerGui" testclass="TransactionController" testname="'.$escape($name).'" enabled="true"><boolProp name="TransactionController.parent">true</boolProp></TransactionController><hashTree>'.$samplers.'</hashTree>';

$base = '/api/v1/project-management/';
$insert = $sampler('Insert project', 'POST', $base.'updateProjectInfo',
    '{"action":"insert","project_key":"${project_key}","name":"JMeter QA Project","priority":"normal","description":"Created by JMeter"}',
    '201', 'body.success == true && body.code == "PROJECT_CREATED"', ['project_id', '$.data.project_id']);
$readInserted = $sampler('Read inserted project', 'GET', $base.'getProjectInfo?project_id=${project_id}', null,
    '200', 'body.success == true && body.data.project_id.toString() == vars.get("project_id") && body.data.name == "JMeter QA Project"');
$update = $sampler('Update project', 'POST', $base.'updateProjectInfo',
    '{"action":"update","project_id":${project_id},"name":"Updated via JMeter","priority":"high"}',
    '200', 'body.success == true && body.code == "PROJECT_UPDATED"');
$readUpdated = $sampler('Verify project update', 'GET', $base.'getProjectInfo?project_id=${project_id}', null,
    '200', 'body.success == true && body.data.name == "Updated via JMeter" && body.data.priority == "high"');
$negative = $sampler('Unknown project', 'GET', $base.'getProjectInfo?project_id=999999999', null,
    '404', 'body.success == false && body.code == "PROJECT_NOT_FOUND"');
$negative .= $sampler('Missing project name', 'POST', $base.'updateProjectInfo',
    '{"action":"insert","project_key":"BAD${__RandomString(6,ABCDEFGHIJKLMNOPQRSTUVWXYZ,)}","priority":"normal"}',
    '422', 'body.success == false && body.code == "VALIDATION_ERROR"');
$negative .= $sampler('Invalid action', 'POST', $base.'updateProjectInfo', '{"action":"upsert"}',
    '422', 'body.success == false && body.code == "VALIDATION_ERROR"');
$negative .= $sampler('Invalid priority', 'POST', $base.'updateProjectInfo',
    '{"action":"update","project_id":${project_id},"priority":"urgent"}',
    '422', 'body.success == false && body.code == "VALIDATION_ERROR"');
$negative .= $sampler('Unauthorized project read', 'GET', $base.'getProjectInfo?project_id=${project_id}', null,
    '401', 'body.success == false && body.code == "UNAUTHENTICATED"');

$ticket = $sampler('Insert requirement ticket', 'POST', $base.'updateTicketInfo',
    '{"action":"insert","project_id":${project_id},"ticket_type":"REQ","title":"JMeter assignment test","priority":"normal","description":"Verify assignment","business_justification":"QA automation","acceptance_criteria":"Assignment persists"}',
    '201', 'body.success == true && body.code == "TICKET_CREATED"', ['ticket_id', '$.data.ticket_id']);
$ticket .= $sampler('Get eligible developers', 'GET', $base.'getAssignableUsers?assignment_type=developer', null,
    '200', 'body.success == true && body.code == "ASSIGNABLE_USERS_RETRIEVED"', ['developer_id', '$.data[0].user_id']);
$ticket .= $sampler('Get eligible QA users', 'GET', $base.'getAssignableUsers?assignment_type=qa', null,
    '200', 'body.success == true && body.code == "ASSIGNABLE_USERS_RETRIEVED"', ['qa_id', '$.data[0].user_id']);
$assignment = $sampler('Add developer to test project', 'POST', '/api/backoffice/pms/projects/${project_id}/members',
    '{"user_id":${developer_id},"role":"Developer"}', '200', 'body.saved == true');
$assignment .= $sampler('Add QA to test project', 'POST', '/api/backoffice/pms/projects/${project_id}/members',
    '{"user_id":${qa_id},"role":"QA Engineer"}', '200', 'body.saved == true');
$assignment .= $sampler('Assign eligible developer', 'POST', $base.'updateTicketInfo',
    '{"action":"update","ticket_id":${ticket_id},"developer_assignee_id":${developer_id}}',
    '200', 'body.success == true && body.data.developer_assignee.user_id.toString() == vars.get("developer_id")');
$assignment .= $sampler('Reject QA-only developer assignment', 'POST', $base.'updateTicketInfo',
    '{"action":"update","ticket_id":${ticket_id},"developer_assignee_id":${qa_id}}',
    '422', 'body.success == false && body.code == "VALIDATION_ERROR"');
$ticket .= '<IfController guiclass="IfControllerPanel" testclass="IfController" testname="Assignment cases require project developer and QA members" enabled="true"><stringProp name="IfController.condition">${__groovy(vars.get("developer_id") &amp;&amp; vars.get("qa_id"))}</stringProp><boolProp name="IfController.evaluateAll">false</boolProp><boolProp name="IfController.useExpression">true</boolProp></IfController><hashTree>'.$assignment.'</hashTree>';

$vars = ['host' => '${__P(host,localhost)}', 'port' => '${__P(port,80)}', 'protocol' => '${__P(protocol,http)}',
    'api_token' => '${__groovy(System.getenv("PMS_API_TOKEN") ?: "")}',
    'project_key' => 'JM${__RandomString(8,ABCDEFGHIJKLMNOPQRSTUVWXYZ,)}',
    'project_id' => '', 'ticket_id' => '', 'developer_id' => '', 'qa_id' => ''];
$args = implode('', array_map(static fn ($key, $value) => $argument($key, $value), array_keys($vars), $vars));
$plan = '<?xml version="1.0" encoding="UTF-8"?><jmeterTestPlan version="1.2" properties="5.0" jmeter="5.6.3"><hashTree>';
$plan .= '<TestPlan guiclass="TestPlanGui" testclass="TestPlan" testname="PMS API v1 QA and load plan" enabled="true"><boolProp name="TestPlan.functional_mode">false</boolProp><boolProp name="TestPlan.tearDown_on_shutdown">true</boolProp><elementProp name="TestPlan.user_defined_variables" elementType="Arguments"><collectionProp name="Arguments.arguments">'.$args.'</collectionProp></elementProp></TestPlan><hashTree>';
$plan .= '<ThreadGroup guiclass="ThreadGroupGui" testclass="ThreadGroup" testname="Configurable QA users" enabled="true"><stringProp name="ThreadGroup.num_threads">${__P(users,1)}</stringProp><stringProp name="ThreadGroup.ramp_time">${__P(ramp_up,1)}</stringProp><boolProp name="ThreadGroup.scheduler">false</boolProp><elementProp name="ThreadGroup.main_controller" elementType="LoopController"><boolProp name="LoopController.continue_forever">false</boolProp><stringProp name="LoopController.loops">${__P(loops,1)}</stringProp></elementProp></ThreadGroup><hashTree>';
$plan .= '<HeaderManager guiclass="HeaderPanel" testclass="HeaderManager" testname="PMS JSON bearer headers" enabled="true"><collectionProp name="HeaderManager.headers"><elementProp name="" elementType="Header"><stringProp name="Header.name">Authorization</stringProp><stringProp name="Header.value">Bearer ${api_token}</stringProp></elementProp><elementProp name="" elementType="Header"><stringProp name="Header.name">Accept</stringProp><stringProp name="Header.value">application/json</stringProp></elementProp><elementProp name="" elementType="Header"><stringProp name="Header.name">Content-Type</stringProp><stringProp name="Header.value">application/json</stringProp></elementProp></collectionProp></HeaderManager><hashTree/>';
$plan .= $transaction('A - Insert and retrieve', $insert.$readInserted);
$plan .= $transaction('B - Update and verify', $update.$readUpdated);
$plan .= $transaction('C - Negative validation and authorization', $negative);
$plan .= $transaction('D - Ticket assignment', $ticket);
$plan .= '</hashTree></hashTree></hashTree></jmeterTestPlan>';
file_put_contents(__DIR__.'/pms-api-v1.jmx', $plan);
