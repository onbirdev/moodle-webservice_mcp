# Change Log
All notable changes to this project will be documented in this file.

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
