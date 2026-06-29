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

        $this->assertEquals('number', $schema['type']);
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
        $this->assertEquals('number', $schema['properties']['age']['type']);
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
        $this->assertEquals('number', $method->invoke(null, new external_value(PARAM_INT)));
        $this->assertEquals('number', $method->invoke(null, new external_value(PARAM_FLOAT)));
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
        $this->assertEquals([], $schema['properties']);
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
}
