# Change Log

All notable changes to this project will be documented in this file.

## Version 0.8.0 (2026111801)

- Security: Enforce Moodle web service authorization (`enabled`, `restrictedusers`, `validuntil`, `iprestriction`,
  `requiredcapability`, and token restrictions) in `tool_provider::get_tools()` / `get_tools_for_service()`
- Fix: Handle CORS preflight `OPTIONS` requests before authentication in `server::run()`
- Fix: Map Moodle exceptions (`invalid_parameter_exception`, unknown tool, `webservice_access_exception`,
  `required_capability_exception`, JSON parse errors) to standard JSON-RPC 2.0 error codes (`-32700`, `-32600`,
  `-32601`, `-32602`, `-32603`)
- Fix: Remove forked `server::clean_response()` and delegate return value validation to Moodle core
  `external_api::clean_returnvalue()`
- Fix: Avoid duplicate `php://input` reads and redundant `parse_request()` execution on `tools/call`
- Feat: Trigger `\core\event\webservice_function_called` (`mcp:<method>`) for MCP protocol and server info requests
- Chore: Expand GitHub Actions CI matrix to Moodle 4.5, 5.0, and 5.1 with PHP 8.1–8.4
- Docs: Recommend `Authorization: Bearer` over `?wstoken=` and clarify audit logging in `README.md`
- Test: Add negative ACL tests for `tool_provider::get_tools()` and unit tests for `OPTIONS` preflight, JSON-RPC error
  code mapping, MCP event logging, `external_api::clean_returnvalue()`, and `request::from_json()`

## Version 0.7.0 (2026111800)

- Fix: Omit `outputSchema` for void-returning external functions and serialize empty/list-keyed `properties` as JSON
  objects (`{}`) (#8, #5)
- Fix: Apply `VALUE_REQUIRED` and `NULL_ALLOWED` to `external_single_structure` and `external_multiple_structure`, and
  map `PARAM_LOCALISEDFLOAT` to `number` (#5)
- Fix: Preserve `"jsonrpc": "2.0"` in error responses, add null-safe request fallbacks, and handle `Throwable` in
  exception logging and response handling (#9)
- Test: Add unit tests for void-returning tools, nullable/empty structures, JSON-RPC error generation, and Draft 2020-12
  schema validation across all installed external functions

## Version 0.6.0 (2026072300)

- Feat: Support MCP `notifications/initialized`
- Feat: Add JSON-RPC `ping` method to MCP server with unit tests and documentation
- Fix: Update namespace for `moodle_exception` usage
- Fix: Update nullable value handling in schema generation and improve type consistency

## Version 0.5.0 (2026062901)

- Fix: Support nullable parameters in tool provider schema
- Improved: Error handling with detailed JSON-RPC error responses
- Improved: Response validation and cleaning for MCP tools

## Version 0.4.1 (2025121302)

- Fix: Simplified `moodle_exception` usage by using global class instead of namespaced version

## Version 0.3.0 (2025121300)

- Initial beta release
- MCP protocol implementation with JSON-RPC 2.0
- Support for initialize, tools/list, and tools/call methods
- Dynamic tool discovery from Moodle external functions
- JSON Schema generation for parameters and return values
- Built-in client class for integration
- Comprehensive test coverage
- CI/CD pipeline with GitHub Actions
