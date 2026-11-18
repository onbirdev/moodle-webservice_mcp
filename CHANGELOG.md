# Change Log
All notable changes to this project will be documented in this file.

## Version 0.7.0 (2026111800)
- Fix: Omit `outputSchema` for void-returning external functions and serialize empty/list-keyed `properties` as JSON objects (`{}`) (#8, #5)
- Fix: Apply `VALUE_REQUIRED` and `NULL_ALLOWED` to `external_single_structure` and `external_multiple_structure`, and map `PARAM_LOCALISEDFLOAT` to `number` (#5)
- Fix: Preserve `"jsonrpc": "2.0"` in error responses, add null-safe request fallbacks, and handle `Throwable` in exception logging and response handling (#9)
- Test: Add unit tests for void-returning tools, nullable/empty structures, JSON-RPC error generation, and Draft 2020-12 schema validation across all installed external functions

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
