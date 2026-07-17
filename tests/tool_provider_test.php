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

use context_system;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use externallib_advanced_testcase;
use ReflectionClass;
use stdClass;
use webservice_mcp\local\tool_provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for MCP tool provider class.
 *
 * @package     webservice_mcp
 * @author      MohammadReza PourMohammad <onbirdev@gmail.com>
 * @copyright   2025 MohammadReza PourMohammad
 * @link        https://onbir.dev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_mcp\local\tool_provider
 */
final class tool_provider_test extends externallib_advanced_testcase {
    /**
     * Test schema generation for simple string value.
     */
    public function test_generate_schema_string(): void {
        $this->resetAfterTest(true);

        $param = new external_value(PARAM_TEXT, 'Test parameter', VALUE_REQUIRED, null, false);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('string', $schema['type']);
        $this->assertEquals('Test parameter', $schema['description']);
        $this->assertTrue($schema['_required']);
    }

    /**
     * Test schema generation for integer value.
     */
    public function test_generate_schema_integer(): void {
        $this->resetAfterTest(true);

        $param = new external_value(PARAM_INT, 'Test integer', VALUE_OPTIONAL, null, false);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('integer', $schema['type']);
        $this->assertEquals('Test integer', $schema['description']);
        $this->assertArrayNotHasKey('_required', $schema);
    }

    /**
     * Test schema generation for float value.
     */
    public function test_generate_schema_float(): void {
        $this->resetAfterTest(true);

        $param = new external_value(PARAM_FLOAT, 'Test float', VALUE_OPTIONAL, null, false);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('number', $schema['type']);
    }

    /**
     * Test schema generation for boolean value.
     */
    public function test_generate_schema_boolean(): void {
        $this->resetAfterTest(true);

        $param = new external_value(PARAM_BOOL, 'Test boolean', VALUE_OPTIONAL, null, false);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('boolean', $schema['type']);
    }

    /**
     * Test schema generation for single structure (object).
     */
    public function test_generate_schema_single_structure(): void {
        $this->resetAfterTest(true);

        $param = new external_single_structure([
            'name' => new external_value(PARAM_TEXT, 'Name field', VALUE_REQUIRED, null, false),
            'age' => new external_value(PARAM_INT, 'Age field', VALUE_OPTIONAL, null, false),
            'active' => new external_value(PARAM_BOOL, 'Active status', VALUE_REQUIRED, null, false),
        ]);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('age', $schema['properties']);
        $this->assertArrayHasKey('active', $schema['properties']);

        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('integer', $schema['properties']['age']['type']);
        $this->assertEquals('boolean', $schema['properties']['active']['type']);

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('active', $schema['required']);
        $this->assertNotContains('age', $schema['required']);
    }

    /**
     * Test schema generation for multiple structure (array).
     */
    public function test_generate_schema_multiple_structure(): void {
        $this->resetAfterTest(true);

        $param = new external_multiple_structure(
            new external_value(PARAM_TEXT, 'Item description', VALUE_OPTIONAL, null, false)
        );

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('string', $schema['items']['type']);
    }

    /**
     * Test schema generation for nested structures.
     */
    public function test_generate_schema_nested_structure(): void {
        $this->resetAfterTest(true);

        $param = new external_single_structure([
            'user' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'User ID', VALUE_REQUIRED),
                'name' => new external_value(PARAM_TEXT, 'User name', VALUE_REQUIRED),
            ]),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Course ID'),
                    'title' => new external_value(PARAM_TEXT, 'Course title'),
                ])
            ),
        ]);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('user', $schema['properties']);
        $this->assertArrayHasKey('courses', $schema['properties']);

        // Test nested user object.
        $userschema = $schema['properties']['user'];
        $this->assertEquals('object', $userschema['type']);
        $this->assertArrayHasKey('id', $userschema['properties']);
        $this->assertArrayHasKey('name', $userschema['properties']);
        $this->assertEquals(['id', 'name'], $userschema['required']);

        // Test nested courses array.
        $coursesschema = $schema['properties']['courses'];
        $this->assertEquals('array', $coursesschema['type']);
        $this->assertEquals('object', $coursesschema['items']['type']);
        $this->assertArrayHasKey('id', $coursesschema['items']['properties']);
        $this->assertArrayHasKey('title', $coursesschema['items']['properties']);
        $this->assertEquals(['user', 'courses'], $schema['required']);
    }

    /**
     * Test schema type conversion.
     */
    public function test_get_schema_type(): void {
        $this->resetAfterTest(true);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('get_schema_type');
        $method->setAccessible(true);

        $this->assertEquals('string', $method->invoke(null, new external_value(PARAM_TEXT)));
        $this->assertEquals('integer', $method->invoke(null, new external_value(PARAM_INT)));
        $this->assertEquals('integer', $method->invoke(null, new external_value(PARAM_SEQUENCE)));
        $this->assertEquals('number', $method->invoke(null, new external_value(PARAM_FLOAT)));
        $this->assertEquals('number', $method->invoke(null, new external_value(PARAM_LOCALISEDFLOAT)));
        $this->assertEquals('boolean', $method->invoke(null, new external_value(PARAM_BOOL)));
        $this->assertEquals('object', $method->invoke(null, new external_single_structure([])));
        $this->assertEquals('array', $method->invoke(null, new external_multiple_structure(
            new external_value(PARAM_TEXT)
        )));
    }

    /**
     * Test build_schema with null description.
     */
    public function test_build_schema_null(): void {
        $this->resetAfterTest(true);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('build_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, null);

        $this->assertEquals('object', $schema['type']);
        $this->assertEquals((object) [], $schema['properties']);
        $this->assertEquals('{"type":"object","properties":{}}', json_encode($schema));
    }

    /**
     * Test nullability and optionality are represented independently.
     */
    public function test_generate_schema_nullable_optional_integer(): void {
        $this->resetAfterTest(true);

        $param = new external_value(
            PARAM_INT,
            'Nullable optional integer',
            VALUE_OPTIONAL,
            null,
            NULL_ALLOWED
        );

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('generate_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, $param);

        $this->assertSame(['integer', 'null'], $schema['type']);
        $this->assertNotContains(null, $schema['type']);
        $this->assertArrayNotHasKey('_required', $schema);
    }

    /**
     * Test structure nullability does not affect whether the property is required.
     */
    public function test_generate_schema_nullable_structures(): void {
        $this->resetAfterTest(true);

        $param = new external_single_structure([
            'optionalobject' => new external_single_structure(
                [],
                'Optional nullable object',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
            'requiredarray' => new external_multiple_structure(
                new external_value(PARAM_TEXT),
                'Required nullable array',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
        ]);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('build_schema');
        $method->setAccessible(true);
        $schema = $method->invoke(null, $param);

        $this->assertSame(['object', 'null'], $schema['properties']['optionalobject']['type']);
        $this->assertSame(['array', 'null'], $schema['properties']['requiredarray']['type']);
        $this->assertSame(['requiredarray'], $schema['required']);
        $this->assertEquals((object) [], $schema['properties']['optionalobject']['properties']);
        $this->assertSame('string', $schema['properties']['requiredarray']['items']['type']);
    }

    /**
     * Test the calendar tool that originally exposed a JSON null in its type union.
     */
    public function test_calendar_nullable_integer_schema_regression(): void {
        $this->resetAfterTest(true);

        $info = \core_external\external_api::external_function_info(
            'core_calendar_get_action_events_by_course'
        );

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('build_schema');
        $method->setAccessible(true);
        $schema = $method->invoke(null, $info->parameters_desc);
        $required = $schema['required'] ?? [];

        foreach (['timesortfrom', 'timesortto', 'aftereventid', 'limitnum'] as $name) {
            $parameter = $info->parameters_desc->keys[$name];
            $expectedtype = $parameter->allownull === NULL_ALLOWED
                ? ['integer', 'null']
                : 'integer';

            $this->assertSame($expectedtype, $schema['properties'][$name]['type'], $name);
            if (is_array($schema['properties'][$name]['type'])) {
                $this->assertNotContains(null, $schema['properties'][$name]['type'], $name);
            }
            if ($parameter->required !== VALUE_REQUIRED) {
                $this->assertNotContains($name, $required, $name);
            }
        }
    }

    /**
     * Test the create-users schema never serialises a JSON null as a type name.
     */
    public function test_create_users_nullable_boolean_schema_regression(): void {
        $this->resetAfterTest(true);

        $info = \core_external\external_api::external_function_info('core_user_create_users');

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('build_schema');
        $method->setAccessible(true);
        $schema = $method->invoke(null, $info->parameters_desc);
        $json = json_encode($schema, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('["boolean","null"]', $json);
        $this->assertStringNotContainsString('["boolean",null]', $json);
    }

    /**
     * Test defensive normalisation of invalid aliases and draft-04 keyword values.
     */
    public function test_normalise_schema_regressions(): void {
        $this->resetAfterTest(true);

        $reflection = new ReflectionClass(tool_provider::class);
        $method = $reflection->getMethod('normalise_schema');
        $method->setAccessible(true);

        $schema = $method->invoke(null, [
            'type' => 'dict',
            'properties' => [
                'integer' => ['type' => 'int'],
                'number' => ['type' => 'float'],
                'boolean' => ['type' => 'bool'],
                'text' => ['type' => 'PARAM_TEXT'],
                'unknown' => ['type' => 'any'],
                'nullable' => ['type' => ['number', null]],
            ],
            'required' => ['integer', 'integer', null],
            'minimum' => '0',
            'exclusiveMinimum' => false,
            'multipleOf' => 0,
            'maximum' => 100,
            'minLength' => -1,
            'maxLength' => 20,
        ]);

        $this->assertSame('object', $schema['type']);
        $this->assertSame('integer', $schema['properties']['integer']['type']);
        $this->assertSame('number', $schema['properties']['number']['type']);
        $this->assertSame('boolean', $schema['properties']['boolean']['type']);
        $this->assertSame('string', $schema['properties']['text']['type']);
        $this->assertSame('string', $schema['properties']['unknown']['type']);
        $this->assertSame(['number', 'null'], $schema['properties']['nullable']['type']);
        $this->assertSame(['integer'], $schema['required']);
        $this->assertArrayNotHasKey('minimum', $schema);
        $this->assertArrayNotHasKey('exclusiveMinimum', $schema);
        $this->assertArrayNotHasKey('multipleOf', $schema);
        $this->assertSame(100, $schema['maximum']);
        $this->assertArrayNotHasKey('minLength', $schema);
        $this->assertSame(20, $schema['maxLength']);
    }

    /**
     * Test get_tools retrieves available functions.
     */
    public function test_get_tools(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a test service.
        $service = new stdClass();
        $service->name = 'Test MCP Service';
        $service->enabled = 1;
        $service->restrictedusers = 0;
        $service->component = null;
        $service->timecreated = time();
        $service->timemodified = time();
        $service->shortname = 'test_mcp_service';
        $service->downloadfiles = 0;
        $service->uploadfiles = 0;
        $serviceid = $DB->insert_record('external_services', $service);

        // Add a function to the service (using an existing core function).
        $function = new stdClass();
        $function->externalserviceid = $serviceid;
        $function->functionname = 'core_webservice_get_site_info';
        $DB->insert_record('external_services_functions', $function);

        // Create a token for the service.
        $token = new stdClass();
        $token->token = bin2hex(random_bytes(32));
        $token->userid = $USER->id;
        $token->tokentype = EXTERNAL_TOKEN_PERMANENT;
        $token->contextid = context_system::instance()->id;
        $token->creatorid = $USER->id;
        $token->timecreated = time();
        $token->externalserviceid = $serviceid;
        $DB->insert_record('external_tokens', $token);

        // Test get_tools.
        $tools = tool_provider::get_tools($token->token);

        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);

        $tool = $tools[0];
        $this->assertArrayHasKey('name', $tool);
        $this->assertArrayHasKey('description', $tool);
        $this->assertArrayHasKey('inputSchema', $tool);
        $this->assertArrayHasKey('outputSchema', $tool);

        $this->assertEquals('core_webservice_get_site_info', $tool['name']);
        $this->assertIsArray($tool['inputSchema']);
        $this->assertIsArray($tool['outputSchema']);
    }

    /**
     * Test every installed external function produces draft 2020-12 schemas.
     */
    public function test_all_exposed_tool_schemas_are_draft_2020_12(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $functionrecords = $DB->get_records('external_functions', null, 'name', 'name');
        $this->assertNotEmpty($functionrecords);

        $token = $this->create_service_token(array_keys($functionrecords));
        $tools = tool_provider::get_tools($token);
        $this->assertNotEmpty($tools);

        $schemas = [];
        foreach ($tools as $tool) {
            $this->assertSame('object', $tool['inputSchema']['type'], $tool['name']);
            $this->assertArrayHasKey('properties', $tool['inputSchema'], $tool['name']);

            $schemas[] = [
                'name' => $tool['name'] . ' inputSchema',
                'schema' => $tool['inputSchema'],
            ];
            $schemas[] = [
                'name' => $tool['name'] . ' outputSchema',
                'schema' => $tool['outputSchema'],
            ];
        }

        $this->assert_draft_2020_12_schemas($schemas);
    }

    /**
     * Create a custom external service and token exposing the supplied functions.
     *
     * @param array $functionnames External function names.
     * @return string The service token.
     */
    private function create_service_token(array $functionnames): string {
        global $DB, $USER;

        $service = new stdClass();
        $service->name = 'MCP schema validation service';
        $service->enabled = 1;
        $service->restrictedusers = 0;
        $service->component = null;
        $service->timecreated = time();
        $service->timemodified = time();
        $service->shortname = 'mcp_schema_' . bin2hex(random_bytes(8));
        $service->downloadfiles = 0;
        $service->uploadfiles = 0;
        $serviceid = $DB->insert_record('external_services', $service);

        foreach ($functionnames as $functionname) {
            $function = new stdClass();
            $function->externalserviceid = $serviceid;
            $function->functionname = $functionname;
            $DB->insert_record('external_services_functions', $function);
        }

        $token = new stdClass();
        $token->token = bin2hex(random_bytes(32));
        $token->userid = $USER->id;
        $token->tokentype = EXTERNAL_TOKEN_PERMANENT;
        $token->contextid = context_system::instance()->id;
        $token->creatorid = $USER->id;
        $token->timecreated = time();
        $token->externalserviceid = $serviceid;
        $DB->insert_record('external_tokens', $token);

        return $token->token;
    }

    /**
     * Validate schemas with Python jsonschema's draft 2020-12 meta-schema implementation.
     *
     * @param array $schemas Named schemas to validate.
     */
    private function assert_draft_2020_12_schemas(array $schemas): void {
        $script = <<<'PYTHON'
import json
import sys

from jsonschema import Draft202012Validator, SchemaError

for entry in json.load(sys.stdin):
    try:
        Draft202012Validator.check_schema(entry["schema"])
    except SchemaError as error:
        print(f'{entry["name"]}: {error.message}', file=sys.stderr)
        sys.exit(1)
PYTHON;
        $descriptors = [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ];
        $process = proc_open(['python3', '-c', $script], $descriptors, $pipes);
        if (!is_resource($process)) {
            $this->fail('Unable to start Python for JSON Schema validation.');
        }

        $payload = json_encode($schemas, JSON_THROW_ON_ERROR);
        $offset = 0;
        $payloadlength = strlen($payload);
        while ($offset < $payloadlength) {
            $written = fwrite($pipes[0], substr($payload, $offset));
            if ($written === false || $written === 0) {
                $this->fail('Unable to send schemas to the JSON Schema validator.');
            }
            $offset += $written;
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitcode = proc_close($process);

        $this->assertSame(0, $exitcode, trim($stderr . "\n" . $stdout));
    }
}
