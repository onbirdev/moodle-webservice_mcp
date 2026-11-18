<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace webservice_mcp;

use advanced_testcase;
use context_system;
use core\event\webservice_function_called;
use core\exception\invalid_parameter_exception;
use core\exception\required_capability_exception;
use core_external\external_single_structure;
use core_external\external_value;
use dml_missing_record_exception;
use Exception;
use moodle_exception;
use ReflectionClass;
use stdClass;
use TypeError;
use webservice_access_exception;
use webservice_mcp\local\request;
use webservice_mcp\local\server;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/lib.php');

/**
 * Tests for MCP server class.
 *
 * @package     webservice_mcp
 * @author      MohammadReza PourMohammad <onbirdev@gmail.com>
 * @copyright   2025 MohammadReza PourMohammad
 * @link        https://onbir.dev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_mcp\local\server
 */
final class server_test extends advanced_testcase {
    /**
     * Test server instantiation.
     */
    public function test_server_instantiation(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        $this->assertInstanceOf(server::class, $server);
    }

    /**
     * Test is_tool_call method with tools/call request.
     */
    public function test_is_tool_call_true(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        // Use reflection to access private method.
        $reflection = new ReflectionClass($server);
        $mcprequestprop = $reflection->getProperty('mcprequest');
        $mcprequestprop->setAccessible(true);

        // Create a mock request.
        $requestdata = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
        ];
        $request = new request($requestdata);
        $mcprequestprop->setValue($server, $request);

        $method = $reflection->getMethod('is_tool_call');
        $method->setAccessible(true);

        $result = $method->invoke($server);

        $this->assertTrue($result);
    }

    /**
     * Test is_tool_call method with non-tools/call request.
     */
    public function test_is_tool_call_false(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        // Use reflection to access private method.
        $reflection = new ReflectionClass($server);
        $mcprequestprop = $reflection->getProperty('mcprequest');
        $mcprequestprop->setAccessible(true);

        // Create a mock request.
        $requestdata = [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 1,
        ];
        $request = new request($requestdata);
        $mcprequestprop->setValue($server, $request);

        $method = $reflection->getMethod('is_tool_call');
        $method->setAccessible(true);

        $result = $method->invoke($server);

        $this->assertFalse($result);
    }

    /**
     * Test extract_tool_call with valid request.
     */
    public function test_extract_tool_call_valid(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        // Use reflection to set up the request.
        $reflection = new ReflectionClass($server);
        $mcprequestprop = $reflection->getProperty('mcprequest');
        $mcprequestprop->setAccessible(true);

        $requestdata = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
            'params' => [
                'name' => 'test_function',
                'arguments' => ['param1' => 'value1'],
            ],
        ];
        $request = new request($requestdata);
        $mcprequestprop->setValue($server, $request);

        // Call extract_tool_call.
        $server->extract_tool_call();

        // Verify functionname was set.
        $functionnameprop = $reflection->getProperty('functionname');
        $functionnameprop->setAccessible(true);
        $functionname = $functionnameprop->getValue($server);

        $this->assertEquals('test_function', $functionname);

        // Verify parameters were set.
        $parametersprop = $reflection->getProperty('parameters');
        $parametersprop->setAccessible(true);
        $parameters = $parametersprop->getValue($server);

        $this->assertEquals(['param1' => 'value1'], $parameters);
    }

    /**
     * Test extract_tool_call with missing tool name.
     */
    public function test_extract_tool_call_missing_name(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        // Use reflection to set up the request.
        $reflection = new ReflectionClass($server);
        $mcprequestprop = $reflection->getProperty('mcprequest');
        $mcprequestprop->setAccessible(true);

        $requestdata = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
            'params' => [],
        ];
        $request = new request($requestdata);
        $mcprequestprop->setValue($server, $request);

        // Expect exception.
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_missing_tool_name', 'webservice_mcp'));

        $server->extract_tool_call();
    }

    /**
     * Test extract_token from URL parameter.
     */
    public function test_extract_token_from_url(): void {
        $this->resetAfterTest(true);

        $_GET['wstoken'] = 'test_token_123';

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        $reflection = new ReflectionClass($server);
        $method = $reflection->getMethod('extract_token');
        $method->setAccessible(true);

        $token = $method->invoke($server);

        $this->assertEquals('test_token_123', $token);

        unset($_GET['wstoken']);
    }

    /**
     * Test extract_token from POST parameter.
     */
    public function test_extract_token_from_post(): void {
        $this->resetAfterTest(true);

        $_POST['wstoken'] = 'test_token_456';

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        $reflection = new ReflectionClass($server);
        $method = $reflection->getMethod('extract_token');
        $method->setAccessible(true);

        $token = $method->invoke($server);

        $this->assertEquals('test_token_456', $token);

        unset($_POST['wstoken']);
    }

    /**
     * Test generate_error method.
     */
    public function test_generate_error(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        $reflection = new ReflectionClass($server);
        $method = $reflection->getMethod('generate_error');
        $method->setAccessible(true);

        $exception = new Exception('Test error message');
        $error = $method->invoke($server, $exception);

        $this->assertIsArray($error);
        $this->assertArrayHasKey('jsonrpc', $error);
        $this->assertArrayHasKey('error', $error);
        $this->assertArrayHasKey('id', $error);

        $this->assertEquals('2.0', $error['jsonrpc']);
        $this->assertArrayHasKey('code', $error['error']);
        $this->assertArrayHasKey('message', $error['error']);
    }

    /**
     * Test generate_error preserves jsonrpc 2.0 when request has an id.
     */
    public function test_generate_error_with_request_id(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        $reflection = new ReflectionClass($server);
        $mcprequestprop = $reflection->getProperty('mcprequest');
        $mcprequestprop->setAccessible(true);
        $mcprequestprop->setValue($server, new request([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 42,
        ]));

        $method = $reflection->getMethod('generate_error');
        $method->setAccessible(true);

        $error = $method->invoke($server, new Exception('Tool execution failed'));

        $this->assertSame('2.0', $error['jsonrpc']);
        $this->assertSame(42, $error['id']);
        $this->assertSame(-32603, $error['error']['code']);
        $this->assertSame('Tool execution failed', $error['error']['message']);
    }

    /**
     * Test generate_error with null exception and no request.
     */
    public function test_generate_error_null_exception(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        $reflection = new ReflectionClass($server);
        $method = $reflection->getMethod('generate_error');
        $method->setAccessible(true);

        $error = $method->invoke($server, null);

        $this->assertSame('2.0', $error['jsonrpc']);
        $this->assertNull($error['id']);
        $this->assertSame(-32603, $error['error']['code']);
        $this->assertSame('Internal error', $error['error']['message']);
    }

    /**
     * Test generate_error handles PHP Error/Throwable instances.
     */
    public function test_generate_error_throwable(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        $reflection = new ReflectionClass($server);
        $mcprequestprop = $reflection->getProperty('mcprequest');
        $mcprequestprop->setAccessible(true);
        $mcprequestprop->setValue($server, new request([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 'req-99',
        ]));

        $method = $reflection->getMethod('generate_error');
        $method->setAccessible(true);

        $error = $method->invoke($server, new TypeError('Invalid type'));

        $this->assertSame('2.0', $error['jsonrpc']);
        $this->assertSame('req-99', $error['id']);
        $this->assertSame(TypeError::class, $error['error']['data']['exception']);
        $this->assertSame('Invalid type', $error['error']['message']);
    }

    /**
     * Test safe_json_encode with valid data.
     */
    public function test_safe_json_encode_valid(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        $reflection = new ReflectionClass($server);
        $method = $reflection->getMethod('safe_json_encode');
        $method->setAccessible(true);

        $data = ['key' => 'value', 'number' => 123];
        $json = $method->invoke($server, $data);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
    }

    /**
     * Test safe_json_encode with unicode characters.
     */
    public function test_safe_json_encode_unicode(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);

        $reflection = new ReflectionClass($server);
        $method = $reflection->getMethod('safe_json_encode');
        $method->setAccessible(true);

        $data = ['text' => 'Hello 世界 🌍'];
        $json = $method->invoke($server, $data);

        $this->assertIsString($json);
        $this->assertStringContainsString('Hello', $json);
        $decoded = json_decode($json, true);
        $this->assertEquals($data, $decoded);
    }

    /**
     * Test server constants are defined.
     */
    public function test_server_constants(): void {
        $this->resetAfterTest(true);

        $reflection = new ReflectionClass(server::class);

        $this->assertTrue($reflection->hasConstant('PROTOCOL_VERSION'));
        $this->assertTrue($reflection->hasConstant('SERVER_NAME'));
        $this->assertTrue($reflection->hasConstant('SERVER_VERSION'));

        $protocolversion = $reflection->getConstant('PROTOCOL_VERSION');
        $servername = $reflection->getConstant('SERVER_NAME');
        $serverversion = $reflection->getConstant('SERVER_VERSION');

        $this->assertIsString($protocolversion);
        $this->assertIsString($servername);
        $this->assertIsString($serverversion);
        $this->assertEquals('2025-03-26', $protocolversion);
        $this->assertEquals('Moodle MCP Server', $servername);
        $this->assertEquals('1.0.0', $serverversion);
    }

    /**
     * Test OPTIONS preflight completes without authentication.
     */
    public function test_options_preflight_skips_authentication(): void {
        $this->resetAfterTest(true);

        $previousmethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        try {
            $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
            $server->run();

            $reflection = new ReflectionClass($server);
            $statusprop = $reflection->getProperty('httpstatus');
            $statusprop->setAccessible(true);
            $parsedprop = $reflection->getProperty('requestparsed');
            $parsedprop->setAccessible(true);

            $this->assertSame(200, $statusprop->getValue($server));
            $this->assertFalse($parsedprop->getValue($server));
        } finally {
            if ($previousmethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previousmethod;
            }
        }
    }

    /**
     * Test generate_error maps Moodle exceptions to appropriate JSON-RPC 2.0 codes.
     */
    public function test_generate_error_maps_jsonrpc_codes(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
        $reflection = new ReflectionClass($server);
        $method = $reflection->getMethod('generate_error');
        $method->setAccessible(true);

        $invalidparam = $method->invoke($server, new invalid_parameter_exception('Bad param'));
        $this->assertSame(-32602, $invalidparam['error']['code']);

        $unknowntool = $method->invoke($server, new dml_missing_record_exception('external_functions'));
        $this->assertSame(-32601, $unknowntool['error']['code']);

        $missingcourse = $method->invoke($server, new dml_missing_record_exception('course'));
        $this->assertSame(-32603, $missingcourse['error']['code']);

        // Simulate tools/call lookup failure when debugging is disabled (tablename becomes null).
        $mcprequestprop = $reflection->getProperty('mcprequest');
        $mcprequestprop->setAccessible(true);
        $mcprequestprop->setValue($server, new request([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
        ]));
        $unknowntoolnodebug = $method->invoke($server, new dml_missing_record_exception(''));
        $this->assertSame(-32601, $unknowntoolnodebug['error']['code']);

        // Reset request for remaining checks.
        $mcprequestprop->setValue($server, null);

        $wsaccess = $method->invoke($server, new webservice_access_exception('Access denied'));
        $this->assertSame(-32600, $wsaccess['error']['code']);

        $capfail = $method->invoke(
            $server,
            new required_capability_exception(context_system::instance(), 'moodle/site:config', 'nopermissions', '')
        );
        $this->assertSame(-32600, $capfail['error']['code']);

        $invalidjson = $method->invoke($server, new moodle_exception('err_invalid_json', 'webservice_mcp'));
        $this->assertSame(-32700, $invalidjson['error']['code']);

        $missingtool = $method->invoke($server, new moodle_exception('err_missing_tool_name', 'webservice_mcp'));
        $this->assertSame(-32602, $missingtool['error']['code']);

        $invalidtoken = $method->invoke($server, new moodle_exception('invalidtoken', 'webservice'));
        $this->assertSame(-32600, $invalidtoken['error']['code']);
    }

    /**
     * Test handle_mcp_method logs webservice_function_called events for MCP methods.
     */
    public function test_handle_mcp_method_logs_events(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $serviceid = $DB->insert_record('external_services', (object) [
            'name' => 'Event Log Service',
            'enabled' => 1,
            'restrictedusers' => 0,
            'component' => null,
            'timecreated' => time(),
            'timemodified' => time(),
            'shortname' => 'event_log_service',
            'downloadfiles' => 0,
            'uploadfiles' => 0,
        ]);

        $token = bin2hex(random_bytes(32));
        $DB->insert_record('external_tokens', (object) [
            'token' => $token,
            'userid' => $USER->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
            'contextid' => context_system::instance()->id,
            'creatorid' => $USER->id,
            'timecreated' => time(),
            'externalserviceid' => $serviceid,
        ]);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
        $reflection = new ReflectionClass($server);

        $httpmethodprop = $reflection->getProperty('httpmethod');
        $httpmethodprop->setAccessible(true);
        $httpmethodprop->setValue($server, 'POST');

        $mcprequestprop = $reflection->getProperty('mcprequest');
        $mcprequestprop->setAccessible(true);

        $tokenprop = $reflection->getProperty('token');
        $tokenprop->setAccessible(true);
        $tokenprop->setValue($server, $token);

        $serviceidprop = $reflection->getProperty('restricted_serviceid');
        $serviceidprop->setAccessible(true);
        $serviceidprop->setValue($server, $serviceid);

        $useridprop = $reflection->getProperty('userid');
        $useridprop->setAccessible(true);
        $useridprop->setValue($server, $USER->id);

        $contextprop = $reflection->getProperty('restricted_context');
        $contextprop->setAccessible(true);
        $contextprop->setValue($server, context_system::instance());

        $handlemcp = $reflection->getMethod('handle_mcp_method');
        $handlemcp->setAccessible(true);

        $sink = $this->redirectEvents();
        try {
            foreach (['initialize', 'ping', 'notifications/initialized', 'tools/list'] as $mcpmethod) {
                $mcprequestprop->setValue($server, new request([
                    'jsonrpc' => '2.0',
                    'method' => $mcpmethod,
                    'id' => 1,
                ]));
                ob_start();
                $handlemcp->invoke($server);
                ob_end_clean();
            }

            $httpmethodprop->setValue($server, 'GET');
            ob_start();
            $handlemcp->invoke($server);
            ob_end_clean();
        } finally {
            $events = $sink->get_events();
            $sink->close();
        }

        $loggedfunctions = array_map(
            static fn(webservice_function_called $e): string => $e->other['function'],
            array_values(array_filter(
                $events,
                static fn($e): bool => $e instanceof webservice_function_called
            ))
        );

        $this->assertSame([
            'mcp:initialize',
            'mcp:ping',
            'mcp:notifications/initialized',
            'mcp:tools/list',
            'mcp:server_info',
        ], $loggedfunctions);
    }

    /**
     * Test send_response cleans return values using core external_api::clean_returnvalue().
     */
    public function test_send_response_cleans_return_value(): void {
        $this->resetAfterTest(true);

        $server = new server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
        $reflection = new ReflectionClass($server);

        $mcprequestprop = $reflection->getProperty('mcprequest');
        $mcprequestprop->setAccessible(true);
        $mcprequestprop->setValue($server, new request([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 7,
        ]));

        $functionprop = $reflection->getProperty('function');
        $functionprop->setAccessible(true);
        $function = new stdClass();
        $function->returns_desc = new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Success flag'),
        ]);
        $functionprop->setValue($server, $function);

        $returnsprop = $reflection->getProperty('returns');
        $returnsprop->setAccessible(true);
        $returnsprop->setValue($server, ['ok' => 1]);

        $sendresponse = $reflection->getMethod('send_response');
        $sendresponse->setAccessible(true);

        ob_start();
        $sendresponse->invoke($server);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(7, $decoded['id']);
        $this->assertTrue($decoded['result']['structuredContent']['result']['ok']);
    }
}
